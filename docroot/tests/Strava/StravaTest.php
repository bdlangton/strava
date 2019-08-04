<?php

namespace Tests\Strava;

use Silex\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The StravaTest class.
 */
class StravaTest extends WebTestCase {

  /**
   * The Silex app.
   *
   * @var array
   */
  protected $app;

  /**
   * Create application.
   */
  public function createApplication() {
    $this->app = require __DIR__ . '/../../app/strava.php';
    $this->app['debug'] = TRUE;
    $this->app['session.test'] = TRUE;
    unset($this->app['exception_handler']);

    return $this->app;
  }

  /**
   * Test as anonymous.
   */
  public function testAnonymous() {
    $client = $this->createClient();
    $crawler = $client->request('GET', '/');

    // Test that the header shows they are logged out and that there is a login
    // button on the home page.
    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedOutHeader($crawler);
    $this->assertCount(1, $crawler->filter('a.login'));

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
    $this->assertCount(1, $crawler->filter('th a:contains("Elevation Gain (ft)")'));

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
    $this->assertCount(1, $crawler->filter('th a:contains("Distance (mi)")'));

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['format'] = 'metric';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('th a:contains("Distance (km)")'));
  }

  /**
   * Test the segments page.
   */
  public function testSegmentPage() {
    $this->login();
    $client = $this->createClient();
    $crawler = $client->request('GET', '/segments');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
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
   * Test the stats page.
   */
  public function testStatsPage() {
    $this->login();
    $client = $this->createClient();
    $crawler = $client->request('GET', '/big');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->assertCount(2, $crawler->filter('form select'));
    $this->assertCount(3, $crawler->filter('form input'));

    // Test the form by creating a new entry.
    $form = $crawler->selectButton('submit')->form();
    $form['type'] = 'Run';
    $form['stat_type'] = 'distance';
    $form['duration'] = '22';
    $crawler = $client->submit($form);

    // Create an expected row array and a column array to specify what each
    // entry corresponds to.
    $expected_row = [
      'Run',
      'Distance',
      '',
      '22 days',
    ];
    $columns = [
      'activity_type',
      'stat_type',
      'excluding_races',
      'duration',
    ];

    // Verify that the new entry shows up in the table.
    $rows = $crawler->filter('table tr');
    $match_row = $this->findRowInTable($rows, $expected_row, $columns);
    $this->assertNotEquals($match_row, FALSE);

    // Update the row that was just added and verify that it is still there.
    $link = $rows->eq($match_row)->filter('td.operations a.update')->link();
    $client->click($link);
    $crawler = $client->followRedirect();
    $rows = $crawler->filter('table tr');
    $match_row = $this->findRowInTable($rows, $expected_row, $columns);
    $this->assertNotEquals($match_row, FALSE);

    // Delete the row that was just added and verify that it is removed.
    $link = $rows->eq($match_row)->filter('td.operations a.delete')->link();
    $client->click($link);
    $crawler = $client->followRedirect();
    $rows = $crawler->filter('table tr');
    $match_row = $this->findRowInTable($rows, $expected_row, $columns);
    $this->assertEquals($match_row, FALSE);
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
   * Test the settings page.
   */
  public function testSettingsPage() {
    $this->login();
    $client = $this->createClient();
    $crawler = $client->request('GET', '/user');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
  }

  /**
   * Login function.
   */
  private function login() {
    $this->app['session']->set('user', [
      'id' => getenv('strava_test_user_id'),
      'access_token' => getenv('strava_test_access_token'),
      'activity_type' => getenv('strava_test_user_activity_type') ?: 'Run',
      'format' => getenv('strava_test_user_format') ?: 'imperial',
    ]);
  }

  /**
   * Logout function.
   */
  private function logout() {
    $this->app['session']->set('user', []);
  }

  /**
   * Verify logged in header links.
   */
  private function verifyLoggedInHeader($crawler) {
    $this->assertCount(1, $crawler->filter('div.header a:contains("Home")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("My Activities")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Graphs")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("KOMs and PRs")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Segments")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Biggest Stats")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Column Charts")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Jon Score")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Settings")'));
    $this->assertCount(1, $crawler->filter('div.header a:contains("Logout")'));
  }

  /**
   * Verify logged out header links.
   */
  private function verifyLoggedOutHeader($crawler) {
    $this->assertCount(1, $crawler->filter('div.header a:contains("Data Analytics")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("My Activities")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("Graphs")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("KOMs and PRs")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("Segments")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("Column Charts")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("Jon Score")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("Settings")'));
    $this->assertCount(0, $crawler->filter('div.header a:contains("Logout")'));
  }

  /**
   * Verify that a form exists with a submit button.
   */
  private function verifyFormExists($crawler) {
    $this->assertCount(1, $crawler->filter('form'));
    $this->assertCount(1, $crawler->filter('form input[name="submit"]'));
  }

  /**
   * Finds a matching row in a table.
   *
   * @param Symfony\Component\DomCrawler\Crawler $rows
   *   The rows of the table.
   * @param array $expected_row
   *   The expected results to get from the row.
   * @param array $columns
   *   Array indicating which column each row entry aligns with.
   *
   * @return mixed
   *   The row number if a row was found, FALSE if not found.
   */
  private function findRowInTable(Crawler $rows, array $expected_row, array $columns) {
    // Loop through each table row to find a match.
    foreach ($rows as $row_index => $values) {
      if ($rows->eq($row_index)->filter('td')->count() > 0) {
        $row = [];
        foreach ($columns as $key => $column) {
          $row[$key] = $rows->eq($row_index)->filter('td.' . $columns[$key])->text();
        }
        if (!array_diff($row, $expected_row)) {
          return $row_index;
        }
      }
    }
    return FALSE;
  }

}
