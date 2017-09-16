#!/usr/bin/env php
<?php
$mysql_host = getenv('strava_db_options_host');
$mysql_user = getenv('strava_db_options_user');
$mysql_password = getenv('strava_db_options_password');
$connection_string = "mysql:host={$mysql_host}";
$db = new PDO($connection_string, $mysql_user, $mysql_password);
$schema = file_get_contents(dirname(__FILE__) . '/schema.sql');
$db->exec($schema);
