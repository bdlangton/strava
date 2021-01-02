<?php

namespace App\Tests\Controller;

/**
 * The ActivitiesControllerTest class.
 */
class ActivitiesControllerTest extends BaseControllerTestCase {

  /**
   * Test the activities page.
   */
  public function testActivitiesPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/activities');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->assertCount(1, $crawler->filter('div.pagerfanta'));
    $this->assertCount(1, $crawler->filter('th a:contains("Elevation Gain (ft)")'));

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['form[format]'] = 'metric';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('th:contains("Elevation Gain (m)")'));
    $form = $crawler->selectButton('submit')->form();
    $form['form[type]'] = 'Ride';
    $crawler = $client->submit($form);
    $this->assertCount(0, $crawler->filter('input[name="workout[]"]'));

    // Test sorting.
    $distance = $crawler->filter('th.distance a')->first();
    $crawler = $client->click($distance->link());
    $this->assertEquals($crawler->filter('td.distance')->first()->html(), '26.4');
    $gain = $crawler->filter('th.elevation_gain a')->first();
    $crawler = $client->click($gain->link());
    $this->assertEquals($crawler->filter('td.elevation_gain')->first()->html(), '1,545');
    $date = $crawler->filter('th.date a')->first();
    $crawler = $client->click($date->link());
    $this->assertEquals($crawler->filter('td.date')->first()->html(), 'May 28, 2020');
  }

  /**
   * Test the individual activity page.
   */
  public function testActivityPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/activities');
    $this->assertTrue($client->getResponse()->isOk());

    // Navigate to an activity page.
    $activity = $crawler->filter('td.name a')->first();
    $name = $activity->html();
    $link = $activity->link();
    $crawler = $client->click($link);
    $this->assertTrue($client->getResponse()->isOk());
    $this->assertCount(1, $crawler->filter('h1:contains("' . $name . '")'));
    $this->assertCount(1, $crawler->filter('h2:contains("Segment Efforts"), p:contains("There are no segment efforts on this activity")'));
  }

}
