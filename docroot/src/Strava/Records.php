<?php

namespace App\Strava;

use App\Constraints\AfterBeginDate;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints\Date;

/**
 * Records class to show KOMs and PRs.
 */
class Records extends Base {

  /**
   * Build the form.
   */
  private function buildForm() {
    $this->params = $this->request->query->get('form') ?? [];
    $this->params += [
      'type' => 'All',
      'format' => $this->user['format'],
      'record' => NULL,
      'sort' => $this->request->query->get('sort'),
    ];

    // If begin and end date are blank, assign them values within the last 20
    // years. We want to have a non-null value, but include everything by
    // default.
    if (empty($this->params['begin_date'])) {
      $this->params['begin_date'] = (new \DateTime('now - 20 years'))->format('Y-m-d');
    }
    if (empty($this->params['end_date'])) {
      $this->params['end_date'] = (new \DateTime('now'))->format('Y-m-d');
    }

    $form = $this->formFactory->createBuilder(FormType::class, $this->params)
      ->add('type', ChoiceType::class, [
        'choices' => $this->strava->getActivityTypes(),
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
  }

  /**
   * Query activities.
   */
  private function query() {
    // Determine what records the user wants to see.
    $record_query = 'se.pr_rank = 1 OR se.kom_rank IS NOT NULL';
    if ($this->params['record'] == 'PR') {
      $record_query = 'se.pr_rank = 1';
    }
    elseif ($this->params['record'] == 'CR') {
      $record_query = 'se.kom_rank = 1';
    }
    elseif ($this->params['record'] == 'Top10') {
      $record_query = 'se.kom_rank IS NOT NULL';
    }

    // Determine the sort order.
    switch ($this->params['sort']) {
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
    if (!empty($this->params['begin_date']) && !empty($this->params['end_date'])) {
      $date_sql = ' AND a.start_date_local BETWEEN ? AND ? ';
      $query_params = [
        $this->user['id'],
        $this->params['begin_date'],
        $this->params['end_date'],
      ];
      $query_types = [
        \PDO::PARAM_INT,
        \PDO::PARAM_STR,
        \PDO::PARAM_STR,
      ];
    }
    else {
      $date_sql = '';
      $query_params = [$this->user['id']];
      $query_types = [\PDO::PARAM_INT];
    }

    // Build the query.
    $sql = 'SELECT s.name, se.id effort_id, se.segment_id, se.activity_id, ';
    $sql .= 's.distance, se.elapsed_time time, s.average_grade, ';
    $sql .= 's.maximum_grade, se.pr_rank, se.kom_rank, s.city, s.state, ';
    $sql .= 'a.start_date_local date, a.type ';
    $sql .= 'FROM activities a ';
    $sql .= 'JOIN segment_efforts se ON (a.athlete_id = se.athlete_id AND a.id = se.activity_id) ';
    $sql .= 'JOIN segments s ON (s.id = se.segment_id) ';
    $sql .= 'WHERE (' . $record_query . ') AND a.athlete_id = ? ' . $date_sql;
    if ($this->params['type'] != 'All') {
      $sql .= 'AND a.type = ? ';
      $query_params[] = $this->params['type'];
      $query_types[] = \PDO::PARAM_STR;
    }
    $sql .= $sort;
    $this->datapoints = $this->connection->executeQuery($sql, $query_params, $query_types);
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

    // Add the segments to the array.
    $efforts = [];
    foreach ($this->datapoints as $point) {
      $point['type'] = $this->strava->convertActivityType($point);
      $point['distance'] = $this->strava->convertDistance($point['distance'], $this->params['format']);
      $point['time'] = $this->strava->convertTimeFormat($point['time']);
      $point['date'] = $this->strava->convertDateFormat($point['date']);
      $point['pr_rank'] = !empty($point['pr_rank']) ? 'Yes' : 'No';
      $efforts[] = $point;
    }

    // Set up pagination.
    $page = $this->request->query->get('page') ?? 1;
    $adapter = new ArrayAdapter($efforts);
    $pagerfanta = new Pagerfanta($adapter);
    $pagerfanta->setMaxPerPage(20);
    $pagerfanta->setCurrentPage($page);
    $efforts = $pagerfanta->getCurrentPageResults();

    // Render the page.
    return [
      'form' => $this->form->createView(),
      'efforts' => $efforts,
      'format' => ($this->params['format'] == 'imperial') ? 'mi' : 'km',
      'type' => $this->params['type'],
      'pager' => $pagerfanta,
      'current_params_minus_sort' => $this->strava->getCurrentParams(['sort']),
    ];
  }

}
