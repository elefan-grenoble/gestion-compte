<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\SwipeCard;
use AppBundle\Service\SearchUserFormHelper;
use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use CodeItNow\BarcodeBundle\Utils\QrCode;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
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
                $session->getFlashBag()->add("warning","Si c'est le tiens, active le sur ton espace membre");
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

    /**
     * activate Swipe Card
     *
     * @param SwipeCard $card
     * @return Response
     * @Route("/active/", name="active_swipe")
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
            $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
            if (!$current_app_user->getBeneficiaries()->contains($card->getBeneficiary())){
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
        if (!$lastCard->getDisabledAt()){ //last card is not active
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
     * @Method({"GET"})
     */
    public function showAction(SwipeCard $card){
        return $this->render('user/swipe_card.html.twig', [
            'card' => $card
        ]);
    }


    /**
     * print Swipe Card
     *
     * @param SwipeCard $card
     * @return Response A Response instance
     * @Route("/print", name="swipe_print")
     * @Method({"POST"})
     */
    public function printAction(Request $request, SearchUserFormHelper $formHelper){
        $em = $this->getDoctrine()->getManager();
        $form = $formHelper->getSearchForm($this->createFormBuilder());
        $form->handleRequest($request);
        $qb = $formHelper->initSearchQuery($em);
        $users = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $qb = $formHelper->processSearchFormData($form,$qb);
            $users = $qb->getQuery()->getResult();
        }elseif ($request->get('beneficiary_id')&&$request->get('column')&&$request->get('line')){
            $beneficiary = $em->getRepository('AppBundle:Beneficiary')->findOneBy(array('id'=>intval($request->get('beneficiary_id'))));
            if ($beneficiary->getId()){
                $this->generateSwipeCard($beneficiary);
                $em->flush();
                $template = $this->renderView('user/swipe_card/printone.html.twig',[
                    'beneficiary' => $beneficiary,
                    'line' => intval($request->get('line')),
                    'column' => intval($request->get('column'))
                ]);
                $html2pdf = $this->get('AppBundle\Helper\Html2Pdf');
                $html2pdf->create('P','A4','fr',true,'UTF-8',array(0,0,0,0),false);
                return $html2pdf->generatePdf($template,'badges');
            }else{
                return $this->redirectToRoute('homepage');
            }
        }else{
            throw $this->createAccessDeniedException();
        }
        if ($users){
            foreach ($users as $user){
                foreach ($user->getBeneficiaries() as $beneficiary){
                    $this->generateSwipeCard($beneficiary,false);
                }
            }
            $em->flush();
            $template = $this->renderView('user/swipe_card/print.html.twig',[
                'users' => $users
            ]);
            $html2pdf = $this->get('AppBundle\Helper\Html2Pdf');
            $html2pdf->create('P','A4','fr',true,'UTF-8',array(0,0,0,0),false);
            return $html2pdf->generatePdf($template,'badges');
            }
        return $this->redirectToRoute('homepage');
    }
}
