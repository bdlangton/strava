<?php

/**
 * @file
 * Contains the majority of the strava app functionality.
 */

require_once __DIR__ . '/../app/Strava/StravaServiceProvider.php';
require_once __DIR__ . '/../app/Strava/Strava.php';

use Doctrine\DBAL\Connection;
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;
use Igorw\Silex\ConfigServiceProvider;
use Kilte\Silex\Pagination\PaginationServiceProvider;
use Silex\Application;
use Silex\Provider\AssetServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\LocaleServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Strava\StravaServiceProvider;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$app = new Application();
unset($app['exception_handler']);

// Register the session provider.
$app->register(new SessionServiceProvider(), [
  'session.storage.options' => [
    'cookie_lifetime' => 0,
  ],
]);

// Get the app environment from the Apache config.
$env = getenv('APP_ENV') ?: 'dev';

// Include the environment specific settings file.
if (file_exists(__DIR__ . "/../config/$env.php")) {
  require_once __DIR__ . "/../config/$env.php";
}

// Our custom strava service.
$app->register(new StravaServiceProvider());

// Register the pagination provider.
$app->register(new PaginationServiceProvider(), ['pagination.per_page' => 20]);

// Register the twig service provider.
$app->register(new TwigServiceProvider(), [
  'twig.path' => __DIR__ . '/../views',
  'twig.autoescape' => FALSE,
]);

// Register the asset service provider.
$app->register(new AssetServiceProvider(), [
  'assets.version' => 'v1',
  'assets.version_format' => '%s?version=%s',
  'assets.named_packages' => [
    'css' => ['version' => 'css2', 'base_path' => '/css'],
  ],
]);

// Register the form provider.
$app->register(new FormServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new LocaleServiceProvider());
$app->register(new TranslationServiceProvider());
$app['twig.form.templates'] = ['form.html'];

// Register the config service provider.
if (file_exists(__DIR__ . "/../config/$env.json")) {
  $app->register(new ConfigServiceProvider(__DIR__ . "/../config/$env.json"));
}
else {
  $app['debug'] = getenv('strava_debug');
  $app['client_id'] = getenv('strava_client_id');
  $app['client_secret'] = getenv('strava_client_secret');
  $app['db.options'] = [
    'dbname' => getenv('strava_db_options_dbname'),
    'user' => getenv('strava_db_options_user'),
    'password' => getenv('strava_db_options_password'),
    'host' => getenv('strava_db_options_host'),
    'driver' => getenv('strava_db_options_driver'),
  ];
}

// Register the doctrine service provider.
$app->register(new DoctrineServiceProvider(), []);

// Home page.
$app->get('/', function () use ($app) {
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

// Logout.
$app->get('/logout', function () use ($app) {
  $app['session']->set('user', NULL);
  return $app->redirect('/');
});

// Token exchange.
$app->get('/token_exchange', function (Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (!empty($user)) {
    return $app->redirect('/');
  }

  // Finish the token exchange.
  $ch = curl_init();
  $params = $request->query->all();
  curl_setopt($ch, CURLOPT_URL, 'https://www.strava.com/oauth/token');
  curl_setopt($ch, CURLOPT_POST, 3);
  curl_setopt($ch, CURLOPT_POSTFIELDS, 'client_id=' . $app['client_id'] . '&client_secret=' . $app['client_secret'] . '&code=' . $params['code']);
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
        'UPDATE athletes set access_token = ? WHERE id = ?',
        [$data['access_token'], $data['athlete']['id']]
      );
    }
    else {
      $app['db']->insert('athletes', [
        'id' => $data['athlete']['id'],
        'access_token' => $data['access_token'],
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
      'activity_type' => $athlete_data['default_activity_type'],
      'format' => $athlete_data['default_format'],
    ]);
  }
  catch (Exception $e) {
  }

  // Import new activities for the user.
  $subRequest = Request::create('/import', 'GET', ['type' => 'new'], $request->cookies->all(), [], $request->server->all());
  if ($request->getSession()) {
    $subRequest->setSession($request->getSession());
  }
  $response = $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, FALSE);

  // Return the user to the homepage.
  return $app->redirect('/');
});

