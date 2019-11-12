<?php

namespace App\Controller;

use App\Strava\Charts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Column charts controller.
 */
class ChartsController extends AbstractController {

  /**
   * @Route("/column", name="column")
   */
  public function column(SessionInterface $session, Charts $charts) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Render the chart.
    return $this->render('column.twig', $charts->render());
  }

}
