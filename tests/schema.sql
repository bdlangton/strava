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

CREATE TABLE `starred_segments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `segment_id` int(11) DEFAULT NULL,
  `athlete_id` int(11) DEFAULT NULL,
  `starred_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `segment_id` (`segment_id`,`athlete_id`)
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
INSERT INTO `activities` (`athlete_id`, `name`, `description`, `distance`, `moving_time`, `elapsed_time`, `total_elevation_gain`, `type`, `start_date`, `start_date_local`, `timezone`, `location_city`, `location_state`, `location_country`, `trainer`, `commute`, `manual`, `private`, `workout_type`, `average_speed`, `max_speed`, `calories`, `average_cadence`, `average_watts`, `average_heartrate`, `max_heartrate`, `id`)
VALUES
	(391930,'04/12/2012 Amarillo, TX',NULL,9651.7,2968,2968,67.5,'Run','2012-04-12','2012-04-12','(GMT-06:00) America/Chicago','Amarillo','TX','United States',0,0,0,0,0,3.252,3.88,NULL,NULL,NULL,NULL,NULL,6682930),
	(391930,'04/03/2012 Amarillo, TX',NULL,6433.4,2054,2054,36.8,'Run','2012-04-03','2012-04-03','(GMT-06:00) America/Chicago','Amarillo','TX','United States',0,0,0,0,0,3.132,3.722,NULL,NULL,NULL,NULL,NULL,6682932),
	(391930,'04/11/2012 Amarillo, TX',NULL,14468.3,2292,2292,64.2,'Ride','2012-04-11','2012-04-11','(GMT-06:00) America/Chicago','Amarillo','TX','United States',0,0,0,0,0,6.313,12.381,NULL,NULL,NULL,NULL,NULL,6682935),
	(391930,'04/01/2012 Amarillo, TX',NULL,21559.1,3208,3269,117.1,'Ride','2012-04-01','2012-04-01','(GMT-06:00) America/Chicago','Amarillo','TX','United States',0,0,0,0,0,6.72,11.824,NULL,NULL,106.1,NULL,NULL,6682936);
UNLOCK TABLES;

LOCK TABLES `athletes` WRITE;
INSERT INTO `athletes` (`id`, `access_token`, `default_activity_type`, `default_format`)
VALUES (391930,'','Run','imperial');
UNLOCK TABLES;

LOCK TABLES `segment_efforts` WRITE;
UNLOCK TABLES;

LOCK TABLES `segments` WRITE;
UNLOCK TABLES;

LOCK TABLES `starred_segments` WRITE;
UNLOCK TABLES;

LOCK TABLES `state` WRITE;
INSERT INTO `state` VALUES ('Alabama','AL'),('Alaska','AK'),('Arizona','AZ'),('Arkansas','AR'),('California','CA'),('Colorado','CO'),('Connecticut','CT'),('Delaware','DE'),('Florida','FL'),('Georgia','GA'),('Hawaii','HI'),('Idaho','ID'),('Illinois','IL'),('Indiana','IN'),('Iowa','IA'),('Kansas','KS'),('Kentucky','KY'),('Louisiana','LA'),('Maine','ME'),('Maryland','MD'),('Massachusetts','MA'),('Michigan','MI'),('Minnesota','MN'),('Mississippi','MS'),('Missouri','MO'),('Montana','MT'),('Nebraska','NE'),('Nevada','NV'),('New Hampshire','NH'),('New Jersey','NJ'),('New Mexico','NM'),('New York','NY'),('North Carolina','NC'),('North Dakota','ND'),('Ohio','OH'),('Oklahoma','OK'),('Oregon','OR'),('Pennsylvania','PA'),('Rhode Island','RI'),('South Carolina','SC'),('South Dakota','SD'),('Tennessee','TN'),('Texas','TX'),('Utah','UT'),('Vermont','VT'),('Virginia','VA'),('Washington','WA'),('West Virginia','WV'),('Wisconsin','WI'),('Wyoming','WY'),('Alabama','Alabama'),('Alaska','Alaska'),('Arizona','Arizona'),('Arkansas','Arkansas'),('California','California'),('Colorado','Colorado'),('Connecticut','Connecticut'),('Delaware','Delaware'),('Florida','Florida'),('Georgia','Georgia'),('Hawaii','Hawaii'),('Idaho','Idaho'),('Illinois','Illinois'),('Indiana','Indiana'),('Iowa','Iowa'),('Kansas','Kansas'),('Kentucky','Kentucky'),('Louisiana','Louisiana'),('Maine','Maine'),('Maryland','Maryland'),('Massachusetts','Massachusetts'),('Michigan','Michigan'),('Minnesota','Minnesota'),('Mississippi','Mississippi'),('Missouri','Missouri'),('Montana','Montana'),('Nebraska','Nebraska'),('Nevada','Nevada'),('New Hampshire','New Hampshire'),('New Jersey','New Jersey'),('New Mexico','New Mexico'),('New York','New York'),('North Carolina','North Carolina'),('North Dakota','North Dakota'),('Ohio','Ohio'),('Oklahoma','Oklahoma'),('Oregon','Oregon'),('Pennsylvania','Pennsylvania'),('Rhode Island','Rhode Island'),('South Carolina','South Carolina'),('South Dakota','South Dakota'),('Tennessee','Tennessee'),('Texas','Texas'),('Utah','Utah'),('Vermont','Vermont'),('Virginia','Virginia'),('Washington','Washington'),('West Virginia','West Virginia'),('Wisconsin','Wisconsin'),('Wyoming','Wyoming');
UNLOCK TABLES;

LOCK TABLES `stats` WRITE;
UNLOCK TABLES;
