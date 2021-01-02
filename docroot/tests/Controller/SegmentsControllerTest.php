<?php

namespace App\Tests\Controller;

/**
 * The SegmentsControllerTest class.
 */
class SegmentsControllerTest extends BaseControllerTestCase {

  /**
   * Test the segments page.
   */
  public function testSegmentPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/segments');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->assertCount(1, $crawler->filter('div.pagerfanta'));
    $this->assertCount(1, $crawler->filter('th a:contains("Distance (mi)")'));
    $this->assertCount(2, $crawler->filter('td.name'));

    // Test sorting.
    $link = $crawler->filter('th.distance a')->first()->link();
    $crawler = $client->click($link);
    $this->assertEquals($crawler->filter('td.distance')->first()->html(), '3.2');
    $link = $crawler->filter('th.name a')->first()->link();
    $crawler = $client->click($link);
    $this->assertEquals($crawler->filter('td.name a')->first()->html(), 'Climb for Independence');
    $link = $crawler->filter('th.location a')->first()->link();
    $crawler = $client->click($link);
    $this->assertEquals($crawler->filter('td.location')->first()->html(), 'Kittredge, Colorado');
    $link = $crawler->filter('th.date a')->first()->link();
    $crawler = $client->click($link);
    $this->assertEquals($crawler->filter('td.date')->first()->html(), 'May 05, 2020');
  }

  /**
   * Test the segment efforts page.
   */
  public function testSegmentEffortPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/segments');
    $this->assertTrue($client->getResponse()->isOk());

    // Navigate to a segment page.
    $segment = $crawler->filter('td.name a')->first();
    $name = $segment->html();
    $link = $segment->link();
    $crawler = $client->click($link);
    $this->assertTrue($client->getResponse()->isOk());
    $this->assertCount(1, $crawler->filter('h1:contains("Segment Efforts for ' . $name . '")'));
    $this->assertCount(1, $crawler->filter('table.segment_efforts, p:contains("There are no segment efforts on this segment.")'));
    $this->assertCount(2, $crawler->filter('td.activity_name'));

    // Test sorting.
    $link = $crawler->filter('th.time a')->first()->link();
    $crawler = $client->click($link);
    $this->assertEquals($crawler->filter('td.time a')->first()->html(), '00:45:40');
    $link = $crawler->filter('th.date a')->first()->link();
    $crawler = $client->click($link);
    $this->assertEquals($crawler->filter('td.date')->first()->html(), 'May 28, 2020');
  }

}
