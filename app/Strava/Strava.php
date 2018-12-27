<?php

namespace Strava;

define("DISTANCE_TO_MILES", 0.00062137);
define("DISTANCE_TO_KM", 0.001);
define("GAIN_TO_FEET", 3.28084);

/**
 * Strava class.
 */
class Strava {

  /**
   * Activity type form choices.
   *
   * @var array
   */
  protected $activityTypeChoices = [
    'All' => 'All',
    'Running' => 'Run',
    'Cycling' => 'Ride',
    'Swimming' => 'Swim',
    'Alpine Skiing' => 'AlpineSki',
    'Backcountry Skiing' => 'BackcountrySki',
    'Canoeing' => 'Canoeing',
    // Deprecated.
    'Cross Country Skiing' => 'CrossCountrySkiing',
    'CrossFit' => 'Crossfit',
    'E-Bike Riding' => 'EBikeRide',
    'Elliptical' => 'Elliptical',
    'Handcycling' => 'Handcycle',
    'Hiking' => 'Hike',
    'Ice Skating' => 'IceSkate',
    'Inline Skating' => 'InlineSkate',
    'Kayaking' => 'Kayaking',
    'Kite Surfing' => 'Kitesurf',
    'Nordic Skiing' => 'NordicSki',
    'Rock Climbing' => 'RockClimbing',
    'Roller Skiing' => 'RollerSki',
    'Rowing' => 'Rowing',
    'Snowboarding' => 'Snowboard',
    'Snowshoeing' => 'Snowshoe',
    'Stair Stepper' => 'StairStepper',
    'Stand Up Paddleboard' => 'StandUpPaddling',
    'Surfing' => 'Surfing',
    'Virtual Ride' => 'VirtualRide',
    'Walking' => 'Walk',
    // Deprecated.
    'Water Sports' => 'WaterSport',
    'Weight Training' => 'WeightTraining',
    'Wheel Chair' => 'WheelChair',
    'Wind Surfing' => 'Windsurf',
    'Workout' => 'Workout',
    'Yoga' => 'Yoga',
  ];

  /**
   * Format form choices.
   *
   * @var array
   */
  protected $formatChoices = ['Imperial' => 'imperial', 'Metric' => 'metric'];

  /**
   * Group form choices.
   *
   * @var array
   */
  protected $groupChoices = [
    'Monthly' => 'month',
    'Weekly' => 'week',
    'Yearly' => 'year',
  ];

  /**
   * Run workout form choices.
   *
   * @var array
   */
  protected $runWorkoutChoices = [
    'Default' => 0,
    'Race' => 1,
    'Long Run' => 2,
    'Intervals' => 3,
  ];

  /**
   * Converts distance depending on format.
   *
   * @param float $distance
   *   Distance provided by Strava.
   * @param string $format
   *   Imperial or metric.
   * @param bool $number_format
   *   Whether or not to use the number_format function.
   *
   * @return string|float
   *   Returns the distance in miles or meters.
   */
  public function convertDistance(float $distance, string $format, bool $number_format = TRUE) {
    if ($format == 'imperial') {
      $distance = round($distance * DISTANCE_TO_MILES, 1);
    }
    else {
      $distance = round($distance * DISTANCE_TO_KM, 1);
    }
    return $number_format ? number_format($distance, 1, '.', ',') : $distance;
  }

  /**
   * Converts elevation gain depending on format.
   *
   * @param float $elevation_gain
   *   Elevation gain provided by Strava.
   * @param string $format
   *   Imperial or metric.
   * @param bool $number_format
   *   Whether or not to use the number_format function.
   *
   * @return string
   *   Returns the elevation gain in feet or meters.
   */
  public function convertElevationGain(float $elevation_gain, string $format, bool $number_format = TRUE) : string {
    if ($format == 'imperial') {
      $elevation_gain = round($elevation_gain * GAIN_TO_FEET);
    }
    else {
      $elevation_gain = round($elevation_gain);
    }
    return $number_format ? number_format($elevation_gain, 0, '.', ',') : $elevation_gain;
  }

  /**
   * Converts time in seconds to a readable format.
   *
   * @param int $time
   *   The time in seconds.
   * @param string $format
   *   Format to output the time.
   *
   * @return string
   *   Returns the time formatted.
   */
  public function convertTimeFormat(int $time, string $format = 'H:i:s') : string {
    return gmdate($format, $time);
  }

  /**
   * Converts a provided date to a certain format.
   *
   * @param string $date
   *   The date in 'Y-m-d' format.
   * @param string $format
   *   The format that we want the date in.
   *
   * @return string
   *   Returns the date in string format.
   */
  public function convertDateFormat(string $date, string $format = 'M d, Y') : string {
    $datetime = new \DateTime($date);
    return $datetime->format($format);
  }