// Import user activities.
$app->get('/import', function (Request $request) use ($app) {
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
      $curl = curl_init();
      curl_setopt_array($curl, [
        CURLOPT_URL => 'https://www.strava.com/api/v3/athlete/activities?access_token=' . $user['access_token'] . '&page=' . $page,
        CURLOPT_RETURNTRANSFER => TRUE,
      ]);
      $activities = curl_exec($curl);
      $activities = json_decode($activities, TRUE);

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

          // If the activity is for a year that is earlier than the import year,
          // then we need to stop importing.
          if ($start_year < $import_type) {
            $processing = FALSE;
            break;
          }
          // If the activity is for a year that is later than the import year,
          // then we need to skip this activity.
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
            // If we're just importing new activities, then since we found an
            // activity already in our db, we need to stop importing.
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

            // We don't bother updating segment efforts for activities that are
            // just being updated.
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
          curl_setopt_array($curl, [
            CURLOPT_URL => 'https://www.strava.com/api/v3/activities/' . $activity['id'] . '?access_token=' . $user['access_token'],
            CURLOPT_RETURNTRANSFER => TRUE,
          ]);
          $activity = curl_exec($curl);
          $activity = json_decode($activity, TRUE);

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

              // Insert the segment related to the segment effort if it doesn't
              // already exist.
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
                  // Note: total_elevation_gain, effort_count, and athlete_count
                  // is not included in the activity endpoint. We would need to
                  // query the segment itself to get that. For now, we are
                  // avoiding that extra API call.
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
      curl_close($curl);
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
        $curl = curl_init();
        curl_setopt_array($curl, [
          CURLOPT_URL => 'https://www.strava.com/api/v3/segments/starred?access_token=' . $user['access_token'] . '&page=' . $page,
          CURLOPT_RETURNTRANSFER => TRUE,
        ]);
        $starred_segments = curl_exec($curl);
        $starred_segments = json_decode($starred_segments, TRUE);

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
        curl_close($curl);
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

// User profile settings.
$app->get('/user', function (Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += [
    'type' => $user['activity_type'],
    'format' => $user['format'],
  ];
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('type', ChoiceType::class, [
      'choices' => $app['strava']->getActivityTypes(),
      'label' => 'Activity Type',
    ])
    ->add('format', ChoiceType::class, [
      'choices' => [
        'Imperial' => 'imperial',
        'Metric' => 'metric',
      ],
      'label' => 'Format',
    ]);
  $form = $form->getForm();

  // Render the page.
  return $app['twig']->render('user.twig', [
    'form' => $form->createView(),
  ]);
});

// User profile settings post.
$app->post('/user', function (Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Get the form submissions.
  $type = $request->get('type') ?: $user['activity_type'];
  $format = $request->get('format') ?: $user['format'];

  // Update the database.
  $result = $app['db']->update('athletes',
    [
      'default_activity_type' => $type,
      'default_format' => $format,
    ],
    [
      'id' => $user['id'],
    ]
  );

  // Update the user session.
  $user['activity_type'] = $type;
  $user['format'] = $format;
  $app['session']->set('user', $user);

  // Redirect to the user page.
  return $app->redirect('/user');
});

// My activities.
$app->get('/activities', function (Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += [
    'type' => $user['activity_type'] ?: 'All',
    'format' => $user['format'] ?: 'imperial',
    'name' => '',
    'workout' => $app['strava']->getRunWorkouts(),
    'sort' => NULL,
  ];
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('type', ChoiceType::class, [
      'choices' => $app['strava']->getActivityTypes(),
      'label' => FALSE,
    ])
    ->add('format', ChoiceType::class, [
      'choices' => $app['strava']->getFormats(),
      'label' => FALSE,
    ])
    ->add('name', TextType::class, [
      'label' => FALSE,
      'required' => FALSE,
    ]);
  if ($params['type'] == 'Run') {
    $form = $form->add('workout', ChoiceType::class, [
      'choices' => $app['strava']->getRunWorkouts(),
      'expanded' => TRUE,
      'multiple' => TRUE,
      'label' => FALSE,
    ]);
  }
  $form = $form->getForm();

  // Determine the sort order.
  switch ($params['sort']) {
    case 'gain':
      $sort = 'ORDER BY total_elevation_gain DESC';
      break;

    case 'distance':
      $sort = 'ORDER BY distance DESC';
      break;

    default:
      $sort = 'ORDER BY start_date_local DESC';
      break;
  }

  // Query params and types.
  $query_params = [
    $user['id'],
    '%' . $params['name'] . '%',
  ];
  $query_types = [
    \PDO::PARAM_STR,
    \PDO::PARAM_STR,
  ];

  // Build the query.
  $sql = 'SELECT * ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE athlete_id = ? ';
  $sql .= 'AND name LIKE ? ';
  if ($params['type'] != 'All') {
    $sql .= 'AND type = ? ';
    $query_params[] = $params['type'];
    $query_types[] = \PDO::PARAM_INT;
  }
  if ($params['type'] == 'Run') {
    $sql .= 'AND workout_type IN (?) ';
    $query_params[] = $params['workout'];
    $query_types[] = Connection::PARAM_INT_ARRAY;
  }
  $sql .= $sort;
  $datapoints = $app['db']->executeQuery($sql, $query_params, $query_types);

  // Get the current page and build the pagination.
  $page = $request->query->get('page') ?: 1;
  $pagination = $app['pagination']($datapoints->rowCount(), $page);
  $pages = $pagination->build();

  // Trim the datapoints to just the results we want for this page.
  $datapoints = $datapoints->fetchAll();
  $datapoints = array_slice($datapoints, ($page - 1) * $app['pagination.per_page'], $app['pagination.per_page']);

  $activities = [];
  foreach ($datapoints as $point) {
    $point['distance'] = $app['strava']->convertDistance($point['distance'], $params['format']);
    $point['date'] = $app['strava']->convertDateFormat($point['start_date_local']);
    $point['elapsed_time'] = $app['strava']->convertTimeFormat($point['elapsed_time']);
    $point['total_elevation_gain'] = $app['strava']->convertElevationGain($point['total_elevation_gain'], $params['format']);
    $activities[] = $point;
  }

  // Render the page.
  return $app['twig']->render('activities.twig', [
    'form' => $form->createView(),
    'activities' => $activities,
    'type' => $params['type'],
    'format' => ($params['format'] == 'imperial') ? 'mi' : 'km',
    'gain_format' => ($params['format'] == 'imperial') ? 'ft' : 'm',
    'pages' => $pages,
    'current' => $pagination->currentPage(),
    'current_params_minus_page' => $app['strava']->getCurrentParams(['page']),
    'current_params_minus_sort' => $app['strava']->getCurrentParams(['sort']),
  ]);
})
->value('page', 1)
->convert(
  'page',
  function ($page) {
    return (int) $page;
  }
);

// My segments.
$app->get('/segments', function (Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += [
    'type' => $user['activity_type'] ?: 'All',
    'name' => '',
    'format' => $user['format'] ?: 'imperial',
    'sort' => NULL,
  ];
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('type', ChoiceType::class, [
      'choices' => $app['strava']->getActivityTypes(),
      'label' => FALSE,
    ])
    ->add('name', TextType::class, [
      'label' => FALSE,
      'required' => FALSE,
    ]);
  $form = $form->getForm();

  // Sort.
  switch ($params['sort']) {
    case 'segment':
      $sort = 'ORDER BY s.name';
      break;

    case 'distance':
      $sort = 'ORDER BY s.distance DESC';
      break;

    default:
      $sort = 'ORDER BY ss.starred_date DESC';
      break;
  }

  // Query params and types.
  $query_params = [
    $user['id'],
    '%' . $params['name'] . '%',
  ];
  $query_types = [
    \PDO::PARAM_STR,
    \PDO::PARAM_STR,
  ];

  // Build the query.
  $sql = 'SELECT s.id, s.name, s.activity_type, s.distance, ss.starred_date ';
  $sql .= 'FROM starred_segments ss ';
  $sql .= 'JOIN segments s ON (ss.segment_id = s.id) ';
  $sql .= 'WHERE ss.athlete_id = ? ';
  $sql .= 'AND s.name LIKE ? ';
  if ($params['type'] != 'All') {
    $sql .= 'AND s.activity_type = ? ';
    $query_params[] = $params['type'];
    $query_types[] = \PDO::PARAM_STR;
  }
  $sql .= $sort;
  $datapoints = $app['db']->executeQuery($sql, $query_params, $query_types);

  // Get the current page and build the pagination.
  $page = $request->query->get('page') ?: 1;
  $pagination = $app['pagination']($datapoints->rowCount(), $page);
  $pages = $pagination->build();

  // Trim the datapoints to just the results we want for this page.
  $datapoints = $datapoints->fetchAll();
  $datapoints = array_slice($datapoints, ($page - 1) * $app['pagination.per_page'], $app['pagination.per_page']);

  $segments = [];
  foreach ($datapoints as $point) {
    $point['distance'] = $app['strava']->convertDistance($point['distance'], $user['format']);
    $point['starred_date'] = $app['strava']->convertDateFormat($point['starred_date']);
    $segments[] = $point;
  }

  // Render the page.
  return $app['twig']->render('segments.twig', [
    'form' => $form->createView(),
    'segments' => $segments,
    'type' => $params['type'],
    'format' => ($params['format'] == 'imperial') ? 'mi' : 'km',
    'pages' => $pages,
    'current' => $pagination->currentPage(),
    'current_params_minus_page' => $app['strava']->getCurrentParams(['page']),
    'current_params_minus_sort' => $app['strava']->getCurrentParams(['sort']),
  ]);
})
->value('page', 1)
->convert(
  'page',
  function ($page) {
    return (int) $page;
  }
);

// General graphs.
$app->get('/data', function (Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += [
    'type' => $user['activity_type'] ?: 'All',
    'format' => $user['format'] ?: 'imperial',
    'group' => 'month',
    'workout' => $app['strava']->getRunWorkouts(),
  ];
  $params += $app['strava']->getBeginAndEndDates($params['group']);
  if (is_string($params['begin_date'])) {
    $params['begin_date'] = new DateTime($params['begin_date']);
  }
  if (is_string($params['end_date'])) {
    $params['end_date'] = new DateTime($params['end_date']);
  }
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('type', ChoiceType::class, [
      'choices' => $app['strava']->getActivityTypes(),
      'label' => FALSE,
    ])
    ->add('group', ChoiceType::class, [
      'choices' => $app['strava']->getGroups(),
      'label' => FALSE,
    ])
    ->add('format', ChoiceType::class, [
      'choices' => $app['strava']->getFormats(),
      'label' => FALSE,
    ])
    ->add('begin_date', DateType::class, [
      'input' => 'datetime',
      'widget' => 'single_text',
    ])
    ->add('end_date', DateType::class, [
      'input' => 'datetime',
      'widget' => 'single_text',
    ]);
  if ($params['type'] == 'Run') {
    $form = $form->add('workout', ChoiceType::class, [
      'choices' => $app['strava']->getRunWorkouts(),
      'expanded' => TRUE,
      'multiple' => TRUE,
      'label' => FALSE,
    ]);
  }
  $form = $form->getForm();
  if ($params['group'] == 'month') {
    $group = 'CONCAT(MONTHNAME(start_date_local), " ", YEAR(start_date_local))';
    $order_by_group = 'DATE_FORMAT(start_date_local, "%Y%m")';
  }
  elseif ($params['group'] == 'week') {
    $group = 'CONCAT("Week ", WEEK(start_date_local), " ", YEAR(start_date_local))';
    $order_by_group = 'CONCAT("Week ", YEARWEEK(start_date_local))';
  }
  else {
    $group = $order_by_group = 'YEAR(start_date_local)';
  }

  // Query params and types.
  $query_params = [
    $user['id'],
    $params['begin_date']->format('Y-m-d'),
    $params['end_date']->format('Y-m-d'),
  ];
  $query_types = [
    \PDO::PARAM_INT,
    \PDO::PARAM_STR,
    \PDO::PARAM_STR,
  ];

  // Build the query.
  $sql = 'SELECT ' . $group . ' grp, SUM(distance) distance, SUM(total_elevation_gain) elevation_gain, ';
  $sql .= 'SUM(elapsed_time) elapsed_time, SUM(moving_time) moving_time ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
  if ($params['type'] != 'All') {
    $sql .= 'AND type = ? ';
    $query_params[] = $params['type'];
    $query_types[] = \PDO::PARAM_STR;
  }
  if ($params['type'] == 'Run') {
    $sql .= 'AND workout_type IN (?) ';
    $query_params[] = $params['workout'];
    $query_types[] = Connection::PARAM_INT_ARRAY;
  }
  $sql .= 'GROUP BY ' . $group . ', ' . $order_by_group . ' ';
  $sql .= 'ORDER BY ' . $order_by_group;
  $datapoints = $app['db']->executeQuery($sql, $query_params, $query_types);

  // Build the chart.
  $chart = new Highchart();
  $chart->chart = [
    'renderTo' => 'chart1',
    'type' => 'line',
    'zoomType' => 'x',
  ];
  $chart->title = ['text' => 'Distance'];
  $chart->xAxis->categories = [];
  $chart->yAxis = [
    'title' => [
      'text' => ($params['format'] == 'imperial' ? 'Miles' : 'Kilometers'),
    ],
    'plotLines' => [
      [
        'value' => 0,
        'width' => 1,
        'color' => '#fc4c02',
      ],
    ],
  ];
  $chart->legend = ['enabled' => FALSE];
  $chart->plotOptions->area->color = '#fc4c02';
  $chart->plotOptions->area->fillColor->linearGradient = [0, 0, 0, 300];
  $chart->plotOptions->area->fillColor->stops = [
    [0, '#fc4c02'],
    [1, '#fff'],
  ];
  $chart->plotOptions->area->marker->enabled = FALSE;
  $chart->plotOptions->area->lineWidth = 1;
  $chart->plotOptions->area->dataLabels->enabled = (count($datapoints) <= 50 ? TRUE : FALSE);
  $chart->plotOptions->area->states->hover->lineWidth = 1;
  $chart->tooltip->formatter = new HighchartJsExpr("function() { return '<b>' + this.series.name + '</b><br/>' + this.x + ': ' + this.y; }");
  $chart->series[] = [
    'type' => 'area',
    'name' => 'Distance per ' . $params['group'],
    'data' => [],
  ];

  // Create elevation gain chart.
  $chart2 = clone $chart;
  $chart2->chart['renderTo'] = 'chart2';
  $chart2->title['text'] = 'Elevation Gain';
  $chart2->yAxis['title']['text'] = ($params['format'] == 'imperial' ? 'Feet' : 'Meters');
  $chart2->series = [
    [
      'type' => 'area',
      'name' => 'Elevation Gain per ' . $params['group'],
      'data' => [],
    ],
  ];

  // Create elevation gain per distance chart.
  $chart3 = clone $chart;
  $chart3->chart['renderTo'] = 'chart3';
  $chart3->title['text'] = 'Elevation Gain / ' . ($params['format'] == 'imperial' ? 'Mile' : 'Kilometer');
  $chart3->yAxis['title']['text'] = ($params['format'] == 'imperial' ? 'Feet' : 'Meters');
  $chart3->series = [
    [
      'type' => 'area',
      'name' => 'Elevation Gain per ' . ($params['format'] == 'imperial' ? 'Mile' : 'Kilometer'),
      'data' => [],
    ],
  ];

  // Create time chart.
  $chart4 = clone $chart;
  $chart4->chart['renderTo'] = 'chart4';
  $chart4->title['text'] = 'Moving Time';
  $chart4->yAxis['title']['text'] = 'Hours';
  $chart4->series = [
    [
      'type' => 'area',
      'name' => 'Moving Time spent per ' . $params['group'],
      'data' => [],
    ],
  ];

  // Add the data points to the chart.
  foreach ($datapoints as $point) {
    $point['distance'] = $app['strava']->convertDistance($point['distance'], $params['format'], FALSE);
    $point['elevation_gain'] = $app['strava']->convertElevationGain($point['elevation_gain'], $params['format'], FALSE);
    $chart->xAxis->categories[] = $point['grp'];
    $chart->series[0]['data'][] = $point['distance'];
    $chart2->xAxis->categories[] = $point['grp'];
    $chart2->series[0]['data'][] = (int) $point['elevation_gain'];
    $chart3->xAxis->categories[] = $point['grp'];
    $chart3->series[0]['data'][] = (!empty($point['distance']) ? round($point['elevation_gain'] / $point['distance']) : 0);
    $chart4->xAxis->categories[] = $point['grp'];
    $chart4->series[0]['data'][] = round($point['moving_time'] / 3600, 1);
  }

  // Render the chart.
  return $app['twig']->render('data.twig', [
    'chart' => $chart->render('chart1'),
    'chart2' => $chart2->render('chart2'),
    'chart3' => $chart3->render('chart3'),
    'chart4' => $chart4->render('chart4'),
    'scripts' => $chart->printScripts(TRUE),
    'form' => $form->createView(),
  ]);
});

// Stacked column charts.
$app->get('/column', function (Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += [
    'group' => 'month',
    'format' => $user['format'],
  ];
  $params += $app['strava']->getBeginAndEndDates($params['group']);
  if (is_string($params['begin_date'])) {
    $params['begin_date'] = new DateTime($params['begin_date']);
  }
  if (is_string($params['end_date'])) {
    $params['end_date'] = new DateTime($params['end_date']);
  }
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('group', ChoiceType::class, [
      'choices' => $app['strava']->getGroups(),
      'label' => FALSE,
    ])
    ->add('format', ChoiceType::class, [
      'choices' => $app['strava']->getFormats(),
      'label' => FALSE,
    ])
    ->add('begin_date', DateType::class, [
      'input' => 'datetime',
      'widget' => 'single_text',
    ])
    ->add('end_date', DateType::class, [
      'input' => 'datetime',
      'widget' => 'single_text',
    ]);
  $form = $form->getForm();
  if ($params['group'] == 'month') {
    $group = 'CONCAT(MONTHNAME(start_date_local), " ", YEAR(start_date_local))';
    $order_by_group = 'DATE_FORMAT(start_date_local, "%Y%m")';
  }
  elseif ($params['group'] == 'week') {
    $group = 'CONCAT("Week ", WEEK(start_date_local), " ", YEAR(start_date_local))';
    $order_by_group = 'CONCAT("Week ", YEARWEEK(start_date_local))';
  }
  else {
    $group = $order_by_group = 'YEAR(start_date_local)';
  }

  // Query for the x-axis points that will be used for running charts.
  $sql = 'SELECT ' . $group . ' grp ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
  $sql .= "AND $group IS NOT NULL ";
  $sql .= 'GROUP BY ' . $group . ', ' . $order_by_group . ' ';
  $sql .= 'ORDER BY ' . $order_by_group;
  $running_xaxis = $app['db']->executeQuery($sql, [
    'Run',
    $user['id'],
    $params['begin_date']->format('Y-m-d'),
    $params['end_date']->format('Y-m-d'),
  ]);

  // Build the query for running workout type.
  $sql = 'SELECT ' . $group . ' grp, workout_type, SUM(distance) distance, SUM(total_elevation_gain) elevation_gain, ';
  $sql .= 'SUM(elapsed_time) elapsed_time, SUM(moving_time) moving_time ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
  $sql .= "AND $group IS NOT NULL ";
  $sql .= 'GROUP BY ' . $group . ', ' . $order_by_group . ', workout_type ';
  $sql .= 'ORDER BY ' . $order_by_group;
  $workout_data = $app['db']->executeQuery($sql, [
    'Run',
    $user['id'],
    $params['begin_date']->format('Y-m-d'),
    $params['end_date']->format('Y-m-d'),
  ]);

  // Build the query for running treadmill chart.
  $sql = "SELECT $group grp, IFNULL(trainer, 0) trainer, SUM(distance) distance, SUM(total_elevation_gain) elevation_gain, ";
  $sql .= 'SUM(elapsed_time) elapsed_time, SUM(moving_time) moving_time ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
  $sql .= "AND $group IS NOT NULL ";
  $sql .= 'GROUP BY ' . $group . ', ' . $order_by_group . ', IFNULL(trainer, 0) ';
  $sql .= 'ORDER BY ' . $order_by_group;
  $treadmill_data = $app['db']->executeQuery($sql, [
    'Run',
    $user['id'],
    $params['begin_date']->format('Y-m-d'),
    $params['end_date']->format('Y-m-d'),
  ]);

  // Query for the x-axis points that will be used for cycling chart.
  $sql = 'SELECT ' . $group . ' grp ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
  $sql .= "AND $group IS NOT NULL ";
  $sql .= 'GROUP BY ' . $group . ', ' . $order_by_group . ' ';
  $sql .= 'ORDER BY ' . $order_by_group;
  $cycling_xaxis = $app['db']->executeQuery($sql, [
    'Ride',
    $user['id'],
    $params['begin_date']->format('Y-m-d'),
    $params['end_date']->format('Y-m-d'),
  ]);

  // Build the query for cycling workout type.
  $sql = 'SELECT ' . $group . ' grp, CONCAT(trainer, commute) ride_type, SUM(distance) distance, SUM(total_elevation_gain) elevation_gain, ';
  $sql .= 'SUM(elapsed_time) elapsed_time, SUM(moving_time) moving_time ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
  $sql .= "AND $group IS NOT NULL ";
  $sql .= 'GROUP BY ' . $group . ', ' . $order_by_group . ', CONCAT(trainer, commute) ';
  $sql .= 'ORDER BY ' . $order_by_group;
  $cycling_data = $app['db']->executeQuery($sql, [
    'Ride',
    $user['id'],
    $params['begin_date']->format('Y-m-d'),
    $params['end_date']->format('Y-m-d'),
  ]);

  // Build the stacked column chart.
  $running_chart = new Highchart();
  $running_chart->chart = [
    'renderTo' => 'running_chart',
    'type' => 'column',
  ];
  $running_chart->title = ['text' => 'Running Distribution'];
  $running_chart->yAxis = [
    'min' => 0,
    'title' => ['text' => ($params['format'] == 'imperial' ? 'Miles' : 'Meters')],
    'stackLabels' => [
      'enabled' => FALSE,
    ],
  ];
  $running_chart->legend = ['enabled' => TRUE];
  $running_chart->plotOptions = [
    'column' => [
      'stacking' => 'normal',
      'dataLabels' => [
        'enabled' => TRUE,
      ],
    ],
  ];
  $running_chart->tooltip->formatter = new HighchartJsExpr("function(e) { return '<b>' + this.series.name + '</b><br/>' + this.x + ': ' + this.y; }");

  // Clone to the treadmill chart.
  $treadmill_chart = clone $running_chart;
  $treadmill_chart->chart['renderTo'] = 'treadmill_chart';
  $treadmill_chart->title = ['text' => 'Running Outside vs Treadmill'];

  // Clone to the cycling chart.
  $cycling_chart = clone $running_chart;
  $cycling_chart->chart['renderTo'] = 'cycling_chart';
  $cycling_chart->title = ['text' => 'Cycling Distribution'];

  // Get the running x-axis labels.
  $xaxis = [];
  foreach ($running_xaxis as $point) {
    $xaxis[] = $point['grp'];
  }
  $running_chart->xAxis->categories = $xaxis;
  $treadmill_chart->xAxis->categories = $xaxis;

  // Add the workout data to the chart.
  $series = [
    '0' => ['name' => 'Regular Run', 'data' => []],
    '1' => ['name' => 'Race', 'data' => []],
    '2' => ['name' => 'Long Run', 'data' => []],
    '3' => ['name' => 'Workout', 'data' => []],
  ];
  foreach ($workout_data as $point) {
    $index = array_search($point['grp'], $xaxis);
    while ($index > count($series[$point['workout_type']]['data'])) {
      $series[$point['workout_type']]['data'][] = 0;
    }
    $point['distance'] = $app['strava']->convertDistance($point['distance'], $params['format'], FALSE);
    $series[$point['workout_type']]['data'][] = $point['distance'];
  }
  $running_chart->series = $series;

  // Add the treadmill data to the chart.
  $series = [
    '0' => ['name' => 'Outside', 'data' => []],
    '1' => ['name' => 'Treadmill', 'data' => []],
  ];
  foreach ($treadmill_data as $point) {
    $index = array_search($point['grp'], $xaxis);
    while ($index > count($series[$point['trainer']]['data'])) {
      $series[$point['trainer']]['data'][] = 0;
    }
    $point['distance'] = $app['strava']->convertDistance($point['distance'], $params['format'], FALSE);
    $series[$point['trainer']]['data'][] = $point['distance'];
  }
  $treadmill_chart->series = $series;

  // Get the cycling x-axis labels.
  $xaxis = [];
  foreach ($cycling_xaxis as $point) {
    $xaxis[] = $point['grp'];
  }
  $cycling_chart->xAxis->categories = $xaxis;

  // Add the cycling data to the chart.
  $series = [
    '0' => ['name' => 'Regular Ride', 'data' => []],
    '1' => ['name' => 'Commute', 'data' => []],
    '2' => ['name' => 'Stationary', 'data' => []],
  ];
  foreach ($cycling_data as $point) {
    $index = array_search($point['grp'], $xaxis);
    $ride_type = 0;
    if ($point['ride_type'] == '01') {
      $ride_type = 1;
    }
    elseif ($point['ride_type'] == '10') {
      $ride_type = 2;
    }
    while ($index > count($series[$ride_type]['data'])) {
      $series[$ride_type]['data'][] = 0;
    }
    $point['distance'] = $app['strava']->convertDistance($point['distance'], $params['format'], FALSE);
    $series[$ride_type]['data'][] = $point['distance'];
  }
  $cycling_chart->series = $series;

  // Render the chart.
  return $app['twig']->render('column.twig', [
    'running_chart' => $running_chart->render('running_chart'),
    'treadmill_chart' => $treadmill_chart->render('treadmill_chart'),
    'cycling_chart' => $cycling_chart->render('cycling_chart'),
    'scripts' => $running_chart->printScripts(TRUE),
    'form' => $form->createView(),
  ]);
});

// Display PRs and CRs.
$app->get('/records', function (Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += [
    'type' => $user['activity_type'] ?: 'All',
    'format' => $user['format'],
    'record' => NULL,
    'begin_date' => new DateTime('now - 1 year'),
    'end_date' => new DateTime('now'),
    'sort' => NULL,
  ];
  if (is_string($params['begin_date'])) {
    $params['begin_date'] = new DateTime($params['begin_date']);
  }
  if (is_string($params['end_date'])) {
    $params['end_date'] = new DateTime($params['end_date']);
  }
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('type', ChoiceType::class, [
      'choices' => [
        'All' => 'All',
        'Running' => 'Run',
        'Cycling' => 'Ride',
      ],
      'label' => FALSE,
    ])
    ->add('record', ChoiceType::class, [
      'choices' => [
        'All Records' => 'All',
        'PR Only' => 'PR',
        'KOM/CR Only' => 'CR',
        'Top 10 Only' => 'Top10',
      ],
      'label' => FALSE,
    ])
    ->add('format', ChoiceType::class, [
      'choices' => $app['strava']->getFormats(),
      'label' => FALSE,
    ])
    ->add('begin_date', DateType::class, [
      'input' => 'datetime',
      'widget' => 'single_text',
    ])
    ->add('end_date', DateType::class, [
      'input' => 'datetime',
      'widget' => 'single_text',
    ]);
  $form = $form->getForm();

  // Determine what records the user wants to see.
  $record_query = 'se.pr_rank = 1 OR se.kom_rank IS NOT NULL';
  if ($params['record'] == 'PR') {
    $record_query = 'se.pr_rank = 1';
  }
  elseif ($params['record'] == 'CR') {
    $record_query = 'se.kom_rank = 1';
  }
  elseif ($params['record'] == 'Top10') {
    $record_query = 'se.kom_rank IS NOT NULL';
  }

  // Determine the sort order.
  switch ($params['sort']) {
    case 'avg':
      $sort = 'ORDER BY s.average_grade DESC';
      break;

    case 'max':
      $sort = 'ORDER BY s.maximum_grade DESC';
      break;

    case 'distance':
      $sort = 'ORDER BY s.distance DESC';
      break;

    default:
      $sort = 'ORDER BY a.start_date_local DESC';
      break;
  }

  // Query params and types.
  $query_params = [
    $user['id'],
    $params['begin_date']->format('Y-m-d'),
    $params['end_date']->format('Y-m-d'),
  ];
  $query_types = [
    \PDO::PARAM_INT,
    \PDO::PARAM_STR,
    \PDO::PARAM_STR,
  ];

  // Build the query.
  $sql = 'SELECT s.name, se.id effort_id, se.segment_id, se.activity_id, ';
  $sql .= 's.distance, se.elapsed_time time, s.average_grade, s.maximum_grade, ';
  $sql .= 'se.pr_rank, se.kom_rank, s.city, s.state, a.start_date_local date, a.type ';
  $sql .= 'FROM activities a ';
  $sql .= 'JOIN segment_efforts se ON (a.athlete_id = se.athlete_id AND a.id = se.activity_id) ';
  $sql .= 'JOIN segments s ON (s.id = se.segment_id) ';
  $sql .= 'WHERE (' . $record_query . ') AND a.athlete_id = ? AND a.start_date_local BETWEEN ? AND ? ';
  if ($params['type'] != 'All') {
    $sql .= 'AND a.type = ? ';
    $query_params[] = $params['type'];
    $query_types[] = \PDO::PARAM_STR;
  }
  $sql .= $sort;
  $datapoints = $app['db']->executeQuery($sql, $query_params, $query_types);

  // Get the current page and build the pagination.
  $page = $request->query->get('page') ?: 1;
  $pagination = $app['pagination']($datapoints->rowCount(), $page);
  $pages = $pagination->build();

  // Trim the datapoints to just the results we want for this page.
  $datapoints = $datapoints->fetchAll();
  $datapoints = array_slice($datapoints, ($page - 1) * $app['pagination.per_page'], $app['pagination.per_page']);

  // Add the segments to the array.
  $efforts = [];
  foreach ($datapoints as $point) {
    $point['distance'] = $app['strava']->convertDistance($point['distance'], $params['format']);
    $point['time'] = $app['strava']->convertTimeFormat($point['time']);
    $point['date'] = $app['strava']->convertDateFormat($point['date']);
    $point['pr_rank'] = !empty($point['pr_rank']) ? 'Yes' : 'No';
    $efforts[] = $point;
  }

  // Render the page.
  return $app['twig']->render('records.twig', [
    'form' => $form->createView(),
    'efforts' => $efforts,
    'format' => ($params['format'] == 'imperial') ? 'mi' : 'km',
    'type' => $params['type'],
    'pages' => $pages,
    'current' => $pagination->currentPage(),
    'current_params_minus_page' => $app['strava']->getCurrentParams(['page']),
    'current_params_minus_sort' => $app['strava']->getCurrentParams(['sort']),
  ]);
})
->value('page', 1)
->convert(
  'page',
  function ($page) {
    return (int) $page;
  }
);

// Display the Biggest Stats page.
$app->get('/big', function (Request $request) use ($app) {
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
    $start_date = new DateTime();
    $start_date->setTimestamp($start_timestamp);
    $end_timestamp = strtotime('+' . ($params['duration'] - 1) . ' days', $start_timestamp);
    $end_date = new DateTime();
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
$app->get('/big/update/{id}', function (Request $request, $id) use ($app) {
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
        'type' => $stat['type'],
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
    $response = $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, FALSE);
    $app['session']->getFlashBag()->add('strava', 'Your strava stat was updated!');
  }
  else {
    $app['session']->getFlashBag()->add('strava', 'We could not find a stat to update.');
  }

  // Reload the big page.
  return $app->redirect('/big');
});

// Delete a biggest stat result.
$app->get('/big/delete/{id}', function (Request $request, $id) use ($app) {
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

// Display the Jon score chart.
$app->get('/jon', function (Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += $app['strava']->getBeginAndEndDates();
  if (is_string($params['begin_date'])) {
    $params['begin_date'] = new DateTime($params['begin_date']);
  }
  if (is_string($params['end_date'])) {
    $params['end_date'] = new DateTime($params['end_date']);
  }
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('begin_date', DateType::class, [
      'input' => 'datetime',
      'widget' => 'single_text',
    ])
    ->add('end_date', DateType::class, [
      'input' => 'datetime',
      'widget' => 'single_text',
    ]);
  $form = $form->getForm();

  // Build the query.
  $sql = 'SELECT name, id, start_date_local, average_heartrate, distance, total_elevation_gain, average_speed ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE type = ? AND average_heartrate > 0 AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
  $sql .= 'ORDER BY start_date_local';
  $datapoints = $app['db']->executeQuery($sql, [
    'Run',
    $user['id'],
    $params['begin_date']->format('Y-m-d'),
    $params['end_date']->format('Y-m-d'),
  ],
  [
    \PDO::PARAM_STR,
    \PDO::PARAM_INT,
    \PDO::PARAM_STR,
    \PDO::PARAM_STR,
  ]);

  // Build the chart.
  $chart = new Highchart();
  $chart->chart = [
    'renderTo' => 'chart1',
    'type' => 'line',
  ];
  $chart->title = ['text' => 'Jon Score'];
  $chart->xAxis->categories = [];
  $chart->yAxis = [
    'title' => 'Score',
    'plotLines' => [
      [
        'value' => 0,
        'width' => 1,
        'color' => '#fc4c02',
      ],
    ],
  ];
  $chart->legend = ['enabled' => FALSE];
  $chart->series[0] = [
    'name' => 'Jon Score',
    'data' => [],
  ];
  $chart->series[0]['point'] = [
    'events' => [
      'click' => new HighchartJsExpr('function(e) { location.href = e.point.url; }'),
    ],
  ];
  $chart->plotOptions->line->color = '#fc4c02';
  $chart->tooltip->formatter = new HighchartJsExpr("function(e) { return '<b>' + this.key + '</b><br/>' + this.x + ': ' + this.y; }");

  // Add the data points to the chart.
  foreach ($datapoints as $point) {
    $hr = round($point['average_heartrate']);
    $miles = round($point['distance'] * DISTANCE_TO_MILES, 1);
    $gain = round($point['total_elevation_gain'] * GAIN_TO_FEET);
    $pace = round(60 / ($point['average_speed'] * 2.23694), 2);
    $chart->xAxis->categories[] = $app['strava']->convertDateFormat($point['start_date_local']);
    $chart->series[0]['data'][] = [
      'name' => $point['name'],
      'y' => round($miles / 2 + (($gain / $miles) / 10) + (120 / ($pace - 5) + ((185 - $hr) * 1.05))),
      'url' => 'http://www.strava.com/activities/' . $point['id'],
    ];
  }

  // Render the chart.
  return $app['twig']->render('jon.twig', [
    'form' => $form->createView(),
    'chart' => $chart->render('chart1'),
    'scripts' => $chart->printScripts(TRUE),
  ]);
});

// Handle errors.
$app->error(function (\Exception $e, Request $request, $code) use ($app) {
  // Redirect to home page on page not found.
  if ($code === 404) {
    return $app->redirect('/');
  }
  if ($app['debug']) {
    return new Response($e);
  }
});

return $app;
