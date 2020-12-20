<?php

namespace App\Controller;

use App\Strava\Activities;
use App\Strava\Activity;
use App\Strava\Strava;
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

  /**
   * @Route("/activities/{activity_id}/refresh", name="activity-refresh")
   */
  public function refresh(SessionInterface $session, Activity $activity, Strava $strava, int $activity_id) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Update the activity and insert/update segment efforts.
    $access_token = $strava->getAccessToken($user['id']);
    $activity = $strava->getActivity($activity_id, $access_token);
    $strava->updateActivity($activity);
    $strava->insertSegmentEfforts($activity, $access_token);
    $session->getFlashBag()->add('strava', 'The activity and segments were refreshed.');

    // Redirect back to activity page.
    return $this->redirectToRoute('activity', ['activity_id' => $activity_id]);
  }

}
