<?php

namespace App\Controller;

use App\Strava\Activities;
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

}
