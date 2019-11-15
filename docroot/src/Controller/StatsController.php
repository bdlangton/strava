<?php

namespace App\Controller;

use App\Strava\Stats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Stats controller.
 */
class StatsController extends AbstractController {

  /**
   * @Route("/big", name="big")
   */
  public function big(SessionInterface $session, Stats $stats) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Render the page.
    return $this->render('big.twig', $stats->render());
  }

  /**
   * @Route("/big/update/{id}", name="big_update", requirements={"id" = "\d+"})
   */
  public function bigUpdate($id, SessionInterface $session, Stats $stats) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    $stats->updateStat($id);

    // Reload the big page.
    return $this->redirectToRoute('big');
  }

  /**
   * @Route("/big/delete/{id}", name="big_delete", requirements={"id" = "\d+"})
   */
  public function bigDelete($id, SessionInterface $session, Stats $stats) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    $stats->deleteStat($id);

    // Reload the big page.
    return $this->redirectToRoute('big');
  }

}
