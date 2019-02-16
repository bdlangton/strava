<?php

namespace Strava;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authentication controller.
 */
class AuthControllerProvider implements ControllerProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function connect(Application $app) {
    $auth = $app['controllers_factory'];

    // Logout.
    $auth->get('/logout', function () use ($app) {
      $app['session']->set('user', NULL);
      return $app->redirect('/');
    });

    // Token exchange.
    $auth->get('/token_exchange', function (Request $request) use ($app) {
      // Check the session.
      $user = $app['session']->get('user');
      $params = $request->query->all();
      if (!empty($user) || empty($params['code'])) {
        return $app->redirect('/');
      }

      // Finish the token exchange.
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, 'https://www.strava.com/oauth/token');
      curl_setopt($ch, CURLOPT_POST, 3);
      curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=authorization_code&client_id=' . $app['client_id'] . '&client_secret=' . $app['client_secret'] . '&code=' . $params['code']);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      $result = curl_exec($ch);
      $data = json_decode($result, TRUE);
      curl_close($ch);
      try {
        $result = $app['db']->executeQuery(
          'SELECT id FROM athletes WHERE id = ?',
          [$data['athlete']['id']]
        )->fetchAll();
        if (!empty($result)) {
          $app['db']->executeQuery(
            'UPDATE athletes set access_token = ?, refresh_token = ?, token_expires = ? WHERE id = ?',
            [
              $data['access_token'],
              $data['refresh_token'],
              $data['expires_at'],
              $data['athlete']['id'],
            ]
          );
        }
        else {
          $app['db']->insert('athletes', [
            'id' => $data['athlete']['id'],
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'token_expires' => $data['expires_at'],
          ]);
        }
        $athlete_data = $app['db']->executeQuery(
          'SELECT default_activity_type, default_format
          FROM athletes WHERE id = ?',
          [$data['athlete']['id']]
        )->fetch();
        $app['session']->set('user', [
          'id' => $data['athlete']['id'],
          'access_token' => $data['access_token'],
          'refresh_token' => $data['refresh_token'],
          'token_expires' => $data['expires_at'],
          'activity_type' => $athlete_data['default_activity_type'],
          'format' => $athlete_data['default_format'],
        ]);
      }
      catch (Exception $e) {
      }

      // Return the user to the homepage.
      return $app->redirect('/');
    });

    return $auth;
  }

}
