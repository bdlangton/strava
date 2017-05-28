<?php

/**
 * This file provides a Strava Service Provider.
 */

namespace Strava;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Strava\Strava;

/**
 * Strava Service Provider.
 */
class StravaServiceProvider implements ServiceProviderInterface {

  /**
   * Register the service provider.
   */
  public function register(Container $app) {
    $app['strava'] = function ($app) {
      return new Strava();
    };
  }

}
