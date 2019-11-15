<?php

namespace App\Controller;

use App\Strava\Segments;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Segments controller.
 */
class SegmentsController extends AbstractController {

  /**
   * @Route("/segments", name="segments")
   */
  public function segments(SessionInterface $session, Segments $segments) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Render the page.
    return $this->render('segments.twig', $segments->render());
  }

}
