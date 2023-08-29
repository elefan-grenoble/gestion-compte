<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Job;
use AppBundle\Entity\Period;
use AppBundle\Entity\PeriodPosition;
use AppBundle\Event\PeriodPositionFreedEvent;
use AppBundle\Form\AutocompleteBeneficiaryType;
use AppBundle\Form\PeriodPositionType;
use AppBundle\Form\PeriodType;
use AppBundle\Service\PeriodFormHelper;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Admin Period Controller ("semaine type" coté admin)
 *
 * @Route("admin/period")
 */
class AdminPeriodController extends Controller
{
    private $cycle_type;

    public function __construct($cycle_type)
    {
        $this->cycle_type = $cycle_type;
    }

    /**
     * Display all the periods in a schedule (available and reserved)
     *
     * @Route("/", name="admin_period_index", methods={"GET","POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function indexAction(Request $request, PeriodFormHelper $formHelper)
    {
        $em = $this->getDoctrine()->getManager();

        $defaults = [
            'job' => null,
            'filling' => null,
            'week' => null,
            'beneficiary' => null,
        ];
        $form = $formHelper->createFilterForm($this->createFormBuilder(), $defaults, true);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $job_filter = $form->get("job")->getData();
            $week_filter = $form->get("week")->getData();
            $filling_filter = $form->get("filling")->getData();
            $beneficiary_filter = $form->get("beneficiary")->getData();
        } else {
            $job_filter = null;
            $week_filter = null;
            $filling_filter = null;
            $beneficiary_filter = null;
        }

        $periodsByDay = array();
        foreach (Period::DAYS_OF_WEEK as $i => $value) {
            $periodsByDay[$i] = $em->getRepository('AppBundle:Period')->findAll($i, $job_filter, true);
        }

        return $this->render('admin/period/index.html.twig', array(
            'periods_by_day' => $periodsByDay,
            'filter_form' => $form->createView(),
            'week_filter' => $week_filter,
            'filling_filter' => $filling_filter,
            'beneficiary_filter' => $beneficiary_filter,
        ));
    }

    /**
     * Create a period
     *
     * @Route("/new", name="admin_period_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function newPeriodAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $period = new Period();
        $job = $em->getRepository(Job::class)->findOneBy(array());

        if (!$job) {
            $session->getFlashBag()->add('warning', 'Commençons par créer un poste de bénévolat');
            return $this->redirectToRoute('job_new');
        }

        $form = $this->createForm(PeriodType::class, $period);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $start = $form->get('start')->getData();
            $period->setStart(new \DateTime($start));
            $end = $form->get('end')->getData();
            $period->setEnd(new \DateTime($end));
            $period->setCreatedBy($current_user);

            $em->persist($period);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le nouveau créneau type ' . $period . ' a bien été créé !');
            return $this->redirectToRoute('admin_period_edit',array('id'=>$period->getId()));
        }

        return $this->render('admin/period/new.html.twig',array(
            "form" => $form->createView()
        ));
    }

    /**
     * Edit a period
     *
     * @Route("/{id}/edit", name="admin_period_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function editPeriodAction(Request $request, Period $period)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $form = $this->createForm(PeriodType::class, $period);
        $form->handleRequest($request);

        if ($request->isMethod('GET')) {
            $form->get('start')->setData($period->getStart()->format('H:i'));
            $form->get('end')->setData($period->getEnd()->format('H:i'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $start = $form->get('start')->getData();
            $period->setStart(new \DateTime($start));
            $end = $form->get('end')->getData();
            $period->setEnd(new \DateTime($end));
            $period->setUpdatedBy($current_user);

            $em->persist($period);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le créneau type ' . $period . ' a bien été édité !');
            return $this->redirectToRoute('admin_period_index');
        }

        $beneficiaries = $em->getRepository(Beneficiary::class)->findAllActive();

        $periodDeleteForm = $this->createPeriodDeleteForm($period);

        $positionAddForm = $this->createPeriodPositionAddForm($period);

        $positionsBookForms = [];
        $positionsFreeForms = [];
        $positionsDeleteForms = [];
        foreach ($period->getPositions() as $position) {
            // book forms
            if (!$position->getShifter()) {
                $positionsBookForms[$position->getId()] = $this->createPeriodPositionBookForm($period, $position)->createView();
            }
            // free forms
            else {
                $positionsFreeForms[$position->getId()] = $this->createPeriodPositionFreeForm($period, $position)->createView();
            }
            // delete forms
            $positionsDeleteForms[$position->getId()] = $this->createPeriodPositionDeleteForm($period, $position)->createView();
        }

        return $this->render('admin/period/edit.html.twig', array(
            "form" => $form->createView(),
            "beneficiaries" => $beneficiaries,
            "period" => $period,
            "admin_period_delete_form" => $periodDeleteForm->createView(),
            "position_add_form" => $positionAddForm->createView(),
            "positions_book_forms" => $positionsBookForms,
            "positions_free_forms" => $positionsFreeForms,
            "positions_delete_forms" => $positionsDeleteForms,
        ));
    }

    /**
     * Create a position
     *
     * @Route("/{id}/position/add", name="admin_periodposition_new", methods={"POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function newPeriodPositionAction(Request $request, Period $period)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $position = new PeriodPosition();
        $form = $this->createForm(PeriodPositionType::class, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $count = $form["nb_of_shifter"]->getData();

            if ($this->cycle_type == "abcd") {
                $week_cycles = $form["week_cycle"]->getData();
                foreach ($week_cycles as $week_cycle) {
                    $position->setWeekCycle($week_cycle);
                    $position->setCreatedBy($current_user);
                    foreach (range(0, $count-1) as $iteration) {
                        $p = clone($position);
                        $period->addPosition($p);
                        $em->persist($p);
                    }
                }
            } else {
                $position->setWeekCycle(null);
                $position->setCreatedBy($current_user);
                foreach (range(0, $count-1) as $iteration) {
                    $p = clone($position);
                    $period->addPosition($p);
                    $em->persist($p);
                }
            }

            $em->persist($period);
            $em->flush();

            $session->getFlashBag()->add('success', $count . ' poste' . (($count>1) ? 's':'') . ' ajouté ' . (($count>1) ? 's':'') . (($this->cycle_type == "abcd") ? ' (pour chaque cycle sélectionné) !':' !'));
        }

        return $this->redirectToRoute('admin_period_edit', array('id' => $period->getId()));
    }

    /**
     * Delete a position
     *
     * @Route("/{id}/position/{position}", name="admin_periodposition_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deletePeriodPositionAction(Request $request, Period $period, PeriodPosition $position)
    {
        $session = new Session();

        $form = $this->createPeriodPositionDeleteForm($period, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($position);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le poste ' . $position . ' a bien été supprimé !');
        }

        return $this->redirectToRoute('admin_period_edit', array('id' => $period->getId()));
    }

    /**
     * Book a position
     *
     * @Route("/{id}/position/{position}/book", name="admin_periodposition_book", methods={"POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function bookPeriodPositionAction(Request $request, Period $period, PeriodPosition $position): Response
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createPeriodPositionBookForm($period, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($position->getShifter()) {
                $session->getFlashBag()->add("error", "Désolé, ce créneau est déjà réservé");
                return new Response($this->generateUrl('admin_period_edit',array('id'=>$period->getId())), 205);
            }

            $beneficiary = $form->get("shifter")->getData();
            if ($position->getFormation() && !$beneficiary->getFormations()->contains($position->getFormation())) {
                $session
                    ->getFlashBag()
                    ->add("error", "Désolé, ce bénévole n'a pas la qualification nécessaire (" . $position->getFormation()->getName() . ")");
                return new Response($this->generateUrl('admin_period_edit',array('id'=>$period->getId())), 205);
            }

            if (!$position->getBooker()) {
                $current_user = $this->get('security.token_storage')->getToken()->getUser();
                $position->setBooker($current_user);
                $position->setBookedTime(new \DateTime('now'));
            }

            $position->setShifter($beneficiary);
            $em->persist($position);
            $em->flush();

            $session->getFlashBag()->add('success', 'Créneau fixe réservé avec succès pour ' . $position->getShifter() . ' : ' . $position);
        }

        return $this->redirectToRoute('admin_period_edit',array('id'=>$period->getId()));
    }

    /**
     * Free a position
     *
     * @Route("/{id}/position/{position}/free", name="admin_periodposition_free", methods={"POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function freePeriodPositionAction(Request $request, Period $period, PeriodPosition $position)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createPeriodPositionFreeForm($period, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // store position beneficiary & bookedTime (before position free())
            $beneficiary = $position->getShifter();
            $bookedTime = $position->getBookedTime();

            // free position
            $position->free();

            $em = $this->getDoctrine()->getManager();
            $em->persist($position);
            $em->flush();

            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(PeriodPositionFreedEvent::NAME, new PeriodPositionFreedEvent($position, $beneficiary, $bookedTime));

            $session->getFlashBag()->add('success', 'Le poste ' . $position . ' a bien été libéré !');
        }

        return $this->redirectToRoute('admin_period_edit',array('id'=>$position->getPeriod()->getId()));
    }

    /**
     * Delete a period
     *
     * @Route("/{id}", name="admin_period_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deletePeriodAction(Request $request, Period $period)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createPeriodDeleteForm($period);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($period);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le créneau type ' . $period . ' a bien été supprimé !');
        }

        return $this->redirectToRoute('admin_period_index');
    }

    /**
     * Duplicate a period
     *
     * @Route("/copyPeriod/", name="admin_period_copy", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function copyPeriodAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_period_copy'))
            ->add('day_of_week_from', ChoiceType::class, array('label' => 'Jour de la semaine référence', 'choices' => Period::DAYS_OF_WEEK_LIST_WITH_INT))
            ->add('day_of_week_to', ChoiceType::class, array('label' => 'Jour de la semaine destination', 'choices' => Period::DAYS_OF_WEEK_LIST_WITH_INT))
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
            $session->getFlashBag()->add('success', $count . ' creneaux copiés de' . array_search($from, Period::DAYS_OF_WEEK_LIST_WITH_INT) . ' à ' . array_search($to, Period::DAYS_OF_WEEK_LIST_WITH_INT));

            return $this->redirectToRoute('admin_period_index');
        }

        return $this->render('admin/period/copy_periods.html.twig',array(
            "form" => $form->createView()
        ));
    }

    /**
     * Generate shifts for a given date
     *
     * @Route("/generateShifts/", name="admin_shifts_generation", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function generateShiftsForDateAction(Request $request, KernelInterface $kernel)
    {
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_shifts_generation'))
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

            return $this->redirectToRoute('admin_period_index');
        }

        return $this->render('admin/period/generate_shifts.html.twig',array(
            "form" => $form->createView()
        ));
    }

    /**
     * Creates a form to delete a period entity.
     *
     * @param Period $period The period entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createPeriodDeleteForm(Period $period)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_period_delete', array('id' => $period->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Creates a form to add a period position entity.
     *
     * @param Period $period The period entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createPeriodPositionAddForm(Period $period)
    {
        return $this->createForm(
            PeriodPositionType::class,
            new PeriodPosition(),
            array(
                'action' => $this->generateUrl(
                    'admin_periodposition_new',
                    array('id' => $period->getId())
                )
            ));
    }

    /**
     * Creates a form to book a period position entity.
     *
     * @param Period $period The period entity
     * @param PeriodPosition $position The period position entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createPeriodPositionBookForm(Period $period, PeriodPosition $position)
    {
        return $this->get('form.factory')->createNamedBuilder('positions_book_forms_' . $position->getId())
            ->setAction($this->generateUrl('admin_periodposition_book', array('id' => $period->getId(), 'position' => $position->getId())))
            ->setMethod('POST')
            ->add('shifter', AutocompleteBeneficiaryType::class, array('label' => 'Numéro d\'adhérent ou nom du membre', 'required' => true))
            ->getForm();
    }

    /**
     * Creates a form to free a period position entity.
     *
     * @param Period $period The period entity
     * @param PeriodPosition $position The period position entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createPeriodPositionFreeForm(Period $period, PeriodPosition $position)
    {
        return $this->get('form.factory')->createNamedBuilder('positions_free_forms_' . $position->getId())
            ->setAction($this->generateUrl('admin_periodposition_free', array('id' => $period->getId(), 'position' => $position->getId())))
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Creates a form to delete a period position entity.
     *
     * @param Period $period The period entity
     * @param PeriodPosition $position The period position entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createPeriodPositionDeleteForm(Period $period, PeriodPosition $position)
    {
        return $this->get('form.factory')->createNamedBuilder('positions_delete_forms_' . $position->getId())
            ->setAction($this->generateUrl('admin_periodposition_delete', array('id' => $period->getId(), 'position' => $position->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
