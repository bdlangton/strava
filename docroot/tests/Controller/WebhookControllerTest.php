<?php

namespace App\Tests\Controller;

/**
 * The WebhookControllerTest class.
 */
class WebhookControllerTest extends BaseControllerTestCase {

  /**
   * Test webhooks.
   */
  public function testWebhook() {
    $client = static::createClient();
    $this->login($client);

    $this->markTestIncomplete(
      'This test has not been implemented yet.'
    );
  }

}
