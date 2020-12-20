<?php

namespace App\Strava;

/**
 * Segment Efforts class.
 */
class SegmentEfforts extends Base {

  /**
   * Query segment efforts.
   */
  private function query(int $segment_id) {
    $this->params = $this->request->query->all() ?? [];
    $this->params += [
      'sort' => '',
    ];
    $sort = '';

    // Determine the sort order.
    switch ($this->params['sort']) {
      case 'time':
        $sort = 'ORDER BY elapsed_time ASC';
        break;

      case 'date':
      default:
        $sort = 'ORDER BY start_date DESC';
        break;
    }

    // Query params and types.
    $query_params = [
      $this->user['id'],
      $segment_id,
    ];
    $query_types = [
      \PDO::PARAM_STR,
      \PDO::PARAM_STR,
    ];

    // Build the query.
    $sql = 'SELECT se.id, s.name, se.activity_id, se.distance, se.kom_rank, ';
    $sql .= 'se.pr_rank, se.start_date, se.elapsed_time, se.segment_id, ';
    $sql .= 's.activity_type, se.distance, se.average_cadence, ';
    $sql .= 'se.average_watts, se.average_heartrate, se.max_heartrate ';
    $sql .= 'FROM segments s ';
    $sql .= 'JOIN segment_efforts se ON (s.id = se.segment_id)';
    $sql .= 'WHERE se.athlete_id = ? ';
    $sql .= 'AND se.segment_id = ? ';
    $sql .= $sort;
    $this->datapoints = $this->connection->executeQuery($sql, $query_params, $query_types);

    // If there are no segment efforts, try to get the segment info so we can
    // still display some information on the page.
    if ($this->datapoints->rowCount() == 0) {
      $sql = 'SELECT id segment_id, name, "" activity_id, distance, ';
      $sql .= 'NULL kom_rank, NULL pr_rank, NULL start_date, activity_type, ';
      $sql .= 'NULL elapsed_time, NULL id ';
      $sql .= 'FROM segments ';
      $sql .= 'WHERE id = ? ';
      $this->datapoints = $this->connection->executeQuery($sql, [$segment_id], [\PDO::PARAM_STR]);
    }
  }

  /**
   * Render the segment efforts.
   *
   * @return array
   *   Return an array of render data.
   */
  public function render(int $segment_id) {
    $this->query($segment_id);

    // Get the segment effort data.
    $segment = [];
    $segment_efforts = [];
    foreach ($this->datapoints as $point) {
      if (empty($segment)) {
        $segment = [
          'id' => $point['segment_id'],
          'name' => $point['name'],
          'activity_type' => $this->strava->convertActivityType($point, $point['activity_type']),
          'distance' => $this->strava->convertDistance($point['distance'] ?? '', $this->user['format']),
          'referer' => $this->request->server->get('HTTP_REFERER'),
        ];
      }
      $point['start_date'] = $this->strava->convertDateFormat($point['start_date'] ?? '');
      $point['elapsed_time'] = $this->strava->convertTimeFormat($point['elapsed_time'] ?? 0);

      // If no start date, then there isn't really a segment effort.
      if (!empty($point['start_date'])) {
        $segment_efforts[] = $point;
      }
    }

    // Render the page.
    return [
      'segment' => $segment,
      'segment_efforts' => $segment_efforts,
      'format' => $this->user['format'] == 'imperial' ? 'mi' : 'km',
    ];
  }

}
