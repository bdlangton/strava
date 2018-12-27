<?php

namespace Strava;

use Doctrine\DBAL\Connection;
use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Import functionality controller.
 */
class ImportControllerProvider implements ControllerProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function connect(Application $app) {
    $import = $app['controllers_factory'];

    // Import user activities.
    $import->get('/import', function (Request $request) use ($app) {
      // Check the session.
      $user = $app['session']->get('user');
      if (empty($user)) {
        return $app->redirect('/');
      }
      $output = '';

      // Build the form.
      $params = $request->query->all();
      $import_type = !empty($params['type']) ? $params['type'] : NULL;
      $params += [
        'type' => 'new',
        'starred_segments' => FALSE,
      ];
      $params['starred_segments'] = !empty($params['starred_segments']);
      $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
        ->add('type', ChoiceType::class, [
          'choices' => [
            'New Activities' => 'new',
            '2018 Activities' => '2018',
            '2017 Activities' => '2017',
            '2016 Activities' => '2016',
            '2015 Activities' => '2015',
            '2014 Activities' => '2014',
            '2013 Activities' => '2013',
            '2012 Activities' => '2012',
            '2011 Activities' => '2011',
            '2010 Activities' => '2010',
          ],
          'label' => FALSE,
        ])
        ->add('starred_segments', CheckboxType::class, [
          'label' => 'Import Starred Segments',
          'required' => FALSE,
          'value' => TRUE,
        ]);
      $form = $form->getForm();

      if (!empty($import_type)) {
        $activities_added = $activities_updated = 0;
        $starred_segments_added = 0;
        $processing = TRUE;
        for ($page = 1; $processing; $page++) {
          // Query for activities.
          $activities = $app['strava']->getActivities($user['access_token'], $page);

          // If no activities are found, then we've reached the end.
          if (empty($activities)) {
            break;
          }

          // Check if we have the activities in our db already.
          $activity_ids = array_column($activities, 'id');
          $activity_results = $app['db']->executeQuery(
            'SELECT id FROM activities WHERE id IN (?) ',
            [$activity_ids],
            [Connection::PARAM_INT_ARRAY]
          )->fetchAll(\PDO::FETCH_COLUMN);

          // Loop through activities and add to the db.
          foreach ($activities as $activity) {
            // If we are importing a specific year.
            if (is_numeric($import_type)) {
              $start_year = (int) $app['strava']->convertDateFormat($activity['start_date_local'], 'Y');

              // If the activity is for a year that is earlier than the import
              // year, then we need to stop importing.
              if ($start_year < $import_type) {
                $processing = FALSE;
                break;
              }
              // If the activity is for a year that is later than the import
              // year, then we need to skip this activity.
              if ($start_year > $import_type) {
                continue;
              }
            }

            try {
              // Convert some data to how we need it stored.
              $activity['start_date'] = str_replace('Z', '', $activity['start_date']);
              $activity['start_date_local'] = str_replace('Z', '', $activity['start_date_local']);
              $activity['manual'] = $activity['manual'] ? 1 : 0;
              $activity['private'] = $activity['private'] ? 1 : 0;

              // Check if we're importing an activity that already exists.
              if (in_array($activity['id'], $activity_results)) {
                // If we're just importing new activities, then since we found
                // an activity already in our db, we need to stop importing.
                if ($import_type == 'new') {
                  $processing = FALSE;
                  break;
                }

                // Update the existing activity.
                $result = $app['db']->update('activities',
                  [
                    'athlete_id' => $activity['athlete']['id'],
                    'name' => $activity['name'],
                    'description' => !empty($activity['description']) ? $activity['description'] : NULL,
                    'distance' => $activity['distance'],
                    'moving_time' => $activity['moving_time'],
                    'elapsed_time' => $activity['elapsed_time'],
                    'total_elevation_gain' => $activity['total_elevation_gain'],
                    'type' => $activity['type'],
                    'start_date' => $activity['start_date'],
                    'start_date_local' => $activity['start_date_local'],
                    'timezone' => $activity['timezone'],
                    'trainer' => $activity['trainer'],
                    'commute' => $activity['commute'],
                    'manual' => $activity['manual'],
                    'private' => $activity['private'],
                    'workout_type' => empty($activity['workout_type']) ? 0 : $activity['workout_type'],
                    'average_speed' => !empty($activity['average_speed']) ? $activity['average_speed'] : NULL,
                    'max_speed' => $activity['max_speed'],
                    'calories' => !empty($activity['calories']) ? $activity['calories'] : NULL,
                    'average_cadence' => !empty($activity['average_cadence']) ? $activity['average_cadence'] : NULL,
                    'average_watts' => !empty($activity['average_watts']) ? $activity['average_watts'] : NULL,
                    'average_heartrate' => !empty($activity['average_heartrate']) ? $activity['average_heartrate'] : NULL,
                    'max_heartrate' => !empty($activity['max_heartrate']) ? $activity['max_heartrate'] : NULL,
                  ],
                  ['id' => $activity['id']]
                );
                if ($result) {
                  $activities_updated++;
                }

                // We don't bother updating segment efforts for activities that
                // are just being updated.
                continue;
              }
              else {
                // Insert a new activity that wasn't already in our database.
                $app['db']->insert('activities', [
                  'id' => $activity['id'],
                  'athlete_id' => $activity['athlete']['id'],
                  'name' => $activity['name'],
                  'description' => !empty($activity['description']) ? $activity['description'] : NULL,
                  'distance' => $activity['distance'],
                  'moving_time' => $activity['moving_time'],
                  'elapsed_time' => $activity['elapsed_time'],
                  'total_elevation_gain' => $activity['total_elevation_gain'],
                  'type' => $activity['type'],
                  'start_date' => $activity['start_date'],
                  'start_date_local' => $activity['start_date_local'],
                  'timezone' => $activity['timezone'],
                  'trainer' => !empty($activity['trainer']) ? $activity['trainer'] : NULL,
                  'commute' => !empty($activity['commute']) ? $activity['commute'] : NULL,
                  'manual' => $activity['manual'],
                  'private' => $activity['private'],
                  'workout_type' => empty($activity['workout_type']) ? 0 : $activity['workout_type'],
                  'average_speed' => !empty($activity['average_speed']) ? $activity['average_speed'] : NULL,
                  'max_speed' => !empty($activity['max_speed']) ? $activity['max_speed'] : NULL,
                  'calories' => !empty($activity['calories']) ? $activity['calories'] : NULL,
                  'average_cadence' => !empty($activity['average_cadence']) ? $activity['average_cadence'] : NULL,
                  'average_watts' => !empty($activity['average_watts']) ? $activity['average_watts'] : NULL,
                  'average_heartrate' => !empty($activity['average_heartrate']) ? $activity['average_heartrate'] : NULL,
                  'max_heartrate' => !empty($activity['max_heartrate']) ? $activity['max_heartrate'] : NULL,
                ]);
                $activities_added++;
              }

              // Query the individual activity so we can get the detailed
              // representation that includes segment efforts.
              $activity = $app['strava']->getActivity($activity['id'], $user['access_token']);

              // If no segment efforts are found, then we are done with this
              // activity.
              if (empty($activity['segment_efforts'])) {
                continue;
              }

              // Check if we already have any segment efforts in our db.
              $segment_effort_ids = array_column($activity['segment_efforts'], 'id');
              $segment_effort_results = $app['db']->executeQuery(
                'SELECT id FROM segment_efforts WHERE id IN (?) ',
                [$segment_effort_ids],
                [Connection::PARAM_INT_ARRAY]
              )->fetchAll(\PDO::FETCH_COLUMN);

              // Process segments.
              if (!empty($activity['segment_efforts'])) {
                // Go through each segment effort.
                foreach ($activity['segment_efforts'] as $segment_effort) {
                  // Convert some data to how we need it stored.
                  $segment_effort['start_date'] = str_replace('Z', '', $segment_effort['start_date']);
                  $segment_effort['start_date_local'] = str_replace('Z', '', $segment_effort['start_date_local']);

                  // Insert the segment effort if it doesn't already exist.
                  if (!in_array($segment_effort['id'], $segment_effort_results)) {
                    $app['db']->insert('segment_efforts', [
                      'id' => $segment_effort['id'],
                      'segment_id' => $segment_effort['segment']['id'],
                      'name' => $segment_effort['name'],
                      'activity_id' => $segment_effort['activity']['id'],
                      'athlete_id' => $segment_effort['athlete']['id'],
                      'elapsed_time' => $segment_effort['elapsed_time'],
                      'moving_time' => $segment_effort['moving_time'],
                      'start_date' => $segment_effort['start_date'],
                      'start_date_local' => $segment_effort['start_date_local'],
                      'distance' => $segment_effort['distance'],
                      'average_cadence' => !empty($segment_effort['average_cadence']) ? $segment_effort['average_cadence'] : NULL,
                      'average_watts' => !empty($segment_effort['average_watts']) ? $segment_effort['average_watts'] : NULL,
                      'average_heartrate' => !empty($segment_effort['average_heartrate']) ? $segment_effort['average_heartrate'] : NULL,
                      'max_heartrate' => !empty($segment_effort['max_heartrate']) ? $segment_effort['max_heartrate'] : NULL,
                      'kom_rank' => !empty($segment_effort['kom_rank']) ? $segment_effort['kom_rank'] : NULL,
                      'pr_rank' => !empty($segment_effort['pr_rank']) ? $segment_effort['pr_rank'] : NULL,
                    ]);
                  }

                  // Check if we already have the segment in our db.
                  $segment = $segment_effort['segment'];
                  $result = $app['db']->executeQuery(
                    'SELECT id FROM segments WHERE id = ? ',
                    [$segment['id']]
                  )->fetchAll();

                  // Insert the segment related to the segment effort if it
                  // doesn't already exist.
                  if (empty($result)) {
                    // Convert some data to how we need it stored.
                    $segment['private'] = $segment['private'] ? 1 : 0;
                    $segment['hazardous'] = $segment['hazardous'] ? 1 : 0;

                    $app['db']->insert('segments', [
                      'id' => $segment['id'],
                      'name' => $segment['name'],
                      'activity_type' => $segment['activity_type'],
                      'distance' => $segment['distance'],
                      'average_grade' => $segment['average_grade'],
                      'maximum_grade' => $segment['maximum_grade'],
                      'elevation_high' => $segment['elevation_high'],
                      'elevation_low' => $segment['elevation_low'],
                      'city' => $segment['city'],
                      'state' => $segment['state'],
                      'country' => $segment['country'],
                      'climb_category' => $segment['climb_category'],
                      'private' => $segment['private'],
                      // Note: total_elevation_gain, effort_count, and
                      // athlete_count is not included in the activity endpoint.
                      // We would need to query the segment itself to get that.
                      // For now, we are avoiding that extra API call.
                      'total_elevation_gain' => !empty($segment['total_elevation_gain']) ? $segment['total_elevation_gain'] : NULL,
                      'effort_count' => !empty($segment['effort_count']) ? $segment['effort_count'] : NULL,
                      'athlete_count' => !empty($segment['athlete_count']) ? $segment['athlete_count'] : NULL,
                      'hazardous' => $segment['hazardous'],
                    ]);
                  }
                }
              }
            }
            catch (Exception $e) {
              // Something went wrong. Stop processing.
              $processing = FALSE;
              break;
            }
          }
        }

        // Importing starred segments.
        if (!empty($params['starred_segments'])) {
          // Query for existing segments so we don't import duplicates.
          $sql = 'SELECT segment_id ';
          $sql .= 'FROM starred_segments ';
          $sql .= 'WHERE athlete_id = ?';
          $existing_starred_segments = $app['db']->executeQuery($sql, [
            $user['id'],
          ])->fetchAll(\PDO::FETCH_COLUMN);

          $processing = TRUE;
          for ($page = 1; $processing; $page++) {
            // Query for starred segments.
            $starred_segments = $app['strava']->getStarredSegments($user['access_token'], $page);

            if (empty($starred_segments)) {
              $processing = FALSE;
              continue;
            }

            // Insert the starred segment if it doesn't exist.
            foreach ($starred_segments as $starred_segment) {
              if (!in_array($starred_segment['id'], $existing_starred_segments)) {
                try {
                  $app['db']->insert('starred_segments', [
                    'athlete_id' => $user['id'],
                    'segment_id' => $starred_segment['id'],
                    'starred_date' => str_replace('Z', '', $starred_segment['starred_date']),
                  ]);
                  $starred_segments_added++;
                }
                catch (Exception $e) {
                  // Something went wrong. Stop processing.
                  $processing = FALSE;
                  break;
                }
              }
            }
          }
        }

        // Generate output.
        $output = 'Added ' . $activities_added . ' activities.';
        if (!empty($activities_updated)) {
          $output .= ' Updated ' . $activities_updated . ' activities.';
        }
        if (!empty($starred_segments_added)) {
          $output .= ' Added ' . $starred_segments_added . ' starred segments.';
        }
      }

      return $app['twig']->render('import.twig', [
        'form' => $form->createView(),
        'output' => $output,
      ]);
    });

    return $import;
  }

}
