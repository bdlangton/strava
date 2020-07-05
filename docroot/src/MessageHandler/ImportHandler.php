<?php

namespace App\MessageHandler;

use App\Message\ImportMessage;
use App\Strava\Strava;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler for the import messages.
 */
class ImportHandler {

  /**
   * Database connection.
   *
   * @var \Doctrin\DBAL\Connection
   */
  protected $connection;

  /**
   * Strava.
   *
   * @var \App\Strava\Strava
   */
  protected $strava;

  /**
   * The message bus.
   *
   * @var \Symfony\Component\Messenger\MessageBus
   */
  protected $messageBus;

  /**
   * The Import Message object.
   *
   * @var \App\Message\ImportMessage
   */
  protected $importMessage;

  /**
   * Constructor.
   */
  public function __construct(Connection $connection, Strava $strava, MessageBusInterface $message_bus) {
    $this->connection = $connection;
    $this->strava = $strava;
    $this->messageBus = $message_bus;
  }

  /**
   * {@inheritdoc}
   */
  public function __invoke(ImportMessage $import) {
    $this->importMessage = $import;
    $this->{$import->function}();
  }

  /**
   * Import activities.
   */
  private function import() {
    $access_token = $this->strava->getAccessToken($this->importMessage->userId);
    $import_type = $this->importMessage->type;
    $page = $this->importMessage->page;

    // Query for activities.
    $activities = $this->strava->getActivities($access_token, $page);

    // If no activities are found (or there was an error), then exit.
    if (empty($activities) || !empty($activities['message'])) {
      return;
    }

    $continue = $this->importActivities($activities, $import_type);

    // If we aren't done processing, add another item to the queue to query the
    // next page.
    if ($continue) {
      $this->messageBus->dispatch(new ImportMessage('import', $this->importMessage->userId, $import_type, $page + 1));
    }
  }

  /**
   * Import selected group of activities.
   *
   * @param array $activities
   *   Activities to import.
   * @param string $import_type
   *   The import type ('new' or a specific year).
   *
   * @return bool
   *   Return whether to continue processing the import or not.
   */
  private function importActivities(array $activities, $import_type) {
    $access_token = $this->strava->getAccessToken($this->importMessage->userId);

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

        // We don't bother updating segment efforts for activities that
        // are just being updated.
        continue;
      }
      else {
        // Insert a new activity that wasn't already in our database.
        $this->strava->insertActivity($activity);
      }

      // Insert any segment effort associated with the activity.
      $this->strava->insertSegmentEfforts($activity, $access_token);
    }

    return TRUE;
  }

  /**
   * Import starred segments.
   */
  private function importStarredSegments() {
    $access_token = $this->strava->getAccessToken($this->importMessage->userId);

    // Query for existing segments so we don't import duplicates.
    $sql = 'SELECT segment_id ';
    $sql .= 'FROM starred_segments ';
    $sql .= 'WHERE athlete_id = ?';
    $existing_starred_segments = $this->connection->executeQuery($sql, [
      $this->importMessage->userId,
    ])->fetchAll(\PDO::FETCH_COLUMN);

    $processing = TRUE;
    for ($page = 1; $processing; $page++) {
      // Query for starred segments.
      $starred_segments = $this->strava->getStarredSegments($access_token, $page);
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
              'athlete_id' => $this->importMessage->userId,
              'segment_id' => $starred_segment['id'],
              'starred_date' => str_replace('Z', '', $starred_segment['starred_date']),
            ]);
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

}
