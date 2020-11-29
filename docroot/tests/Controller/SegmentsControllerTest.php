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
    $this->markTestIncomplete(
      'Test submitting the form.'
    );
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
    $segment = $crawler->filter('td.segment a')->first();
    $name = $segment->html();
    $link = $segment->link();
    $crawler = $client->click($link);
    $this->assertTrue($client->getResponse()->isOk());
    $this->assertCount(1, $crawler->filter('h1:contains("Segment Efforts for ' . $name . '")'));
    $this->assertCount(1, $crawler->filter('table.segment_efforts, p:contains("There are no segment efforts on this segment.")'));
  }

}
