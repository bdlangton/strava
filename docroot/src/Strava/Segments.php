<?php

namespace App\Strava;

use Doctrine\DBAL\Connection;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Segments class.
 */
class Segments extends Base {

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

    // Build the form.
    $this->request = $this->requestStack->getCurrentRequest();
    $this->params = $this->request->query->get('form') ?? [];
    $this->params += [
      'type' => $this->user['activity_type'] ?: 'All',
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
   * Query activities.
   */
  private function query() {
    // Sort.
    switch ($this->params['sort']) {
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
      $this->user['id'],
      '%' . $this->params['name'] . '%',
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
    if ($this->params['type'] != 'All') {
      $sql .= 'AND s.activity_type = ? ';
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

    // Get the segments.
    $segments = [];
    foreach ($this->datapoints as $point) {
      $point['distance'] = $this->strava->convertDistance($point['distance'], $this->user['format']);
      $point['starred_date'] = $this->strava->convertDateFormat($point['starred_date']);
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
