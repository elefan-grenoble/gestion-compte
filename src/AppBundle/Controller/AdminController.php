<?php

namespace AppBundle\Controller;

use AppBundle\Command\ImportUsersCommand;
use AppBundle\Entity\AbstractRegistration;
use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Commission;
use AppBundle\Entity\HelloassoPayment;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Formation;
use AppBundle\Entity\User;
use AppBundle\Event\HelloassoEvent;
use AppBundle\Form\BeneficiaryType;
use AppBundle\Form\RegistrationType;
use AppBundle\Service\SearchUserFormHelper;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use OAuth2\OAuth2;
use Ornicar\GravatarBundle\GravatarApi;
use Ornicar\GravatarBundle\Templating\Helper\GravatarHelper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("admin")
 * @Security("has_role('ROLE_ADMIN_PANEL')")
 */
class AdminController extends Controller
{
    /**
     * Admin panel
     *
     * @Route("/", name="admin", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN_PANEL')")
     */
    public function indexAction()
    {
        return $this->render('admin/index.html.twig');
    }

    /**
     * Lists all user entities.
     *
     * @param Request $request, SearchUserFormHelper $formHelper
     * @return Response
     * @Route("/users", name="user_index", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_MANAGER')")
     */
    public function usersAction(Request $request, SearchUserFormHelper $formHelper)
    {
        $form = $formHelper->getSearchForm($this->createFormBuilder(), $request->getQueryString());
        $form->handleRequest($request);

        $action = $form->get('action')->getData();

        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager());

