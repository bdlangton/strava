<?php

namespace Strava;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stats controller.
 */
class StatsControllerProvider implements ControllerProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function connect(Application $app) {
    $stats = $app['controllers_factory'];

    // Display the Biggest Stats page.
    $stats->get('/big', function (Request $request) use ($app) {
      // Check the session.
      $user = $app['session']->get('user');
      if (empty($user)) {
        return $app->redirect('/');
      }

      // Build the form.
      $params = $request->query->all();
      $generating = !empty($params['stat_type']);
      $params += [
        'type' => $user['activity_type'] ?: 'Run',
        'stat_type' => 'distance',
        'duration' => 7,
        'excluding_races' => [],
      ];
      $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
        ->add('type', ChoiceType::class, [
          'choices' => $app['strava']->getActivityTypes(FALSE),
          'label' => FALSE,
        ])
        ->add('stat_type', ChoiceType::class, [
          'choices' => [
            'Distance' => 'distance',
            'Elevation Gain' => 'total_elevation_gain',
            'Time' => 'elapsed_time',
          ],
          'label' => FALSE,
        ])
        ->add('duration', TextType::class, [
          'label' => 'Days',
        ])
        ->add('excluding_races', ChoiceType::class, [
          'choices' => ['Exclude Races' => 'excluding_races'],
          'expanded' => TRUE,
          'multiple' => TRUE,
          'label' => FALSE,
        ]);
      $form = $form->getForm();

      // If we need to generate a new stat.
      if ($generating) {
        // Build the query.
        $sql = 'SELECT DATEDIFF(start_date_local, "2000-01-01") day, SUM(' . $params['stat_type'] . ') stat ';
        $sql .= 'FROM activities ';
        $sql .= 'WHERE athlete_id = ? AND type = ? ';
        if (!empty($params['excluding_races'])) {
          $sql .= 'AND workout_type <> 1 ';
        }
        $sql .= 'GROUP BY start_date_local ';
        $sql .= 'ORDER BY start_date_local';
        $results = $app['db']->executeQuery($sql, [
          $user['id'],
          $params['type'],
        ])->fetchAll();
        $days = [];
        foreach ($results as $result) {
          $days[$result['day']] = $result['stat'];
        }
        $days += array_fill_keys(range(min(array_keys($days)), max(array_keys($days))), 0);
        ksort($days);

        // Find the biggest data.
        $biggest_date = NULL;
        $biggest_stat = 0;
        $i = 0;
        foreach ($days as $key => $day) {
          $slice = array_slice($days, $i, $params['duration']);
          $current_stat = array_sum($slice);
          if ($current_stat > $biggest_stat) {
            $biggest_stat = $current_stat;
            $biggest_date = $key;
          }
          $i++;
        }
        $start_timestamp = strtotime('+' . $biggest_date . ' days', strtotime('2000-01-01'));
        $start_date = new \DateTime();
        $start_date->setTimestamp($start_timestamp);
        $end_timestamp = strtotime('+' . ($params['duration'] - 1) . ' days', $start_timestamp);
        $end_date = new \DateTime();
        $end_date->setTimestamp($end_timestamp);

        // Update or insert the stat.
        $sql = 'SELECT * ';
        $sql .= 'FROM stats ';
        $sql .= 'WHERE athlete_id = ? AND activity_type = ? AND duration = ? AND stat_type = ? AND excluding_races = ?';
        $result = $app['db']->executeQuery($sql, [
          $user['id'],
          $params['type'],
          $params['duration'],
          $params['stat_type'],
          !empty($params['excluding_races']),
        ])->fetchAll();
        if (empty($result)) {
          $app['db']->insert('stats', [
            'athlete_id' => $user['id'],
            'activity_type' => $params['type'],
            'duration' => $params['duration'],
            'stat_type' => $params['stat_type'],
            'stat' => $biggest_stat,
            'start_date' => $start_date->format('Y-m-d'),
            'end_date' => $end_date->format('Y-m-d'),
            'excluding_races' => !empty($params['excluding_races']) ? 1 : 0,
          ]);
          $result = $app['db']->executeQuery($sql, [
            $user['id'],
            $params['type'],
            $params['duration'],
            $params['stat_type'],
            !empty($params['excluding_races']),
          ])->fetchAll();
        }
        else {
          $result = $app['db']->update('stats', [
            'stat' => $biggest_stat,
            'start_date' => $start_date->format('Y-m-d'),
            'end_date' => $end_date->format('Y-m-d'),
          ],
          [
            'athlete_id' => $user['id'],
            'activity_type' => $params['type'],
            'duration' => $params['duration'],
            'stat_type' => $params['stat_type'],
            'excluding_races' => !empty($params['excluding_races']) ? 1 : 0,
          ]);
        }
      }

      // Query all stats from this user.
      $sql = 'SELECT * ';
      $sql .= 'FROM stats ';
      $sql .= 'WHERE athlete_id = ? ';
      $sql .= 'ORDER BY activity_type, stat_type, duration';
      $stats = $app['db']->executeQuery($sql, [$user['id']], [\PDO::PARAM_INT])->fetchAll();

      foreach ($stats as &$stat) {
        if ($stat['stat_type'] == 'distance') {
          $stat['stat_type'] = 'Distance';
          $stat['stat'] = $app['strava']->convertDistance($stat['stat'], $user['format']) . ' ' . ($user['format'] == 'imperial' ? 'miles' : 'kilometers');
        }
        elseif ($stat['stat_type'] == 'total_elevation_gain') {
          $stat['stat_type'] = 'Elevation Gain';
          $stat['stat'] = $app['strava']->convertElevationGain($stat['stat'], $user['format']) . ' ' . ($user['format'] == 'imperial' ? 'feet' : 'meters');
        }
        elseif ($stat['stat_type'] == 'elapsed_time') {
          $stat['stat_type'] = 'Time';
          $minutes = $app['strava']->convertTimeFormat($stat['stat'], 'i');
          $hours = $app['strava']->convertTimeFormat($stat['stat'], 'H');
          $days = $app['strava']->convertTimeFormat($stat['stat'], 'j') - 1;
          if ($days > 0) {
            $hours += $days * 24;
          }
          $stat['stat'] = $hours . ' hours, ' . $minutes . ' minutes';
        }
        $stat['excluding_races'] = empty($stat['excluding_races']) ? '' : 'Yes';
        $stat['start_date'] = $app['strava']->convertDateFormat($stat['start_date']);
        $stat['end_date'] = $app['strava']->convertDateFormat($stat['end_date']);
      }

      // Render the page.
      return $app['twig']->render('big.twig', [
        'form' => $form->createView(),
        'stats' => $stats,
      ]);
    });

