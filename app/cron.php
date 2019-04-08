<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/strava.php';

// Check for users that need their tokens refreshed.
$app['strava']->refreshTokens($app);
