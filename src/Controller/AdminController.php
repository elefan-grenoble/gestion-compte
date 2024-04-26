<?php

namespace App\Controller;

use App\Command\ImportUsersCommand;
use App\Entity\AbstractRegistration;
use App\Entity\Address;
use App\Entity\Beneficiary;
use App\Entity\Commission;
use App\Entity\HelloassoPayment;
use App\Entity\Registration;
use App\Entity\Formation;
use App\Entity\User;
use App\Event\HelloassoEvent;
use App\Service\SearchUserFormHelper;
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
 * Admin controller.
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
    public function indexAction(Request $request)
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
        $defaults = [
            'withdrawn' => 1,
            'sort' => 'm.member_number',
            'dir' => 'ASC'
        ];
        $form = $formHelper->createMemberFilterForm($this->createFormBuilder(), $defaults);
        $form->handleRequest($request);

        $action = $form->get('action')->getData();

        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager());

        if ($form->isSubmitted() && $form->isValid()) {
            $formHelper->processSearchFormData($form, $qb);
            $sort = $form->get('sort')->getData();
            $order = $form->get('dir')->getData();
            $currentPage = $form->get('page')->getData();
        } else {
            $sort = $defaults['sort'];
            $order = $defaults['dir'];
            $currentPage = 1;
            $qb = $qb->andWhere('m.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $defaults['withdrawn']-1);
        }
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
            $limitPerPage = 25;
            $paginator = new Paginator($qb);
            $resultCount = count($paginator);
            $pageCount = ($resultCount == 0) ? 1 : ceil($resultCount / $limitPerPage);
            $currentPage = ($currentPage > $pageCount) ? $pageCount : $currentPage;

            $paginator
                ->getQuery()
                ->setFirstResult($limitPerPage * ($currentPage-1)) // set the offset
                ->setMaxResults($limitPerPage); // set the limit
        }

        return $this->render('admin/user/list.html.twig', array(
            'members' => $paginator,
            'form' => $form->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount
        ));
    }

    /**
     * Lists all non-member users.
     *
     * @param Request $request
     * @return Response
     * @Route("/non_member_users", name="non_member_users_list", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function nonMemberUsersAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $non_members = $em->getRepository("App:User")->findNonMembers();

        return $this->render('admin/user/non_member_list.html.twig', array(
            'non_members' => $non_members,
        ));
    }

    /**
     * Lists all users with ROLE_ADMIN.
     *
     * @param Request $request
     * @return Response
     * @Route("/admin_users", name="admin_users_list", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function adminUsersAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $admins = $em->getRepository("App:User")->findByRole('ROLE_ADMIN');
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
            $role_icon_key = strtolower($role_code) . "_material_icon";
            $role_name_key = strtolower($role_code) . "_name";
            $role["code"] = $role_code;
            $role["icon"] = $this->get("twig")->getGlobals()[strtolower($role_icon_key)] ?? "";
            $role["name"] = $this->get("twig")->getGlobals()[strtolower($role_name_key)] ?? "";
            $role["children"] = in_array($role_code, array_keys($roles_hierarchy)) ? implode(", ", $roles_hierarchy[$role_code]) : "";
            $role["user_count"] = count($em->getRepository("App:User")->findByRole($role_code));
            array_push($roles_list_enriched, $role);
        }

        return $this->render('admin/user/roles_list.html.twig', array(
            'roles' => $roles_list_enriched,
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
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
