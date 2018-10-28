<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\SwipeCard;
use AppBundle\Entity\User;
use AppBundle\Service\SearchUserFormHelper;
use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use CodeItNow\BarcodeBundle\Utils\QrCode;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * User controller.
 *
 * @Route("sw") //keep it short for qr size
 */
class SwipeCardController extends Controller
{

    /**
     * Swipe Card login
     * used to connect to the app using qr
     *
     * @param String $code
     * @param Request $request
     * @return Response
     * @Route("/in/{code}", name="swipe_in")
     * @Method({"GET"})
     */
    public function swipeInAction(Request $request, $code){
        $session = new Session();
        $code = $this->get('AppBundle\Helper\SwipeCard')->vigenereDecode($code);
        $em = $this->getDoctrine()->getManager();
        $card = $em->getRepository('AppBundle:SwipeCard')->findLastEnable($code);
        if (!$card){
            $session->getFlashBag()->add("error","Oups, ce badge n'est pas actif ou n'existe pas");
            $card = $em->getRepository('AppBundle:SwipeCard')->findOneBy(array("code"=>$code));
            if ($card && !$card->getEnable() && !$card->getDisabledAt())
                $session->getFlashBag()->add("warning","Si c'est le tiens, <a href=\"".$this->generateUrl('fos_user_security_login')."\">connecte toi</a> sur ton espace membre pour l'activer");
        }else{
            $user = $card->getBeneficiary()->getUser();
            $token = new UsernamePasswordToken($user, $user->getPassword(), "main", $user->getRoles());
            $this->get("security.token_storage")->setToken($token);
            $event = new InteractiveLoginEvent($request, $token);
            $this->get("event_dispatcher")->dispatch("security.interactive_login", $event);
        }
        return $this->redirectToRoute('homepage');
    }
    /**
     * generate Swipe Card
     *
     * @param Beneficiary $beneficiary
     * @return Response
     * @Route("/generate/{id}", name="generate_swipe")
     * @Security("has_role('ROLE_USER_MANAGER')")
     * @Method({"GET"})
     */
    public function generateSwipeCardAction(Beneficiary $beneficiary){
        $session = new Session();
        $result = $this->generateSwipeCard($beneficiary);
        if (!$result){
            $session->getFlashBag()->add('error','Un badge est déjà actif');
            return $this->redirectToRoute('user_show',array('username'=>$beneficiary->getUser()->getUsername()));
        }else{
            $session->getFlashBag()->add('success','Le badge #'.$result->getNumber().' a bien été généré');
            return $this->redirectToRoute('swipe_show',array('id'=>$result->getId()));
        }
    }

    public function homepageAction(){
        return $this->render('user/swipe_card/homepage.html.twig');
    }

    /**
     * activate Swipe Card
     *
     * @param SwipeCard $card
     * @return Response
     * @Route("/active/", name="active_swipe")
     * @Security("has_role('ROLE_USER')")
     * @Method({"POST"})
     */
    public function activeSwipeCardAction(Request $request){
        $session = new Session();
        $code = intval($request->get("code"));
        $card_id = intval($request->get("card_id"));
        $em = $this->getDoctrine()->getManager();
        $card = $em->getRepository('AppBundle:SwipeCard')->findOneBy(array('id'=>$card_id));
        if (!$card || !$card->getId()){
            $session->getFlashBag()->add('error','Badge non trouvé avec ce numéro');
            return $this->redirectToRoute('homepage');
        }else{
            /** @var User $current_app_user */
            $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
            $membership = $current_app_user->getBeneficiary()->getMembership();
            if (!$membership->getBeneficiaries()->contains($card->getBeneficiary())){
                $session->getFlashBag()->add('error','Ce badge ne t\'appartient pas !');
                return $this->redirectToRoute('homepage');
            }else{
                if ($card->getCode()!=$code){
                    $session->getFlashBag()->add('error','Le code ne correspond pas !');
                    return $this->redirectToRoute('homepage');
                }else {
                    $card->setEnable(1);
                    $em->persist($card);
                    $em->flush();
                    $session->getFlashBag()->add('success','Le badge #'.$card->getNumber().' a bien été activé !');
                    return $this->redirectToRoute('homepage');
                }
            }
        }
    }

