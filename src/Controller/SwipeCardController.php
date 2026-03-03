<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use App\Helper\SwipeCard as SwipeCardHelper;
use App\Entity\SwipeCard as SwipeCardEntity;
use App\Security\SwipeCardVoter;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\ErrorCorrectionLevel;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * User controller.
 *
 * @Route("sw") //keep it short for qr size
 */
class SwipeCardController extends AbstractController
{


    private $logger;
    private SwipeCardHelper $swipeCardHelper;

    public function __construct(LoggerInterface $logger, SwipeCardHelper $swipeCardHelper)
    {
        $this->logger = $logger;
        $this->swipeCardHelper = $swipeCardHelper;
    }

    /**
     * Swipe Card login
     * used to connect to the app using qr
     *
     * @param String $code
     * @param Request $request
     * @return Response
     * @Route("/in/{code}", name="swipe_in", methods={"GET"})
     */
    public function swipeInAction(Request $request, $code, EventDispatcherInterface $event_dispatcher)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $code = $this->swipeCardHelper->vigenereDecode($code);
        $card = $em->getRepository('App:SwipeCard')->findLastEnable($code);

        if (!$card) {
            $session->getFlashBag()->add("error","Oups, ce badge n'est pas actif ou n'est pas associé à un compte");
            $card = $em->getRepository('App:SwipeCard')->findOneBy(array("code"=>$code));
            if ($card && !$card->getEnable() && !$card->getDisabledAt())
                $session->getFlashBag()->add("warning","Si c'est le tiens, <a href=\"".$this->generateUrl('fos_user_security_login')."\">connecte toi</a> sur ton espace membre pour l'activer");
        } else {
            $user = $card->getBeneficiary()->getUser();
            $token = new UsernamePasswordToken($user, $user->getPassword(), "main", $user->getRoles());
            $this->get("security.token_storage")->setToken($token);
            $event = new InteractiveLoginEvent($request, $token);
            $event_dispatcher->dispatch($event, "security.interactive_login");
        }

