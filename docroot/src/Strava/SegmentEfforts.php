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
    $sql = 'SELECT id, name, activity_id, distance, kom_rank, pr_rank, ';
    $sql .= 'start_date, elapsed_time, segment_id ';
    $sql .= 'FROM segment_efforts ';
    $sql .= 'WHERE athlete_id = ? ';
    $sql .= 'AND segment_id = ? ';
    $sql .= $sort;
    $this->datapoints = $this->connection->executeQuery($sql, $query_params, $query_types);

    // If there are no segment efforts, try to get the segment info so we can
    // still display some information on the page.
    if ($this->datapoints->rowCount() == 0) {
      $sql = 'SELECT id segment_id, name, "" activity_id, distance, ';
      $sql .= 'NULL kom_rank, NULL pr_rank, NULL start_date, ';
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
