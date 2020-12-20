<?php

namespace App\Tests\Controller;

/**
 * The StatsControllerTest class.
 */
class StatsControllerTest extends BaseControllerTestCase {

  /**
   * Test the stats page.
   */
  public function testStatsPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/big');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->assertCount(2, $crawler->filter('form select'));
    $this->assertCount(3, $crawler->filter('form input'));

    // Test the form by creating a new entry.
    $form = $crawler->selectButton('submit')->form();
    $form['form[type]'] = 'Run';
    $form['form[stat_type]'] = 'distance';
    $form['form[duration]'] = '22';
    $crawler = $client->submit($form);

    // Create an expected row array and a column array to specify what each
    // entry corresponds to.
    $expected_row = [
      'Running',
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

}
