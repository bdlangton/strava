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
    $this->assertCount(1, $crawler->filter('h1:contains("' .  $name . '")'));
    $this->assertCount(1, $crawler->filter('h2:contains("Segment Efforts")'));
  }

}
