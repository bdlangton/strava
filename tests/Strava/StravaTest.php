<?php

namespace Tests\Strava;

use Silex\WebTestCase;

/**
 * The StravaTest class.
 */
class StravaTest extends WebTestCase {

  /**
   * Create application.
   */
  public function createApplication() {
    $app = require __DIR__ . '/../../app/strava.php';
    $app['debug'] = TRUE;
    $app['session.test'] = TRUE;
    unset($app['exception_handler']);

    // Load the test php file (used for login/logout).
    if (file_exists(__DIR__ . "/../../../config/test.php")) {
      require_once __DIR__ . "/../../../config/test.php";
    }

    return $app;
  }

  /**
   * Test as anonymous.
   */
  public function testAnonymous() {
    $client = $this->createClient();
    $crawler = $client->request('GET', '/');

    // Test that there is a login button on the home page.
    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedOutHeader($crawler);
    $this->assertContains('Click the button below to login using Strava.', $crawler->filter('body')->text());

    // Test that the user gets redirected to the home page if they try to access
    // other pages.
    $client->request('GET', '/activities');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/records');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/data');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/column');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/jon');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/import');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
  }

  /**
   * Test the home page.
   */
  public function testHomePage() {
    $this->login();
    $client = $this->createClient();
    $crawler = $client->request('GET', '/');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
  }

  /**
   * Test the activities page.
   */
  public function testActivitiesPage() {
    $this->login();
    $client = $this->createClient();
    $crawler = $client->request('GET', '/activities');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->assertCount(1, $crawler->filter('ul.pagination'));
    $this->assertCount(4, $crawler->filter('input[name="workout[]"]'));
    $this->assertCount(1, $crawler->filter('th:contains("Elevation Gain (ft)")'));

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['format'] = 'metric';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('th:contains("Elevation Gain (m)")'));
    $form = $crawler->selectButton('submit')->form();
    $form['type'] = 'Ride';
    $crawler = $client->submit($form);
    $this->assertCount(0, $crawler->filter('input[name="workout[]"]'));
  }

  /**
   * Test the records page.
   */
  public function testRecordsPage() {
    $this->login();
    $client = $this->createClient();
    $crawler = $client->request('GET', '/records');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->assertCount(1, $crawler->filter('ul.pagination'));
    $this->assertCount(1, $crawler->filter('th:contains("Distance (mi)")'));

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['format'] = 'metric';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('th:contains("Distance (km)")'));
  }

  /**
   * Test the graphs page.
   */
  public function testGraphsPage() {
    $this->login();
    $client = $this->createClient();
    $crawler = $client->request('GET', '/data');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->assertCount(4, $crawler->filter('input[name="workout[]"]'));

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['type'] = 'Ride';
    $crawler = $client->submit($form);
    $this->assertCount(0, $crawler->filter('input[name="workout[]"]'));
  }

  /**
   * Test the charts page.
   */
  public function testChartsPage() {
    $this->login();
    $client = $this->createClient();
    $crawler = $client->request('GET', '/column');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
  }

  /**
   * Test the Jon score page.
   */
  public function testJonScorePage() {
    $this->login();
    $client = $this->createClient();
    $crawler = $client->request('GET', '/jon');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
  }

  /**
   * Test the import/update page.
   */
  public function testImportUpdatePage() {
    $this->login();
    $client = $this->createClient();
    $crawler = $client->request('GET', '/import');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
  }

  /**
   * Login function.
   */
  private function login() {
    if (function_exists('test_login')) {
      test_login($this->app);
    }
  }

  /**
   * Logout function.
   */
  private function logout() {
    if (function_exists('test_logout')) {
      test_logout($this->app);
    }
  }

  /**
   * Verify logged in header links.
   */
  private function verifyLoggedInHeader($crawler) {
    $this->assertCount(1, $crawler->filter('div.header h1:contains("Data Analytics")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("My Activities")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("KOMs and PRs")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("General Graphs")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Column Charts")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Jon Score Graph")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Import/Update")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Logout")'));
  }

  /**
   * Verify logged out header links.
   */
  private function verifyLoggedOutHeader($crawler) {
    $this->assertCount(1, $crawler->filter('div.header h1:contains("Data Analytics")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("My Activities")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("KOMs and PRs")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("General Graphs")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("Column Charts")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("Jon Score Graph")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("Import/Update")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("Logout")'));
  }

  /**
   * Verify that a form exists with a submit button.
   */
  private function verifyFormExists($crawler) {
    $this->assertCount(1, $crawler->filter('form'));
    $this->assertCount(1, $crawler->filter('form input[name="submit"]'));
  }

}
