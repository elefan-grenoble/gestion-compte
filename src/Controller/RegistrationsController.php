<?php

namespace App\Controller;

use App\Command\ImportUsersCommand;
use App\Entity\AbstractRegistration;
use App\Entity\Address;
use App\Entity\Beneficiary;
use App\Entity\Commission;
use App\Entity\HelloassoPayment;
use App\Entity\Registration;
use App\Entity\Formation;
use App\Entity\User;
use App\Event\HelloassoEvent;
use App\Form\BeneficiaryType;
use App\Form\RegistrationType;
use App\Service\SearchUserFormHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use OAuth2\OAuth2;
use Ornicar\GravatarBundle\GravatarApi;
use Ornicar\GravatarBundle\Templating\Helper\GravatarHelper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Registrations controller.
 *
 * @Route("registrations")
 */
class RegistrationsController extends Controller
{

    /**
     * Registrations list
     *
     * @Route("/", name="registrations")
     * @Method({"POST","GET"})
     * @Security("has_role('ROLE_FINANCE_MANAGER')")
     */
    public function registrationsAction(Request $request, EntityManagerInterface $em)
    {
        $session = new Session();

        $qfrom = $request->query->get('from');
        if (!$qfrom) {
            $monday = strtotime('last monday', strtotime('tomorrow'));
            $from = new DateTime();
            $from->setTimestamp($monday);
        }else{
            $from = date_create_from_format('Y-m-d', $qfrom );
            if (!$from || $from->format('Y-m-d') != $qfrom) {
                $session->getFlashBag()->add('warning','la date "'.$qfrom.'"" n\'est pas au bon format (Y-m-d)');
                $monday = strtotime('last monday', strtotime('tomorrow'));
                $from = new DateTime();
                $from->setTimestamp($monday);
            }
        }
        $from = $from->setTime('0','0','0');

        $qto = $request->query->get('to');
        if ($qto) {
            $to = date_create_from_format('Y-m-d', $qto );
            if (!$to || $to->format('Y-m-d') != $qto) {
                $session->getFlashBag()->add('warning','la date "'.$qto.'"" n\'est pas au bon format (Y-m-d)');
                $to = null;
            }else{
                $to = $to->setTime('0','0','0');
            }
        }else{
            $to = null;
        }

        if (!($page = $request->get('page')))
            $page = 1;
        $limit = 25;
        $qb = $em->createQueryBuilder()->from('App\Entity\AbstractRegistration', 'r')
            ->select('count(r.id)')
            ->where('r.date >= :from')
            ->setParameter('from', $from);
        if ($to){
            $qb = $qb->andwhere('r.date <= :to')->setParameter('to', $to);
        }

        $max = $qb->getQuery()
            ->getSingleScalarResult();
        $nb_of_pages = intval($max / $limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;
        $repository = $em->getRepository('App:AbstractRegistration');
        $queryb = $repository->createQueryBuilder('r')
            ->where('r.date >= :from')
            ->setParameter('from', $from);
        if ($to){
            $queryb = $queryb->andwhere('r.date <= :to')->setParameter('to', $to);
        }
        $queryb = $queryb->orderBy('r.date', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $registrations = $queryb->getQuery()->getResult();
        $delete_forms = array();

        $table_name = $em->getClassMetadata('App:AbstractRegistration')->getTableName();
        $connection = $em->getConnection();
        $statement = $connection->prepare("SELECT date, SUM(sum_1) as sum_1,SUM(sum_2) as sum_2,SUM(sum_3) as sum_3,SUM(sum_4) as sum_4,SUM(sum_5) as sum_5,SUM(sum_6) as sum_6,SUM(grand_total) as grand_total FROM
(SELECT date_format(date,\"%Y-%m-%d\") as date,
SUM(IF(mode='1',amount,0)) as sum_1,
SUM(IF(mode='2',amount,0)) as sum_2,
SUM(IF(mode='3',amount,0)) as sum_3,
SUM(IF(mode='4',amount,0)) as sum_4,
SUM(IF(mode='5',amount,0)) as sum_5,
SUM(IF(mode='6',amount,0)) as sum_6,
SUM(amount) as grand_total
FROM ".$table_name."
WHERE date >= :from ".(($to) ? "AND date <= :to" : "")."
GROUP BY date) as sum GROUP BY date ORDER BY date DESC;");
        $statement->bindValue('from', $from->format('Y-m-d'));
        if ($to){
            $statement->bindValue('to', $to->format('Y-m-d'));
        }
        $statement->execute();
        $results = $statement->fetchAll();

        $totaux = array();
        foreach ($results as $result){
            $totaux[$result['date']] = $result;
        }

        $connection = $em->getConnection();
        $statement = $connection->prepare("SELECT
SUM(IF(mode='1',amount,0)) as sum_1,
SUM(IF(mode='2',amount,0)) as sum_2,
SUM(IF(mode='3',amount,0)) as sum_3,
SUM(IF(mode='4',amount,0)) as sum_4,
SUM(IF(mode='5',amount,0)) as sum_5,
SUM(IF(mode='6',amount,0)) as sum_6,
SUM(amount) as grand_total
FROM ".$table_name."
WHERE date >= :from ".(($to) ? "AND date <= :to" : "").";");
        $statement->bindValue('from', $from->format('Y-m-d'));
        if ($to){
            $statement->bindValue('to', $to->format('Y-m-d'));
        }
        $statement->execute();
        $grand_total = $statement->fetch();


        $re = '/1_([0-9]+)$/m';
        foreach ($registrations as $registration) {
            if ($registration->getType() == AbstractRegistration::TYPE_MEMBER){
                $matches = array();
                if (preg_match($re, $registration->getId(), $matches)) {
                    $delete_forms[$registration->getId()] = $this->getRegistrationDeleteForm($matches[1])->createView();
                }
            }
        }

        return $this->render('registrations/list.html.twig',
            array(
                'R' => new Registration(),
                'registrations' => $registrations,
                'grand_total' => $grand_total,
                'totaux' => $totaux,
                'delete_forms' => $delete_forms,
                'page' => $page,
                'from' => $from,
                'to' => $to,
                'nb_of_pages' => $nb_of_pages));
    }

    /**
     * edit registration
     *
     * @Route("/{id}/edit", name="registration_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_FINANCE_MANAGER')")
     */
    public function editRegistrationAction(Request $request, Registration $registration, EntityManagerInterface $em)
    {
        $session = new Session();
        if ($registration->getId() && ($request->attributes->get('id') == $registration->getId())){
            $edit_form = $this->createForm(RegistrationType::class, $registration);
            $edit_form->handleRequest($request);
            if ($edit_form->isSubmitted() && $edit_form->isValid()) {
                $em->persist($registration);
                $em->flush();
                $session->getFlashBag()->add('success', 'La ligne a bien été éditée !');
                return $this->redirectToRoute("registrations");
            }
        }else{
            $session->getFlashBag()->add('error', 'l\'entrée #'.$request->attributes->get('id').' n\'a pas été trouvée');
            return $this->redirectToRoute("registrations");
        }

        return $this->render('registrations/edit.html.twig', array('edit_form' => $edit_form->createView(),'registration' => $registration));
    }

    /**
     * remove registration
     *
     * @Route("/{id}/remove", name="registration_remove")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removeRegistrationAction(Request $request, Registration $registration, EntityManagerInterface $em)
    {
        $session = new Session();
        $form = $this->getRegistrationDeleteForm($registration->getId());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($registration->getMembership() && count($registration->getMembership()->getRegistrations()) === 1 && $registration === $registration->getMembership()->getLastRegistration()) {
                $session->getFlashBag()->add('error', 'C\'est la seule adhésion de cette adhérent, corrigez là plutôt que de la supprimer');
            } else {
                if ($registration->getMembership()) {
                    $registration->getMembership()->removeRegistration($registration);
                    $em->persist($registration->getMembership());
                }
                if ($registration->getRegistrar()) {
                    $registration->getRegistrar()->removeRecordedRegistration($registration);
                    $em->persist($registration->getRegistrar());
                }
                $em->remove($registration);
                $em->flush();
                $session->getFlashBag()->add('success', 'L\'adhésion a bien été supprimée !');
            }
        }
        return $this->redirectToRoute('registrations');
    }

    /**
     * @param integer $registration_id
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getRegistrationDeleteForm(int $registration_id)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('registration_remove', array('id' => $registration_id)))
            ->setMethod('DELETE')
            ->getForm();
    }

}
