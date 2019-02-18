<?php

namespace App\Controller;

use Doctrine\DBAL\Driver\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Home page controller.
 */
class HomeController extends AbstractController {

  /**
   * @Route("/", name="home")
   */
  public function home(SessionInterface $session, Connection $connection) {
    $user = $session->get('user');

    // The user is logged in.
    if (!empty($user)) {
      $running = $connection->fetchAssoc(
        'SELECT ROUND(MAX(distance) * ?, 1) as max_distance, ROUND(MAX(moving_time) / 3600, 1) max_moving_time FROM activities WHERE athlete_id = ? AND type = ? ',
        [DISTANCE_TO_MILES, $user['id'], 'Run']
      );
      $riding = $connection->fetchAssoc(
        'SELECT ROUND(MAX(distance) * ?, 1) as max_distance, ROUND(MAX(moving_time) / 3600, 1) max_moving_time FROM activities WHERE athlete_id = ? AND type = ? ',
        [DISTANCE_TO_MILES, $user['id'], 'Ride']
      );

      return $this->render('index.twig', [
        'running' => $running,
        'riding' => $riding,
        'login' => FALSE,
      ]);
    }
    // The user is not logged in.
    else {
      return $this->render('index.twig', [
        'login' => TRUE,
      ]);
    }
  }

}
