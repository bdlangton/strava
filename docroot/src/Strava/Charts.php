<?php

namespace App\Strava;

use App\Constraints\AfterBeginDate;
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints\Date;

/**
 * Charts class.
 */
class Charts extends Base {

  /**
   * Build the form.
   */
  private function buildForm() {
    $this->params = $this->request->query->get('form') ?? [];
    $this->params += [
      'group' => 'month',
      'format' => $this->user['format'],
    ];
    $this->params += $this->strava->getBeginAndEndDates($this->params['group']);

    // If begin and end date are blank, assign them values within this year.
    if (empty($this->params['begin_date'])) {
      $this->params['begin_date'] = (new \DateTime('now - 1 year'))->format('Y-m-d');
    }
    if (empty($this->params['end_date'])) {
      $this->params['end_date'] = (new \DateTime('now'))->format('Y-m-d');
    }

    $form = $this->formFactory->createBuilder(FormType::class, $this->params)
      ->add('group', ChoiceType::class, [
        'choices' => $this->strava->getGroups(),
        'label' => FALSE,
      ])
      ->add('format', ChoiceType::class, [
        'choices' => $this->strava->getFormats(),
        'label' => FALSE,
      ])
      ->add('begin_date', DateType::class, [
        'input' => 'string',
        'widget' => 'single_text',
        'constraints' => new Date(),
        'required' => FALSE,
      ])
      ->add('end_date', DateType::class, [
        'input' => 'string',
        'widget' => 'single_text',
        'constraints' => [
          new AfterBeginDate(['value' => $this->params['begin_date']]),
          new Date(),
        ],
        'required' => FALSE,
      ]);

    $this->form = $form->getForm();
    $this->form->submit($this->request->query->get('form'));
  }

  /**
   * Query all of the data needed.
   */
  private function query() {
    if ($this->params['group'] == 'month') {
      $this->group = 'CONCAT(MONTHNAME(start_date_local), " ", YEAR(start_date_local))';
      $this->order_by_group = 'DATE_FORMAT(start_date_local, "%Y%m")';
    }
    elseif ($this->params['group'] == 'week') {
      $this->group = 'CONCAT("Week ", WEEK(start_date_local), " ", YEAR(start_date_local))';
      $this->order_by_group = 'CONCAT("Week ", YEARWEEK(start_date_local))';
    }
    else {
      $this->group = $this->order_by_group = 'YEAR(start_date_local)';
    }

    $this->queryRunningXAxis();
    $this->queryCyclingData();
    $this->queryWorkoutData();
    $this->queryCyclingXAxis();
    $this->queryTreadmillData();
  }

  /**
   * Query the running x-axis data.
   */
  private function queryRunningXAxis() {
    // Query for the x-axis points that will be used for running charts.
    $sql = 'SELECT ' . $this->group . ' grp ';
    $sql .= 'FROM activities ';
    $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
    $sql .= "AND $this->group IS NOT NULL ";
    $sql .= 'GROUP BY ' . $this->group . ', ' . $this->order_by_group . ' ';
    $sql .= 'ORDER BY ' . $this->order_by_group;
    $this->running_xaxis = $this->connection->executeQuery($sql, [
      'Run',
      $this->user['id'],
      $this->params['begin_date'],
      $this->params['end_date'],
    ])->fetchAll();
  }

  /**
   * Query the workout data.
   */
  private function queryWorkoutData() {
    // Build the query for running workout type.
    $sql = 'SELECT ' . $this->group . ' grp, workout_type, SUM(distance) distance, SUM(total_elevation_gain) elevation_gain, ';
    $sql .= 'SUM(elapsed_time) elapsed_time, SUM(moving_time) moving_time ';
    $sql .= 'FROM activities ';
    $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
    $sql .= "AND $this->group IS NOT NULL ";
    $sql .= 'GROUP BY ' . $this->group . ', ' . $this->order_by_group . ', workout_type ';
    $sql .= 'ORDER BY ' . $this->order_by_group;
    $this->workout_data = $this->connection->executeQuery($sql, [
      'Run',
      $this->user['id'],
      $this->params['begin_date'],
      $this->params['end_date'],
    ])->fetchAll();
  }

