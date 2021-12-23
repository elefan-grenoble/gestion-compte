<?php

namespace AppBundle\Controller;

use AppBundle\AppBundle;
use AppBundle\Entity\BookedShift;
use AppBundle\Entity\Job;
use AppBundle\Entity\Period;
use AppBundle\Entity\PeriodPosition;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use AppBundle\Form\PeriodPositionType;
use AppBundle\Form\PeriodType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Validator\Constraints\DateTime;

/**
* @Route("period")
*/
class PeriodController extends Controller
{
    /**
     * @Route("/", name="period")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $periods = array();
        for($i=0;$i<7;$i++){
            $periods[$i] = $em->getRepository('AppBundle:Period')->findBy(array('dayOfWeek'=>$i),array('start'=>'ASC'));
        }
        return $this->render('admin/period/list.html.twig',array(
            "periods" => $periods
        ));
    }

    /**
     * @Route("/new", name="period_new")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $period = new Period();

        $em = $this->getDoctrine()->getManager();
        $job = $em->getRepository(Job::class)->findOneBy(array());

        if (!$job) {
            $session->getFlashBag()->add('warning', 'Commençons par créer un poste de bénevolat');
            return $this->redirectToRoute('job_new');
        }

        $form = $this->createForm(PeriodType::class, $period);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $time = $form->get('start')->getData();
            $period->setStart(new \DateTime($time));
            $time = $form->get('end')->getData();
            $period->setEnd(new \DateTime($time));

            $em->persist($period);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le nouveau creneau type a bien été créé !');
            return $this->redirectToRoute('period_edit',array('id'=>$period->getId()));
        }

        return $this->render('admin/period/new.html.twig',array(
            "form" => $form->createView()
        ));
    }

    /**
     * @Route("/edit/{id}", name="period_edit")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request,Period $period)
    {
        $session = new Session();

        $form = $this->createForm(PeriodType::class, $period);
        $form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();
        if ($form->isSubmitted() && $form->isValid()) {

            $time = $form->get('start')->getData();
            $period->setStart(new \DateTime($time));
            $time = $form->get('end')->getData();
            $period->setEnd(new \DateTime($time));

            $em->persist($period);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le créneau type a bien été édité !');
            return $this->redirectToRoute('period');
        }

        $beneficiariesQb = $em->getRepository('AppBundle:Beneficiary')
            ->createQueryBuilder('b')
            ->select('b, m')
            ->join('b.user', 'u')
            ->join('b.membership', 'm');
        $beneficiaries = $beneficiariesQb->getQuery()->getResult();

        $form->get('start')->setData($period->getStart()->format('H:i'));
        $form->get('end')->setData($period->getEnd()->format('H:i'));

        $delete_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('period_delete', array('id' => $period->getId())))
            ->setMethod('DELETE')
            ->getForm();

        $positions_delete_form = array();
        foreach($period->getPositions() as $position){
            $positions_delete_form[$position->getId()] = $this->createFormBuilder()
                ->setAction($this->generateUrl('remove_position_from_period', array('period' => $period->getId(),'position' => $position->getId())))
                ->setMethod('DELETE')
                ->getForm()->createView();
        }

        return $this->render('admin/period/edit.html.twig', array(
            "form" => $form->createView(),
            "period" => $period,
            "beneficiaries" => $beneficiaries,
            "position_form" => $this->createForm(PeriodPositionType::class, new PeriodPosition(), array('action' => $this->generateUrl('add_position_to_period', array('id' => $period->getId()))))->createView(),
            "delete_form" => $delete_form->createView(),
            "positions_delete_form" => $positions_delete_form
        ));
    }

    /**
     * @Route("/{id}/add_position/", name="add_position_to_period")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method({"POST"})
     */
    public function addPositionToPeriodAction(Request $request,Period $period)
    {
        $session = new Session();

        $position = new PeriodPosition();
        $form = $this->createForm(PeriodPositionType::class, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            foreach ($form["week_cycle"]->getData() as $week_cycle) {
                $position->setWeekCycle($week_cycle);
                $nb_of_shifter = $form["nb_of_shifter"]->getData();
                while (0 < $nb_of_shifter ){
                    $p = clone($position);
                    $period->addPosition($p);
                    $em->persist($p);
                    $nb_of_shifter--;
                }
            }
            $em->persist($period);
            $em->flush();
            $session->getFlashBag()->add('success', 'La position '.$position.' a bien été ajoutée');
            return $this->redirectToRoute('period_edit',array('id'=>$period->getId()));
        }

        return $this->redirectToRoute('period_edit',array('id'=>$period->getId()));
    }

