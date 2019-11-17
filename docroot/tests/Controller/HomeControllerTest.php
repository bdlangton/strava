<?php

namespace App\Tests\Controller;

/**
 * The HomeControllerTest class.
 */
class HomeControllerTest extends BaseControllerTestCase {

  /**
   * Test as anonymous.
   */
  public function testAnonymous() {
    $client = static::createClient();
    $crawler = $client->request('GET', '/');

    // Test that the header shows they are logged out and that there is a login
    // // button on the home page.
    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedOutHeader($crawler);
    $this->assertCount(1, $crawler->filter('a.login'));

    // Test that the user gets redirected to the home page if they try to access
    // other pages.
    $client->request('GET', '/activities');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/records');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/data');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/column');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/jon');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/import');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
    $client->request('GET', '/user');
    $this->assertTrue($client->getResponse()->isRedirect('/'));
  }

  /**
   * Test the home page.
   */
  public function testHomePage() {
    $client = static::createClient();
    $this->login($client);
    $crawler = $client->request('GET', '/');

    $this->assertTrue($client->getResponse()->isOk());
    $this->verifyLoggedInHeader($crawler);
  }

}
