<?php

namespace App\Tests\Controller;

/**
 * The RecordsControllerTest class.
 */
class RecordsControllerTest extends BaseControllerTestCase {

  /**
   * Test the records page.
   */
  public function testRecordsPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/records');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
    $this->assertCount(1, $crawler->filter('div.pagerfanta'));
    $this->assertCount(1, $crawler->filter('th a:contains("Distance (mi)")'));

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['form[format]'] = 'metric';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('th a:contains("Distance (km)")'));
  }

}
