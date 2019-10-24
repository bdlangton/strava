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

}