    private function generateSwipeCard(Beneficiary $beneficiary, $flush = true){
        $em = $this->getDoctrine()->getManager();
        $card = $em->getRepository('AppBundle:SwipeCard')->findLastEnable(null,$beneficiary);
        if ($card){
            return false;
        }
        $lastCard = $em->getRepository('AppBundle:SwipeCard')->findLast($beneficiary);
        if ($lastCard && !$lastCard->getDisabledAt()){ //last card is not active
            return false;
        }

        $card = new SwipeCard();
        $code = null;
        $exist = true;
        while ($exist){
            $code = $this->get('AppBundle\Helper\SwipeCard')->generateCode();
            $exist = $em->getRepository('AppBundle:SwipeCard')->findOneBy(array('code'=>$code));
        }
        $card->setCode($code);
        $card->setBeneficiary($beneficiary);
        $card->setNumber($lastCard ? $lastCard->getNumber() + 1 : 1 );
        $card->setEnable(0); // default is not enable
        $beneficiary->addSwipeCard($card);
        $em->persist($beneficiary);
        $em->persist($card);
        if ($flush)
            $em->flush();
        return $card;
    }

    /**
     * show Swipe Card
     *
     * @param SwipeCard $card
     * @return Response A Response instance
     * @Route("/{id}/show", name="swipe_show")
     * @Security("has_role('ROLE_USER_MANAGER')")
     * @Method({"GET"})
     */
    public function showAction(SwipeCard $card){
        return $this->render('user/swipe_card.html.twig', [
            'card' => $card
        ]);
    }

    private function _getQr($url){
        $qrCode = new QrCode();
        $qrCode
                ->setText($url)
                ->setSize(200)
                ->setPadding(0)
                ->setErrorCorrection('high')
                ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
                ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
                ->setImageType(QrCode::IMAGE_TYPE_PNG);
        return $qrCode->generate();
    }

    /**
     * Swipe Card QR Code
     *
     * @param String $code
     * @return Response A Response instance
     * @Route("/{code}/qr.png", name="swipe_qr")
     * @Method({"GET"})
     */
    public function qrAction(Request $request, $code){
        $code = $this->get('AppBundle\Helper\SwipeCard')->vigenereDecode($code);
        $em = $this->getDoctrine()->getManager();
        $card = $em->getRepository('AppBundle:SwipeCard')->findOneBy(array('code'=>$code));
        if (!$card){
            throw $this->createAccessDeniedException();
        }

        $url = $this->generateUrl('swipe_in',array('code'=>$this->get('AppBundle\Helper\SwipeCard')->vigenereEncode($card->getCode())),UrlGeneratorInterface::ABSOLUTE_URL);
        $content = base64_decode($this->_getQr($url));
        $response = new Response();
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE,'qr.png');
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set("Content-length",strlen($content));
        $response->headers->set('Content-Type', 'image/png');
        $response->setContent($content);

