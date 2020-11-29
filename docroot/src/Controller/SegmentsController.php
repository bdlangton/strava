<?php

namespace App\Controller;

use App\Strava\Segments;
use App\Strava\SegmentEfforts;
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

  /**
   * @Route("/segments/{segment_id}", name="segment_efforts")
   */
  public function segmentEfforts(SessionInterface $session, SegmentEfforts $segment_efforts, int $segment_id) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Render the page.
    return $this->render('segment.twig', $segment_efforts->render($segment_id));
  }

}
