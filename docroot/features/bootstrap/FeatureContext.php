<?php

use Behat\Behat\Context\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use PHPUnit\Framework\Assert;

/**
 * This context class contains the definitions of the steps used by the demo
 * feature file. Learn how to get started with Behat and BDD on Behat's website.
 *
 * @see http://behat.org/en/latest/quick_start.html
 */
class FeatureContext implements Context
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var Response|null
     */
    private $response;

    public function __construct(KernelInterface $kernel, SessionInterface $session)
    {
        $this->kernel = $kernel;
        $this->session = $session;
    }

    /**
     * @When a demo scenario sends a request to :path
     */
    public function aDemoScenarioSendsARequestTo(string $path)
    {
        $this->response = $this->kernel->handle(Request::create($path, 'GET'));
    }

    /**
     * @Then the response should be received
     */
    public function theResponseShouldBeReceived()
    {
        if ($this->response === null) {
            throw new \RuntimeException('No response received');
        }
    }

    /**
     * @Given user is logged in
     */
    public function userIsLoggedIn()
    {
        $this->session->set('user', [
            'id' => getenv('strava_test_user_id'),
            'access_token' => getenv('strava_test_access_token'),
            'activity_type' => getenv('strava_test_user_activity_type') ?: 'Run',
            'format' => getenv('strava_test_user_format') ?: 'imperial',
        ]);
    }

    /**
     * @Given user is logged out
     */
    public function userIsLoggedOut()
    {
        $this->session->set('user', []);
    }

    /**
     * @Then the user can view charts
     */
    public function theUserCanViewCharts()
    {
        $this->response = $this->kernel->handle(Request::create('/column', 'GET'));
        $this->assertTrue($this->response->isOk());
    }

    /**
     * @When /^call "([^"]*)" "([^"]*)" with parameters:$/
     */
    public function callWithParameters($method, $endpoint, PyStringNode $postParametersStringNode)
    {
        $postParameters = json_decode($postParametersStringNode->getRaw(), TRUE);
        $this->response = $this->kernel->handle(Request::create($endpoint, $method, $postParameters));
    }

    /**
     * @Then /^response status is "([^"]*)"$/
     */
    public function responseStatusIs($statusCode)
    {
        Assert::assertEquals($statusCode, $this->response->getStatusCode());
    }

    /**
     * @Given /^collection "([^"]*)" having the following data:$/
     */
    public function collectionHavingTheFollowingData($collectionName, PyStringNode $dataStringNode)
    {
        $data = json_decode($dataStringNode->getRaw(), TRUE);
        foreach ($data as $document) {
            $this->app->storage[$collectionName][] = $document;
        }
    }

    /**
     * @When /^call "([^"]*)" "([^"]*)" with resource id "([^"]*)"$/
     */
    public function callWithResourceId($method, $endpoint, $resourceId)
    {
        $this->response = $this->kernel->handle(Request::create("$endpoint/{$resourceId}", $method));
    }

    /**
     * @Then /^response status should be "([^"]*)"$/
     */
    public function responseStatusShouldBe($statusCode)
    {
        return $this->responseStatusIs($statusCode);
    }

    /**
     * @Given /^json response should be:$/
     */
    public function jsonResponseShouldBe(PyStringNode $expectedResponseStringNode)
    {
        $clientResponse = json_decode($this->response->getContent(), TRUE);
        $expectedResponse = json_decode($expectedResponseStringNode->getRaw(), TRUE);
        Assert::assertEquals($expectedResponse, $clientResponse);
    }

    /**
     * @Given /^response content is blank$/
     */
    public function responseContentIsBlank()
    {
        Assert::assertEmpty($this->response->getContent());
    }

    /**
     * @When call ":method" ":endpoint"
     */
    public function callEndpoint($method, $endpoint)
    {
        $this->response = $this->kernel->handle(Request::create($endpoint, $method));
    }

    /**
     * @Then response content is ":content"
     */
    public function responseContentIs($content)
    {
        Assert::assertEquals($content, $this->response->getContent());
    }

}
