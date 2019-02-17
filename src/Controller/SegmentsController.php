<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Segments controller.
 */
class SegmentsController extends AbstractController {

  /**
   * {@inheritdoc}
   */
  public function connect() {
    $segments = $app['controllers_factory'];

    // My segments.
    $segments->get('/segments', function (Request $request) use ($app) {
      // Check the session.
      $user = $app['session']->get('user');
      if (empty($user)) {
        return $this->redirectToRoute('/');
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

    return $segments;
  }

}
