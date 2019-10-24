<?php

namespace App\Tests\Controller;

/**
 * The JonControllerTest class.
 */
class JonControllerTest extends BaseControllerTestCase {

  /**
   * Test the Jon score page.
   */
  public function testJonScorePage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/jon');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->markTestIncomplete(
      'Test submitting the form.'
    );
  }

}