  /**
   * Query the treadmill data.
   */
  private function queryTreadmillData() {
    // Build the query for running treadmill chart.
    $sql = "SELECT $this->group grp, IFNULL(trainer, 0) trainer, SUM(distance) distance, SUM(total_elevation_gain) elevation_gain, ";
    $sql .= 'SUM(elapsed_time) elapsed_time, SUM(moving_time) moving_time ';
    $sql .= 'FROM activities ';
    $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
    $sql .= "AND $this->group IS NOT NULL ";
    $sql .= 'GROUP BY ' . $this->group . ', ' . $this->order_by_group . ', IFNULL(trainer, 0) ';
    $sql .= 'ORDER BY ' . $this->order_by_group;
    $this->treadmill_data = $this->connection->executeQuery($sql, [
      'Run',
      $this->user['id'],
      $this->params['begin_date'],
      $this->params['end_date'],
    ])->fetchAll();
  }

  /**
   * Query cycling x-axis data.
   */
  private function queryCyclingXAxis() {
    // Query for the x-axis points that will be used for cycling chart.
    $sql = 'SELECT ' . $this->group . ' grp ';
    $sql .= 'FROM activities ';
    $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
    $sql .= "AND $this->group IS NOT NULL ";
    $sql .= 'GROUP BY ' . $this->group . ', ' . $this->order_by_group . ' ';
    $sql .= 'ORDER BY ' . $this->order_by_group;
    $this->cycling_xaxis = $this->connection->executeQuery($sql, [
      'Ride',
      $this->user['id'],
      $this->params['begin_date'],
      $this->params['end_date'],
    ])->fetchAll();
  }

  /**
   * Query cycling data.
   */
  private function queryCyclingData() {
    // Build the query for cycling workout type.
    $sql = 'SELECT ' . $this->group . ' grp, CONCAT(trainer, commute) ride_type, SUM(distance) distance, SUM(total_elevation_gain) elevation_gain, ';
    $sql .= 'SUM(elapsed_time) elapsed_time, SUM(moving_time) moving_time ';
    $sql .= 'FROM activities ';
    $sql .= 'WHERE type = ? AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
    $sql .= "AND $this->group IS NOT NULL ";
    $sql .= 'GROUP BY ' . $this->group . ', ' . $this->order_by_group . ', CONCAT(trainer, commute) ';
    $sql .= 'ORDER BY ' . $this->order_by_group;
    $this->cycling_data = $this->connection->executeQuery($sql, [
      'Ride',
      $this->user['id'],
      $this->params['begin_date'],
      $this->params['end_date'],
    ])->fetchAll();
  }

  /**
   * Create charts to display.
   */
  private function createCharts() {
    $this->createRunningChart();
    $this->createTreadmillChart();
    $this->createCyclingChart();
  }

  /**
   * Create running chart.
   */
  private function createRunningChart() {
    // Build the stacked column chart.
    $this->runningChart = new Highchart();
    $this->runningChart->chart = [
      'renderTo' => 'running_chart',
      'type' => 'column',
    ];
    $this->runningChart->title = ['text' => 'Running Distribution'];
    $this->runningChart->yAxis = [
      'min' => 0,
      'title' => ['text' => ($this->params['format'] == 'imperial' ? 'Miles' : 'Meters')],
      'stackLabels' => [
        'enabled' => FALSE,
      ],
    ];
    $this->runningChart->legend = ['enabled' => TRUE];
    $this->runningChart->plotOptions = [
      'column' => [
        'stacking' => 'normal',
        'dataLabels' => [
          'enabled' => TRUE,
        ],
      ],
    ];
    $this->runningChart->tooltip->formatter = new HighchartJsExpr("function(e) { return '<b>' + this.series.name + '</b><br/>' + this.x + ': ' + this.y; }");
    $this->runningChart->xAxis->categories = $xaxis = $this->getRunningXAxisLabels();

    // Add the workout data to the chart.
    $series = [
      '0' => ['name' => 'Regular Run', 'data' => []],
      '1' => ['name' => 'Race', 'data' => []],
      '2' => ['name' => 'Long Run', 'data' => []],
      '3' => ['name' => 'Workout', 'data' => []],
    ];
    foreach ($this->workout_data as $point) {
      $index = array_search($point['grp'], $xaxis);
      while ($index > count($series[$point['workout_type']]['data'])) {
        $series[$point['workout_type']]['data'][] = 0;
      }
      $point['distance'] = $this->strava->convertDistance($point['distance'], $this->params['format'], FALSE);
      $series[$point['workout_type']]['data'][] = $point['distance'];
    }
    $this->runningChart->series = $series;
  }

