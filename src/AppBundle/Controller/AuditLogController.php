<?php

namespace AppBundle\Controller;

use AppBundle\Entity\AuditLog;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Auditlog controller.
 *
 * @Route("admin/logs")
 */
class AuditLogController extends Controller
{
    /**
     * Lists all AuditLog entities.
     *
     * @Route("/", name="admin_auditlog_index")
     * @Security("has_role('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $qb = $em->getRepository('AppBundle:AuditLog')->createQueryBuilder('a')
                                                      ->orderBy('a.createdAt', 'DESC');

        $limitPerPage = 25;
        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $pagesCount = ($totalItems == 0) ? 1 : ceil($totalItems / $limitPerPage);
        $currentPage = $request->get('page', 1);
        $currentPage = ($currentPage > $pagesCount) ? $pagesCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage-1)) // set the offset
            ->setMaxResults($limitPerPage); // set the limit

        return $this->render('admin/auditlog/index.html.twig', array(
            'auditLogs' => $paginator,
            'current_page' => $currentPage,
            'pages_count' => $pagesCount,
        ));
    }

}
