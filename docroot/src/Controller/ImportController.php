<?php

namespace App\Controller;

use App\Strava\Strava;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Import functionality controller.
 */
class ImportController extends AbstractController {

  /**
   * @Route("/import", name="import", methods={"GET"})
   */
  public function import(SessionInterface $session, RequestStack $requestStack, Strava $strava, FormFactoryInterface $formFactory, Connection $connection) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }
    $output = '';

    // Build the form.
    $request = $requestStack->getCurrentRequest();
    $params = $request->query->get('form') ?? [];
    $import_type = !empty($params['type']) ? $params['type'] : NULL;
    $params += [
      'type' => 'new',
      'starred_segments' => FALSE,
    ];
    $params['starred_segments'] = !empty($params['starred_segments']);
    $form = $formFactory->createBuilder(FormType::class, $params)
      ->add('type', ChoiceType::class, [
        'choices' => [
          'New Activities' => 'new',
          '2019 Activities' => '2019',
          '2018 Activities' => '2018',
          '2017 Activities' => '2017',
          '2016 Activities' => '2016',
          '2015 Activities' => '2015',
          '2014 Activities' => '2014',
          '2013 Activities' => '2013',
          '2012 Activities' => '2012',
          '2011 Activities' => '2011',
          '2010 Activities' => '2010',
        ],
        'label' => FALSE,
      ])
      ->add('starred_segments', CheckboxType::class, [
        'label' => 'Import Starred Segments',
        'required' => FALSE,
        'value' => TRUE,
      ]);
    $form = $form->getForm();

    if (!empty($import_type)) {
      $activities_added = $activities_updated = 0;
      $starred_segments_added = 0;
      $processing = TRUE;
      for ($page = 1; $processing; $page++) {
        // Query for activities.
        $activities = $strava->getActivities($user['access_token'], $page);

        // If no activities are found (or there was an error), then break the
        // loop.
        if (empty($activities) || !empty($activities['message'])) {
          break;
        }

        // Check if we have the activities in our db already.
        $activity_ids = array_column($activities, 'id');
        $activity_results = $connection->executeQuery(
          'SELECT id FROM activities WHERE id IN (?) ',
          [$activity_ids],
          [Connection::PARAM_INT_ARRAY]
        )->fetchAll(\PDO::FETCH_COLUMN);

        // Loop through activities and add to the db.
        foreach ($activities as $activity) {
          // If we are importing a specific year.
          if (is_numeric($import_type)) {
            $start_year = (int) $strava->convertDateFormat($activity['start_date_local'], 'Y');

            // If the activity is for a year that is earlier than the import
            // year, then we need to stop importing.
            if ($start_year < $import_type) {
              $processing = FALSE;
              break;
            }
            // If the activity is for a year that is later than the import
            // year, then we need to skip this activity.
            if ($start_year > $import_type) {
              continue;
            }
          }

          // Convert some data to how we need it stored.
          $activity['start_date'] = str_replace('Z', '', $activity['start_date']);
          $activity['start_date_local'] = str_replace('Z', '', $activity['start_date_local']);
          $activity['manual'] = $activity['manual'] ? 1 : 0;
          $activity['private'] = $activity['private'] ? 1 : 0;

          // Check if we're importing an activity that already exists.
          if (in_array($activity['id'], $activity_results)) {
            // If we're just importing new activities, then since we found
            // an activity already in our db, we need to stop importing.
            if ($import_type == 'new') {
              $processing = FALSE;
              break;
            }

            // Update the existing activity.
            $result = $strava->updateActivity($activity);
            if ($result) {
              $activities_updated++;
            }

            // We don't bother updating segment efforts for activities that
            // are just being updated.
            continue;
          }
          else {
            // Insert a new activity that wasn't already in our database.
            $strava->insertActivity($activity);
            $activities_added++;
          }

          // Insert any segment efforst associated with the activity.
          $strava->insertSegmentEfforts($activity, $user['access_token']);
        }
      }

      // Importing starred segments.
      if (!empty($params['starred_segments'])) {
        // Query for existing segments so we don't import duplicates.
        $sql = 'SELECT segment_id ';
        $sql .= 'FROM starred_segments ';
        $sql .= 'WHERE athlete_id = ?';
        $existing_starred_segments = $connection->executeQuery($sql, [
          $user['id'],
        ])->fetchAll(\PDO::FETCH_COLUMN);

        $processing = TRUE;
        for ($page = 1; $processing; $page++) {
          // Query for starred segments.
          $starred_segments = $strava->getStarredSegments($user['access_token'], $page);

          if (empty($starred_segments)) {
            $processing = FALSE;
            continue;
          }

          // Insert the starred segment if it doesn't exist.
          foreach ($starred_segments as $starred_segment) {
            if (!in_array($starred_segment['id'], $existing_starred_segments)) {
              try {
                $connection->insert('starred_segments', [
                  'athlete_id' => $user['id'],
                  'segment_id' => $starred_segment['id'],
                  'starred_date' => str_replace('Z', '', $starred_segment['starred_date']),
                ]);
                $starred_segments_added++;
              }
              catch (Exception $e) {
                // Something went wrong. Stop processing.
                $processing = FALSE;
                break;
              }
            }
          }
        }
      }

      // Generate output.
      $output = 'Added ' . $activities_added . ' activities.';
      if (!empty($activities_updated)) {
        $output .= ' Updated ' . $activities_updated . ' activities.';
      }
      if (!empty($starred_segments_added)) {
        $output .= ' Added ' . $starred_segments_added . ' starred segments.';
      }
    }

    return $this->render('import.twig', [
      'form' => $form->createView(),
      'output' => $output,
    ]);
  }

}
