<?php

namespace App\Tests\Controller;

/**
 * The JonControllerTest class.
 */
class JonControllerTest extends BaseControllerTestCase {

  /**
   * Test the Jon score page.
   */
  public function testJonScorePage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/jon');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
  }

  /**
   * Test the Jon score form.
   */
  public function testJonScoreForm() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/jon');

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['form[begin_date]'] = '2010-01-01';
    $form['form[end_date]'] = '2019-03-01';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('div#chart1'));
  }

  /**
   * Test the Jon score form invalid values.
   */
  public function testJonScoreFormInvalid() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/jon');

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['form[begin_date]'] = '2019-05-01';
    $form['form[end_date]'] = '2019-03-01';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('li:contains("This value should be after the begin date.")'));
  }

}
