<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Membership;
use AppBundle\Entity\Note;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Form\NoteType;
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
     * @Route("/phone", name="ambassador_phone_list")
     * @Method({"GET","POST"})
     */
    public function phoneAction(Request $request)
    {

        $this->denyAccessUnlessGranted('view', $this->get('security.token_storage')->getToken()->getUser());

        $session = new Session();
        $lastYear = new \DateTime('last year');
        $form = $this->createFormBuilder()
            ->add('withdrawn', ChoiceType::class, array('label' => 'fermé','required' => true,'data' => 1,'choices'  => array(
                'fermé' => 2,
                'ouvert' => 1,
            )))
            ->add('frozen', ChoiceType::class, array('label' => 'gelé','required' => true,'data' => 1,'choices'  => array(
                'Non gelé' => 1,
                'gelé' => 2,
            )))
            ->add('membernumber', IntegerType::class, array('label' => '# =','required' => false))
            ->add('membernumbergt', IntegerType::class, array('label' => '# >','required' => false))
            ->add('membernumberlt', IntegerType::class, array('label' => '# <','required' => false))
            ->add('lastregistrationdategt', TextType::class, array('label' => 'après le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('lastregistrationdatelt', TextType::class, array('label' => 'avant le','required' => false, 'attr' => array( 'class' => 'datepicker')))
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

        $qb = $em->getRepository("AppBundle:Membership")->createQueryBuilder('o');
        $qb = $qb->leftJoin("o.beneficiaries", "b")->addSelect("b")
            ->leftJoin("b.user", "u")->addSelect("u")
            ->leftJoin("o.lastRegistration", "lr")->addSelect("lr")
            ->leftJoin("o.registrations", "r")->addSelect("r");

        $qb = $qb->andWhere('o.member_number > 0'); //do not include admin user

        $page = $request->get('page');
        if (!intval($page))
            $page = 1;
        $order = 'ASC';
        $sort = 'o.member_number';

        if ($form->isSubmitted() && $form->isValid()) {

            if ($form->get('withdrawn')->getData() > 0){
                $qb = $qb->andWhere('o.withdrawn = :withdrawn')
                    ->setParameter('withdrawn', $form->get('withdrawn')->getData()-1);
            }
            if ($form->get('frozen')->getData() > 0){
                $qb = $qb->andWhere('o.frozen = :frozen')
                    ->setParameter('frozen', $form->get('frozen')->getData()-1);
            }

            if ($form->get('page')->getData() > 0){
                $page = $form->get('page')->getData();
            }
            if ($form->get('sort')->getData()){
                $sort = $form->get('sort')->getData();
            }
            if ($form->get('dir')->getData()){
                $order = $form->get('dir')->getData();
            }

            if ($form->get('lastregistrationdategt')->getData()){
                $date = $form->get('lastregistrationdategt')->getData();
                $datetime = \DateTime::createFromFormat('Y-m-d', $date);
                if ($datetime > $lastYear){
                    $session->getFlashBag()->add('warning','Oups, cet outil n\'est pas conçu pour rechercher des membres à jour sur leurs adhésions');
                    $date = $lastYear->format('Y-m-d') ;
                }
                $qb = $qb->andWhere('lr.date > :lastregistrationdategt')
                        ->setParameter('lastregistrationdategt', $date);
            }
            if ($form->get('lastregistrationdatelt')->getData()){
                $date = $form->get('lastregistrationdatelt')->getData();
                $datetime = \DateTime::createFromFormat('Y-m-d', $date);
                if ($datetime > $lastYear){
                    $session->getFlashBag()->add('warning','Oups, cet outil n\'est pas conçu pour rechercher des membres à jour sur leurs adhésions');
                    $date = $lastYear->format('Y-m-d') ;
                }
                $qb = $qb->andWhere('lr.date < :lastregistrationdatelt')
                        ->setParameter('lastregistrationdatelt', $date);
            }else{
                $date = $lastYear->format('Y-m-d') ;
                $qb = $qb->andWhere('lr.date < :lastregistrationdatelt')
                    ->setParameter('lastregistrationdatelt', $date);
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
            if ($form->get('firstname')->getData()){
                $qb = $qb->andWhere('b.firstname LIKE :firstname')
                    ->setParameter('firstname', '%'.$form->get('firstname')->getData().'%');
            }
            if ($form->get('lastname')->getData()){
                $qb = $qb->andWhere('b.lastname LIKE :lastname')
                    ->setParameter('lastname', '%'.$form->get('lastname')->getData().'%');
            }
            if ($form->get('email')->getData()){
                $qb = $qb->andWhere('u.email LIKE :email')
                    ->setParameter('email', '%'.$form->get('email')->getData().'%');
            }
        }else{
            $form->get('sort')->setData($sort);
            $form->get('dir')->setData($order);
            $qb = $qb->andWhere('o.withdrawn = 0');
            $qb = $qb->andWhere('o.frozen = 0');
            $qb = $qb->andWhere('lr.date < :lastregistrationdatelt')
                    ->setParameter('lastregistrationdatelt', $lastYear->format('Y-m-d'));
        }

        $limit = 25;
        $qb2 = clone $qb;
        $max = $qb2->select('count(DISTINCT o.id)')->getQuery()->getSingleScalarResult();
        $nb_of_pages = intval($max/$limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;

        $qb = $qb->orderBy($sort, $order);
        $qb = $qb->setFirstResult( ($page - 1)*$limit )->setMaxResults( $limit );
        $members = new Paginator($qb->getQuery());

        return $this->render('ambassador/phone/list.html.twig', array(
            'members' => $members,
            'form' => $form->createView(),
            'nb_of_result' => $max,
            'page'=>$page,
            'nb_of_pages'=>$nb_of_pages
        ));

    }

    /**
     * display a member phones.
     *
     * @Route("/phone/{member_number}", name="ambassador_phone_show")
     * @Method("GET")
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function showAction(Membership $member)
    {
        return $this->redirectToRoute('member_show', array('member_number'=>$member->getMemberNumber()));
    }

    /**
     * Creates a form to delete a note entity.
     *
     * @param Note $note the note entity
     *
     * @return \Symfony\Component\Form\FormInterface The form
     */
    private function createNoteDeleteForm(Note $note)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('note_delete', array('id' => $note->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     *
     * @Route("/note/{member_number}", name="ambassador_new_note")
     * @Method("POST")
     * @param Membership $member
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function newNoteAction(Membership $member, Request $request)
    {
        $this->denyAccessUnlessGranted('annotate', $member);
        $session = new Session();
        $note = new Note();
        $form = $this->createForm(NoteType::class, $note);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $note->setSubject($member);
            $note->setAuthor($this->get('security.token_storage')->getToken()->getUser());

            $em = $this->getDoctrine()->getManager();
            $em->persist($note);
            $em->flush();

            $session->getFlashBag()->add('success','La note a bien été ajoutée');
        } else {
            $session->getFlashBag()->add('error', 'Impossible d\'ajouter une note');
        }

        return $this->redirectToRoute("ambassador_phone_show", array('member_number'=>$member->getMemberNumber()));
    }
}
