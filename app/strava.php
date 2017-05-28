<?php

use Doctrine\DBAL\Connection;
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;
use Igorw\Silex\ConfigServiceProvider;
use Kilte\Silex\Pagination\PaginationServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$app = new Silex\Application();
unset($app['exception_handler']);

// Register the session provider.
$app->register(new Silex\Provider\SessionServiceProvider(), [
  'session.storage.options' => [
    'cookie_lifetime' => 0,
  ],
]);

// Our custom strava service.
$app->register(new Strava\StravaServiceProvider());

// Register the pagination provider.
$app->register(new PaginationServiceProvider, array('pagination.per_page' => 20));

// Register the twig service provider.
$app->register(new TwigServiceProvider(), [
  'twig.path' => __DIR__ . '/../views',
  'twig.autoescape' => FALSE,
]);

// Register the form provider.
$app->register(new FormServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\LocaleServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider());
$app['twig.form.templates'] = ['form.html'];

// Register the config service provider.
// Get the app environment from the Apache config.
$env = getenv('APP_ENV') ?: 'prod';
$app->register(new ConfigServiceProvider(__DIR__ . "/../config/$env.json"));

// Include the environment specific settings file.
if (file_exists(__DIR__ . "/../config/$env.php")) {
  require_once __DIR__ . "/../config/$env.php";
}

// Register the doctrine service provider.
$app->register(new DoctrineServiceProvider(), []);

// Home page.
$app->get('/', function() use ($app) {
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
$app->get('/logout', function() use ($app) {
  $app['session']->set('user', NULL);
  return $app->redirect('/');
});

// Token exchange.
$app->get('/token_exchange', function(Request $request) use ($app) {
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
    $app['session']->set('user', [
      'id' => $data['athlete']['id'],
      'access_token' => $data['access_token'],
    ]);
  }
  catch (Exception $e) { }

  // Import new activities for the user.
  $subRequest = Request::create('/import', 'GET', array('type' => 'new'), $request->cookies->all(), array(), $request->server->all());
  if ($request->getSession()) {
    $subRequest->setSession($request->getSession());
  }
  $response = $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, FALSE);

  // Return the user to the homepage.
  return $app->redirect('/');
});

// Import user activities.
$app->get('/import', function(Request $request) use ($app) {
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
  ];
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('type', ChoiceType::class, [
      'choices' => [
        'new' => 'New Activities',
        '2017' => '2017 Activities',
        '2016' => '2016 Activities',
        '2015' => '2015 Activities',
        '2014' => '2014 Activities',
        '2013' => '2013 Activities',
        '2012' => '2012 Activities',
        '2011' => '2011 Activities',
        '2010' => '2010 Activities',
      ],
      'label' => FALSE,
    ]);
  $form = $form->getForm();

  if (!empty($import_type)) {
    $activities_added = $activities_updated = 0;
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
          $start_year = (int) $app['strava']->convert_date_format($activity['start_date_local'], 'Y');

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
          // Check if we got a result.
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
                'workout_type' => empty($activity['workout_type']) ? 0: $activity['workout_type'],
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

            // We don't both updating segment efforts for activities that are
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
              'workout_type' => empty($activity['workout_type']) ? 0: $activity['workout_type'],
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
                  'total_elevation_gain' => $segment['total_elevation_gain'],
                  'effort_count' => $segment['effort_count'],
                  'athlete_count' => $segment['athlete_count'],
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
    curl_close($curl);

    // Generate output.
    $output = 'Added ' . $activities_added . ' activities.';
    if (!empty($activities_updated)) {
      $output .= ' Updated ' . $activities_updated . ' activities.';
    }
  }

  return $app['twig']->render('import.twig', [
    'form' => $form->createView(),
    'output' => $output,
  ]);
});

