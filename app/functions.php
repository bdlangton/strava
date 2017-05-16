<?php

define("DISTANCE_TO_MILES", 0.00062137);
define("DISTANCE_TO_KM", 0.001);
define("GAIN_TO_FEET", 3.28084);

/**
 * Convert distance depending on format.
 *
 * @param float $distance
 *   Distance provided by Strava.
 * @param string $format
 *   Imperial or metric.
 *
 * @return float
 *   Returns the distance in miles or meters.
 */
function convert_distance($distance, $format) {
  if ($format == 'imperial') {
    $distance = round($distance * DISTANCE_TO_MILES, 2);
  }
  else {
    $distance = round($distance * DISTANCE_TO_KM, 2);
  }
  return $distance;
}

/**
 * Convert elevation gain depending on format.
 *
 * @param float $elevation_gain
 *   Elevation gain provided by Strava.
 * @param string $format
 *   Imperial or metric.
 *
 * @return float
 *   Returns the elevation gain in feet or meters.
 */
function convert_elevation_gain($elevation_gain, $format) {
  if ($format == 'imperial') {
    $elevation_gain = round($elevation_gain * GAIN_TO_FEET);
  }
  else {
    $elevation_gain = round($elevation_gain);
  }
  return $elevation_gain;
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
