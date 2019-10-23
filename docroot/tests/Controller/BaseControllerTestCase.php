<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The BaseControllerTest class.
 */
abstract class BaseControllerTestCase extends WebTestCase {

  /**
   * Login function.
   */
  protected function login($client) {
    $session = $client->getContainer()->get('session');
    $session->set('user', [
      'id' => getenv('strava_test_user_id'),
      'access_token' => getenv('strava_test_access_token'),
      'activity_type' => getenv('strava_test_user_activity_type') ?: 'Run',
      'format' => getenv('strava_test_user_format') ?: 'imperial',
    ]);
  }

  /**
   * Logout function.
   */
  protected function logout($client) {
    $session = $client->getContainer()->get('session');
    $session->set('user', []);
  }

  /**
   * Verify logged in header links.
   */
  protected function verifyLoggedInHeader($crawler) {
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
  protected function verifyLoggedOutHeader($crawler) {
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
  protected function verifyFormExists($crawler) {
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
  protected function findRowInTable(Crawler $rows, array $expected_row, array $columns) {
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
