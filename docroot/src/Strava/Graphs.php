<?php

namespace App\Strava;

use App\Constraints\AfterBeginDate;
use Doctrine\DBAL\Connection;
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints\Date;

/**
 * Graphs class.
 */
class Graphs extends Base {

  /**
   * Build the form.
   */
  private function buildForm() {
    $this->params = $this->request->query->get('form') ?? [];
    $this->params += [
      'type' => $this->user['activity_type'] ?: 'All',
      'format' => $this->user['format'] ?: 'imperial',
      'group' => 'month',
      'workout' => $this->strava->getRunWorkouts(),
    ];
    $this->params += $this->strava->getBeginAndEndDates($this->params['group']);

    $begin_date = $end_date = '';
    if (is_string($this->params['begin_date'])) {
      $begin_date = $this->params['begin_date'];
      $this->params['begin_date'] = new \DateTime($this->params['begin_date']);
    }
    if (is_string($this->params['end_date'])) {
      $end_date = $this->params['end_date'];
      $this->params['end_date'] = new \DateTime($this->params['end_date']);
    }
    $form = $this->formFactory->createBuilder(FormType::class, $this->params)
      ->add('type', ChoiceType::class, [
        'choices' => $this->strava->getActivityTypes(),
        'label' => FALSE,
      ])
      ->add('group', ChoiceType::class, [
        'choices' => $this->strava->getGroups(),
        'label' => FALSE,
      ])
      ->add('format', ChoiceType::class, [
        'choices' => $this->strava->getFormats(),
        'label' => FALSE,
      ])
      ->add('begin_date', DateType::class, [
        'input' => 'datetime',
        'widget' => 'single_text',
        'constraints' => new Date(),
      ])
      ->add('end_date', DateType::class, [
        'input' => 'datetime',
        'widget' => 'single_text',
        'constraints' => [
          new AfterBeginDate(['value' => $begin_date]),
          new Date(),
        ],
      ]);

    if ($this->params['type'] == 'Run') {
      $form = $form->add('workout', ChoiceType::class, [
        'choices' => $this->strava->getRunWorkouts(),
        'expanded' => TRUE,
        'multiple' => TRUE,
        'label' => FALSE,
      ]);
    }

    $this->form = $form->getForm();
    $this->form->submit($this->request->query->get('form'));
  }

