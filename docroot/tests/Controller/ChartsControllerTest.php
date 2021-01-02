<?php

namespace App\Tests\Controller;

/**
 * The ChartsControllerTest class.
 */
class ChartsControllerTest extends BaseControllerTestCase {

  /**
   * Test the charts page.
   */
  public function testChartsPage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/column');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
    $this->verifyFormExists($crawler);
  }

  /**
   * Test the charts form.
   */
  public function testChartsForm() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/column');

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['form[begin_date]'] = '2010-01-01';
    $form['form[end_date]'] = '2019-03-01';
    $form['form[group]'] = 'week';
    $crawler = $client->submit($form);
    $this->assertTrue($client->getResponse()->isOk());
    $form['form[group]'] = 'year';
    $crawler = $client->submit($form);
    $this->assertTrue($client->getResponse()->isOk());
  }

  /**
   * Test the charts form invalid values.
   */
  public function testChartsFormInvalid() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/column');

    // Test the form.
    $form = $crawler->selectButton('submit')->form();
    $form['form[begin_date]'] = '2019-05-01';
    $form['form[end_date]'] = '2019-03-01';
    $crawler = $client->submit($form);
    $this->assertCount(1, $crawler->filter('li:contains("This value should be after the begin date.")'));
  }

}
