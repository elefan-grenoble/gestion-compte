<?php

namespace AppBundle\Controller;

use AppBundle\Entity\ShiftFreeLog;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * ShiftFreeLog controller.
 *
 * @Route("admin/shifts/logs")
 */
class ShiftFreeLogController extends Controller
{
    /**
     * Lists all ShiftFreeLog entities.
     *
     * @Route("/", name="admin_shiftfreelog_index")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method("GET")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $qb = $em->getRepository('AppBundle:ShiftFreeLog')->createQueryBuilder('s')
                                                      ->orderBy('s.createdAt', 'DESC');

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

        return $this->render('admin/shiftfreelog/index.html.twig', array(
            'shiftFreeLogs' => $paginator,
            'current_page' => $currentPage,
            'pages_count' => $pagesCount,
        ));
    }

}