  /**
   * Create treadmill chart.
   */
  private function createTreadmillChart() {
    // Clone to the treadmill chart.
    $this->treadmillChart = clone $this->runningChart;
    $this->treadmillChart->chart['renderTo'] = 'treadmill_chart';
    $this->treadmillChart->title = ['text' => 'Running Outside vs Treadmill'];
    $this->treadmillChart->xAxis->categories = $xaxis = $this->getRunningXAxisLabels();

    // Add the treadmill data to the chart.
    $series = [
      '0' => ['name' => 'Outside', 'data' => []],
      '1' => ['name' => 'Treadmill', 'data' => []],
    ];
    foreach ($this->treadmill_data as $point) {
      $index = array_search($point['grp'], $xaxis);
      while ($index > count($series[$point['trainer']]['data'])) {
        $series[$point['trainer']]['data'][] = 0;
      }
      $point['distance'] = $this->strava->convertDistance($point['distance'], $this->params['format'], FALSE);
      $series[$point['trainer']]['data'][] = $point['distance'];
    }
    $this->treadmillChart->series = $series;
  }

  /**
   * Create cycling chart.
   */
  private function createCyclingChart() {
    // Clone to the cycling chart.
    $this->cyclingChart = clone $this->runningChart;
    $this->cyclingChart->chart['renderTo'] = 'cycling_chart';
    $this->cyclingChart->title = ['text' => 'Cycling Distribution'];
    $this->cyclingChart->xAxis->categories = $xaxis = $this->getCyclingXAxisLabels();

    // Add the cycling data to the chart.
    $series = [
      '0' => ['name' => 'Regular Ride', 'data' => []],
      '1' => ['name' => 'Commute', 'data' => []],
      '2' => ['name' => 'Stationary', 'data' => []],
    ];
    foreach ($this->cycling_data as $point) {
      $index = array_search($point['grp'], $xaxis);
      $ride_type = $point['ride_type'] ? bindec($point['ride_type']) : 0;
      while ($index > count($series[$ride_type]['data'])) {
        $series[$ride_type]['data'][] = 0;
      }
      $point['distance'] = $this->strava->convertDistance($point['distance'], $this->params['format'], FALSE);
      $series[$ride_type]['data'][] = $point['distance'];
    }
    $this->cyclingChart->series = $series;
  }

  /**
   * Get running x-axis labels.
   *
   * @return array
   *   Return an array of x-axis labels.
   */
  private function getRunningXAxisLabels() {
    $xaxis = [];
    foreach ($this->running_xaxis as $point) {
      $xaxis[] = $point['grp'];
    }
    return $xaxis;
  }

  /**
   * Get cycling x-axis labels.
   *
   * @return array
   *   Return an array of x-axis labels.
   */
  private function getCyclingXAxisLabels() {
    $xaxis = [];
    foreach ($this->cycling_xaxis as $point) {
      $xaxis[] = $point['grp'];
    }
    return $xaxis;
  }

  /**
   * Render the chart data.
   *
   * @return array
   *   Return an array of render data.
   */
  public function render() {
    $this->buildForm();
    $this->query();
    $this->createCharts();
    return [
      'running_chart' => $this->runningChart->render('running_chart'),
      'treadmill_chart' => $this->treadmillChart->render('treadmill_chart'),
      'cycling_chart' => $this->cyclingChart->render('cycling_chart'),
      'scripts' => $this->runningChart->printScripts(TRUE),
      'form' => $this->form->createView(),
    ];
  }

}
