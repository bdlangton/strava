<?php

namespace App\Controller;

use App\Strava\Strava;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Segments controller.
 */
class SegmentsController extends AbstractController {

  /**
   * @Route("/segments", name="segments")
   */
  public function segments(SessionInterface $session, RequestStack $requestStack, Strava $strava, FormFactoryInterface $formFactory, Connection $connection) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('/');
    }

    // Build the form.
    $request = $requestStack->getCurrentRequest();
    $params = $request->query->get('form');
    $params += [
      'type' => $user['activity_type'] ?: 'All',
      'name' => '',
      'format' => $user['format'] ?: 'imperial',
      'sort' => NULL,
    ];
    $form = $formFactory->createBuilder(FormType::class, $params)
      ->add('type', ChoiceType::class, [
        'choices' => $strava->getActivityTypes(),
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
    $datapoints = $connection->executeQuery($sql, $query_params, $query_types);

    $segments = [];
    foreach ($datapoints as $point) {
      $point['distance'] = $strava->convertDistance($point['distance'], $user['format']);
      $point['starred_date'] = $strava->convertDateFormat($point['starred_date']);
      $segments[] = $point;
    }

    // Render the page.
    return $this->render('segments.twig', [
      'form' => $form->createView(),
      'segments' => $segments,
      'type' => $params['type'],
      'format' => ($params['format'] == 'imperial') ? 'mi' : 'km',
      'current_params_minus_page' => $strava->getCurrentParams(['page']),
      'current_params_minus_sort' => $strava->getCurrentParams(['sort']),
    ]);
  }

}
