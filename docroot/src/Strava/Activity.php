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
    $sql = 'SELECT a.id activity_id, a.name, a.distance, se.kom_rank, ';
    $sql .= 'se.pr_rank, a.start_date, se.elapsed_time, se.segment_id, ';
    $sql .= 'a.elapsed_time activity_time, se.id effort_id, ';
    $sql .= 'se.name effort_name, se.average_cadence, se.average_heartrate, ';
    $sql .= 'se.max_heartrate, se.average_watts, a.type activity_type ';
    $sql .= 'FROM activities a ';
    $sql .= 'LEFT JOIN segment_efforts se ON (se.activity_id = a.id)';
    $sql .= 'WHERE a.athlete_id = ? ';
    $sql .= 'AND a.id = ? ';
    $sql .= "ORDER BY start_date DESC";
    $this->datapoints = $this->connection->executeQuery($sql, $query_params, $query_types);
  }

  /**
   * Render the activity.
   *
   * @return array
   *   Return an array of render data.
   */
  public function render(int $activity_id) {
    $this->query($activity_id);

    // Get the segment effort data.
    $activity = [];
    $segment_efforts = [];
    foreach ($this->datapoints as $point) {
      if (empty($activity)) {
        $activity = [
          'id' => $point['activity_id'],
          'name' => $point['name'],
          'type' => $point['activity_type'],
          'distance' => $this->strava->convertDistance($point['distance'], $this->user['format']),
          'start_date' => $this->strava->convertDateFormat($point['start_date']),
          'elapsed_time' => $this->strava->convertTimeFormat($point['activity_time']),
          'referer' => $this->request->server->get('HTTP_REFERER'),
        ];
      }
      if (!empty($point['elapsed_time'])) {
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
