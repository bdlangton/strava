CREATE DATABASE strava;

USE strava;

CREATE TABLE `activities` (
  `athlete_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` mediumtext,
  `distance` float DEFAULT NULL,
  `moving_time` int(11) DEFAULT NULL,
  `elapsed_time` int(11) DEFAULT NULL,
  `total_elevation_gain` float DEFAULT NULL,
  `type` varchar(25) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `start_date_local` date DEFAULT NULL,
  `timezone` varchar(255) DEFAULT NULL,
  `location_city` varchar(255) DEFAULT NULL,
  `location_state` varchar(255) DEFAULT NULL,
  `location_country` varchar(255) DEFAULT NULL,
  `trainer` tinyint(1) DEFAULT NULL,
  `commute` tinyint(1) DEFAULT NULL,
  `manual` tinyint(1) DEFAULT NULL,
  `private` tinyint(1) DEFAULT NULL,
  `workout_type` int(11) NOT NULL DEFAULT '0',
  `average_speed` float DEFAULT NULL,
  `max_speed` float DEFAULT NULL,
  `calories` float DEFAULT NULL,
  `average_cadence` float DEFAULT NULL,
  `average_watts` float DEFAULT NULL,
  `average_heartrate` float DEFAULT NULL,
  `max_heartrate` int(11) DEFAULT NULL,
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `athletes` (
  `id` int(11) NOT NULL DEFAULT '0',
  `access_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `segment_efforts` (
  `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
  `segment_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `activity_id` int(11) DEFAULT NULL,
  `athlete_id` int(11) DEFAULT NULL,
  `elapsed_time` int(11) DEFAULT NULL,
  `moving_time` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `start_date_local` date DEFAULT NULL,
  `distance` float DEFAULT NULL,
  `average_cadence` float DEFAULT NULL,
  `average_watts` float DEFAULT NULL,
  `average_heartrate` float DEFAULT NULL,
  `max_heartrate` int(11) DEFAULT NULL,
  `kom_rank` int(11) DEFAULT NULL,
  `pr_rank` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `segments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `activity_type` varchar(255) DEFAULT NULL,
  `distance` float DEFAULT NULL,
  `average_grade` float DEFAULT NULL,
  `maximum_grade` float DEFAULT NULL,
  `elevation_high` float DEFAULT NULL,
  `elevation_low` float DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `climb_category` int(11) DEFAULT NULL,
  `private` tinyint(1) DEFAULT NULL,
  `total_elevation_gain` float DEFAULT NULL,
  `effort_count` int(11) DEFAULT NULL,
  `athlete_count` int(11) DEFAULT NULL,
  `hazardous` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `state` (
  `name_long` varchar(20) NOT NULL DEFAULT '' COMMENT 'Common Name',
  `name_short` varchar(20) NOT NULL DEFAULT '' COMMENT 'USPS Abbreviation'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `stats` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `athlete_id` int(11) NOT NULL,
  `activity_type` varchar(255) NOT NULL DEFAULT 'Run' COMMENT 'Run, Ride, etc.',
  `duration` int(11) NOT NULL DEFAULT '1' COMMENT 'Duration in days',
  `stat_type` varchar(255) NOT NULL DEFAULT 'distance' COMMENT 'Can be: distance, elevation_gain, time',
  `stat` float NOT NULL DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `excluding_races` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

LOCK TABLES `activities` WRITE;
UNLOCK TABLES;

LOCK TABLES `athletes` WRITE;
INSERT INTO `athletes` (`id`, `access_token`, `default_activity_type`, `default_format`)
VALUES (391930,'','Run','imperial');
UNLOCK TABLES;

LOCK TABLES `segment_efforts` WRITE;
UNLOCK TABLES;

LOCK TABLES `segments` WRITE;
UNLOCK TABLES;

LOCK TABLES `state` WRITE;
INSERT INTO `state` VALUES ('Alabama','AL'),('Alaska','AK'),('Arizona','AZ'),('Arkansas','AR'),('California','CA'),('Colorado','CO'),('Connecticut','CT'),('Delaware','DE'),('Florida','FL'),('Georgia','GA'),('Hawaii','HI'),('Idaho','ID'),('Illinois','IL'),('Indiana','IN'),('Iowa','IA'),('Kansas','KS'),('Kentucky','KY'),('Louisiana','LA'),('Maine','ME'),('Maryland','MD'),('Massachusetts','MA'),('Michigan','MI'),('Minnesota','MN'),('Mississippi','MS'),('Missouri','MO'),('Montana','MT'),('Nebraska','NE'),('Nevada','NV'),('New Hampshire','NH'),('New Jersey','NJ'),('New Mexico','NM'),('New York','NY'),('North Carolina','NC'),('North Dakota','ND'),('Ohio','OH'),('Oklahoma','OK'),('Oregon','OR'),('Pennsylvania','PA'),('Rhode Island','RI'),('South Carolina','SC'),('South Dakota','SD'),('Tennessee','TN'),('Texas','TX'),('Utah','UT'),('Vermont','VT'),('Virginia','VA'),('Washington','WA'),('West Virginia','WV'),('Wisconsin','WI'),('Wyoming','WY'),('Alabama','Alabama'),('Alaska','Alaska'),('Arizona','Arizona'),('Arkansas','Arkansas'),('California','California'),('Colorado','Colorado'),('Connecticut','Connecticut'),('Delaware','Delaware'),('Florida','Florida'),('Georgia','Georgia'),('Hawaii','Hawaii'),('Idaho','Idaho'),('Illinois','Illinois'),('Indiana','Indiana'),('Iowa','Iowa'),('Kansas','Kansas'),('Kentucky','Kentucky'),('Louisiana','Louisiana'),('Maine','Maine'),('Maryland','Maryland'),('Massachusetts','Massachusetts'),('Michigan','Michigan'),('Minnesota','Minnesota'),('Mississippi','Mississippi'),('Missouri','Missouri'),('Montana','Montana'),('Nebraska','Nebraska'),('Nevada','Nevada'),('New Hampshire','New Hampshire'),('New Jersey','New Jersey'),('New Mexico','New Mexico'),('New York','New York'),('North Carolina','North Carolina'),('North Dakota','North Dakota'),('Ohio','Ohio'),('Oklahoma','Oklahoma'),('Oregon','Oregon'),('Pennsylvania','Pennsylvania'),('Rhode Island','Rhode Island'),('South Carolina','South Carolina'),('South Dakota','South Dakota'),('Tennessee','Tennessee'),('Texas','Texas'),('Utah','Utah'),('Vermont','Vermont'),('Virginia','Virginia'),('Washington','Washington'),('West Virginia','West Virginia'),('Wisconsin','Wisconsin'),('Wyoming','Wyoming');
UNLOCK TABLES;

LOCK TABLES `stats` WRITE;
UNLOCK TABLES;
