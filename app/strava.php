<?php

/**
 * @file
 * Contains the majority of the strava app functionality.
 */

require_once __DIR__ . '/../app/Strava/StravaServiceProvider.php';
require_once __DIR__ . '/../app/Strava/Strava.php';

use Igorw\Silex\ConfigServiceProvider;
use Kilte\Silex\Pagination\PaginationServiceProvider;
use Silex\Application;
use Silex\Provider\AssetServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\LocaleServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Strava\ActivitiesControllerProvider;
use Strava\AuthControllerProvider;
use Strava\ChartsControllerProvider;
use Strava\GraphsControllerProvider;
use Strava\HomeControllerProvider;
use Strava\ImportControllerProvider;
use Strava\JonControllerProvider;
use Strava\RecordsControllerProvider;
use Strava\SegmentsControllerProvider;
use Strava\StatsControllerProvider;
use Strava\StravaServiceProvider;
use Strava\UserControllerProvider;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Application();
unset($app['exception_handler']);

// Get the app environment from the Apache config.
$env = getenv('APP_ENV') ?: 'dev';

// Register the session provider.
$app->register(new SessionServiceProvider(), [
  'session.storage.handler' => ($env == 'test' ? new NativeSessionHandler() : new NativeFileSessionHandler()),
  'session.storage.options' => [
    'cookie_lifetime' => 0,
  ],
]);

// Include the environment specific settings file.
if (file_exists(__DIR__ . "/../config/$env.php")) {
  require_once __DIR__ . "/../config/$env.php";
}

// Our custom strava service.
$app->register(new StravaServiceProvider());

// Register the pagination provider.
$app->register(new PaginationServiceProvider(), ['pagination.per_page' => 20]);

// Register the twig service provider.
$app->register(new TwigServiceProvider(), [
  'twig.path' => __DIR__ . '/../views',
  'twig.autoescape' => FALSE,
]);

// Register the asset service provider.
$app->register(new AssetServiceProvider(), [
  'assets.version' => 'v1',
  'assets.version_format' => '%s?version=%s',
  'assets.named_packages' => [
    'css' => ['version' => 'css2', 'base_path' => '/css'],
  ],
]);

// Register the form provider.
$app->register(new FormServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new LocaleServiceProvider());
$app->register(new TranslationServiceProvider());
$app['twig.form.templates'] = ['form.html'];

// Register the config service provider.
if (file_exists(__DIR__ . "/../config/$env.json")) {
  $app->register(new ConfigServiceProvider(__DIR__ . "/../config/$env.json"));
}
else {
  $app['debug'] = getenv('strava_debug');
  $app['client_id'] = getenv('strava_client_id');
  $app['client_secret'] = getenv('strava_client_secret');
  $app['db.options'] = [
    'dbname' => getenv('strava_db_options_dbname'),
    'user' => getenv('strava_db_options_user'),
    'password' => getenv('strava_db_options_password'),
    'host' => getenv('strava_db_options_host'),
    'driver' => getenv('strava_db_options_driver'),
  ];
}

// Register the doctrine service provider.
$app->register(new DoctrineServiceProvider(), []);

// Mount all of the controller providers.
$app->mount('/', new HomeControllerProvider());
$app->mount('/', new AuthControllerProvider());
$app->mount('/', new ImportControllerProvider());
$app->mount('/', new UserControllerProvider());
$app->mount('/', new ActivitiesControllerProvider());
$app->mount('/', new SegmentsControllerProvider());
$app->mount('/', new GraphsControllerProvider());
$app->mount('/', new ChartsControllerProvider());
$app->mount('/', new RecordsControllerProvider());
$app->mount('/', new StatsControllerProvider());
$app->mount('/', new JonControllerProvider());

// Handle errors.
$app->error(function (\Exception $e, Request $request, $code) use ($app) {
  // Redirect to home page on page not found.
  if ($code === 404) {
    return $app->redirect('/');
  }
  if ($app['debug']) {
    return new Response($e);
  }
});

return $app;
