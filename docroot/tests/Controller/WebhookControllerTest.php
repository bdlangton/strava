<?php

namespace App\Tests\Controller;

/**
 * The WebhookControllerTest class.
 */
class WebhookControllerTest extends BaseControllerTestCase {

  /**
   * Test webhook GET.
   */
  public function testWebhook() {
    $client = static::createClient();
    $this->login($client);

    $this->markTestIncomplete(
      'This test has not been implemented yet.'
    );
  }

  /**
   * Test webhook POST.
   */
  public function testWebhookPost() {
    $client = static::createClient();
    $this->login($client);

    $this->markTestIncomplete(
      'This test has not been implemented yet.'
    );
  }

}
