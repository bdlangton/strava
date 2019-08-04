<?php

namespace App\Controller;

use App\Strava\Strava;
use Doctrine\DBAL\Connection;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Activities controller.
 */
class ActivitiesController extends AbstractController {

  /**
   * @Route("/activities", name="activities")
   */
  public function activities(SessionInterface $session, RequestStack $requestStack, Strava $strava, FormFactoryInterface $formFactory, Connection $connection) {
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
      'format' => $user['format'] ?: 'imperial',
      'name' => '',
      'workout' => $strava->getRunWorkouts(),
      'sort' => NULL,
    ];
    $form = $formFactory->createBuilder(FormType::class, $params)
      ->add('type', ChoiceType::class, [
        'choices' => $strava->getActivityTypes(),
        'label' => FALSE,
      ])
      ->add('format', ChoiceType::class, [
        'choices' => $strava->getFormats(),
        'label' => FALSE,
      ])
      ->add('name', TextType::class, [
        'label' => FALSE,
        'required' => FALSE,
      ]);
    if ($params['type'] == 'Run') {
      $form = $form->add('workout', ChoiceType::class, [
        'choices' => $strava->getRunWorkouts(),
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
      $query_types[] = \PDO::PARAM_STR;
    }
    if ($params['type'] == 'Run') {
      $sql .= 'AND workout_type IN (?) ';
      $query_params[] = $params['workout'];
      $query_types[] = Connection::PARAM_INT_ARRAY;
    }
    $sql .= $sort;
    $datapoints = $connection->fetchAll($sql, $query_params, $query_types);

    $activities = [];
    foreach ($datapoints as $point) {
      $point['distance'] = $strava->convertDistance($point['distance'], $params['format']);
      $point['date'] = $strava->convertDateFormat($point['start_date_local']);
      $point['elapsed_time'] = $strava->convertTimeFormat($point['elapsed_time']);
      $point['total_elevation_gain'] = $strava->convertElevationGain($point['total_elevation_gain'], $params['format']);
      $activities[] = $point;
    }

    // Set up pagination.
    $page = $request->query->get('page') ?? 1;
    $adapter = new ArrayAdapter($activities);
    $pagerfanta = new Pagerfanta($adapter);
    $pagerfanta->setMaxPerPage(20);
    $pagerfanta->setCurrentPage($page);
    $activities = $pagerfanta->getCurrentPageResults();

    // Render the page.
    return $this->render('activities.twig', [
      'form' => $form->createView(),
      'activities' => $activities,
      'type' => $params['type'],
      'format' => ($params['format'] == 'imperial') ? 'mi' : 'km',
      'gain_format' => ($params['format'] == 'imperial') ? 'ft' : 'm',
      'pager' => $pagerfanta,
      'current_params_minus_sort' => $strava->getCurrentParams(['sort']),
    ]);
  }

}
