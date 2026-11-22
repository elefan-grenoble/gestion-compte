<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\SettingsType;
use App\Service\SettingsManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Security("is_granted('ROLE_SUPER_ADMIN')")
 */
class SuperAdminSettingsController extends AbstractController
{
    private SettingsManager $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * @Route(name="super_admin_settings", path="/superadmin/settings")
     */
    public function settings(Request $request): Response
    {
        $form = $this->createForm(SettingsType::class, $this->settingsManager->all());

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsManager->setAll($form->getData());

            return $this->redirectToRoute('super_admin_settings');
        }

        return $this->render('super_admin/settings.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
