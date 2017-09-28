<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Registration;
use AppBundle\Entity\User;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * User controller.
 *
 * @Route("user")
 */
class UserController extends Controller
{
    /**
     * Lists all user entities.
     *
     * @Route("/", name="user_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $users = $em->getRepository('AppBundle:User')->findBy(array(), array('member_number' => 'ASC'));

        return $this->render('user/index.html.twig', array(
            'users' => $users,
        ));
    }


    /**
     * install admin
     *
     * @Route("/install_admin", name="user_install_admin")
     * @Method("GET")
     */
    public function installAdminAction()
    {
        $em = $this->getDoctrine()->getManager();

        $user = $em->getRepository('AppBundle:User')->findOneBy(array("member_number"=>0));

        if ($user){
            return $this->redirectToRoute('homepage');
        }

        $admin = new User();
        $admin->setEmail("admin@lelefan.org");
        $admin->setPlainPassword("password");
        $admin->setUsername("babar");
        $admin->setMemberNumber(0);
        $admin->setEnabled(true);
        $admin->addRole('ROLE_SUPER_ADMIN');
        $em->persist($admin);
        $em->flush();

        return $this->redirectToRoute('homepage');
    }

    /**
     * Import from CSV
     *
     * @Route("/importcsv", name="user_import_csv")
     * @Method({"GET","POST"})
     */
    public function csvImportAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('submitFile', FileType::class, array('label' => 'File to Submit'))
            ->add('delimiter', TextType::class, array('label' => 'delimiter','attr' => array(
                'placeholder' => ',',
            ),'data'=>','))
            ->add('persist',CheckboxType::class,array('required'=>false,'label'=>'Sauver en base'))
            ->add('compute', SubmitType::class, array('label' => 'compute'))
        ->getForm();

