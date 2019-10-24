<?php

namespace App\Tests\Controller;

/**
 * The AuthControllerTest class.
 */
class AuthControllerTest extends BaseControllerTestCase {

  /**
   * Test authentication.
   */
  public function testAuth() {
    $client = static::createClient();
    $this->login($client);

    $this->markTestIncomplete(
      'This test has not been implemented yet.'
    );
  }

}
