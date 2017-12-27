<?php

namespace Strava;

use DateTime;

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
  public $activityTypeChoices = [
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
  public $formatChoices = ['Imperial' => 'imperial', 'Metric' => 'metric'];

  /**
   * Group form choices.
   *
   * @var array
   */
  public $groupChoices = [
    'Monthly' => 'month',
    'Weekly' => 'week',
    'Yearly' => 'year',
  ];

  /**
   * Run workout form choices.
   *
   * @var array
   */
  public $runWorkoutChoices = [
    'Default' => 0,
    'Race' => 1,
    'Long Run' => 2,
    'Intervals' => 3,
  ];

  /**
   * Convert distance depending on format.
   *
   * @param float $distance
   *   Distance provided by Strava.
   * @param string $format
   *   Imperial or metric.
   * @param bool $number_format
   *   Whether or not to use the number_format function.
   *
   * @return float
   *   Returns the distance in miles or meters.
   */
  public function convertDistance($distance, $format, $number_format = TRUE) {
    if ($format == 'imperial') {
      $distance = round($distance * DISTANCE_TO_MILES, 1);
    }
    else {
      $distance = round($distance * DISTANCE_TO_KM, 1);
    }
    return $number_format ? number_format($distance, 1, '.', ',') : $distance;
  }

  /**
   * Convert elevation gain depending on format.
   *
   * @param float $elevation_gain
   *   Elevation gain provided by Strava.
   * @param string $format
   *   Imperial or metric.
   * @param bool $number_format
   *   Whether or not to use the number_format function.
   *
   * @return float
   *   Returns the elevation gain in feet or meters.
   */
  public function convertElevationGain($elevation_gain, $format, $number_format = TRUE) {
    if ($format == 'imperial') {
      $elevation_gain = round($elevation_gain * GAIN_TO_FEET);
    }
    else {
      $elevation_gain = round($elevation_gain);
    }
    return $number_format ? number_format($elevation_gain, 0, '.', ',') : $elevation_gain;
  }

  /**
   * Convert time in seconds to a readable format.
   *
   * @param int $time
   *   The time in seconds.
   * @param string $format
   *   Format to output the time.
   *
   * @return string
   *   Returns the time formatted.
   */
  public function convertTimeFormat($time, $format = 'H:i:s') {
    return gmdate($format, $time);
  }

  /**
   * Convert a provided date to a certain format.
   *
   * @param string $date
   *   The date in 'Y-m-d' format.
   * @param string $format
   *   The format that we want the date in.
   *
   * @return string
   *   Return the date in string format.
   */
  public function convertDateFormat($date, $format = 'M d, Y') {
    $datetime = new DateTime($date);
    return $datetime->format($format);
  }

  /**
   * Get the begin and end dates based on the grouping option selected.
   *
   * @param string $group
   *   Group by year, month, or week.
   *
   * @return array
   *   Return array of begin date and end date.
   */
  public function getBeginAndEndDates($group = 'month') {
    $dates = array(
      'begin_date' => NULL,
      'end_date' => new DateTime('now'),
    );
    if ($group == 'month' || $group == 'week') {
      $dates['begin_date'] = new DateTime('first day of this month - 1 year');
    }
    elseif ($group == 'year') {
      $dates['begin_date'] = new DateTime('first day of this year - 5 years');
    }
    return $dates;
  }

  /**
   * Get the current URL params.
   *
   * @param array $exclude
   *   Optional array of params to exclude from the return. Used if you want to
   *   exclude something such as 'page' or 'sort'.
   *
   * @return string
   *   Returns a string of params joined by "&".
   */
  public function getCurrentParams(array $exclude = array()) {
    $current_params = !empty($_SERVER['QUERY_STRING']) ? html_entity_decode($_SERVER['QUERY_STRING']) : NULL;
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

}
