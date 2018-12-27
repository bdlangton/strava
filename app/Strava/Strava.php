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

}