    /**
     * @Route("/{period}/remove_position/{position}", name="remove_position_from_period")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method({"DELETE"})
     */
    public function removePositionToPeriodAction(Request $request,Period $period,PeriodPosition $position)
    {
        $session = new Session();

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('remove_position_from_period', array('period' => $period->getId(),'position' => $position->getId())))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($position);
            $em->flush();
            $session->getFlashBag()->add('success', 'La position '.$position.' a bien été supprimée');
            return $this->redirectToRoute('period_edit',array('id'=>$period->getId()));
        }

        return $this->redirectToRoute('period_edit',array('id'=>$period->getId()));
    }

    /**
     * Book a period.
     *
     * @Route("/book/{id}", name="book_position_from_period")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method("POST")
     */
    public function bookPositionToPeriodAction(Request $request, PeriodPosition $position)
    {
        $session = new Session();
        $period = $position->getPeriod();

        if ($position->getShifter()) {
            $session->getFlashBag()->add("error", "Désolé, ce créneau est déjà réservé");
            return new Response($this->generateUrl('period_edit',array('id'=>$period->getId())), 205);
        }

        $content = json_decode($request->getContent());
        $str = $content->beneficiary;

        $em = $this->getDoctrine()->getManager();
        $beneficiary = $em->getRepository('AppBundle:Beneficiary')->findFromAutoComplete($str);

        if (!$beneficiary) {
            $session->getFlashBag()->add("error", "Impossible de trouve ce béneficiaire 😕");
            return new Response($this->generateUrl('period_edit',array('id'=>$period->getId())), 205);
        }

        if ($position->getFormation() && !$beneficiary->getFormations()->contains($position->getFormation())) {
            $session->getFlashBag()->add("error", "Désolé, ce bénévole n'a pas la qualification necessaire (" . $position->getFormation()->getName() . ")");
            return new Response($this->generateUrl('period_edit',array('id'=>$period->getId())), 205);
        }

        if (!$position->getBooker()) {
            $current_user = $this->get('security.token_storage')->getToken()->getUser();
            $current_beneficiary = $current_user->getBeneficiary();
            $position->setBooker($current_beneficiary);
            $position->setBookedTime(new \DateTime('now'));
        }
        $position->setShifter($beneficiary);
        $em->persist($position);
        $em->flush();

        $session->getFlashBag()->add("success", "Créneau fixe réservé avec succès pour " . $position->getShifter());
        return new Response($this->generateUrl('period_edit',array('id'=>$period->getId())), 200);

    }

    /**
     * free a position.
     *
     * @Route("/free/{id}", name="free_position_from_period")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method("POST")
     */
    public function freePositionToPeriodAction(Request $request, PeriodPosition $position)
    {
        $session = new Session();

        $em = $this->getDoctrine()->getManager();
        $position->free();
        $em->persist($position);
        $em->flush();

        $session->getFlashBag()->add('success', "Le poste a bien été libéré");
        return $this->redirectToRoute('period_edit',array('id'=>$position->getPeriod()->getId()));
    }

    /**
     * Deletes a period entity.
     *
     * @Route("/period/{id}", name="period_delete")
     * @Security("has_role('ROLE_ADMIN')")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Period $period)
    {
        $session = new Session();

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('period_delete', array('id' => $period->getId())))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($period);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le créneau type a bien été supprimé !');
        }

        return $this->redirectToRoute('period');

    }

    /**
     * @Route("/copyPeriod/", name="period_copy")
     * @Security("has_role('ROLE_ADMIN')")
     * @Method({"GET","POST"})
     */
    public function periodCopyAction(Request $request){
        $days = array(
            "Lundi" => 0,
            "Mardi" => 1,
            "Mercredi" => 2,
            "Jeudi" => 3,
            "Vendredi" => 4,
            "Samedi" => 5,
            "Dimanche" => 6,
        );
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('period_copy'))
            ->add('day_of_week_from',ChoiceType::class,array('label'=>'Jour de la semaine référence','choices' => $days))
            ->add('day_of_week_to',ChoiceType::class,array('label'=>'Jour de la semaine destination','choices' => $days))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $from = $form->get('day_of_week_from')->getData();
            $to = $form->get('day_of_week_to')->getData();

            $em = $this->getDoctrine()->getManager();
            $periods = $em->getRepository('AppBundle:Period')->findBy(array('dayOfWeek'=>$from));

            $count = 0;
            foreach ($periods as $period){
                $p = clone $period;
                $p->setDayOfWeek($to);
                foreach ($period->getPositions() as $position){
                    $p->addPosition($position);
                }
                $em->persist($p);
                $count++;
            }
            $em->flush();

            $session = new Session();
            $session->getFlashBag()->add('success',$count.' creneaux copiés de'.array_search($from,$days).' à '.array_search($to,$days));

            return $this->redirectToRoute('period');
        }

        return $this->render('admin/period/copy_periods.html.twig',array(
            "form" => $form->createView()
        ));
    }

    /**
     * @Route("/generateShifts/", name="shifts_generation")
     * @Security("has_role('ROLE_ADMIN')")
     * @Method({"GET","POST"})
     */
    public function generateShiftsForDateAction(Request $request, KernelInterface $kernel){
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('shifts_generation'))
            ->add('date_from',TextType::class,array('label'=>'du*','attr'=>array('class'=>'datepicker')))
            ->add('date_to',TextType::class,array('label'=>'au' ,'attr'=>array('class'=>'datepicker','require' => false)))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $date_from = $form->get('date_from')->getData();
            $date_to = $form->get('date_to')->getData();

            $application = new Application($kernel);
            $application->setAutoExit(false);

            $input = new ArrayInput(array(
                'command' => 'app:shift:generate',
                'date' => $date_from,
                '--to' => $date_to
            ));

            $output = new BufferedOutput();
            $application->run($input, $output);
            $content = $output->fetch();

            $session = new Session();
            $session->getFlashBag()->add('success',$content);

            return $this->redirectToRoute('period');
        }

        return $this->render('admin/period/generate_shifts.html.twig',array(
            "form" => $form->createView()
        ));
    }
    
}
