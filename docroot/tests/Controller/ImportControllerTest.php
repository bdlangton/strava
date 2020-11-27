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

    $form = $crawler->selectButton('submit')->form();
    $crawler = $client->submit($form);
    $this->assertTrue($client->getResponse()->isOk());

    $this->markTestIncomplete(
      'Test more than just that we get a 200 response.'
    );
  }

}
