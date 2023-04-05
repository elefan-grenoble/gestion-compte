<?php

namespace AppBundle\Controller;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Widget controller.
 * 
 * @Route("widget")
 */
class WidgetController extends Controller
{

    /**
     * Widget generator
     *
     * @Route("/generator", name="widget_generator", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function widgetBuilderAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('job', EntityType::class, array(
                'label' => 'Quel poste ?',
                'class' => 'AppBundle:Job',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => true
            ))
            ->add('display_end', CheckboxType::class, array('required' => false, 'label' => 'Afficher l\'heure de fin ?'))
            ->add('display_on_empty', CheckboxType::class, array('required' => false, 'label' => 'Afficher les créneaux vides ?'))
            ->add('title', CheckboxType::class, array('required' => false, 'data' => true, 'label' => 'Afficher le titre ?'))
            ->add('generate', SubmitType::class, array('label' => 'Générer'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {
            $data = $form->getData();

            $widgetQueryString = 'job_id='.$data['job']->getId().'&display_end='.($data['display_end'] ? 1 : 0).'&display_on_empty='.($data['display_on_empty'] ? 1 : 0).'&title='.($data['title'] ? 1 : 0);

            return $this->render('widget/generate.html.twig', array(
                'query_string' => $widgetQueryString,
                'form' => $form->createView(),
            ));
        }

        return $this->render('widget/generate.html.twig', array(
            'form' => $form->createView(),
        ));
    }
}
