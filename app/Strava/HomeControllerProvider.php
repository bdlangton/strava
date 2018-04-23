<?php

namespace Strava;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;

/**
 * Home page controller.
 */
class HomeControllerProvider implements ControllerProviderInterface
{

  /**
   * @{inheritdoc}
   */
  public function connect(Application $app)
  {
    $home = $app['controllers_factory'];

    // Home page.
    $home->get('/', function () use ($app) {
      $user = $app['session']->get('user');

      // The user is logged in.
      if (!empty($user)) {
        $running = $app['db']->executeQuery(
          'SELECT ROUND(MAX(distance) * ?, 1) as max_distance, ROUND(MAX(moving_time) / 3600, 1) max_moving_time FROM activities WHERE athlete_id = ? AND type = ? ',
          [DISTANCE_TO_MILES, $user['id'], 'Run']
        )->fetch();
        $riding = $app['db']->executeQuery(
          'SELECT ROUND(MAX(distance) * ?, 1) as max_distance, ROUND(MAX(moving_time) / 3600, 1) max_moving_time FROM activities WHERE athlete_id = ? AND type = ? ',
          [DISTANCE_TO_MILES, $user['id'], 'Ride']
        )->fetch();

        return $app['twig']->render('index.twig', [
          'running' => $running,
          'riding' => $riding,
          'login' => FALSE,
        ]);
      }
      // The user is not logged in.
      else {
        return $app['twig']->render('index.twig', [
          'login' => TRUE,
        ]);
      }
    });

    return $home;
  }

}
