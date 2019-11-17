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
  }

  /**
   * Test the graphs page form.
   */
  public function testGraphsForm() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/data');

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['form[type]'] = 'Ride';
    $crawler = $client->submit($form);
    $this->assertCount(0, $crawler->filter('input[name="workout[]"]'));
  }

  /**
   * Test the graphs form invalid values.
   */
  public function testGraphsFormInvalid() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/data');

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['form[begin_date]'] = '2019-05-01';
    $form['form[end_date]'] = '2019-03-01';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('li:contains("This value should be after the begin date.")'));
  }


}
