<?php

namespace App\Controller;

use App\Strava\Strava;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * KOM and PR records controller.
 */
class RecordsController extends AbstractController {

  /**
   * @Route("/records", name="records")
   */
  public function records(SessionInterface $session, RequestStack $requestStack, Strava $strava, FormFactoryInterface $formFactory, Connection $connection) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('/');
    }

    // Build the form.
    $request = $requestStack->getCurrentRequest();
    $params = $request->query->get('form') ?? [];
    $params += [
      'type' => $user['activity_type'] ?: 'All',
      'format' => $user['format'],
      'record' => NULL,
      'begin_date' => new \DateTime('now - 1 year'),
      'end_date' => new \DateTime('now'),
      'sort' => NULL,
    ];
    if (is_string($params['begin_date'])) {
      $params['begin_date'] = new \DateTime($params['begin_date']);
    }
    if (is_string($params['end_date'])) {
      $params['end_date'] = new \DateTime($params['end_date']);
    }
    $form = $formFactory->createBuilder(FormType::class, $params)
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
    $datapoints = $connection->executeQuery($sql, $query_params, $query_types);

    // Add the segments to the array.
    $efforts = [];
    foreach ($datapoints as $point) {
      $point['distance'] = $strava->convertDistance($point['distance'], $params['format']);
      $point['time'] = $strava->convertTimeFormat($point['time']);
      $point['date'] = $strava->convertDateFormat($point['date']);
      $point['pr_rank'] = !empty($point['pr_rank']) ? 'Yes' : 'No';
      $efforts[] = $point;
    }

    // Render the page.
    return $this->render('records.twig', [
      'form' => $form->createView(),
      'efforts' => $efforts,
      'format' => ($params['format'] == 'imperial') ? 'mi' : 'km',
      'type' => $params['type'],
      'current_params_minus_page' => $strava->getCurrentParams(['page']),
      'current_params_minus_sort' => $strava->getCurrentParams(['sort']),
    ]);
  }

}
