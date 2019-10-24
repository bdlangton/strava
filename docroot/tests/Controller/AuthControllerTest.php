<?php

namespace App\Tests\Controller;

/**
 * The AuthControllerTest class.
 */
class AuthControllerTest extends BaseControllerTestCase {

  /**
   * Test token exchange.
   */
  public function testTokenExchange() {
    $client = static::createClient();

    $this->markTestIncomplete(
      'This test has not been implemented yet.'
    );
  }

  /**
   * Test logout.
   */
  public function testLogout() {
    $client = static::createClient();
    $this->login($client);

    $this->markTestIncomplete(
      'This test has not been implemented yet.'
    );
  }

}
