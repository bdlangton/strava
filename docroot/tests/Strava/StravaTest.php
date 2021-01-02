<?php

namespace Tests\Strava;

use App\Strava\Strava;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The StravaTest class.
 */
class StravaTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    $this->connection = $this->createMock(Connection::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->strava = new Strava($this->connection, $this->logger);
  }

  /**
   * Test convertDistance.
   */
  public function testConvertDistance() {
    $distance = 2500000;
    $result = $this->strava->convertDistance($distance, 'imperial');
    $this->assertEquals($result, '1,553.4');
    $result = $this->strava->convertDistance($distance, 'imperial', FALSE);
    $this->assertEquals($result, '1553.4');
    $result = $this->strava->convertDistance($distance, 'metric');
    $this->assertEquals($result, '2,500.0');
  }

  /**
   * Test convertElevationGain.
   */
  public function testConvertElevationGain() {
    $elevation_gain = 1000;
    $result = $this->strava->convertElevationGain($elevation_gain, 'imperial');
    $this->assertEquals($result, '3,281');
    $result = $this->strava->convertElevationGain($elevation_gain, 'imperial', FALSE);
    $this->assertEquals($result, '3281');
    $result = $this->strava->convertElevationGain($elevation_gain, 'metric');
    $this->assertEquals($result, '1,000');
  }

  /**
   * Test convertTimeFormat.
   */
  public function testConvertTimeFormat() {
    $time = '12500';
    $result = $this->strava->convertTimeFormat($time, 'H:i:s');
    $this->assertEquals($result, '03:28:20');
    $time = '75';
    $result = $this->strava->convertTimeFormat($time, 'i:s');
    $this->assertEquals($result, '01:15');
  }

  /**
   * Test convertDateFormat.
   */
  public function testConvertDateFormat() {
    $date = '2019-09-22 11:00:00PM';
    $result = $this->strava->convertDateFormat($date, 'M d, Y');
    $this->assertEquals($result, 'Sep 22, 2019');
    $result = $this->strava->convertDateFormat($date, 'Y-m-d');
    $this->assertEquals($result, '2019-09-22');
  }

  /**
   * Test convertActivityType.
   */
  public function testConvertActivityType() {
    $activity = [
      'type' => 'Run',
    ];
    $result = $this->strava->convertActivityType($activity);
    $this->assertEquals($result, 'Running');
    $activity['workout_type'] = 1;
    $result = $this->strava->convertActivityType($activity);
    $this->assertEquals($result, 'Running - Race');
    $activity['workout_type'] = 2;
    $result = $this->strava->convertActivityType($activity);
    $this->assertEquals($result, 'Running - Long Run');
    $activity['workout_type'] = 3;
    $result = $this->strava->convertActivityType($activity);
    $this->assertEquals($result, 'Running - Workout');
    $activity['workout_type'] = NULL;
    $activity['commute'] = 1;
    $result = $this->strava->convertActivityType($activity);
    $this->assertEquals($result, 'Running - Commute');
    $activity['commute'] = 0;
    $activity['trainer'] = 1;
    $result = $this->strava->convertActivityType($activity);
    $this->assertEquals($result, 'Running - Treadmill');

    $activity = [
      'type' => 'Ride',
      'workout_type' => 12,
    ];
    $result = $this->strava->convertActivityType($activity);
    $this->assertEquals($result, 'Cycling - Workout');
    $activity['workout_type'] = NULL;
    $activity['commute'] = 1;
    $result = $this->strava->convertActivityType($activity);
    $this->assertEquals($result, 'Cycling - Commute');
  }

  /**
   * Test getBeginAndEndDates.
   */
  public function testGetBeginAndEndDates() {
    // Get year and month values for testing.
    $year = date('Y');
    $month = date('m');

    $result = $this->strava->getBeginAndEndDates('month');
    $this->assertEquals($result['begin_date'], $year - 1 . '-' . $month . '-01');
    $this->assertEquals($result['end_date'], date('Y-m-d'));
    $result = $this->strava->getBeginAndEndDates('week');
    $this->assertEquals($result['begin_date'], $year - 1 . '-' . $month . '-01');
    $this->assertEquals($result['end_date'], date('Y-m-d'));
    $result = $this->strava->getBeginAndEndDates('year');
    $this->assertEquals($result['begin_date'], $year - 5 . '-01-01');
    $this->assertEquals($result['end_date'], date('Y-m-d'));
  }

}
