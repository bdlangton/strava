<?php
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode;
use Symfony\Component\HttpKernel\Client;
use PHPUnit\Framework\Assert;

/**
 * Features context.
 */
class FeatureContext implements SnippetAcceptingContext
{
    /**
     * @var Silex\Application
     */
    protected $app;

    /**
     * @var \Symfony\Component\BrowserKit\Client
     */
    protected $client;

    /**
     * @BeforeScenario
     */
    public function setup($event)
    {
        putenv('APP_ENV=test');
        $app = require __DIR__ . '/../../app/strava.php';
        $app['debug'] = TRUE;
        unset($app['exception_handler']);
        $this->app = $app;
        $this->client = new Client($app);
    }

    /**
     * @Given user is logged in
     */
    public function userIsLoggedIn()
    {
        $this->app['session']->set('user', [
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
        $this->app['session']->set('user', []);
    }

    /**
     * @Then the user can view charts
     */
    public function theUserCanViewCharts()
    {
        $crawler = $this->client->request('GET', '/column');
        $this->assertTrue($client->getResponse()->isOk());
    }

    /**
     * @When /^call "([^"]*)" "([^"]*)" with parameters:$/
     */
    public function callWithParameters($method, $endpoint, PyStringNode $postParametersStringNode)
    {
        $postParameters = json_decode($postParametersStringNode->getRaw(), TRUE);
        $this->client->request($method, $endpoint, $postParameters);
    }

    /**
     * @Then /^response status is "([^"]*)"$/
     */
    public function responseStatusIs($statusCode)
    {
        Assert::assertEquals($statusCode, $this->client->getResponse()->getStatusCode());
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
        $this->client->request($method, "{$endpoint}/{$resourceId}");
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
        $clientResponse = json_decode($this->client->getResponse()->getContent(), TRUE);
        $expectedResponse = json_decode($expectedResponseStringNode->getRaw(), TRUE);
        Assert::assertEquals($expectedResponse, $clientResponse);
    }

    /**
     * @Given /^response content is blank$/
     */
    public function responseContentIsBlank()
    {
        Assert::assertEmpty($this->client->getResponse()->getContent());
    }

    /**
     * @When call ":method" ":endpoint"
     */
    public function callEndpoint($method, $endpoint)
    {
        $this->client->request($method, "{$endpoint}");
    }

    /**
     * @Then response content is ":content"
     */
    public function responseContentIs($content)
    {
        Assert::assertEquals($content, $this->client->getResponse()->getContent());
    }
}
