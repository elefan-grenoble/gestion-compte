<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Task;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * Task controller.
 *
 * @Route("ambassador")
 */
class AmbassadorController extends Controller
{

    /**
     * Lists all tasks.
     *
     * @Route("/phone", name="ambassador_phone")
     * @Method({"GET","POST"})
     */
    public function phoneAction(Request $request){
        $form = $this->createFormBuilder()
            ->add('membernumber', IntegerType::class, array('label' => '# =','required' => false))
            ->add('membernumbergt', IntegerType::class, array('label' => '# >','required' => false))
            ->add('membernumberlt', IntegerType::class, array('label' => '# <','required' => false))
            ->add('registrationdate', TextType::class, array('label' => 'le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('registrationdategt', TextType::class, array('label' => 'après le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('registrationdatelt', TextType::class, array('label' => 'avant le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('lastregistrationdate', TextType::class, array('label' => 'le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('lastregistrationdategt', TextType::class, array('label' => 'après le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('lastregistrationdatelt', TextType::class, array('label' => 'avant le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('username', TextType::class, array('label' => 'username','required' => false))
            ->add('firstname', TextType::class, array('label' => 'prénom','required' => false))
            ->add('lastname', TextType::class, array('label' => 'nom','required' => false))
            ->add('email', TextType::class, array('label' => 'email','required' => false))
            ->add('action', HiddenType::class,array())
            ->add('page', HiddenType::class,array())
            ->add('dir', HiddenType::class,array())
            ->add('sort', HiddenType::class,array())
            ->add('submit', SubmitType::class, array('label' => 'Filtrer','attr' => array('class' => 'btn','value' => 'show')))
            ->getForm();

        $form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();

        $action = $form->get('action')->getData();

        $qb = $em->getRepository("AppBundle:User")->createQueryBuilder('o');
        $qb = $qb->leftJoin("o.beneficiaries", "b")->addSelect("b")
            ->leftJoin("o.lastRegistration", "lr")->addSelect("lr")
            ->leftJoin("o.registrations", "r")->addSelect("r");

        $qb = $qb->andWhere('o.member_number > 0'); //do not include admin user
        $qb = $qb->andWhere('o.withdrawn = 0');
        $qb = $qb->andWhere('o.frozen = 0');

        $page = 1;
        $order = 'ASC';
        $sort = 'o.member_number';

        if ($form->isSubmitted() && $form->isValid()) {

            if ($form->get('page')->getData() > 0){
                $page = $form->get('page')->getData();
            }
            if ($form->get('sort')->getData()){
                $sort = $form->get('sort')->getData();
            }
            if ($form->get('dir')->getData()){
                $order = $form->get('dir')->getData();
            }

            if ($form->get('registrationdate')->getData()){
                $qb = $qb->andWhere('r.date LIKE :registrationdate')
                    ->setParameter('registrationdate', $form->get('registrationdate')->getData().'%');
            }
            if ($form->get('registrationdategt')->getData()){
                $qb = $qb->andWhere('r.date > :registrationdategt')
                    ->setParameter('registrationdategt', $form->get('registrationdategt')->getData());
            }
            if ($form->get('registrationdatelt')->getData()){
                $qb = $qb->andWhere('r.date < :registrationdatelt')
                    ->setParameter('registrationdatelt', $form->get('registrationdatelt')->getData());
            }
            if ($form->get('lastregistrationdate')->getData()){
                $qb = $qb->andWhere('lr.date LIKE :lastregistrationdate')
                    ->setParameter('lastregistrationdate', $form->get('lastregistrationdate')->getData().'%');
            }
            if ($form->get('lastregistrationdategt')->getData()){
                $qb = $qb->andWhere('lr.date > :lastregistrationdategt')
                    ->setParameter('lastregistrationdategt', $form->get('lastregistrationdategt')->getData());
            }
            if ($form->get('lastregistrationdatelt')->getData()){
                $qb = $qb->andWhere('lr.date < :lastregistrationdatelt')
                    ->setParameter('lastregistrationdatelt', $form->get('lastregistrationdatelt')->getData());
            }
            if ($form->get('membernumber')->getData()){
                $qb = $qb->andWhere('o.member_number = :membernumber')
                    ->setParameter('membernumber', $form->get('membernumber')->getData());
            }
            if ($form->get('membernumbergt')->getData()){
                $qb = $qb->andWhere('o.member_number > :membernumbergt')
                    ->setParameter('membernumbergt', $form->get('membernumbergt')->getData());
            }
            if ($form->get('membernumberlt')->getData()){
                $qb = $qb->andWhere('o.member_number < :membernumberlt')
                    ->setParameter('membernumberlt', $form->get('membernumberlt')->getData());
            }
            if ($form->get('username')->getData()){
                $qb = $qb->andWhere('o.username LIKE :username')
                    ->setParameter('username', '%'.$form->get('username')->getData().'%');
            }
            if ($form->get('firstname')->getData()){
                $qb = $qb->andWhere('b.firstname LIKE :firstname')
                    ->setParameter('firstname', '%'.$form->get('firstname')->getData().'%');
            }
            if ($form->get('lastname')->getData()){
                $qb = $qb->andWhere('b.lastname LIKE :lastname')
                    ->setParameter('lastname', '%'.$form->get('lastname')->getData().'%');
            }
            if ($form->get('email')->getData()){
                $qb = $qb->andWhere('b.email LIKE :email')
                    ->setParameter('email', '%'.$form->get('email')->getData().'%');
            }
        }else{
            $form->get('sort')->setData($sort);
            $form->get('dir')->setData($order);
        }

        $limit = 25;
        $qb2 = clone $qb;
        $max = $qb2->select('count(DISTINCT o.id)')->getQuery()->getSingleScalarResult();
        $nb_of_pages = intval($max/$limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;


        $qb = $qb->orderBy($sort, $order);
        if ($action != "csv"){
            $qb = $qb->setFirstResult( ($page - 1)*$limit )->setMaxResults( $limit );
            $users = new Paginator($qb->getQuery());
        }else{
            $users = $qb->getQuery()->getResult();
            $return = '';
            $d = ','; // this is the default but i like to be explicit
            foreach($users as $user) {
                foreach ($user->getBeneficiaries() as $beneficiary)
                    $return .= $beneficiary->getFirstname().$d.$beneficiary->getLastname().$d.$beneficiary->getEmail()."\n";
            }
            return new Response($return, 200, array(
                'Content-Encoding: UTF-8',
                'Content-Type' => 'application/force-download; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="emails_'.date('dmyhis').'.csv"'
            ));
        }

        return $this->render('ambassador/phone.html.twig', array(
            'users' => $users,
            'form' => $form->createView(),
            'nb_of_result' => $max,
            'page'=>$page,
            'nb_of_pages'=>$nb_of_pages
        ));

    }

}