  /**
   * Query all of the data needed.
   */
  private function query() {
    if ($this->params['group'] == 'month') {
      $group = 'CONCAT(MONTHNAME(start_date_local), " ", YEAR(start_date_local))';
      $order_by_group = 'DATE_FORMAT(start_date_local, "%Y%m")';
    }
    elseif ($this->params['group'] == 'week') {
      $group = 'CONCAT("Week ", WEEK(start_date_local), " ", YEAR(start_date_local))';
      $order_by_group = 'CONCAT("Week ", YEARWEEK(start_date_local))';
    }
    else {
      $group = $order_by_group = 'YEAR(start_date_local)';
    }

    // Query params and types.
    $query_params = [
      $this->user['id'],
      $this->params['begin_date']->format('Y-m-d'),
      $this->params['end_date']->format('Y-m-d'),
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
    if ($this->params['type'] != 'All') {
      $sql .= 'AND type = ? ';
      $query_params[] = $this->params['type'];
      $query_types[] = \PDO::PARAM_STR;
    }
    if ($this->params['type'] == 'Run') {
      $sql .= 'AND workout_type IN (?) ';
      $query_params[] = $this->params['workout'];
      $query_types[] = Connection::PARAM_INT_ARRAY;
    }
    $sql .= 'GROUP BY ' . $group . ', ' . $order_by_group . ' ';
    $sql .= 'ORDER BY ' . $order_by_group;
    $this->datapoints = $this->connection->executeQuery($sql, $query_params, $query_types);
  }

  /**
   * Create charts to display.
   */
  private function createCharts() {
    $this->createDistanceChart();
    $this->createElevationGainChart();
    $this->createElevationGainPerDistanceChart();
    $this->createMovingTimeChart();
  }

  /**
   * Create distance chart.
   */
  private function createDistanceChart() {
    // Build the chart.
    $this->chart = new Highchart();
    $this->chart->chart = [
      'renderTo' => 'chart1',
      'type' => 'line',
      'zoomType' => 'x',
    ];
    $this->chart->title = ['text' => 'Distance'];
    $this->chart->xAxis->categories = [];
    $this->chart->yAxis = [
      'title' => [
        'text' => ($this->params['format'] == 'imperial' ? 'Miles' : 'Kilometers'),
      ],
      'plotLines' => [
        [
          'value' => 0,
          'width' => 1,
          'color' => '#fc4c02',
        ],
      ],
    ];
    $this->chart->legend = ['enabled' => FALSE];
    $this->chart->plotOptions->area->color = '#fc4c02';
    $this->chart->plotOptions->area->fillColor->linearGradient = [0, 0, 0, 300];
    $this->chart->plotOptions->area->fillColor->stops = [
      [0, '#fc4c02'],
      [1, '#fff'],
    ];
    $this->chart->plotOptions->area->marker->enabled = FALSE;
    $this->chart->plotOptions->area->lineWidth = 1;
    $this->chart->plotOptions->area->dataLabels->enabled = ($this->datapoints->rowCount() <= 50 ? TRUE : FALSE);
    $this->chart->plotOptions->area->states->hover->lineWidth = 1;
    $this->chart->tooltip->formatter = new HighchartJsExpr("function() { return '<b>' + this.series.name + '</b><br/>' + this.x + ': ' + this.y; }");
    $this->chart->series[] = [
      'type' => 'area',
      'name' => 'Distance per ' . $this->params['group'],
      'data' => [],
    ];
  }

  /**
   * Create elevation gain chart.
   */
  private function createElevationGainChart() {
    // Create elevation gain chart.
    $this->chart2 = clone $this->chart;
    $this->chart2->chart['renderTo'] = 'chart2';
    $this->chart2->title['text'] = 'Elevation Gain';
    $this->chart2->yAxis['title']['text'] = ($this->params['format'] == 'imperial' ? 'Feet' : 'Meters');
    $this->chart2->series = [
      [
        'type' => 'area',
        'name' => 'Elevation Gain per ' . $this->params['group'],
        'data' => [],
      ],
    ];
  }

  /**
   * Create elevation gain per distance chart.
   */
  private function createElevationGainPerDistanceChart() {
    // Create elevation gain per distance chart.
    $this->chart3 = clone $this->chart;
    $this->chart3->chart['renderTo'] = 'chart3';
    $this->chart3->title['text'] = 'Elevation Gain / ' . ($this->params['format'] == 'imperial' ? 'Mile' : 'Kilometer');
    $this->chart3->yAxis['title']['text'] = ($this->params['format'] == 'imperial' ? 'Feet' : 'Meters');
    $this->chart3->series = [
      [
        'type' => 'area',
        'name' => 'Elevation Gain per ' . ($this->params['format'] == 'imperial' ? 'Mile' : 'Kilometer'),
        'data' => [],
      ],
    ];
  }

  /**
   * Create moving time chart.
   */
  private function createMovingTimeChart() {
    // Create time chart.
    $this->chart4 = clone $this->chart;
    $this->chart4->chart['renderTo'] = 'chart4';
    $this->chart4->title['text'] = 'Moving Time';
    $this->chart4->yAxis['title']['text'] = 'Hours';
    $this->chart4->series = [
      [
        'type' => 'area',
        'name' => 'Moving Time spent per ' . $this->params['group'],
        'data' => [],
      ],
    ];
  }

  /**
   * Render the graph data.
   *
   * @return array
   *   Return an array of render data.
   */
  public function render() {
    $this->buildForm();
    $this->query();
    $this->createCharts();

    // Add the data points to the charts.
    foreach ($this->datapoints as $point) {
      $point['distance'] = $this->strava->convertDistance($point['distance'], $this->params['format'], FALSE);
      $point['elevation_gain'] = $this->strava->convertElevationGain($point['elevation_gain'], $this->params['format'], FALSE);
      $this->chart->xAxis->categories[] = $point['grp'];
      $this->chart->series[0]['data'][] = $point['distance'];
      $this->chart2->xAxis->categories[] = $point['grp'];
      $this->chart2->series[0]['data'][] = (int) $point['elevation_gain'];
      $this->chart3->xAxis->categories[] = $point['grp'];
      $this->chart3->series[0]['data'][] = (!empty($point['distance']) ? round($point['elevation_gain'] / $point['distance']) : 0);
      $this->chart4->xAxis->categories[] = $point['grp'];
      $this->chart4->series[0]['data'][] = round($point['moving_time'] / 3600, 1);
    }

    return [
      'chart' => $this->chart->render('chart1'),
      'chart2' => $this->chart2->render('chart2'),
      'chart3' => $this->chart3->render('chart3'),
      'chart4' => $this->chart4->render('chart4'),
      'scripts' => $this->chart->printScripts(TRUE),
      'form' => $this->form->createView(),
    ];
  }

}
