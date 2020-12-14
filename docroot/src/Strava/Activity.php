<?php

namespace App\Strava;

/**
 * An individual activity.
 */
class Activity extends Base {

  /**
   * Query the activity.
   */
  private function query(int $activity_id) {
    // Query params and types.
    $query_params = [
      $this->user['id'],
      $activity_id,
    ];
    $query_types = [
      \PDO::PARAM_STR,
      \PDO::PARAM_STR,
    ];

    // Build the query.
    $sql = 'SELECT * ';
    $sql .= 'FROM activities ';
    $sql .= 'WHERE athlete_id = ? ';
    $sql .= 'AND id = ?';
    $this->activity = $this->connection->executeQuery($sql, $query_params, $query_types);

    // Build the query.
    $sql = 'SELECT * ';
    $sql .= 'FROM segment_efforts ';
    $sql .= 'WHERE athlete_id = ? ';
    $sql .= 'AND activity_id = ? ';
    $sql .= "ORDER BY start_date DESC";
    $this->segment_efforts = $this->connection->executeQuery($sql, $query_params, $query_types);
  }

  /**
   * Render the activity.
   *
   * @return array
   *   Return an array of render data.
   */
  public function render(int $activity_id) {
    $this->query($activity_id);

    $activity = [];
    foreach ($this->activity as $point) {
      $activity = [
        'type' => $this->strava->convertActivityType($point),
        'distance' => $this->strava->convertDistance($point['distance'], $this->user['format']),
        'start_date' => $this->strava->convertDateFormat($point['start_date']),
        'elapsed_time' => $this->strava->convertTimeFormat($point['elapsed_time']),
        'referer' => $this->request->server->get('HTTP_REFERER'),
      ];
      $activity += $point;
    }

    // Get the segment effort data.
    $segment_efforts = [];
    foreach ($this->segment_efforts as $point) {
      if (!empty($point['elapsed_time'])) {
        $point['distance'] = $this->strava->convertDistance($point['distance'], $this->user['format']);
        $point['elapsed_time'] = $this->strava->convertTimeFormat($point['elapsed_time']);
        $segment_efforts[] = $point;
      }
    }

    // Render the page.
    return [
      'activity' => $activity,
      'segment_efforts' => $segment_efforts,
      'format' => $this->user['format'] == 'imperial' ? 'mi' : 'km',
    ];
  }

}