    // Update a biggest stat result.
    $stats->get('/big/update/{id}', function (Request $request, $id) use ($app) {
      // Check the session.
      $user = $app['session']->get('user');
      if (empty($user)) {
        return $app->redirect('/');
      }

      // Find the stat.
      $sql = 'SELECT * ';
      $sql .= 'FROM stats ';
      $sql .= 'WHERE athlete_id = ? AND id = ?';
      $stat = $app['db']->executeQuery($sql, [
        $user['id'],
        $id,
      ],
      [
        \PDO::PARAM_INT,
        \PDO::PARAM_INT,
      ])->fetch();

      // Update the stat.
      if (!empty($stat)) {
        $subRequest = Request::create(
          '/big',
          'GET',
          [
            'type' => $stat['activity_type'],
            'stat_type' => $stat['stat_type'],
            'duration' => $stat['duration'],
            'excluding_races' => $stat['excluding_races'] ? ['excluding_races'] : [],
          ],
          $request->cookies->all(),
          [],
          $request->server->all()
        );
        if ($request->getSession()) {
          $subRequest->setSession($request->getSession());
        }
        $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, FALSE);
        $app['session']->getFlashBag()->add('strava', 'Your strava stat was updated!');
      }
      else {
        $app['session']->getFlashBag()->add('strava', 'We could not find a stat to update.');
      }

      // Reload the big page.
      return $app->redirect('/big');
    });

    // Delete a biggest stat result.
    $stats->get('/big/delete/{id}', function (Request $request, $id) use ($app) {
      // Check the session.
      $user = $app['session']->get('user');
      if (empty($user)) {
        return $app->redirect('/');
      }

      // Only let the user delete the stat if they own it.
      $result = $app['db']->delete('stats', [
        'id' => $id,
        'athlete_id' => $user['id'],
      ]);

      if ($result) {
        $app['session']->getFlashBag()->add('strava', 'Your strava stat was deleted!');
      }
      else {
        $app['session']->getFlashBag()->add('strava', 'We could not find a stat to delete.');
      }

      // Reload the big page.
      return $app->redirect('/big');
    });

    return $stats;
  }

}