        return $this->redirectToRoute('homepage');
    }

    public function homepageAction()
    {
        return $this->render('user/swipe_card/homepage.html.twig');
    }

    /**
     * activate (pair) Swipe Card
     *
     * @Route("/activate", name="activate_swipe", methods={"POST"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function activateSwipeCardAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $this->denyAccessUnlessGranted(SwipeCardVoter::PAIR, new SwipeCardEntity());
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $referer = $request->headers->get('referer');
        $code = $request->get("code");
        $beneficiaryId = $request->get("beneficiary");

        // verify code
        if (!SwipeCardEntity::checkEAN13($code)) {
            $session->getFlashBag()->add('error', 'Hum, ces chiffres ne correspondent pas à un code badge valide... 🤔');
            return new RedirectResponse($referer);
        }
        $code = substr($code, 0, -1);  // remove controle
        if ($code === '421234567890') {
            $session->getFlashBag()->add('warning', 'Hihi, ceci est le numéro d\'exemple 😁 Utilise un badge physique 🍌');
            return new RedirectResponse($referer);
        }

        // get beneficiary
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $em->getRepository('App:Beneficiary')->find($beneficiaryId);

        // beneficiary should have 0 enabled cards
        $beneficiaryCards = $beneficiary->getEnabledSwipeCards();
        if ($beneficiaryCards->count()) {
            if ($current_user === $beneficiary->getUser()) {
                $session->getFlashBag()->add('error', 'Ton compte possède déjà un badge actif');
            } else {
                $session->getFlashBag()->add('error', 'Il existe déjà un badge actif associé à ce compte');
            }
            return new RedirectResponse($referer);
        }

        // card should not be already in use
        $card = $em->getRepository('App:SwipeCard')->findOneBy(array('code' => $code));
        if ($card) {
            if ($beneficiary != $card->getBeneficiary()) {
                $session->getFlashBag()->add('error', 'Ce badge est déjà associé à un autre utilisateur 👮');
            } else {
                $session->getFlashBag()->add('error', 'Oups ! Ce badge est déjà associé mais il est inactif. Réactive-le !');
            }
            return new RedirectResponse($referer);
        }

        $lastCard = $em->getRepository('App:SwipeCard')->findLast($beneficiary);
        $card = new SwipeCardEntity();
        $card->setBeneficiary($beneficiary);
        $card->setCode($code);
        $card->setNumber($lastCard ? max($lastCard->getNumber(),$beneficiary->getSwipeCards()->count()) + 1 : 1);
        $card->setEnable(true);
        $em->persist($card);
        $em->flush();

        $session->getFlashBag()->add('success', 'Le badge ' . $card->getcode() . ' a bien été associé à ton compte.');
        return new RedirectResponse($referer);
    }

    /**
     * enable existing Swipe Card
     *
     * @Route("/enable", name="enable_swipe", methods={"POST"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function enableSwipeCardAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $referer = $request->headers->get('referer');
        $code = $request->get("code");
        $code = $this->swipeCardHelper->vigenereDecode($code);
        $beneficiaryId = $request->get("beneficiary");

        // get beneficiary
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $em->getRepository('App:Beneficiary')->find($beneficiaryId);

        // beneficiary should have 0 enabled cards
        $beneficiaryCards = $beneficiary->getEnabledSwipeCards();
        if ($beneficiaryCards->count()) {
            $session->getFlashBag()->add('error', 'Tu as déjà un badge actif');
            return new RedirectResponse($referer);
        }

        $card = $em->getRepository('App:SwipeCard')->findOneBy(array('code' => $code));
        if ($card) {
            $this->denyAccessUnlessGranted(SwipeCardVoter::ENABLE, $card);
            if ($beneficiary != $card->getBeneficiary()) {
                if ($current_user === $beneficiary->getUser())
                    $session->getFlashBag()->add('error', 'Ce badge ne t\'appartient pas');
                else
                    $session->getFlashBag()->add('error', 'Ce badge n\'appartient pas au bénéficiaire');
            } else {
                $card->setEnable(true);
                $card->setDisabledAt(null);
                $em->persist($card);
                $em->flush();
                $session->getFlashBag()->add('success', 'Le badge #' . $card->getNumber() . ' a bien été réactivé');
            }
        } else {
            $session->getFlashBag()->add('error', 'Aucun badge ne correspond à ce code');
        }

        return new RedirectResponse($referer);
    }

    /**
     * disable Swipe Card
     *
     * @Route("/disable", name="disable_swipe", methods={"POST"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function disableSwipeCardAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $referer = $request->headers->get('referer');
        $code = $request->get("code");
        $code = $this->swipeCardHelper->vigenereDecode($code);
        $beneficiaryId = $request->get("beneficiary");

        // get beneficiary
        /** @var Beneficiary|null $beneficiary */
        $beneficiary = $em->getRepository('App:Beneficiary')->find($beneficiaryId);

        $card = $em->getRepository('App:SwipeCard')->findOneBy(array('code'=>$code));
        if ($card) {
            $this->denyAccessUnlessGranted(SwipeCardVoter::DISABLE, $card);
            if ($beneficiary != $card->getBeneficiary()) {
                if ($current_user === $beneficiary->getUser())
                    $session->getFlashBag()->add('error', 'Ce badge ne t\'appartient pas');
                else
                    $session->getFlashBag()->add('error', 'Ce badge n\'appartient pas au bénéficiaire');
            } else {
                $card->setEnable(false);
                $em->persist($card);
                $em->flush();
                $session->getFlashBag()->add('success','Ce badge est maintenant désactivé');
            }
        } else {
            $session->getFlashBag()->add('error','Aucune badge trouvé');
        }

        return new RedirectResponse($referer);
    }

    /**
     * remove Swipe Card
     *
     * @Route("/delete", name="delete_swipe", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function deleteAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $referer = $request->headers->get('referer');
        $code = $request->get("code");
        $code = $this->swipeCardHelper->vigenereDecode($code);

        $card = $em->getRepository('App:SwipeCard')->findOneBy(array('code'=>$code));

        if ($card) {
            $this->denyAccessUnlessGranted(SwipeCardVoter::DELETE, $card);
            $em->remove($card);
            $em->flush();
            $session->getFlashBag()->add('success','Le badge a bien été supprimé');
        } else {
            $session->getFlashBag()->add('error','Aucune badge trouvé');
        }

        return new RedirectResponse($referer);
    }

    /**
     * show Swipe Card
     *
     * @param SwipeCardEntity $card
     * @return Response A Response instance
     * @Route("/{id}/show", name="swipe_show", methods={"GET"})
     * @Security("is_granted('ROLE_USER_MANAGER')")
     */
    public function showAction(SwipeCardEntity $card){
        return $this->render('user/swipe_card.html.twig', [
            'card' => $card
        ]);
    }

    private function _getQr($url) {
        $qrCode = new QrCode($url);
        try {
            $qrCode->setSize(200)
                ->setMargin(0)
                ->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH)
                ->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0])
                ->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255])
                ->setEncoding('UTF-8');

            // Return the QR code as a base64-encoded PNG image:
            return 'data:image/png;base64,' . base64_encode($qrCode->writeString());
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * Swipe Card QR Code
     *
     * @param String $code
     * @return Response A Response instance
     * @Route("/{code}/qr.png", name="swipe_qr", methods={"GET"})
     */
    public function qrAction(Request $request, $code){
        $code = urldecode($code);
        $code = $this->swipeCardHelper->vigenereDecode($code);
        $em = $this->getDoctrine()->getManager();
        $card = $em->getRepository('App:SwipeCard')->findOneBy(array('code'=>$code));
        if (!$card){
            throw $this->createAccessDeniedException();
        }

        $url = $this->generateUrl('swipe_in',array('code'=>$this->swipeCardHelper->vigenereEncode($card->getCode())),UrlGeneratorInterface::ABSOLUTE_URL);
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
     * @Route("/{code}/br.png", name="swipe_br", methods={"GET"})
     */
    public function brAction(Request $request, $code){
        $code = urldecode($code);
        $code = $this->swipeCardHelper->vigenereDecode($code);
        $em = $this->getDoctrine()->getManager();
        $card = $em->getRepository('App:SwipeCard')->findOneBy(array('code'=>$code));
        if (!$card instanceof SwipeCardEntity){
            throw $this->createAccessDeniedException();
        }
        $content = $card->getBarcode();

        return new Response(
            $content,
            Response::HTTP_OK,
            [
                'Content-Type' => 'image/png',
                'Content-Length' => strlen($content),
                'Content-Disposition' => HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE,'br.png')
            ]
        );
    }


}
