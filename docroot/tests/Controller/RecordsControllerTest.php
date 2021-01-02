<?php

namespace App\Tests\Controller;

/**
 * The RecordsControllerTest class.
 */
class RecordsControllerTest extends BaseControllerTestCase {

  /**
   * Test the records page.
   */
  public function testRecordsPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/records');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->assertCount(1, $crawler->filter('div.pagerfanta'));
    $this->assertCount(1, $crawler->filter('th a:contains("Distance (mi)")'));
  }

  /**
   * Test the records form.
   */
  public function testRecordsForm() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/records');

    // Test format.
    $form = $crawler->selectButton('submit')->form();
    $form['form[format]'] = 'metric';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('th a:contains("Distance (km)")'));

    // Test record types.
    $form['form[record]'] = 'PR';
    $crawler = $client->submit($form);
    $this->assertCount(3, $crawler->filter('td.date'));
    $form['form[record]'] = 'CR';
    $crawler = $client->submit($form);
    $this->assertCount(2, $crawler->filter('td.date'));
    $form['form[record]'] = 'Top10';
    $crawler = $client->submit($form);
    $this->assertCount(2, $crawler->filter('td.date'));

    // Test activity types.
    $form['form[record]'] = 'All';
    $form['form[type]'] = 'Run';
    $crawler = $client->submit($form);
    $this->assertCount(4, $crawler->filter('td.date'));

    // Test sorting.
    $crawler = $client->request('GET', '/records');
    $link = $crawler->filter('th.distance a')->first()->link();
    $crawler = $client->click($link);
    $this->assertEquals($crawler->filter('td.distance')->first()->html(), '6.3');
    $link = $crawler->filter('th.avg_grade a')->first()->link();
    $crawler = $client->click($link);
    $this->assertEquals($crawler->filter('td.avg_grade')->first()->html(), '8.4%');
    $link = $crawler->filter('th.max_grade a')->first()->link();
    $crawler = $client->click($link);
    $this->assertEquals($crawler->filter('td.max_grade')->first()->html(), '47.4%');
    $link = $crawler->filter('th.date a')->first()->link();
    $crawler = $client->click($link);
    $this->assertEquals($crawler->filter('td.date')->first()->html(), 'May 28, 2020');
  }

  /**
   * Test the records form invalid values.
   */
  public function testRecordsFormInvalid() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/records');

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['form[begin_date]'] = '2019-05-01';
    $form['form[end_date]'] = '2019-03-01';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('li:contains("This value should be after the begin date.")'));
  }

}
