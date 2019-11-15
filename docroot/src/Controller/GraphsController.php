<?php

namespace App\Controller;

use App\Strava\Graphs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Graphs controller.
 */
class GraphsController extends AbstractController {

  /**
   * @Route("/data", name="graph")
   */
  public function data(SessionInterface $session, Graphs $graphs) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Render the chart.
    return $this->render('data.twig', $graphs->render());
  }

}
