<?php

namespace App\Strava;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Show information about segments.
 */
class Segments extends Base {

  /**
   * Build the form.
   */
  private function buildForm() {
    $this->params = $this->request->query->get('form') ?? [];
    $this->params += [
      'type' => 'All',
      'name' => '',
      'format' => $this->user['format'] ?: 'imperial',
      'sort' => $this->request->query->get('sort'),
    ];
    $form = $this->formFactory->createBuilder(FormType::class, $this->params)
      ->add('type', ChoiceType::class, [
        'choices' => $this->strava->getActivityTypes(),
        'label' => FALSE,
      ])
      ->add('name', TextType::class, [
        'label' => FALSE,
        'required' => FALSE,
      ]);
    $this->form = $form->getForm();
  }

  /**
   * Query segments.
   */
  private function query() {
    // Sort.
    switch ($this->params['sort']) {
      case 'segment':
        $sort = 'ORDER BY s.name';
        break;

      case 'location':
        $sort = 'ORDER BY s.city DESC';
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
      $this->user['id'],
      '%' . $this->params['name'] . '%',
    ];
    $query_types = [
      \PDO::PARAM_STR,
      \PDO::PARAM_STR,
    ];

    // Build the query.
    $sql = 'SELECT s.id, s.name, s.activity_type, ss.starred_date, ';
    $sql .= 's.distance, CONCAT(s.city, ", ", s.state) location ';
    $sql .= 'FROM starred_segments ss ';
    $sql .= 'JOIN segments s ON (ss.segment_id = s.id) ';
    $sql .= 'WHERE ss.athlete_id = ? ';
    $sql .= 'AND s.name LIKE ? ';
    if ($this->params['type'] != 'All') {
      $sql .= 'AND s.activity_type = ? ';
      $query_params[] = $this->params['type'];
      $query_types[] = \PDO::PARAM_STR;
    }
    $sql .= $sort;
    $this->datapoints = $this->connection->executeQuery($sql, $query_params, $query_types);
  }

  /**
   * Render the segments.
   *
   * @return array
   *   Return an array of render data.
   */
  public function render() {
    $this->buildForm();
    $this->query();

    // Get the segments.
    $segments = [];
    foreach ($this->datapoints as $point) {
      $point['distance'] = $this->strava->convertDistance($point['distance'], $this->user['format']);
      $point['starred_date'] = $this->strava->convertDateFormat($point['starred_date']);
      $point['activity_type'] = $this->strava->convertActivityType($point, $point['activity_type']);
      $segments[] = $point;
    }

    // Set up pagination.
    $page = $this->request->query->get('page') ?? 1;
    $adapter = new ArrayAdapter($segments);
    $pagerfanta = new Pagerfanta($adapter);
    $pagerfanta->setMaxPerPage(20);
    $pagerfanta->setCurrentPage($page);
    $segments = $pagerfanta->getCurrentPageResults();

    // Render the page.
    return [
      'form' => $this->form->createView(),
      'segments' => $segments,
      'type' => $this->params['type'],
      'format' => ($this->params['format'] == 'imperial') ? 'mi' : 'km',
      'pager' => $pagerfanta,
      'current_params_minus_sort' => $this->strava->getCurrentParams(['sort']),
    ];
  }

}
