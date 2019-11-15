<?php

namespace App\Strava;

use Doctrine\DBAL\Connection;
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Jon class.
 */
class Jon extends Base {

  /**
   * Constructor.
   */
  public function __construct(RequestStack $request_stack, Connection $connection, FormFactoryInterface $form_factory, Strava $strava, SessionInterface $session) {
    $this->requestStack = $request_stack;
    $this->connection = $connection;
    $this->formFactory = $form_factory;
    $this->strava = $strava;
    $this->session = $session;
  }

  /**
   * Build the form.
   */
  private function buildForm() {
    $this->user = $this->session->get('user');
    $this->request = $this->requestStack->getCurrentRequest();
    $this->params = $this->request->query->get('form') ?? [];
    $this->params += $this->strava->getBeginAndEndDates();
    if (is_string($this->params['begin_date'])) {
      $this->params['begin_date'] = new \DateTime($this->params['begin_date']);
    }
    if (is_string($this->params['end_date'])) {
      $this->params['end_date'] = new \DateTime($this->params['end_date']);
    }
    $form = $this->formFactory->createBuilder(FormType::class, $this->params)
      ->add('begin_date', DateType::class, [
        'input' => 'datetime',
        'widget' => 'single_text',
      ])
      ->add('end_date', DateType::class, [
        'input' => 'datetime',
        'widget' => 'single_text',
      ]);
    $this->form = $form->getForm();
  }

  /**
   * Query activities.
   */
  private function query() {
    $sql = 'SELECT name, id, start_date_local, average_heartrate, distance, total_elevation_gain, average_speed ';
    $sql .= 'FROM activities ';
    $sql .= 'WHERE type = ? AND average_heartrate > 0 AND athlete_id = ? AND start_date_local BETWEEN ? AND ? ';
    $sql .= 'ORDER BY start_date_local';
    $this->datapoints = $this->connection->executeQuery($sql, [
      'Run',
      $this->user['id'],
      $this->params['begin_date']->format('Y-m-d'),
      $this->params['end_date']->format('Y-m-d'),
    ],
    [
      \PDO::PARAM_STR,
      \PDO::PARAM_INT,
      \PDO::PARAM_STR,
      \PDO::PARAM_STR,
    ]);
  }

  /**
   * Build the chart.
   */
  private function buildChart() {
    $this->chart = new Highchart();
    $this->chart->chart = [
      'renderTo' => 'chart1',
      'type' => 'line',
    ];
    $this->chart->title = ['text' => 'Jon Score'];
    $this->chart->xAxis->categories = [];
    $this->chart->yAxis = [
      'title' => 'Score',
      'plotLines' => [
        [
          'value' => 0,
          'width' => 1,
          'color' => '#fc4c02',
        ],
      ],
    ];
    $this->chart->legend = ['enabled' => FALSE];
    $this->chart->series[0] = [
      'name' => 'Jon Score',
      'data' => [],
    ];
    $this->chart->series[0]['point'] = [
      'events' => [
        'click' => new HighchartJsExpr('function(e) { location.href = e.point.url; }'),
      ],
    ];
    $this->chart->plotOptions->line->color = '#fc4c02';
    $this->chart->tooltip->formatter = new HighchartJsExpr("function(e) { return '<b>' + this.key + '</b><br/>' + this.x + ': ' + this.y; }");

    // Add the data points to the chart.
    foreach ($this->datapoints as $point) {
      $hr = round($point['average_heartrate']);
      $miles = round($point['distance'] * DISTANCE_TO_MILES, 1);
      $gain = round($point['total_elevation_gain'] * GAIN_TO_FEET);
      $pace = round(60 / ($point['average_speed'] * 2.23694), 2);
      $this->chart->xAxis->categories[] = $this->strava->convertDateFormat($point['start_date_local']);
      $this->chart->series[0]['data'][] = [
        'name' => $point['name'],
        'y' => round($miles / 2 + (($gain / $miles) / 10) + (120 / ($pace - 5) + ((185 - $hr) * 1.05))),
        'url' => 'http://www.strava.com/activities/' . $point['id'],
      ];
    }
  }

  /**
   * Render the activities.
   *
   * @return array
   *   Return an array of render data.
   */
  public function render() {
    $this->buildForm();
    $this->query();
    $this->buildChart();

    // Render the page.
    return [
      'form' => $this->form->createView(),
      'chart' => $this->chart->render('chart1'),
      'scripts' => $this->chart->printScripts(TRUE),
    ];
  }

}
