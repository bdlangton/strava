<?php

namespace App\Tests\Controller;

/**
 * The ImportControllerTest class.
 */
class ImportControllerTest extends BaseControllerTestCase {

  /**
   * Test the import/update page.
   */
  public function testImportUpdatePage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/import');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->markTestIncomplete(
      'Test submitting the form.'
    );
  }

}
