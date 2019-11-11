<?php

namespace App\Controller;

use App\Strava\Strava;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Webhook functionality controller.
 */
class WebhookController extends AbstractController {

  /**
   * @Route("/webhook", methods={"GET"})
   */
  public function webhook(RequestStack $requestStack) {
    // Webhook callback to validate the callback challenge. Just for creating a
    // webhook.
    $output = [];
    $request = $requestStack->getCurrentRequest();
    $params = $request->query->all() ?? [];
    if ($params['hub_mode'] == 'subscribe' && $params['hub_verify_token'] == 'STRAVA') {
      $output = ['hub.challenge' => $params['hub_challenge']];
    }
    return json_encode($output);
  }

  /**
   * @Route("/webhook", methods={"POST"})
   */
  public function webhookPost(RequestStack $requestStack, Strava $strava, Connection $connection) {
    // Webhook callback.
    $request = $requestStack->getCurrentRequest();
    $params = $request->getContent();
    $params = (array) json_decode($params);

    // Activity type.
    if ($params['object_type'] == 'activity') {
      $this->processActivity($params, $strava, $connection);
    }
    elseif ($params['object_type'] == 'athlete') {
      $this->processAthlete($params, $connection);
    }

    // Generate output.
    $output = 'Processed webhook';

    return $this->render('webhook.twig', [
      'output' => $output,
    ]);
  }

  /**
   * Process an activity webhook.
   *
   * @param array $params
   *   Parameters from teh request.
   * @param \App\Strava\Strava $strava
   *   The Strava service.
   * @param \Doctrine\DBAL\Connection $connection
   *   The database connection.
   */
  private function processActivity(array $params, Strava $strava, Connection $connection) {
    $access_token = $strava->getAccessToken($params['owner_id']);

    // Check if it's a create/update/delete.
    if ($params['aspect_type'] == 'create') {
      $activity = $strava->getActivity($params['object_id'], $access_token);
      $strava->insertActivity($activity);
    }
    elseif ($params['aspect_type'] == 'update') {
      // Set the updates to the appropriate field names and don't include
      // the 'private' update.
      $updates = [];
      foreach ($params['updates'] as $key => $update) {
        if ($key == 'title') {
          $updates['name'] = $update;
        }
        elseif ($key == 'type') {
          $updates[$key] = $update;
        }
      }

      // Update the existing activity.
      if ($strava->activityExists($params['object_id'])) {
        $connection->update('activities',
          $updates,
          ['id' => $params['object_id']]
        );
      }
      else {
        // Even though it's an update, we don't have the activity so we have
        // to create it.
        $activity = $strava->getActivity($params['object_id'], $access_token);
        $strava->insertActivity($activity);
      }
    }
    elseif ($params['aspect_type'] == 'delete') {
      $connection->delete(
        'activities',
        ['id' => $params['object_id']]
      );
    }
  }

  /**
   * Process an athlete webhook.
   *
   * @param array $params
   *   Parameters from teh request.
   * @param \Doctrine\DBAL\Connection $connection
   *   The database connection.
   */
  private function processAthlete(array $params, Connection $connection) {
    // For athlete webhooks, we only care if they are deleting.
    if ($params['aspect_type'] == 'delete') {
      // Delete all activities and the athlete.
      $connection->delete(
        'athletes',
        ['id' => $params['object_id']]
      );
      $connection->delete(
        'activities',
        ['athlete_id' => $params['object_id']]
      );
    }
  }

}