        if ($form->handleRequest($request)->isValid()) {

            // Get file
            $file = $form->get('submitFile');
            $delimiter = ($form->get('delimiter'))? $form->get('delimiter')->getData() : ',';
            $persist = ($form->get('persist'))? $form->get('persist')->getData() : false;

            // Your csv file here when you hit submit button
            $data = $file->getData();
            $filename = $file->getData()->getPathName();

            $row = 1;
            $lastdate = DateTime::createFromFormat('d/m/Y', '04/05/2016')->format('Y-m-d H:i:s');
            $em = $this->getDoctrine()->getManager();
            $return = array();
            $usernames = array();
            $emails = array();
            if (($handle = fopen($filename, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE /*
                    && $row<10 //*/
                    ) {
                    /*
                     Array
                    (
                    [0] => compare
                    [1] => Date d'adhésion
                    [2] => Type Adhésion
                    [3] => Nom
                    [4] => Prénom
                    [5] => Adresse1
                    [6] => CP
                    [7] => Ville
                    [8] => Téléphone
                    [9] => Mail
                    [10] => Montant
                    [11] => Mode de réglement
                    [12] => A intégrer?
                    [13] => Renouvellement adhésion - Date
                    [14] => Montant
                    [15] => Mode de réglement
                    [16] => Qualité
                    [17] => Bénévole Ressource
                    [18] => Ambassadeur
                    [19] =>
                    )*/
                    preg_match_all('/^[0-9]+$/', $data[0], $matches, PREG_SET_ORDER, 0);
                    if (count($data)>11&&isset($data[3])&&isset($data[4])&&count($matches)&&strlen($data[3])>1&&strlen($data[4])>1){ // on ne traite que les colonnes qui commence par un numéro d'adhérent valide (entier)
                        $member_number = $data[0];
                        $user = $em->getRepository('AppBundle:User')->findOneBy(array("member_number"=>$member_number));
                        if ($user)
                            $return[] = array($user,array("error","user with same member number already exist"));
                        else {
                            $mail = $data[9];
                            $validator = $this->container->get('validator');
                            $constraints = array(
                                new EmailConstraint(),
                                new NotBlank()
                            );
                            $error = $validator->validate($mail, $constraints);
                            if ($error->count()){
                                $return[] = array($user,array("error","email is not valid (".$mail.")"));
                            }else{
                                $user = $em->getRepository('AppBundle:User')->findOneBy(array("email"=>$mail));
                                $already_registred = (isset($emails[$mail])) ? true : false;
                                if ($user||$already_registred)
                                    $return[] = array($user,array("error","user with same email already exist"));
                                else {
                                    $user = new User();
                                    $firstname = trim(preg_replace('/\s\s+/', ' ', $data[4]));
                                    $lastname = trim(preg_replace('/\s\s+/', ' ', $data[3]));
                                    $username = User::makeUsername($firstname,$lastname);
                                    $qb = $em->createQueryBuilder();
                                    $users = $qb->select('u')->from('AppBundle\Entity\User', 'u')
                                        ->where( $qb->expr()->like('u.username', $qb->expr()->literal($username.'%')) )
                                        ->getQuery()
                                        ->getResult();
                                    //$users = $em->getRepository('AppBundle:User')->findBy(array("username"=>$username));
                                    $already_registred = (isset($usernames[$username])) ? $usernames[$username]  : 0;
                                    if (count($users)||$already_registred){
                                        $username = User::makeUsername($firstname,$lastname,count($users)+1+$already_registred);
                                    }
                                    if (strlen($username)>3){
                                        $user->setUsername($username);
                                        $user->setEmail($mail);
                                        $user->setMemberNumber($member_number);
                                        $password = User::randomPassword();
                                        $user->setPassword($password);
                                        //beneficiary
                                        $beneficiary = new Beneficiary();
                                        $beneficiary->setFirstname($firstname);
                                        $beneficiary->setLastname($lastname);
                                        $beneficiary->setPhone($data[8]);
                                        $beneficiary->setEmail($mail);
                                        $beneficiary->setIsMain(true);
                                        $beneficiary->setIsAmbassador(($data[8]!=''));
                                        $beneficiary->setIsExpert(false);//default all false
                                        $beneficiary->setUser($user);
                                        $user->addBeneficiary($beneficiary);
                                        //address
                                        $address = new Address();
                                        $address->setStreet1($data[5]);
                                        $address->setStreet2('');
                                        $address->setZipcode($data[6]);
                                        $address->setCity($data[7]);
                                        $address->setUser($user);
                                        $user->setAddress($address);
                                        //registration
                                        $registration = new Registration();
                                        $date = $data[1];
                                        if (!$date)
                                            $date = $lastdate;
                                        else {
                                            $date = DateTime::createFromFormat('d/m/Y', $date);
                                            if (!$date)
                                                $date = $lastdate;
                                            else
                                                $date->format('Y-m-d H:i:s');
                                        }
                                        $lastdate = $date;
                                        $registration->setDate($date); //Y-m-d H:i:s
                                        $registration->setAmount(intval($data[10]));
                                        $reglement = $data[11];
                                        if (!$reglement&&strtolower($data[2])=='site')
                                            $reglement = 'cb';
                                        switch ($reglement){
                                            case 'chq' :
                                            case 'CHQ' :
                                            case 'ch' :
                                                $registration->setMode(Registration::TYPE_CHECK);
                                                break;
                                            case 'EPP':
                                            case 'ESP':
                                            case 'esp':
                                            case 'Espèce':
                                                $registration->setMode(Registration::TYPE_CASH);
                                                break;
                                            case 'Site':
                                            case 'site':
                                            case 'cb':
                                                $registration->setMode(Registration::TYPE_CREDIT_CARD);
                                                break;
                                            default:
                                                $registration->setMode(Registration::TYPE_DEFAULT);
                                        }
                                        $registration->setUser($user);
                                        $user->addRegistration($registration);
                                        $return[] = array($user,array("check","user added"));
                                        $usernames[$user->getUsername()] = (isset($usernames[$user->getUsername()])) ? $usernames[$user->getUsername()] +1 : 1;
                                        $emails[$user->getEmail()] = true;
                                        if ($persist)
                                            $em->persist($user);
                                    }else{
                                        $return[] = array($user,array("error","username build to short"));
                                    }
                                }
                            }
                        }
                    }
                    $row++;
                }
                fclose($handle);
                $em->flush();
            }

            if ($persist){
                $request->getSession()->getFlashBag()->add('notice', 'Le fichier a été traité complétement.');
                return $this->redirectToRoute('user_index');
            }else{
                return $this->render('user/test_import.html.twig', array(
                    'users' => $return,
                ));
            }

        }

        return $this->render('user/import.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Creates a new user entity.
     *
     * @Route("/new", name="user_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $user = new User();
        $form = $this->createForm('AppBundle\Form\UserType', $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('user_show', array('id' => $user->getId()));
        }

        return $this->render('user/new.html.twig', array(
            'user' => $user,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a user entity.
     *
     * @Route("/{username}", name="user_show")
     * @Method("GET")
     */
    public function showAction(User $user)
    {
        $deleteForm = $this->createDeleteForm($user);

        return $this->render('user/show.html.twig', array(
            'user' => $user,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing user entity.
     *
     * @Route("/{username}/edit", name="user_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, User $user)
    {
        $deleteForm = $this->createDeleteForm($user);
        $editForm = $this->createForm('AppBundle\Form\UserType', $user);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('user_edit', array('id' => $user->getId()));
        }

        return $this->render('user/edit.html.twig', array(
            'user' => $user,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a user entity.
     *
     * @Route("/{id}", name="user_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, User $user)
    {
        $form = $this->createDeleteForm($user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($user);
            $em->flush();
        }

        return $this->redirectToRoute('user_index');
    }

    /**
     * Creates a form to delete a user entity.
     *
     * @param User $user The user entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(User $user)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('user_delete', array('id' => $user->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
