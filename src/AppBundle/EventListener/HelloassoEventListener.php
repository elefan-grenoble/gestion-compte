<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Registration;
use AppBundle\Event\HelloassoEvent;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Container;

class HelloassoEventListener
{
    protected $_em;
    protected $container;

    public function __construct(EntityManager $entityManager, Container $container)
    {
        $this->_em = $entityManager;
        $this->container = $container;
    }

    public function onPaymentAfterSave(HelloassoEvent $event)
    {
        $payment = $event->getPayment();
        $campaign = $this->container->get('AppBundle\Helper\Helloasso')->get('campaigns/'.$payment->getCampaignId());
        if ($campaign->url == $this->container->getParameter('helloasso_registration_campaign_url')){ //good campaign
            $beneficiary = $this->_em->getRepository('AppBundle:Beneficiary')->findOneBy(array('email'=>$payment->getEmail()));
            if ($beneficiary){
                if (!$beneficiary->getMembership()->canRegister()){
                    //throw new \LogicException('user cannot register yet');
                }else{
                    $registration = new Registration();
                    $registration->setAmount($payment->getAmount());
                    $registration->setDate(new \DateTime('now'));
                    $registration->setHelloassoPayment($payment);
                    $registration->setMode(Registration::TYPE_HELLOASSO);
                    $registration->setUser($beneficiary->getUser());

                    $this->_em->persist($registration);
                    $payment->setRegistration($registration);
                    $beneficiary->getUser()->addRegistration($registration);
                    $this->_em->flush();
                }
            }else{
                //throw new \LogicException('user not found');
            }
        }
    }
}
