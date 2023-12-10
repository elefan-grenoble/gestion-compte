<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Code;
use AppBundle\Entity\CodeDevice;
use AppBundle\Security\CodeVoter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Admin Code controller.
 *
 * @Route("admin")
 */
class AdminCodeController extends Controller
{

    /**
     * Lists all codes.
     *
     * @Route("/code/", name="admin_code_index", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function listAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $code_devices = $em->getRepository('AppBundle:CodeDevice')->findAll();

        if (!$code_devices) {
            $session->getFlashBag()->add('warning', 'Commençons par créer un équipement à code');
            return $this->redirectToRoute('admin_codedevice_new');
        }
        $active_codes = $em->getRepository('AppBundle:Code')->findActiveCodes();
        $active_codes_per_device = $this->get('code_service')->groupCodesPerDevice($active_codes);
        $old_codes = $em->getRepository('AppBundle:Code')->findOldCodes(10);
        $old_codes_per_device = $this->get('code_service')->groupCodesPerDevice($old_codes);

        $delete_forms = [];
        foreach (array_merge($active_codes,$old_codes) as $code) {
            $delete_forms[$code->getId()] = $this->getDeleteForm($code)->createView();
        }

        return $this->render('admin/code/list.html.twig', array(
            'active_codes' => $active_codes_per_device,
            'old_codes' => $old_codes_per_device,
            'code_devices' => $code_devices,
            'delete_forms' => $delete_forms,
        ));
    }

    /**
     * Add a new code
     *
     * @Route("/codedevice/{id}/code/new", name="admin_code_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function newAction(Request $request, CodeDevice $codeDevice)
    {
        $session = new Session();
        $code = new Code();
        $form = $this->getNewForm($code, $codeDevice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $code->setRegistrar($this->getUser());
            $code->setClosed(0);
            $code->setCodeDevice($codeDevice);
            if ($form->has('generate_random_value') && $form->get('generate_random_value')->getData()) {
                $value = rand(0,9999); // code aléatoire à 4 chiffres
                $code->setValue($value);
            }
            if($codeDevice->getType() == 'igloohome')
            {
                $passcode = $this->get('code_service')->generateIgloohomeLockCode($codeDevice, $code);
                if($passcode == null) {
                    $session->getFlashBag()->add('error', 'Impossible de générer le code igloohome');
                    return $this->redirectToRoute('admin_code_index');
                }
                $code->setValue($passcode);
            }
            $em = $this->getDoctrine()->getManager();
            $em->persist($code);

            if ($form->has('deactivate_old_codes') && $form->get('deactivate_old_codes')->getData()) {
                // deactivate old codes
                $active_codes = $em->getRepository('AppBundle:Code')->findBy(array('codeDevice' => $code->getCodeDevice(), 'closed' => 0));
                foreach ($active_codes as $c) {
                    $c->setClosed(true);
                    $em->persist($c);
                }
            }

            $em->flush();

            $session->getFlashBag()->add('success', '🎉 Nouveau code enregistré.');
            return $this->redirectToRoute('admin_code_index');
        }

        return $this->render('admin/code/new.html.twig', array(
            'code' => $code,
            'form' => $form->createView(),
        ));
    }

    /**
     * toggle code
     *
     * @Route("/code/{id}/toggle", name="admin_code_toggle", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function toggleAction(Request $request, Code $code)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        if ($code->getClosed())
            $this->denyAccessUnlessGranted('activate',$code);
        else
            $this->denyAccessUnlessGranted('deactivate',$code);

        $code->setClosed(!$code->getClosed());

        $em->persist($code);
        $em->flush();

        $session->getFlashBag()->add('success', 'Le code a bien été marqué '.(($code->getClosed())?'fermé':'ouvert').' !');

        return $this->redirectToRoute('admin_code_index');
    }


    /**
     * delete code
     *
     * @Route("/code/{id}", name="admin_code_delete", methods={"DELETE"})
     */
    public function deleteAction(Request $request, Code $code)
    {
        $this->denyAccessUnlessGranted('delete', $code);

        $session = new Session();

        $form = $this->getDeleteForm($code);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($code);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le code a bien été supprimé !');
        }
        return $this->redirectToRoute('admin_code_index');
    }

    /**
     * Creates a form to add a new code.
     *
     * @param Code $code
     * @return \Symfony\Component\Form\Form
     */
    private function getNewForm(Code $code, CodeDevice $codeDevice)
    {
        return $this->get('form.factory')->createNamed(
            'appbundle_code',
            'AppBundle\Form\CodeType',
            $code,
            array(
                'action' => $this->generateUrl('admin_code_new', array('id' => $codeDevice->getId())),
                'codedevice_type' => $codeDevice->getType(),
            ));
    }

    /**
     * @param Code $code
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Code $code) {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_code_delete', array('id' => $code->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }


}
