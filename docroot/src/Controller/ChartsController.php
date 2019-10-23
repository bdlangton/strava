<?php

namespace App\Controller;

use App\Strava\Strava;
use Doctrine\DBAL\Connection;
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Column charts controller.
 */
class ChartsController extends AbstractController {

  /**
   * @Route("/column", name="column")
   */
  public function column(SessionInterface $session, RequestStack $requestStack, Strava $strava, FormFactoryInterface $formFactory, Connection $connection) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Build the form.
    $request = $requestStack->getCurrentRequest();
    $params = $request->query->get('form') ?? [];
    $params += [
      'group' => 'month',
      'format' => $user['format'],
    ];
    $params += $strava->getBeginAndEndDates($params['group']);
    if (is_string($params['begin_date'])) {
      $params['begin_date'] = new \DateTime($params['begin_date']);
    }
    if (is_string($params['end_date'])) {
      $params['end_date'] = new \DateTime($params['end_date']);
    }
    $form = $formFactory->createBuilder(FormType::class, $params)
      ->add('group', ChoiceType::class, [
        'choices' => $strava->getGroups(),
        'label' => FALSE,
      ])
      ->add('format', ChoiceType::class, [
        'choices' => $strava->getFormats(),
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
    $running_xaxis = $connection->executeQuery($sql, [
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
    $workout_data = $connection->executeQuery($sql, [
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
    $treadmill_data = $connection->executeQuery($sql, [
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
    $cycling_xaxis = $connection->executeQuery($sql, [
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
    $cycling_data = $connection->executeQuery($sql, [
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
      $point['distance'] = $strava->convertDistance($point['distance'], $params['format'], FALSE);
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
      $point['distance'] = $strava->convertDistance($point['distance'], $params['format'], FALSE);
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
      $point['distance'] = $strava->convertDistance($point['distance'], $params['format'], FALSE);
      $series[$ride_type]['data'][] = $point['distance'];
    }
    $cycling_chart->series = $series;

    // Render the chart.
    return $this->render('column.twig', [
      'running_chart' => $running_chart->render('running_chart'),
      'treadmill_chart' => $treadmill_chart->render('treadmill_chart'),
      'cycling_chart' => $cycling_chart->render('cycling_chart'),
      'scripts' => $running_chart->printScripts(TRUE),
      'form' => $form->createView(),
    ]);
  }

}
