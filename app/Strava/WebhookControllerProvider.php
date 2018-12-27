<?php

namespace Strava;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Webhook functionality controller.
 */
class WebhookControllerProvider implements ControllerProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function connect(Application $app) {
    $webhook = $app['controllers_factory'];

    // Webhook callback to validate the callback challenge. Just for creating a
    // webhook.
    $webhook->get('/webhook', function (Request $request) use ($app) {
      $output = [];
      $params = $request->query->all();
      if ($params['hub_mode'] == 'subscribe' && $params['hub_verify_token'] == 'STRAVA') {
        $output = ['hub.challenge' => $params['hub_challenge']];
      }
      return $app->json($output);
    });

    // Webhook callback.
    $webhook->post('/webhook', function (Request $request) use ($app) {
      $params = $request->getContent();
      $params = (array) json_decode($params);

      // Activity type.
      if ($params['object_type'] == 'activity') {
        // Check if it's a create/update/delete.
        if ($params['aspect_type'] == 'create') {
        }
        elseif ($params['aspect_type'] == 'update') {
          // Set the updates to the appropriate field names and don't include
          // the 'private' update.
          foreach ($params['updates'] as $key => $update) {
            if ($key == 'title') {
              $updates['name'] = $update;
            }
            elseif ($key == 'type') {
              $updates[$key] = $update;
            }
          }

          // Update the existing activity.
          $app['db']->update('activities',
            $updates,
            ['id' => $params['object_id']]
          );
        }
        elseif ($params['aspect_type'] == 'delete') {
          $app['db']->delete(
            'activities',
            ['id' => $params['object_id']]
          );
        }
      }
      elseif ($params['object_type'] == 'athlete') {
        // For athlete webhooks, we only care if they are deleting.
        if ($params['aspect_type'] == 'delete') {
          // Delete all activities and the athlete.
          $app['db']->delete(
            'athletes',
            ['id' => $params['object_id']]
          );
          $app['db']->delete(
            'activities',
            ['athlete_id' => $params['object_id']]
          );
        }
      }

      // Generate output.
      $output = 'Processed webhook';

      return $app['twig']->render('webhook.twig', [
        'output' => $output,
      ]);
    });

    return $webhook;
  }

}
