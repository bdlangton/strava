<?php

namespace Strava;

use Doctrine\DBAL\Connection;
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;
use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Graphs controller.
 */
class GraphsControllerProvider implements ControllerProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function connect(Application $app) {
    $graphs = $app['controllers_factory'];

    // General graphs.
    $graphs->get('/data', function (Request $request) use ($app) {
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
        $params['begin_date'] = new \DateTime($params['begin_date']);
      }
      if (is_string($params['end_date'])) {
        $params['end_date'] = new \DateTime($params['end_date']);
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
      $chart->plotOptions->area->dataLabels->enabled = ($datapoints->rowCount() <= 50 ? TRUE : FALSE);
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

    return $graphs;
  }

}