  /**
   * Gets activity type choices.
   *
   * @param bool $include_all
   *   Whether or not to include All.
   *
   * @return array
   *   Returns an array of activity types.
   */
  public function getActivityTypes(bool $include_all = TRUE) : array {
    $choices = $this->activityTypeChoices;
    if (!$include_all) {
      unset($choices['All']);
    }
    return $choices;
  }

  /**
   * Gets format choices.
   *
   * @return array
   *   Returns an array of formats.
   */
  public function getFormats() : array {
    return $this->formatChoices;
  }

  /**
   * Gets group choices.
   *
   * @return array
   *   Returns an array of groups.
   */
  public function getGroups() : array {
    return $this->groupChoices;
  }

  /**
   * Gets run workout choices.
   *
   * @return array
   *   Returns an array of run workouts.
   */
  public function getRunWorkouts() : array {
    return $this->runWorkoutChoices;
  }

  /**
   * Gets the begin and end dates based on the grouping option selected.
   *
   * @param string $group
   *   Group by year, month, or week.
   *
   * @return array
   *   Returns array of begin date and end date.
   */
  public function getBeginAndEndDates(string $group = 'month') : array {
    $dates = [
      'begin_date' => NULL,
      'end_date' => new \DateTime('now'),
    ];
    if ($group == 'month' || $group == 'week') {
      $dates['begin_date'] = new \DateTime('first day of this month - 1 year');
    }
    elseif ($group == 'year') {
      $dates['begin_date'] = new \DateTime('first day of this year - 5 years');
    }
    return $dates;
  }

  /**
   * Gets the current URL params.
   *
   * @param array $exclude
   *   Optional array of params to exclude from the return. Used if you want to
   *   exclude something such as 'page' or 'sort'.
   *
   * @return string
   *   Returns a string of params joined by "&".
   */
  public function getCurrentParams(array $exclude = []) : string {
    $current_params = '';
    if (!empty($_SERVER['QUERY_STRING'])) {
      $current_params = html_entity_decode($_SERVER['QUERY_STRING']);
    }
    if (!empty($current_params) && !empty($exclude)) {
      $params_array = explode('&', $current_params);
      foreach ($params_array as $key => $param) {
        $param_explode = explode('=', $param);
        if (in_array($param_explode[0], $exclude)) {
          unset($params_array[$key]);
        }
      }
      $current_params = implode('&', $params_array);
    }
    return $current_params;
  }

