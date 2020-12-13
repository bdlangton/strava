<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Authentication controller.
 */
class AuthController extends AbstractController {

  /**
   * @Route("/logout", name="logout")
   */
  public function logout(SessionInterface $session) {
    $session->set('user', NULL);
    return $this->redirectToRoute('home');
  }

  /**
   * @Route("/token_exchange", name="token_exchange")
   */
  public function tokenExchange(SessionInterface $session, RequestStack $requestStack, Connection $connection) {
    // Check the session.
    $user = $session->get('user');
    $request = $requestStack->getCurrentRequest();
    $params = $request->query->all() ?? [];
    if (!empty($user) || empty($params['code'])) {
      return $this->redirectToRoute('home');
    }

    // Finish the token exchange.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.strava.com/oauth/token');
    curl_setopt($ch, CURLOPT_POST, 3);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=authorization_code&client_id=' . getenv('CLIENT_ID') . '&client_secret=' . getenv('CLIENT_SECRET') . '&code=' . $params['code']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    $data = json_decode($result, TRUE);
    curl_close($ch);
    try {
      $result = $connection->executeQuery(
        'SELECT id FROM athletes WHERE id = ?',
        [$data['athlete']['id']]
      )->fetchAll();
      if (!empty($result)) {
        $connection->executeQuery(
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
        $connection->insert('athletes', [
          'id' => $data['athlete']['id'],
          'access_token' => $data['access_token'],
          'refresh_token' => $data['refresh_token'],
          'token_expires' => $data['expires_at'],
        ]);
      }
      $athlete_data = $connection->executeQuery(
        'SELECT default_activity_type, default_format
        FROM athletes WHERE id = ?',
        [$data['athlete']['id']]
      )->fetch();
      $session->set('user', [
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

    // Return the user to their activities page.
    return $this->redirectToRoute('activities');
  }

}