        # default data
        $defaultWithdrawn = 1;  # open accounts
        $page = 1;
        $sort = 'o.member_number';
        $order = 'ASC';
        $limit = 25;

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('page')->getData() > 0) {
                $page = $form->get('page')->getData();
            }
            if ($form->get('sort')->getData()) {
                $sort = $form->get('sort')->getData();
            }
            if ($form->get('dir')->getData()) {
                $order = $form->get('dir')->getData();
            }
        } else {
            $form->get('withdrawn')->setData($defaultWithdrawn);
            $form->get('sort')->setData($sort);
            $form->get('dir')->setData($order);
        }
        $formHelper->processSearchFormData($form, $qb);
        $formHelper->processSearchQueryData($request->getQueryString(), $qb);

        $qb = $qb->orderBy($sort, $order);

        // Export CSV
        if ($action == "csv") {
            $members = $qb->getQuery()->getResult();
            $return = '';
            $d = ','; // this is the default but i like to be explicit
            foreach ($members as $member) {
                foreach ($member->getBeneficiaries() as $beneficiary) {
                    $return .=
                        $beneficiary->getMemberNumber() . $d .
                        $beneficiary->getFirstname() . $d .
                        $beneficiary->getLastname() . $d .
                        $beneficiary->getEmail() . $d .
                        $beneficiary->getPhone() .
                        "\n";
                }
            }
            return new Response($return, 200, array(
                'Content-Encoding: UTF-8',
                'Content-Type' => 'application/force-download; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="emails_' . date('dmyhis') . '.csv"'
            ));
        // Envoyer un mail
        } else if ($action === "mail") {
            return $this->redirectToRoute('mail_edit', [
                'request' => $request
            ], 307);
        } else {
            $qb = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
            $members = new Paginator($qb->getQuery());
            $max = sizeof($members);
            $nb_of_pages = intval($max / $limit);
            $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;
        }

        return $this->render('admin/user/list.html.twig', array(
            'members' => $members,
            'form' => $form->createView(),
            'nb_of_result' => $max,
            'page' => $page,
            'nb_of_pages' => $nb_of_pages
        ));
    }

    /**
     * Lists all users with ROLE_ADMIN.
     *
     * @param Request $request , SearchUserFormHelper $formHelper
     * @param SearchUserFormHelper $formHelper
     * @return Response
     * @Route("/admin_users", name="admins_list", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function adminUsersAction(Request $request, SearchUserFormHelper $formHelper)
    {
        $em = $this->getDoctrine()->getManager();

        $admins = $em->getRepository("AppBundle:User")->findByRole('ROLE_ADMIN');
        $delete_forms = array();
        foreach ($admins as $admin) {
            $delete_forms[$admin->getId()] = $this->createFormBuilder()
                ->setAction($this->generateUrl('user_delete', array('id' => $admin->getId())))
                ->setMethod('DELETE')
                ->getForm()->createView();
        }

        return $this->render('admin/user/admin_list.html.twig', array(
            'admins' => $admins,
            'delete_forms' => $delete_forms
        ));
    }

    /**
     * Lists all roles.
     *
     * @param Request $request
     * @return Response
     * @Route("/roles", name="roles_list", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function rolesListAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $roles_hierarchy = $this->container->getParameter('security.role_hierarchy.roles');
        $roles_list = array_merge(["ROLE_USER"], array_keys($roles_hierarchy));
        $roles_list_enriched = array();

        foreach ($roles_list as $role_code) {
            $role = array();
            $role_icon_key = strtolower($role_code) . "_icon";
            $role_name_key = strtolower($role_code) . "_name";
            $role["code"] = $role_code;
            $role["icon"] = $this->get("twig")->getGlobals()[strtolower($role_icon_key)] ?? "";
            $role["name"] = $this->get("twig")->getGlobals()[strtolower($role_name_key)] ?? "";
            $role["children"] = in_array($role_code, array_keys($roles_hierarchy)) ? implode(", ", $roles_hierarchy[$role_code]) : "";
            $role["user_count"] = count($em->getRepository("AppBundle:User")->findByRole($role_code));
            array_push($roles_list_enriched, $role);
        }

        return $this->render('admin/user/roles_list.html.twig', array(
            'roles' => $roles_list_enriched,
        ));
    }

    /**
     * Widget generator
     *
     * @Route("/widget", name="widget_generator", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function widgetBuilderAction(Request $request){
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
            return $this->render('admin/widget/generate.html.twig', array(
                'query_string' => 'job_id='.$data['job']->getId().'&display_end='.($data['display_end'] ? 1 : 0).'&display_on_empty='.($data['display_on_empty'] ? 1 : 0).'&title='.($data['title'] ? 1 : 0),
                'form' => $form->createView(),
            ));
        }

        return $this->render('admin/widget/generate.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Import from CSV
     *
     * @Route("/importcsv", name="user_import_csv", methods={"GET","POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function csvImportAction(Request $request, KernelInterface $kernel)
    {
        $form = $this->createFormBuilder()
            ->add('submitFile', FileType::class, array('label' => 'File to Submit'))
            ->add('delimiter', ChoiceType::class, array(
                'label' => 'delimiter',
                'choices'  => array(
                    'virgule ,' => ',',
                    'point virgule ;' => ';',
                )
            ))
            //->add('persist', CheckboxType::class, array('required' => false, 'label' => 'Sauver en base'))
            //->add('compute', SubmitType::class, array('label' => 'Importer les données'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {

            // Get file
            $file = $form->get('submitFile');
            $delimiter = ($form->get('delimiter')) ? $form->get('delimiter')->getData() : ',';
            //$persist = ($form->get('persist')) ? $form->get('persist')->getData() : false;

            // Your csv file here when you hit submit button
            //$data = $file->getData();
            $filename = $file->getData()->getPathName();

            $application = new Application($kernel);
            $application->setAutoExit(false);

            $input = new ArrayInput([
                'command' => 'app:import:users',
                '--delimiter' => $delimiter,
                'file' => $filename,
                '--default_mapping' => true
            ]);

            // You can use NullOutput() if you don't need the output
            $output = new BufferedOutput();
            $application->run($input, $output);

            // return the output, don't use if you used NullOutput()
            $content = $output->fetch();

            $request->getSession()->getFlashBag()->add('notice', 'Le fichier a été traité.');

            return $this->render('admin/user/import_return.html.twig', array(
                'content' => $content,
            ));

        }

        return $this->render('admin/user/import.html.twig', array(
            'form' => $form->createView(),
        ));
    }
}