  /**
   * Get an individual activity from the Strava API.
   *
   * @param int $activity_id
   *   The activity ID.
   * @param string $access_token
   *   The user's access token.
   *
   * @return array
   *   Return the activity information.
   */
  public function getActivity(int $activity_id, string $access_token) : array {
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => 'https://www.strava.com/api/v3/activities/' . $activity_id . '?access_token=' . $access_token,
      CURLOPT_RETURNTRANSFER => TRUE,
    ]);
    $activity = curl_exec($curl);
    curl_close($curl);

    return json_decode($activity, TRUE);
  }

  /**
   * Get multiple activities from the Strava API.
   *
   * @param string $access_token
   *   The user's access token.
   * @param int $page
   *   The page number.
   *
   * @return array
   *   Return the activities.
   */
  public function getActivities(string $access_token, int $page = 1) : array {
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => 'https://www.strava.com/api/v3/athlete/activities?access_token=' . $access_token . '&page=' . $page,
      CURLOPT_RETURNTRANSFER => TRUE,
    ]);
    $activities = curl_exec($curl);
    curl_close($curl);

    return json_decode($activities, TRUE);
  }

  /**
   * Get starred segments by the user.
   *
   * @param string $access_token
   *   The user's access token.
   * @param int $page
   *   The page number.
   *
   * @return array
   *   Return the starred segments.
   */
  public function getStarredSegments(string $access_token, int $page = 1) : array {
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => 'https://www.strava.com/api/v3/segments/starred?access_token=' . $access_token . '&page=' . $page,
      CURLOPT_RETURNTRANSFER => TRUE,
    ]);
    $starred_segments = curl_exec($curl);
    curl_close($curl);

    return json_decode($starred_segments, TRUE);
  }

  /**
   * Update an activity.
   *
   * @param array $activity
   *   The activity.
   * @param mixed $app
   *   The Silex app.
   *
   * @return bool
   *   Return TRUE if it was updated, FALSE if there was an error.
   */
  public function updateActivity(array $activity, $app) : bool {
    try {
      // Convert some data to how we need it stored.
      $activity['start_date'] = str_replace('Z', '', $activity['start_date']);
      $activity['start_date_local'] = str_replace('Z', '', $activity['start_date_local']);
      $activity['manual'] = $activity['manual'] ? 1 : 0;
      $activity['private'] = $activity['private'] ? 1 : 0;

      // Update the existing activity.
      $result = $app['db']->update('activities',
        [
          'athlete_id' => $activity['athlete']['id'],
          'name' => $activity['name'],
          'description' => !empty($activity['description']) ? $activity['description'] : NULL,
          'distance' => $activity['distance'],
          'moving_time' => $activity['moving_time'],
          'elapsed_time' => $activity['elapsed_time'],
          'total_elevation_gain' => $activity['total_elevation_gain'],
          'type' => $activity['type'],
          'start_date' => $activity['start_date'],
          'start_date_local' => $activity['start_date_local'],
          'timezone' => $activity['timezone'],
          'trainer' => $activity['trainer'],
          'commute' => $activity['commute'],
          'manual' => $activity['manual'],
          'private' => $activity['private'],
          'workout_type' => empty($activity['workout_type']) ? 0 : $activity['workout_type'],
          'average_speed' => !empty($activity['average_speed']) ? $activity['average_speed'] : NULL,
          'max_speed' => $activity['max_speed'],
          'calories' => !empty($activity['calories']) ? $activity['calories'] : NULL,
          'average_cadence' => !empty($activity['average_cadence']) ? $activity['average_cadence'] : NULL,
          'average_watts' => !empty($activity['average_watts']) ? $activity['average_watts'] : NULL,
          'average_heartrate' => !empty($activity['average_heartrate']) ? $activity['average_heartrate'] : NULL,
          'max_heartrate' => !empty($activity['max_heartrate']) ? $activity['max_heartrate'] : NULL,
        ],
        ['id' => $activity['id']]
      );

      if ($result) {
        return TRUE;
      }
    }
    catch (Exception $e) {
      // Something went wrong.
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Insert an activity.
   *
   * @param array $activity
   *   The activity.
   * @param mixed $app
   *   The Silex app.
   *
   * @return bool
   *   Return TRUE if it was inserted, FALSE if there was an error.
   */
  public function insertActivity(array $activity, $app) : bool {
    try {
      // Convert some data to how we need it stored.
      $activity['start_date'] = str_replace('Z', '', $activity['start_date']);
      $activity['start_date_local'] = str_replace('Z', '', $activity['start_date_local']);
      $activity['manual'] = $activity['manual'] ? 1 : 0;
      $activity['private'] = $activity['private'] ? 1 : 0;

      // Insert a new activity that wasn't already in our database.
      $result = $app['db']->insert('activities', [
        'id' => $activity['id'],
        'athlete_id' => $activity['athlete']['id'],
        'name' => $activity['name'],
        'description' => !empty($activity['description']) ? $activity['description'] : NULL,
        'distance' => $activity['distance'],
        'moving_time' => $activity['moving_time'],
        'elapsed_time' => $activity['elapsed_time'],
        'total_elevation_gain' => $activity['total_elevation_gain'],
        'type' => $activity['type'],
        'start_date' => $activity['start_date'],
        'start_date_local' => $activity['start_date_local'],
        'timezone' => $activity['timezone'],
        'trainer' => !empty($activity['trainer']) ? $activity['trainer'] : NULL,
        'commute' => !empty($activity['commute']) ? $activity['commute'] : NULL,
        'manual' => $activity['manual'],
        'private' => $activity['private'],
        'workout_type' => empty($activity['workout_type']) ? 0 : $activity['workout_type'],
        'average_speed' => !empty($activity['average_speed']) ? $activity['average_speed'] : NULL,
        'max_speed' => !empty($activity['max_speed']) ? $activity['max_speed'] : NULL,
        'calories' => !empty($activity['calories']) ? $activity['calories'] : NULL,
        'average_cadence' => !empty($activity['average_cadence']) ? $activity['average_cadence'] : NULL,
        'average_watts' => !empty($activity['average_watts']) ? $activity['average_watts'] : NULL,
        'average_heartrate' => !empty($activity['average_heartrate']) ? $activity['average_heartrate'] : NULL,
        'max_heartrate' => !empty($activity['max_heartrate']) ? $activity['max_heartrate'] : NULL,
      ]);

      if ($result) {
        return TRUE;
      }
    }
    catch (Exception $e) {
      // Something went wrong.
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Insert segment efforts associated with an activity.
   *
   * @param array $activity
   *   The activity.
   * @param string $access_token
   *   The user's access token.
   * @param mixed $app
   *   The Silex app.
   *
   * @return bool
   *   Return TRUE if segment efforts were inserted, FALSE if there was an
   *   error.
   */
  public function insertSegmentEfforts(array $activity, string $access_token, $app) : bool {
    try {
      // Query the individual activity so we can get the detailed
      // representation that includes segment efforts.
      $activity = $app['strava']->getActivity($activity['id'], $access_token);

      // If no segment efforts are found, then we are done with this
      // activity.
      if (empty($activity['segment_efforts'])) {
        return TRUE;
      }

      // Check if we already have any segment efforts in our db.
      $segment_effort_ids = array_column($activity['segment_efforts'], 'id');
      $segment_effort_results = $app['db']->executeQuery(
        'SELECT id FROM segment_efforts WHERE id IN (?) ',
        [$segment_effort_ids],
        [Connection::PARAM_INT_ARRAY]
      )->fetchAll(\PDO::FETCH_COLUMN);

      // Process segments.
      if (!empty($activity['segment_efforts'])) {
        // Go through each segment effort.
        foreach ($activity['segment_efforts'] as $segment_effort) {
          // Convert some data to how we need it stored.
          $segment_effort['start_date'] = str_replace('Z', '', $segment_effort['start_date']);
          $segment_effort['start_date_local'] = str_replace('Z', '', $segment_effort['start_date_local']);

          // Insert the segment effort if it doesn't already exist.
          if (!in_array($segment_effort['id'], $segment_effort_results)) {
            $app['db']->insert('segment_efforts', [
              'id' => $segment_effort['id'],
              'segment_id' => $segment_effort['segment']['id'],
              'name' => $segment_effort['name'],
              'activity_id' => $segment_effort['activity']['id'],
              'athlete_id' => $segment_effort['athlete']['id'],
              'elapsed_time' => $segment_effort['elapsed_time'],
              'moving_time' => $segment_effort['moving_time'],
              'start_date' => $segment_effort['start_date'],
              'start_date_local' => $segment_effort['start_date_local'],
              'distance' => $segment_effort['distance'],
              'average_cadence' => !empty($segment_effort['average_cadence']) ? $segment_effort['average_cadence'] : NULL,
              'average_watts' => !empty($segment_effort['average_watts']) ? $segment_effort['average_watts'] : NULL,
              'average_heartrate' => !empty($segment_effort['average_heartrate']) ? $segment_effort['average_heartrate'] : NULL,
              'max_heartrate' => !empty($segment_effort['max_heartrate']) ? $segment_effort['max_heartrate'] : NULL,
              'kom_rank' => !empty($segment_effort['kom_rank']) ? $segment_effort['kom_rank'] : NULL,
              'pr_rank' => !empty($segment_effort['pr_rank']) ? $segment_effort['pr_rank'] : NULL,
            ]);
          }

          // Check if we already have the segment in our db.
          $segment = $segment_effort['segment'];
          $result = $app['db']->executeQuery(
            'SELECT id FROM segments WHERE id = ? ',
            [$segment['id']]
          )->fetchAll();

          // Insert the segment related to the segment effort if it
          // doesn't already exist.
          if (empty($result)) {
            // Convert some data to how we need it stored.
            $segment['private'] = $segment['private'] ? 1 : 0;
            $segment['hazardous'] = $segment['hazardous'] ? 1 : 0;

            $app['db']->insert('segments', [
              'id' => $segment['id'],
              'name' => $segment['name'],
              'activity_type' => $segment['activity_type'],
              'distance' => $segment['distance'],
              'average_grade' => $segment['average_grade'],
              'maximum_grade' => $segment['maximum_grade'],
              'elevation_high' => $segment['elevation_high'],
              'elevation_low' => $segment['elevation_low'],
              'city' => $segment['city'],
              'state' => $segment['state'],
              'country' => $segment['country'],
              'climb_category' => $segment['climb_category'],
              'private' => $segment['private'],
              // Note: total_elevation_gain, effort_count, and
              // athlete_count is not included in the activity endpoint.
              // We would need to query the segment itself to get that.
              // For now, we are avoiding that extra API call.
              'total_elevation_gain' => !empty($segment['total_elevation_gain']) ? $segment['total_elevation_gain'] : NULL,
              'effort_count' => !empty($segment['effort_count']) ? $segment['effort_count'] : NULL,
              'athlete_count' => !empty($segment['athlete_count']) ? $segment['athlete_count'] : NULL,
              'hazardous' => $segment['hazardous'],
            ]);
          }
        }
      }
    }
    catch (Exception $e) {
      // Something went wrong.
      return FALSE;
    }

    return TRUE;
  }

}