// My activities.
$app->get('/activities', function(Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += [
    'type' => 'Run',
    'format' => 'imperial',
    'workout' => [0, 1, 2, 3],
  ];
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('type', ChoiceType::class, [
      'choices' => [
        'Run' => 'Running',
        'Ride' => 'Cycling',
        'Swim' => 'Swimming',
        'AlpineSki' => 'Alpine Skiing',
        'BackcountrySki' => 'Backcountry Skiing',
        'CrossCountrySkiing' => 'Cross Country Skiing',
        'Crossfit' => 'CrossFit',
        'Hike' => 'Hiking',
        'Kayaking' => 'Kayaking',
        'NordicSki' => 'Nordic Skiing',
        'RockClimbing' => 'Rock Climbing',
        'Rowing' => 'Rowing',
        'Snowshoe' => 'Snowshoeing',
        'StandUpPaddling' => 'Stand Up Paddleboard',
        'VirtualRide' => 'Virtual Ride',
        'Walk' => 'Walking',
        'WaterSport' => 'Water Sports',
        'WeightTraining' => 'Weight Training',
        'Workout' => 'Workout',
        'Yoga' => 'Yoga',
      ],
      'label' => FALSE,
    ])
    ->add('format', ChoiceType::class, [
      'choices' => ['imperial' => 'Imperial', 'metric' => 'Metric'],
      'label' => FALSE,
    ]);
  if ($params['type'] == 'Run') {
    $form = $form->add('workout', ChoiceType::class, [
      'choices' => ['Default', 'Race', 'Long Run', 'Intervals'],
      'expanded' => TRUE,
      'multiple' => TRUE,
      'label' => FALSE,
    ]);
  }
  $form = $form->getForm();
  $sql = 'SELECT * ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE type = ? AND athlete_id = ? ';
  if ($params['type'] == 'Run') {
    $sql .= 'AND workout_type IN (?) ';
  }
  $sql .= 'ORDER BY start_date_local DESC';
  if ($params['type'] == 'Run') {
    $datapoints = $app['db']->executeQuery($sql,
      [
        $params['type'],
        $user['id'],
        $params['workout'],
      ],
      [
        \PDO::PARAM_STR,
        \PDO::PARAM_INT,
        Connection::PARAM_INT_ARRAY,
      ]
    );
  }
  else {
    $datapoints = $app['db']->executeQuery($sql, [
      $params['type'],
      $user['id'],
    ]);
  }

  // Get the current page and build the pagination.
  $page = $request->query->get('page') ?: 1;
  $pagination = $app['pagination']($datapoints->rowCount(), $page);
  $pages = $pagination->build();

  // Trim the datapoints to just the results we want for this page.
  $datapoints = $datapoints->fetchAll();
  $datapoints = array_slice($datapoints, ($page - 1) * $app['pagination.per_page'], $app['pagination.per_page']);

  $activities = [];
  foreach ($datapoints as $point) {
    $point['distance'] = $app['strava']->convert_distance($point['distance'], $params['format']);
    $point['date'] = $app['strava']->convert_date_format($point['start_date_local']);
    $point['elapsed_time'] = gmdate("H:i:s", $point['elapsed_time']);
    $point['total_elevation_gain'] = $app['strava']->convert_elevation_gain($point['total_elevation_gain'], $params['format']);
    $activities[] = $point;
  }

  // Render the page.
  return $app['twig']->render('activities.twig', [
    'form' => $form->createView(),
    'activities' => $activities,
    'format' => ($params['format'] == 'imperial') ? 'mi' : 'km',
    'gain_format' => ($params['format'] == 'imperial') ? 'ft' : 'm',
    'pages' => $pages,
    'current' => $pagination->currentPage(),
    'currentParams' => !empty($_SERVER['QUERY_STRING']) ? html_entity_decode($_SERVER['QUERY_STRING']) : NULL,
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
$app->get('/data', function(Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += [
    'type' => 'Run',
    'group' => 'month',
    'format' => 'imperial',
    'workout' => [0, 1, 2, 3],
  ];
  $params += $app['strava']->get_begin_and_end_dates($params['group']);
  if (is_string($params['begin_date'])) {
    $params['begin_date'] = new DateTime($params['begin_date']);
  }
  if (is_string($params['end_date'])) {
    $params['end_date'] = new DateTime($params['end_date']);
  }
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('type', ChoiceType::class, [
      'choices' => [
        'Run' => 'Running',
        'Ride' => 'Cycling',
        'Swim' => 'Swimming',
        'AlpineSki' => 'Alpine Skiing',
        'BackcountrySki' => 'Backcountry Skiing',
        'CrossCountrySkiing' => 'Cross Country Skiing',
        'Crossfit' => 'CrossFit',
        'Hike' => 'Hiking',
        'Kayaking' => 'Kayaking',
        'NordicSki' => 'Nordic Skiing',
        'RockClimbing' => 'Rock Climbing',
        'Rowing' => 'Rowing',
        'Snowshoe' => 'Snowshoeing',
        'StandUpPaddling' => 'Stand Up Paddleboard',
        'VirtualRide' => 'Virtual Ride',
        'Walk' => 'Walking',
        'WaterSport' => 'Water Sports',
        'WeightTraining' => 'Weight Training',
        'Workout' => 'Workout',
        'Yoga' => 'Yoga',
      ],
      'label' => FALSE,
    ])
    ->add('group', ChoiceType::class, [
      'choices' => ['month' => 'Monthly', 'week' => 'Weekly', 'year' => 'Yearly'],
      'label' => FALSE,
    ])
    ->add('format', ChoiceType::class, [
      'choices' => ['imperial' => 'Imperial', 'metric' => 'Metric'],
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
      'choices' => ['Default', 'Race', 'Long Run', 'Intervals'],
      'expanded' => TRUE,
      'multiple' => TRUE,
      'label' => FALSE,
    ]);
  }
  $form = $form->getForm();
  if ($params['group'] == 'month') {
    $group = 'CONCAT(MONTHNAME(start_date_local), " ", YEAR(start_date_local))';
  }
  elseif ($params['group'] == 'week') {
    $group = 'CONCAT("Week ", YEARWEEK(start_date_local))';
  }
  else {
    $group = 'YEAR(start_date_local)';
  }
  $sql = 'SELECT ' . $group . ' grp, SUM(distance) distance, SUM(total_elevation_gain) elevation_gain, ';
  $sql .= 'SUM(elapsed_time) elapsed_time, SUM(moving_time) moving_time ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
  if ($params['type'] == 'Run') {
    $sql .= 'AND workout_type IN (?) ';
  }
  $sql .= 'GROUP BY ' . $group . ' ';
  $sql .= 'ORDER BY start_date_local';
  if ($params['type'] == 'Run') {
    $datapoints = $app['db']->executeQuery($sql,
      [
        $params['type'],
        $user['id'],
        $params['begin_date']->format('Y-m-d'),
        $params['end_date']->format('Y-m-d'),
        $params['workout'],
      ],
      [
        \PDO::PARAM_STR,
        \PDO::PARAM_INT,
        \PDO::PARAM_STR,
        \PDO::PARAM_STR,
        Connection::PARAM_INT_ARRAY,
      ]
    );
  }
  else {
    $datapoints = $app['db']->executeQuery($sql, [
      $params['type'],
      $user['id'],
      $params['begin_date']->format('Y-m-d'),
      $params['end_date']->format('Y-m-d'),
    ]);
  }

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
      'text' => ($params['format'] == 'imperial' ? 'Miles' : 'Meters'),
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
    'data' => []
  ];

  // Create elevation gain chart.
  $chart2 = clone $chart;
  $chart2->chart['renderTo'] = 'chart2';
  $chart2->title['text'] = 'Elevation Gain';
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
  $chart3->title['text'] = 'Elevation Gain / ' . ($params['format'] == 'imperial' ? 'Mile' : 'Meter');
  $chart3->series = [
    [
      'type' => 'area',
      'name' => 'Elevation Gain per ' . ($params['format'] == 'imperial' ? 'Mile' : 'Meter'),
      'data' => [],
    ],
  ];

  // Create time chart.
  $chart4 = clone $chart;
  $chart4->chart['renderTo'] = 'chart4';
  $chart4->title['text'] = 'Moving Time';
  $chart4->series = [
    [
      'type' => 'area',
      'name' => 'Moving Time spent per ' . $params['group'],
      'data' => [],
    ],
  ];

  // Add the data points to the chart.
  foreach ($datapoints as $point) {
    $point['distance'] = $app['strava']->convert_distance($point['distance'], $params['format']);
    $point['elevation_gain'] = $app['strava']->convert_elevation_gain($point['elevation_gain'], $params['format']);
    $chart->xAxis->categories[] = $point['grp'];
    $chart->series[0]['data'][] = $point['distance'];
    $chart2->xAxis->categories[] = $point['grp'];
    $chart2->series[0]['data'][] = $point['elevation_gain'];
    $chart3->xAxis->categories[] = $point['grp'];
    $chart3->series[0]['data'][] = round($point['elevation_gain'] / $point['distance']);
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
$app->get('/column', function(Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += [
    'group' => 'month',
    'format' => 'imperial',
  ];
  $params += $app['strava']->get_begin_and_end_dates($params['group']);
  if (is_string($params['begin_date'])) {
    $params['begin_date'] = new DateTime($params['begin_date']);
  }
  if (is_string($params['end_date'])) {
    $params['end_date'] = new DateTime($params['end_date']);
  }
  $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
    ->add('group', ChoiceType::class, [
      'choices' => ['month' => 'Monthly', 'week' => 'Weekly', 'year' => 'Yearly'],
      'label' => FALSE,
    ])
    ->add('format', ChoiceType::class, [
      'choices' => ['imperial' => 'Imperial', 'metric' => 'Metric'],
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
  }
  elseif ($params['group'] == 'week') {
    $group = 'CONCAT("Week ", WEEK(start_date_local), " ", YEAR(start_date_local))';
  }
  else {
    $group = 'YEAR(start_date_local)';
  }

  // Query for the x-axis points that will be used for running charts.
  $sql = 'SELECT ' . $group . ' grp ';
  $sql .= 'FROM activities ';
  $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
  $sql .= "AND $group IS NOT NULL ";
  $sql .= 'GROUP BY ' . $group . ' ';
  $sql .= 'ORDER BY start_date_local';
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
  $sql .= 'GROUP BY ' . $group . ', workout_type ';
  $sql .= 'ORDER BY start_date_local';
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
  $sql .= 'GROUP BY ' . $group . ', IFNULL(trainer, 0) ';
  $sql .= 'ORDER BY start_date_local';
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
  $sql .= 'GROUP BY ' . $group . ' ';
  $sql .= 'ORDER BY start_date_local';
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
  $sql .= 'GROUP BY ' . $group . ', CONCAT(trainer, commute) ';
  $sql .= 'ORDER BY start_date_local';
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
    $point['distance'] = $app['strava']->convert_distance($point['distance'], $params['format']);
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
    $point['distance'] = $app['strava']->convert_distance($point['distance'], $params['format']);
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
    $point['distance'] = $app['strava']->convert_distance($point['distance'], $params['format']);
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
$app->get('/records', function(Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += [
    'type' => 'Run',
    'format' => 'imperial',
    'record' => NULL,
    'begin_date' => new DateTime('now - 1 year'),
    'end_date' => new DateTime('now'),
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
        'Run' => 'Running',
        'Ride' => 'Cycling',
      ],
      'label' => FALSE,
    ])
    ->add('record', ChoiceType::class, [
      'choices' => [
        'All' => 'All Records',
        'PR' => 'PR Only',
        'CR' => 'KOM/CR Only',
        'Top10' => 'Top 10 Only',
      ],
      'label' => FALSE,
    ])
    ->add('format', ChoiceType::class, [
      'choices' => ['imperial' => 'Imperial', 'metric' => 'Metric'],
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

  // Build the query.
  $sql = 'SELECT s.name, se.id effort_id, se.segment_id, se.activity_id, ';
  $sql .= 's.distance, se.elapsed_time time, s.average_grade, s.maximum_grade, ';
  $sql .= 'se.pr_rank, se.kom_rank, s.city, s.state, a.start_date_local date ';
  $sql .= 'FROM activities a ';
  $sql .= 'JOIN segment_efforts se ON (a.athlete_id = se.athlete_id AND a.id = se.activity_id) ';
  $sql .= 'JOIN segments s ON (s.id = se.segment_id) ';
  $sql .= 'WHERE (' . $record_query . ') AND a.type = ? AND a.athlete_id = ? AND a.start_date_local BETWEEN ? AND ? ';
  $sql .= 'ORDER BY a.start_date_local DESC';
  $datapoints = $app['db']->executeQuery($sql, [
    $params['type'],
    $user['id'],
    $params['begin_date']->format('Y-m-d'),
    $params['end_date']->format('Y-m-d'),
  ]);

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
    $point['distance'] = $app['strava']->convert_distance($point['distance'], $params['format']);
    $point['time'] = gmdate("H:i:s", $point['time']);
    $point['date'] = $app['strava']->convert_date_format($point['date']);
    $point['pr_rank'] = !empty($point['pr_rank']) ? 'Yes' : 'No';
    $efforts[] = $point;
  }

  // Render the page.
  return $app['twig']->render('records.twig', [
    'form' => $form->createView(),
    'efforts' => $efforts,
    'format' => ($params['format'] == 'imperial') ? 'mi' : 'km',
    'pages' => $pages,
    'current' => $pagination->currentPage(),
    'currentParams' => !empty($_SERVER['QUERY_STRING']) ? html_entity_decode($_SERVER['QUERY_STRING']) : NULL,
  ]);
})
->value('page', 1)
->convert(
  'page',
  function ($page) {
    return (int) $page;
  }
);

// Display the Jon score chart.
$app->get('/jon', function(Request $request) use ($app) {
  // Check the session.
  $user = $app['session']->get('user');
  if (empty($user)) {
    return $app->redirect('/');
  }

  // Build the form.
  $params = $request->query->all();
  $params += $app['strava']->get_begin_and_end_dates();
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
    $chart->xAxis->categories[] = $app['strava']->convert_date_format($point['start_date_local']);
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
});

return $app;
