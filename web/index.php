<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Strava/StravaServiceProvider.php';
require_once __DIR__ . '/../app/Strava/Strava.php';

$strava = require __DIR__ . '/../app/strava.php';
$strava->run();
