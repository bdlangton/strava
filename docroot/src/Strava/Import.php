<?php

namespace App\Strava;

use Doctrine\DBAL\Connection;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Import.
 */
class Import extends Base {

  /**
   * Constructor.
   */
  public function __construct(RequestStack $request_stack, Connection $connection, FormFactoryInterface $form_factory, Strava $strava, SessionInterface $session) {
    parent::__construct($request_stack, $connection, $form_factory, $strava, $session);
    $this->output = '';
  }

  /**
   * Build the form.
   */
  public function buildForm() {
    $this->params = $this->request->query->get('form') ?? [];
    $this->import_type = !empty($this->params['type']) ? $this->params['type'] : NULL;
    $this->params += [
      'type' => 'new',
      'starred_segments' => FALSE,
    ];
    $this->params['starred_segments'] = !empty($this->params['starred_segments']);
    $form = $this->formFactory->createBuilder(FormType::class, $this->params)
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
    $this->form = $form->getForm();
  }

  /**
   * Import activities.
   */
  private function import() {
    $import_type = $this->params['type'];
    $activities_added = $activities_updated = 0;
    $processing = TRUE;
    for ($page = 1; $processing; $page++) {
      // Query for activities.
      $activities = $this->strava->getActivities($this->user['access_token'], $page);

      // If no activities are found (or there was an error), then break the
      // loop.
      if (empty($activities) || !empty($activities['message'])) {
        break;
      }

      $processing = $this->importActivities($activities, $import_type, $activities_added, $activities_updated);
    }

    $this->output = 'Added ' . $activities_added . ' activities.';
    if (!empty($activities_updated)) {
      $this->output .= ' Updated ' . $activities_updated . ' activities.';
    }
  }

  /**
   * Import activities.
   *
   * @param array $activities
   *   Activities to import.
   * @param string $import_type
   *   The import type ('new' or a specific year).
   * @param int $activities_added
   *   Count of activities added.
   * @param int $activities_updated
   *   Count of activities updated.
   *
   * @return bool
   *   Return whether to continue processing the import or not.
   */
  private function importActivities(array $activities, $import_type, &$activities_added, &$activities_updated) {
    // Check if we have the activities in our db already.
    $activity_ids = array_column($activities, 'id');
    $activity_results = $this->connection->executeQuery(
      'SELECT id FROM activities WHERE id IN (?) ',
      [$activity_ids],
      [Connection::PARAM_INT_ARRAY]
    )->fetchAll(\PDO::FETCH_COLUMN);

    // Loop through activities and add to the db.
    foreach ($activities as $activity) {
      // If we are importing a specific year.
      if (is_numeric($import_type)) {
        $start_year = (int) $this->strava->convertDateFormat($activity['start_date_local'], 'Y');

        // If the activity is for a year that is earlier than the import
        // year, then we need to stop importing.
        if ($start_year < $import_type) {
          return FALSE;
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
          return FALSE;
        }

        // Update the existing activity.
        $result = $this->strava->updateActivity($activity);
        if ($result) {
          $activities_updated++;
        }

        // We don't bother updating segment efforts for activities that
        // are just being updated.
        continue;
      }
      else {
        // Insert a new activity that wasn't already in our database.
        $this->strava->insertActivity($activity);
        $activities_added++;
      }

      // Insert any segment effort associated with the activity.
      $this->strava->insertSegmentEfforts($activity, $this->user['access_token']);
    }

    return TRUE;
  }

  /**
   * Import starred segments.
   */
  private function importStarredSegments() {
    $starred_segments_added = 0;

    // Query for existing segments so we don't import duplicates.
    $sql = 'SELECT segment_id ';
    $sql .= 'FROM starred_segments ';
    $sql .= 'WHERE athlete_id = ?';
    $existing_starred_segments = $this->connection->executeQuery($sql, [
      $this->user['id'],
    ])->fetchAll(\PDO::FETCH_COLUMN);

    $processing = TRUE;
    for ($page = 1; $processing; $page++) {
      // Query for starred segments.
      $starred_segments = $this->strava->getStarredSegments($this->user['access_token'], $page);

      // If no activities are found (or there was an error), then break the
      // loop.
      if (empty($starred_segments) || !empty($starred_segments['message'])) {
        break;
      }

      // Insert the starred segment if it doesn't exist.
      foreach ($starred_segments as $starred_segment) {
        if (!empty($starred_segment['id']) && !in_array($starred_segment['id'], $existing_starred_segments)) {
          try {
            $this->connection->insert('starred_segments', [
              'athlete_id' => $this->user['id'],
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

    if (!empty($starred_segments_added)) {
      $this->output .= ' Added ' . $starred_segments_added . ' starred segments.';
    }
  }

  /**
   * Render.
   */
  public function render() {
    $this->buildForm();
    if (!empty($this->import_type)) {
      $this->import();
    }
    if (!empty($this->params['starred_segments'])) {
      $this->importStarredSegments();
    }

    return [
      'form' => $this->form->createView(),
      'output' => $this->output,
    ];
  }

}
