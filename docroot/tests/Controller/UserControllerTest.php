<?php

namespace App\Tests\Controller;

/**
 * The UserControllerTest class.
 */
class UserControllerTest extends BaseControllerTestCase {

  /**
   * Test the settings page.
   */
  public function testSettingsPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/user');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);

    $this->markTestIncomplete(
      'Add tests for saving the user settings.'
    );
  }

}
