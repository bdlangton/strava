<?php

/**
 * @file
 * Strava test functionality.
 */

namespace Strava\Tests;

use Silex\WebTestCase;

/**
 * The StravaTest class.
 */
class StravaTest extends WebTestCase {

  /**
   * Create application.
   */
  public function createApplication() {
    $app = require __DIR__ . '/../../../app/strava.php';
    $app['debug'] = TRUE;
    $app['session.test'] = TRUE;
    unset($app['exception_handler']);

    return $app;
  }

  /**
   * Test the home page.
   */
  public function testHomePage() {
    $client = $this->createClient();
    $crawler = $client->request('GET', '/');

    $this->assertTrue($client->getResponse()->isOk());
    $this->assertCount(1, $crawler->filter('h1:contains("Barrett\'s Strava App")'));
  }
}
