<?php

namespace App\Tests\Controller;

/**
 * The ChartsControllerTest class.
 */
class ChartsControllerTest extends BaseControllerTestCase {

  /**
   * Test the charts page.
   */
  public function testChartsPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/column');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
  }

}
