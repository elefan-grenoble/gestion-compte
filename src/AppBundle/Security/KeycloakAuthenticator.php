<?php

namespace AppBundle\Security;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Commission;
use AppBundle\Entity\Formation;
use AppBundle\Entity\Membership;
use AppBundle\Entity\MembershipShiftExemption;
use AppBundle\Entity\Note;
use AppBundle\Entity\Proxy;
use AppBundle\Entity\Registration;
use AppBundle\Entity\User;
use AppBundle\Event\BeneficiaryCreatedEvent;
use AppBundle\EventListener\SetFirstPasswordListener;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use KnpU\OAuth2ClientBundle\Client\Provider\KeycloakClient;
use KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator;
use PHPUnit\Util\Type;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class KeycloakAuthenticator
 */
class KeycloakAuthenticator extends SocialAuthenticator
{

    /**
     * @var ClientRegistry
     */
    private $clientRegistry;
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        EventDispatcher $eventDispatcher,
        ContainerInterface $container
    )
    {
        $this->clientRegistry = $clientRegistry;
        $this->em = $entityManager;
        $this->router = $router;
        $this->eventDispatcher = $eventDispatcher;
        $this->container = $container;
    }

    public function start(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $authException = null)
    {
        return new RedirectResponse(
            '/oauth/login', // might be the site, where users choose their oauth provider
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }

    public function supports(Request $request)
    {
        return $request->attributes->get('_route') === 'oauth_check';
    }

    public function getCredentials(Request $request)
    {
        return $this->fetchAccessToken($this->getKeycloakClient());
    }

    public function getUser($credentials, \Symfony\Component\Security\Core\User\UserProviderInterface $userProvider)
    {
        $keycloakUser = $this->getKeycloakClient()->fetchUserFromToken($credentials);
        /** @var Beneficiary $existingBeneficiary */
        $existingBeneficiary = $this
            ->em
            ->getRepository(Beneficiary::class)
            ->findOneBy(['openid' => $keycloakUser->getId()]);
        if ($existingBeneficiary) {
            $this->updateBeneficiary($keycloakUser,$existingBeneficiary);
            $membership = $existingBeneficiary->getMembership();
            $membership->setMemberNumber($existingBeneficiary->getOpenIdMemberNumber());

            // co_member
            $this->updateCoMembership($keycloakUser,$existingBeneficiary);

            $this->em->flush();
            return $existingBeneficiary->getUser();
        }
        // if user exist but never connected with keycloak
        $email = $keycloakUser->getEmail();
        if (!$email) { //may be an admin ?
            return null;
        }
        /** @var User $userInDatabase */
        $userInDatabase = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email]);
        if($userInDatabase) {
            $userInDatabase->getBeneficiary()->setOpenId($keycloakUser->getId());
            $this->updateBeneficiary($keycloakUser,$userInDatabase->getBeneficiary());

            $userInDatabase->getBeneficiary()->setOpenId($keycloakUser->getId());
            $userInDatabase->getBeneficiary()->getUser()->setEnabled(true);

            // co_member
            $this->updateCoMembership($keycloakUser,$userInDatabase->getBeneficiary());

            $this->em->flush();
            return $userInDatabase;
        }
        //user not exist in database
        $beneficiary = new Beneficiary();

        // co_member
        $is_co_member = $this->updateCoMembership($keycloakUser,$beneficiary);
        if (!$is_co_member){
            $this->createMembership($beneficiary,$this->getKeycloakUserAttribute($keycloakUser,'member_number',1));
        }

        $this->updateBeneficiary($keycloakUser,$beneficiary);

        $beneficiary->setOpenId($keycloakUser->getId());
        $beneficiary->getUser()->setEnabled(true);

        $this->em->flush();
        return $beneficiary->getUser();
    }

    /**
     * @param KeycloakResourceOwner $keycloakUser
     * @param Beneficiary $beneficiary
     * @return bool
     */
    private function updateCoMembership(KeycloakResourceOwner $keycloakUser,Beneficiary $beneficiary) : bool
    {
        $is_co_member = false;
        $co_member = $this->getKeycloakUserAttribute($keycloakUser,'co_member_number'); //co_member_number
        if ($co_member){
            /** @var Beneficiary $existingCoBeneficiary */
            $existingCoBeneficiary = $this
                ->em
                ->getRepository(Beneficiary::class)
                ->findOneBy(['openid_member_number' => $co_member]);
            if ($existingCoBeneficiary) {
                $is_co_member = true;
                $membership = $existingCoBeneficiary->getMembership();
                $oldMembership = $beneficiary->getMembership();
                if ($oldMembership===$membership){
                    return $is_co_member;
                }
                $membership->addBeneficiary($beneficiary);
                $beneficiary->setMembership($membership);
                $this->em->persist($membership);
                $this->em->persist($beneficiary);
                $this->em->flush();
                if ($oldMembership){
                    $oldMembership->removeBeneficiary($beneficiary);
                    $oldMembership->setMainBeneficiary(null);
                    $oldMembership->setMemberNumber(10000+$oldMembership->getMemberNumber());

                    /** @var Proxy $givenProxy */
                    foreach ($oldMembership->getGivenProxies() as $givenProxy){
                        $oldMembership->removeGivenProxy($givenProxy);
                        $givenProxy->setGiver($membership);
                        $membership->addGivenProxy($givenProxy);
                        $this->em->persist($givenProxy);
                    }
                    /** @var Note $note */
                    foreach ($oldMembership->getNotes() as $note){
                        $oldMembership->removeNote($note);
                        $note->setSubject($membership);
                        $membership->addNote($note);
                        $this->em->persist($note);
                    }
                    /** @var MembershipShiftExemption $membershipShiftExemption */
                    foreach ($oldMembership->getMembershipShiftExemptions() as $membershipShiftExemption){
                        $membershipShiftExemption->setMembership($membership);
                        $this->em->persist($membershipShiftExemption);
                    }

                    $this->em->persist($oldMembership);
                    $this->em->flush();
                    $this->em->remove($oldMembership);
                    $this->em->flush();
                }
            }
        }else if($beneficiary->getMembership()){ //no co user
            $membership = $beneficiary->getMembership();
            /** @var Beneficiary $b */
            foreach ($membership->getBeneficiaries() as $b){
                //if membership is shared
                if ($b !== $beneficiary && $beneficiary->getMembership()->getMainBeneficiary() !== $b){ //should create a new membership for this beneficiary
                    $membership->removeBeneficiary($b);
                    $membership->setMemberNumber($beneficiary->getOpenIdMemberNumber());
                    $this->em->persist($membership);
                    $this->em->flush();
                    $this->createMembership($b);
                }elseif ($b !== $beneficiary && $beneficiary->getMembership()->getMainBeneficiary() === $b) //should create a new membership for me (beneficiary)
                {
                    $membership->setMemberNumber($b->getOpenIdMemberNumber());
                    $membership->removeBeneficiary($beneficiary);
                    $this->em->persist($membership);
                    $this->em->flush();
                    $this->createMembership($beneficiary);
                }
            }
        }

        return $is_co_member;
    }

    /**
     * @param Beneficiary $beneficiary
     * @return void
     */
    private function createMembership(Beneficiary $beneficiary,$member_number = null): void
    {
        if (!$member_number){
            $member_number = $beneficiary->getOpenIdMemberNumber();
        }
        if (!$member_number){
            $member_number = rand(10000,100000);
        }
        $membership = new Membership();
        $membership->setMemberNumber($member_number);
        $membership->setWithdrawn(false);
        $membership->setFrozen(false);
        $membership->setFrozenChange(false);
        $membership->setMainBeneficiary($beneficiary);

        $registration = new Registration();
        $registration->setDate(new \DateTime('now'));
        $registration->setMembership($membership);
        $registration->setMode(Registration::TYPE_DEFAULT);
        $registration->setAmount(0);

        $this->em->persist($registration);
    }

    /**
     * @param KeycloakResourceOwner $keycloakUser
     * @param Beneficiary $beneficiary
     * @return Void
     * @throws \Exception
     */
    private function updateBeneficiary(KeycloakResourceOwner $keycloakUser,Beneficiary $beneficiary) : Void
    {

        $mandatory = ['firstname','lastname','member_number'];
        $default = ['flying' => false];
        foreach ([
             'firstname'=>'setFirstname',
             'lastname'=>'setLastname',
             'phone'=>'setPhone',
             'member_number'=>'setOpenIdMemberNumber',
             'flying'=>'setFlying'] as $key => $action){
            $value = $this->getKeycloakUserAttribute($keycloakUser,$key);
            if (!$value && in_array($key,$mandatory)) {
                throw new \Exception('no '.$key.' found, is `'.$this->getKeycloakUserAttributeKeyMap($key).'` a good mapping key ? '.
                    'available keys are : '.implode(', ',$this->getKeycloakUserAvailableKeys($keycloakUser)));
            }elseif (!$value && isset($default[$key]))
            {
                $value = $default[$key];
            }
            $beneficiary->$action($value);
        }

        $s1 = $this->getKeycloakUserAttribute($keycloakUser,'address_street1');
        $s2 = $this->getKeycloakUserAttribute($keycloakUser,'address_street2');
        $city = $this->getKeycloakUserAttribute($keycloakUser,'address_city');
        $zip = $this->getKeycloakUserAttribute($keycloakUser,'address_zipcode');
        if ($s1&&$city&&$zip) {
            $address = $beneficiary->getAddress();
            if (!$address) {
                $address = new Address();
            }
            $address->setCity($city);
            $address->setStreet1($s1);
            $address->setStreet2($s2);
            $address->setZipcode($zip);

            $this->em->persist($address);

            $beneficiary->setAddress($address);
        } else { // address must be fully fill or may not exist
            $address = $beneficiary->getAddress();
            if ($address){
                $beneficiary->setAddress(null);
                $this->em->remove($address);
                $this->em->flush();
            }
        }
        if (!$beneficiary->getId())
            $this->eventDispatcher->dispatch(BeneficiaryCreatedEvent::NAME, new BeneficiaryCreatedEvent($beneficiary));

        //email once user exist
        $value = $this->getKeycloakUserAttribute($keycloakUser,'email');
        if (!$value) {
            throw new Exception('no email found, is `'.$this->getKeycloakUserAttributeKeyMap('email').'` a good mapping key ? '.
                'available keys are : '.implode(', ',$this->getKeycloakUserAvailableKeys($keycloakUser)));
        }
        $beneficiary->setEmail($value);

        // roles
        $roles_claim = $this->container->getParameter('oidc_roles_claim');
        $roles = (isset($keycloakUser->toArray()[$roles_claim])) ? $keycloakUser->toArray()[$roles_claim] : [];
        $roles_map = $this->container->getParameter('oidc_roles_map');
        $beneficiary->getUser()->setRoles([]); // RAZ
        foreach ($roles as $role_name){
            foreach ($roles_map as $role => $key){
                if ($key === $role_name){
                    $beneficiary->getUser()->addRole("ROLE_".$role);
                }
            }
        }

        // formations
        $formations_claim = $this->container->getParameter('oidc_formations_claim');
        $formations_from_keycloak = (isset($keycloakUser->toArray()[$formations_claim])) ? $keycloakUser->toArray()[$formations_claim] : [];
        $formations_map = $this->container->getParameter('oidc_formations_map');
        foreach ($beneficiary->getFormations() as $formation){
            $beneficiary->removeFormation($formation);
        }
        foreach ($formations_from_keycloak as $formation){
            foreach ($formations_map as $formation_name => $oidc_formation_name){
                if ($oidc_formation_name === $formation){
                    $forma = $this
                        ->em
                        ->getRepository(Formation::class)
                        ->findOneBy(['name' => $formation_name]);
                    if ($forma){
                        $beneficiary->addFormation($forma);
                    }
                }
            }
        }
        
        
        // commissions
        $commissions_claim = $this->container->getParameter('oidc_commissions_claim');
        $commissions_from_keycloak = (isset($keycloakUser->toArray()[$commissions_claim])) ? $keycloakUser->toArray()[$commissions_claim] : [];
        $commissions_map = $this->container->getParameter('oidc_commissions_map');
        foreach ($beneficiary->getCommissions() as $commission){
            $beneficiary->removeCommission($commission);
        }
        foreach ($commissions_from_keycloak as $commission){
            foreach ($commissions_map as $commission_name => $oidc_commission_name){
                if ($oidc_commission_name === $commission){
                    $comm = $this
                        ->em
                        ->getRepository(Commission::class)
                        ->findOneBy(['name' => $commission_name]);
                    if ($comm){
                        $beneficiary->addCommission($comm);
                    }
                }
            }
        }

        $this->em->persist($beneficiary);

    }

    private function getKeycloakUserAttribute(KeycloakResourceOwner $keycloakUser,$attributeKey, $defaultValue = null)
    {
        $map = $this->container->getParameter('oidc_user_attributes_map');
        $array = $keycloakUser->toArray();
        if (isset($map[$attributeKey])){
            $key = $map[$attributeKey];
            $keys = explode('.',$key);
            if (count($keys) === 1 && isset($array[$keys[0]]))
                return $array[$keys[0]];
            else if (count($keys) > 1){
                $t = $array;
                $i = 0;
                do {
                    $exist = isset($t[$keys[$i]]);
                    $t = ($exist) ? $t[$keys[$i]] : [];
                }while($exist&&++$i<count($keys));
                if ($exist)
                    return $t;
            }
        }
        return $defaultValue;

    }

    private function getKeycloakUserAttributeKeyMap($attributeKey)
    {
        $map = $this->container->getParameter('oidc_user_attributes_map');
        return $map[$attributeKey];
    }

    private function getKeycloakUserAvailableKeys($keycloakUser) {
        return array_keys($keycloakUser->toArray()) ;
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception)
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, $providerKey)
    {
        $targetUrl = $this->router->generate('homepage');

        return new RedirectResponse($targetUrl);
    }

    /**
     * @return \KnpU\OAuth2ClientBundle\Client\OAuth2Client
     */
    private function getKeycloakClient() : OAuth2Client
    {
        return $this->clientRegistry->getClient('keycloak');
    }
}