<?php

namespace Strava;

use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;
use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Jon score controller.
 */
class JonControllerProvider implements ControllerProviderInterface
{

  /**
   * @{inheritdoc}
   */
  public function connect(Application $app)
  {
    $jon = $app['controllers_factory'];

    // Display the Jon score chart.
    $jon->get('/jon', function (Request $request) use ($app) {
      // Check the session.
      $user = $app['session']->get('user');
      if (empty($user)) {
        return $app->redirect('/');
      }

      // Build the form.
      $params = $request->query->all();
      $params += $app['strava']->getBeginAndEndDates();
      if (is_string($params['begin_date'])) {
        $params['begin_date'] = new \DateTime($params['begin_date']);
      }
      if (is_string($params['end_date'])) {
        $params['end_date'] = new \DateTime($params['end_date']);
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

    return $jon;
  }

}
