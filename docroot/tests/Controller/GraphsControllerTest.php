<?php

namespace App\Tests\Controller;

/**
 * The GraphsControllerTest class.
 */
class GraphsControllerTest extends BaseControllerTestCase {

  /**
   * Test the graphs page.
   */
  public function testGraphsPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/data');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['form[type]'] = 'Ride';
    $crawler = $client->submit($form);
    $this->assertCount(0, $crawler->filter('input[name="workout[]"]'));
  }

}
