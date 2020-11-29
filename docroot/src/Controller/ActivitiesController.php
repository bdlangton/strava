<?php

namespace App\Controller;

use App\Strava\Activities;
use App\Strava\Activity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Activities controller.
 */
class ActivitiesController extends AbstractController {

  /**
   * @Route("/activities", name="activities")
   */
  public function activities(SessionInterface $session, Activities $activities) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Render the page.
    return $this->render('activities.twig', $activities->render());
  }

  /**
   * @Route("/activities/{activity_id}", name="activity")
   */
  public function activity(SessionInterface $session, Activity $activity, int $activity_id) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Render the page.
    return $this->render('activity.twig', $activity->render($activity_id));
  }

}
