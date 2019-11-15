<?php

namespace App\Controller;

use App\Strava\Jon;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Jon score controller.
 */
class JonController extends AbstractController {

  /**
   * @Route("/jon", name="jon")
   */
  public function jon(SessionInterface $session, Jon $jon) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Render the chart.
    return $this->render('jon.twig', $jon->render());
  }

}
