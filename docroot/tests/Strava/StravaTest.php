<?php

namespace Tests\Strava;

use App\Strava\Strava;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * The StravaTest class.
 */
class StravaTest extends TestCase {

  /**
   * Test convertDistance.
   */
  public function testConvertDistance() {
    $connection = $this->createMock(Connection::class);
    $strava = new Strava($connection);

    $distance = 2500000;
    $result = $strava->convertDistance($distance, 'imperial');
    $this->assertEquals($result, '1,553.4');
    $result = $strava->convertDistance($distance, 'imperial', FALSE);
    $this->assertEquals($result, '1553.4');
    $result = $strava->convertDistance($distance, 'metric');
    $this->assertEquals($result, '2,500.0');
  }

  /**
   * Test convertElevationGain.
   */
  public function testConvertElevationGain() {
    $connection = $this->createMock(Connection::class);
    $strava = new Strava($connection);

    $elevation_gain = 1000;
    $result = $strava->convertElevationGain($elevation_gain, 'imperial');
    $this->assertEquals($result, '3,281');
    $result = $strava->convertElevationGain($elevation_gain, 'imperial', FALSE);
    $this->assertEquals($result, '3281');
    $result = $strava->convertElevationGain($elevation_gain, 'metric');
    $this->assertEquals($result, '1,000');
  }

  /**
   * Test convertTimeFormat.
   */
  public function testConvertTimeFormat() {
    $connection = $this->createMock(Connection::class);
    $strava = new Strava($connection);

    $time = '12500';
    $result = $strava->convertTimeFormat($time, 'H:i:s');
    $this->assertEquals($result, '03:28:20');
    $time = '75';
    $result = $strava->convertTimeFormat($time, 'i:s');
    $this->assertEquals($result, '01:15');
  }

  /**
   * Test convertDateFormat.
   */
  public function testConvertDateFormat() {
    $connection = $this->createMock(Connection::class);
    $strava = new Strava($connection);

    $date = '2019-09-22 11:00:00PM';
    $result = $strava->convertDateFormat($date, 'M d, Y');
    $this->assertEquals($result, 'Sep 22, 2019');
    $result = $strava->convertDateFormat($date, 'Y-m-d');
    $this->assertEquals($result, '2019-09-22');
  }

}
