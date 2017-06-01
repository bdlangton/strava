<?php

/**
 * This file provides functions for use with the Strava app.
 */

namespace Strava;

use \DateTime;

define("DISTANCE_TO_MILES", 0.00062137);
define("DISTANCE_TO_KM", 0.001);
define("GAIN_TO_FEET", 3.28084);

/**
 * Strava class.
 */
class Strava {

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
  function convert_distance($distance, $format, $number_format = TRUE) {
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
  function convert_elevation_gain($elevation_gain, $format, $number_format = TRUE) {
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
  function convert_time_format($time, $format = 'H:i:s') {
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
  function convert_date_format($date, $format = 'M d, Y') {
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
  function get_begin_and_end_dates($group = 'month') {
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
   * Get the current URL params minus the 'page' param.
   */
  function get_current_params() {
    $current_params = !empty($_SERVER['QUERY_STRING']) ? html_entity_decode($_SERVER['QUERY_STRING']) : NULL;
    if (!empty($current_params)) {
      $params_array = explode('&', $current_params);
      foreach ($params_array as $key => $param) {
        if (strpos($param, 'page=') === 0) {
          unset($params_array[$key]);
        }
      }
      $current_params = implode('&', $params_array);
    }
    return $current_params;
  }
}
