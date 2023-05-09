<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Job;
use AppBundle\Entity\Period;
use AppBundle\Entity\PeriodPosition;
use AppBundle\Form\AutocompleteBeneficiaryType;
use AppBundle\Form\PeriodPositionType;
use AppBundle\Form\PeriodType;
use AppBundle\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Controller to manage the period shift (a.k.a. "semaine type")
 *
 * @Route("period")
 */
class PeriodController extends Controller
{
    /**
     * Build the filter form for the admin main page (route /booking/admin)
     * and rerun an array with  the form object and the date range and the action
     *
     * the return object :
     * array(
     *      "form" => FormBuilderInterface
     *      "from" => DateTime,
     *      "to" => DateTime,
     *      "job"=> Job|null,
     *      "filling" => str|null,
     *      "beneficiary" => Entity/Beneficiary
     *      )
     *
     * @param Request $request the request sent by the client, used to process the form
     * @param bool $withBeneficiaryField if true will add a beneficiary and a 'problematic' filter field
     * @return array
     */
    private function filterFormFactory(Request $request, bool $withBeneficiaryField): array
    {
        // default values
        $res = [
            'beneficiary' => null,
            'job' => null,
            'filling' => null,
            'week' => null,
        ];

        // filter creation ----------------------
        $formBuilder = $this->createFormBuilder()
            ->add('job', EntityType::class, array(
                'label' => 'Type de créneau',
                'class' => 'AppBundle:Job',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
                'query_builder' => function(JobRepository $repository) {
                    $qb = $repository->createQueryBuilder('j');
                    return $qb
                        ->where($qb->expr()->eq('j.enabled', '?1'))
                        ->setParameter('1', '1')
                        ->orderBy('j.name', 'ASC');
                }
            ))
            ->add('week', ChoiceType::class, array(
                'label' => 'Semaine',
                'required' => false,
                'choices' => array(
                    'A' => 'A',
                    'B' => 'B',
                    'C' => 'C',
                    'D' => 'D',
                ),
            ))
            ->add('filter', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'filtrer')
            ));

        if ($withBeneficiaryField) {
            $formBuilder
                ->setAction($this->generateUrl('period_admin'))
                ->add('beneficiary', AutocompleteBeneficiaryType::class, array(
                    'label' => 'Bénéficiaire',
                    'required' => false,
                ))
                ->add('filling', ChoiceType::class, array(
                    'label' => 'Remplissage',
                    'required' => false,
                    'choices' => array(
                        'Complet' => 'full',
                        'Partiel' => 'partial',
                        'Vide' => 'empty',
                        'Problématique' => 'problematic'
                    ),
                ));
        }else{
            $formBuilder
                ->setAction($this->generateUrl('period'))
                ->add('filling', ChoiceType::class, array(
                    'label' => 'Remplissage',
                    'required' => false,
                    'choices' => array(
                        'Complet' => 'full',
                        'Partiel' => 'partial',
                        'Vide' => 'empty',
                    ),
                ));
        }

        $res["form"] = $formBuilder->getForm();
        $res["form"]->handleRequest($request);

        if ($res["form"]->isSubmitted() && $res["form"]->isValid()) {
            if ($withBeneficiaryField) {
                $res["beneficiary"] = $res["form"]->get("beneficiary")->getData();
            }
            $res["job"] = $res["form"]->get("job")->getData();
            $res["filling"] = $res["form"]->get("filling")->getData();
            $res["week"] = $res["form"]->get("week")->getData();

        }

        return $res;
    }

    /**
     * Display all the period (available and reserved) anonymized
     *
     * @Route("/", name="period", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function indexAction(Request $request, EntityManagerInterface $em): Response
    {
        $filter = $this->filterFormFactory($request, false);
        $sort = 'start';
        $order = 'ASC';
        $periodsByDay = array();

        foreach (Period::DAYS_OF_WEEK as $i => $value) {
            $findByFilter = array('dayOfWeek' => $i);
            $periodsByDay[$i] = $em->getRepository('AppBundle:Period')->findAll($i, $filter['job'], false);
        }

        return $this->render('period/index.html.twig', array(
            'days_of_week' => Period::DAYS_OF_WEEK,
            'periods_by_day' => $periodsByDay,
            'filter_form' => $filter['form']->createView(),
            'week_filter' => $filter['week'],
            'filling_filter' => $filter["filling"]
        ));
    }

    /**
     * Display all the periods in a schedule (available and reserved)
     *
     * @Route("/admin", name="period_admin", methods={"GET","POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function adminIndexAction(Request $request, EntityManagerInterface $em): Response
    {
        $filter = $this->filterFormFactory($request, true);
        $periodsByDay = array();

        foreach (Period::DAYS_OF_WEEK as $i => $value) {
            $findByFilter = array('dayOfWeek' => $i);
            $periodsByDay[$i] = $em->getRepository('AppBundle:Period')->findAll($i, $filter['job'], true);
        }

        return $this->render('admin/period/index.html.twig', array(
            'days_of_week' => Period::DAYS_OF_WEEK,
            'periods_by_day' => $periodsByDay,
            'filter_form' => $filter['form']->createView(),
            'beneficiary_filter' => $filter['beneficiary'],
            'week_filter' => $filter['week'],
            'filling_filter' => $filter["filling"]
        ));
    }

    /**
     * @Route("/new", name="period_new", methods={"GET","POST"})
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
            return $this->redirectToRoute('period_edit',array('id'=>$period->getId()));
        }

        return $this->render('admin/period/new.html.twig',array(
            "form" => $form->createView()
        ));
    }

    /**
     * @Route("/{id}/edit", name="period_edit", methods={"GET","POST"})
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
            return $this->redirectToRoute('period_admin');
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
            "period_delete_form" => $periodDeleteForm->createView(),
            "position_add_form" => $positionAddForm->createView(),
            "positions_book_forms" => $positionsBookForms,
            "positions_free_forms" => $positionsFreeForms,
            "positions_delete_forms" => $positionsDeleteForms,
        ));
    }

    /**
     * @Route("/{id}/position/add", name="period_position_new", methods={"POST"})
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
            foreach ($form["week_cycle"]->getData() as $week_cycle) {
                $position->setWeekCycle($week_cycle);
                $position->setCreatedBy($current_user);
                foreach (range(0, $count-1) as $iteration) {
                    $p = clone($position);
                    $period->addPosition($p);
                    $em->persist($p);
                }
            }
            $em->persist($period);
            $em->flush();

            $session->getFlashBag()->add('success', $count . ' poste' . (($count>1) ? 's':'') . ' ajouté ' . (($count>1) ? 's':'') . ' (pour chaque cycle sélectionné) !');
        }

        return $this->redirectToRoute('period_edit',array('id'=>$period->getId()));
    }

    /**
     * @Route("/{id}/position/{position}", name="period_position_delete", methods={"DELETE"})
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

        return $this->redirectToRoute('period_edit',array('id'=>$period->getId()));
    }

    /**
     * Book a period.
     *
     * @Route("/{id}/position/{position}/book", name="period_position_book", methods={"POST"})
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
                return new Response($this->generateUrl('period_edit',array('id'=>$period->getId())), 205);
            }

            $beneficiary = $form->get("shifter")->getData();
            if ($position->getFormation() && !$beneficiary->getFormations()->contains($position->getFormation())) {
                $session
                    ->getFlashBag()
                    ->add("error", "Désolé, ce bénévole n'a pas la qualification nécessaire (" . $position->getFormation()->getName() . ")");
                return new Response($this->generateUrl('period_edit',array('id'=>$period->getId())), 205);
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

        return $this->redirectToRoute('period_edit',array('id'=>$period->getId()));
    }

    /**
     * Free a position.
     *
     * @Route("/{id}/position/{position}/free", name="period_position_free", methods={"POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function freePeriodPositionAction(Request $request, Period $period, PeriodPosition $position)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createPeriodPositionFreeForm($period, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $position->free();
            $em->persist($position);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le poste ' . $position . ' a bien été libéré !');
        }

        return $this->redirectToRoute('period_edit',array('id'=>$position->getPeriod()->getId()));
    }

    /**
     * Deletes a period entity.
     *
     * @Route("/{id}", name="period_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deletePeriodAction(Request $request, Period $period)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('period_delete', array('id' => $period->getId())))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($period);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le créneau type ' . $period . ' a bien été supprimé !');
        }

        return $this->redirectToRoute('period_admin');

    }

    /**
     * @Route("/copyPeriod/", name="period_copy", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function copyPeriodAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('period_copy'))
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

            return $this->redirectToRoute('period_admin');
        }

        return $this->render('admin/period/copy_periods.html.twig',array(
            "form" => $form->createView()
        ));
    }

    /**
     * @Route("/generateShifts/", name="shifts_generation", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function generateShiftsForDateAction(Request $request, KernelInterface $kernel)
    {
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

            return $this->redirectToRoute('period_admin');
        }

        return $this->render('admin/period/generate_shifts.html.twig',array(
            "form" => $form->createView()
        ));
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
            ->setAction($this->generateUrl('period_position_free', array('id' => $period->getId(), 'position' => $position->getId())))
            ->setMethod('POST')
            ->getForm();
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
            ->setAction($this->generateUrl('period_delete', array('id' => $period->getId())))
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
                    'period_position_new',
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
            ->setAction($this->generateUrl('period_position_book', array('id' => $period->getId(), 'position' => $position->getId())))
            ->setMethod('POST')
            ->add('shifter', AutocompleteBeneficiaryType::class, array('label' => 'Numéro d\'adhérent ou nom du membre', 'required' => true))
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
            ->setAction($this->generateUrl('period_position_delete', array('id' => $period->getId(), 'position' => $position->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
