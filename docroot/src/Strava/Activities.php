<?php

namespace App\Strava;

use Doctrine\DBAL\Connection;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * List activities by type and workout type.
 */
class Activities extends Base {

  /**
   * Build the form.
   */
  private function buildForm() {
    $this->params = $this->request->query->get('form') ?? [];
    $this->params += [
      'type' => $this->user['activity_type'] ?: 'All',
      'format' => $this->user['format'] ?: 'imperial',
      'name' => '',
      'workout' => $this->strava->getRunWorkouts(),
      'sort' => $this->request->query->get('sort'),
    ];
    $form = $this->formFactory->createBuilder(FormType::class, $this->params)
      ->add('type', ChoiceType::class, [
        'choices' => $this->strava->getActivityTypes(),
        'label' => FALSE,
      ])
      ->add('format', ChoiceType::class, [
        'choices' => $this->strava->getFormats(),
        'label' => FALSE,
      ])
      ->add('name', TextType::class, [
        'label' => FALSE,
        'required' => FALSE,
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
  }

  /**
   * Query activities.
   */
  private function query() {
    // Determine the sort order.
    $sort = 'ORDER BY start_date_local DESC';
    if ($this->params['sort'] == 'gain') {
      $sort = 'ORDER BY total_elevation_gain DESC';
    }
    elseif ($this->params['sort'] == 'distance') {
      $sort = 'ORDER BY distance DESC';
    }

    // Query params and types.
    $query_params = [
      $this->user['id'],
      '%' . $this->params['name'] . '%',
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
    $sql .= $sort;
    $this->datapoints = $this->connection->fetchAll($sql, $query_params, $query_types);
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

    $activities = [];
    foreach ($this->datapoints as $point) {
      $point['distance'] = $this->strava->convertDistance($point['distance'], $this->params['format']);
      $point['date'] = $this->strava->convertDateFormat($point['start_date_local']);
      $point['elapsed_time'] = $this->strava->convertTimeFormat($point['elapsed_time']);
      $point['total_elevation_gain'] = $this->strava->convertElevationGain($point['total_elevation_gain'], $this->params['format']);

      // Add workout type to activity type if applicable.
      switch ($point['workout_type']) {
        case '1':
          $point['type'] .= ' - Race';
          break;

        case '2':
          $point['type'] .= ' - Long Run';
          break;

        case '3':
        case '12':
          $point['type'] .= ' - Workout';
          break;

        default:
      }

      if ($point['commute']) {
        $point['type'] .= ' - Commute';
      }

      if ($point['type'] == 'Run') {
        if ($point['trainer']) {
          $point['type'] .= ' - Treadmill';
        }
      }
      $activities[] = $point;
    }

    // Set up pagination.
    $page = $this->request->query->get('page') ?? 1;
    $adapter = new ArrayAdapter($activities);
    $pagerfanta = new Pagerfanta($adapter);
    $pagerfanta->setMaxPerPage(20);
    $pagerfanta->setCurrentPage($page);
    $activities = $pagerfanta->getCurrentPageResults();

    return [
      'form' => $this->form->createView(),
      'activities' => $activities,
      'type' => $this->params['type'],
      'format' => ($this->params['format'] == 'imperial') ? 'mi' : 'km',
      'gain_format' => ($this->params['format'] == 'imperial') ? 'ft' : 'm',
      'pager' => $pagerfanta,
      'current_params_minus_sort' => $this->strava->getCurrentParams(['sort']),
    ];
  }

}
