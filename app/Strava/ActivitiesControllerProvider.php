<?php

namespace Strava;

use Doctrine\DBAL\Connection;
use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Activities controller.
 */
class ActivitiesControllerProvider implements ControllerProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function connect(Application $app) {
    $activities = $app['controllers_factory'];

    // My activities.
    $activities->get('/activities', function (Request $request) use ($app) {
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

    return $activities;
  }

}