        return $response;
    }

    /**
     * Swipe Card QR Code
     *
     * @param String $code
     * @return Response A Response instance
     * @Route("/{code}/br.png", name="swipe_br")
     * @Method({"GET"})
     */
    public function brAction(Request $request, $code){
        $code = $this->get('AppBundle\Helper\SwipeCard')->vigenereDecode($code);
        $em = $this->getDoctrine()->getManager();
        $card = $em->getRepository('AppBundle:SwipeCard')->findOneBy(array('code'=>$code));
        if (!$card){
            throw $this->createAccessDeniedException();
        }
        $content = base64_decode($card->getBarcode());
        $response = new Response();
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE,'br.png');
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set("Content-length",strlen($content));
        $response->headers->set('Content-Type', 'image/png');
        $response->setContent($content);

        return $response;
    }

    /**
     * print Swipe Card
     *
     * @param SwipeCard $card
     * @return Response A Response instance
     * @Route("/print", name="swipe_print")
     * @Security("has_role('ROLE_USER_MANAGER')")
     * @Method({"POST"})
     */
    public function printAction(Request $request, SearchUserFormHelper $formHelper){
        $em = $this->getDoctrine()->getManager();
        $form = $formHelper->getSearchForm($this->createFormBuilder());
        $form->handleRequest($request);
        $qb = $formHelper->initSearchQuery($em);
        $memberships = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $qb = $formHelper->processSearchFormData($form,$qb);
            $memberships = $qb->getQuery()->getResult();
        }elseif ($request->get('beneficiary_id')&&$request->get('column')&&$request->get('line')){
            $beneficiary = $em->getRepository('AppBundle:Beneficiary')->findOneBy(array('id'=>intval($request->get('beneficiary_id'))));
            if ($beneficiary->getId()){
                $this->generateSwipeCard($beneficiary);
                $card = $beneficiary->getSwipeCards()->first();
                $barcodeImg = $card->getBarcode();
                $qr_swipein_url = $this->generateUrl('swipe_in',array('code'=>$this->get('AppBundle\Helper\SwipeCard')->vigenereEncode($card->getCode())),UrlGeneratorInterface::ABSOLUTE_URL);
                $qrImg = $this->_getQr($qr_swipein_url);
                if (!is_dir($this->getParameter('images_tmp_dir')))
                    mkdir($this->getParameter('images_tmp_dir'));
                file_put_contents($this->getParameter('images_tmp_dir').'/'.$card->getCode().'_bc.png',base64_decode($barcodeImg));
                file_put_contents($this->getParameter('images_tmp_dir').'/'.$card->getCode().'_qr.png',base64_decode($qrImg));
                $template = $this->renderView('user/swipe_card/printone.html.twig',[
                    'beneficiary' => $beneficiary,
                    'line' => intval($request->get('line')),
                    'column' => intval($request->get('column'))
                ]);
                $html2pdf = $this->get('AppBundle\Helper\Html2Pdf');
                $html2pdf->create('P','A4','fr',true,'UTF-8',array(0,0,0,0),false);
                $response = $html2pdf->generatePdf($template,'badge_'.$beneficiary->getId());
                unlink($this->getParameter('images_tmp_dir').'/'.$card->getCode().'_bc.png');
                unlink($this->getParameter('images_tmp_dir').'/'.$card->getCode().'_qr.png');
                return $response;
            }else{
                return $this->redirectToRoute('homepage');
            }
        }else{
            throw $this->createAccessDeniedException();
        }
        if ($memberships){
            /** @var User $user */
            foreach ($memberships as $membership){
                $beneficiaries = $membership->getBeneficiaries();
                foreach ($beneficiaries as $beneficiary){
                    $this->generateSwipeCard($beneficiary,false);
                    $card = $beneficiary->getSwipeCards()->first();
                    $barcodeImg = $card->getBarcode();
                    $qr_swipein_url = $this->generateUrl('swipe_in',array('code'=>$this->get('AppBundle\Helper\SwipeCard')->vigenereEncode($card->getCode())),UrlGeneratorInterface::ABSOLUTE_URL);
                    $qrImg = $this->_getQr($qr_swipein_url);
                    if (!is_dir($this->getParameter('images_tmp_dir')))
                        mkdir($this->getParameter('images_tmp_dir'));
                    file_put_contents($this->getParameter('images_tmp_dir').'/'.$card->getCode().'_bc.png',base64_decode($barcodeImg));
                    file_put_contents($this->getParameter('images_tmp_dir').'/'.$card->getCode().'_qr.png',base64_decode($qrImg));
                }
            }
            $em->flush();
            $template = $this->renderView('user/swipe_card/print.html.twig',[
                'memberships' => $memberships
            ]);
            $html2pdf = $this->get('AppBundle\Helper\Html2Pdf');
            $html2pdf->create('P','A4','fr',true,'UTF-8',array(0,0,0,0),false);
            $response = $html2pdf->generatePdf($template,'badges');
            /** @var User $user */
            foreach ($memberships as $membership){
                $beneficiaries = $membership->getBeneficiaries();
                foreach ($beneficiaries as $beneficiary){
                    $card = $beneficiary->getSwipeCards()->first();
                    unlink($this->getParameter('images_tmp_dir').'/'.$card->getCode().'_bc.png');
                    unlink($this->getParameter('images_tmp_dir').'/'.$card->getCode().'_qr.png');
                }
            }
            return $response;
            }
        return $this->redirectToRoute('homepage');
    }
}
