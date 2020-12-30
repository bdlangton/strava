USE strava;

DROP TABLE IF EXISTS `activities`;

CREATE TABLE `activities` (
  `id` bigint(22) NOT NULL,
  `athlete_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `distance` float DEFAULT NULL,
  `moving_time` int(11) DEFAULT NULL,
  `elapsed_time` int(11) DEFAULT NULL,
  `total_elevation_gain` float DEFAULT NULL,
  `type` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `start_date_local` date DEFAULT NULL,
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `type` (`type`),
  KEY `start_date_local` (`start_date_local`),
  KEY `athlete_id` (`athlete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `activities` WRITE;
/*!40000 ALTER TABLE `activities` DISABLE KEYS */;

INSERT INTO `activities` (`id`, `athlete_id`, `name`, `description`, `distance`, `moving_time`, `elapsed_time`, `total_elevation_gain`, `type`, `start_date`, `start_date_local`, `timezone`, `location_city`, `location_state`, `location_country`, `trainer`, `commute`, `manual`, `private`, `workout_type`, `average_speed`, `max_speed`, `calories`, `average_cadence`, `average_watts`, `average_heartrate`, `max_heartrate`)
VALUES
	(6981313,391930,'White Rock Marathon - Dallas, TX',NULL,42443.1,16662,16822,125.8,'Run','2010-12-05','2010-12-05','(GMT-06:00) America/Chicago','Dallas','TX','United States',0,0,0,0,1,2.523,4.152,NULL,NULL,NULL,NULL,NULL);

/*!40000 ALTER TABLE `activities` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table athletes
# ------------------------------------------------------------

DROP TABLE IF EXISTS `athletes`;

CREATE TABLE `athletes` (
  `id` int(11) NOT NULL DEFAULT '0',
  `access_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_activity_type` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Run',
  `default_format` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imperial',
  `refresh_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_expires` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `athletes` WRITE;
/*!40000 ALTER TABLE `athletes` DISABLE KEYS */;

INSERT INTO `athletes` (`id`, `access_token`, `default_activity_type`, `default_format`, `refresh_token`, `token_expires`)
VALUES
	(391930,'ac89da2f78ee4081649a5f897f57eef334f0d0b0','All','imperial','5247be2a3a26a96bce55fbadf402ff35de73a155',1606698900);

/*!40000 ALTER TABLE `athletes` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table segment_efforts
# ------------------------------------------------------------

DROP TABLE IF EXISTS `segment_efforts`;

CREATE TABLE `segment_efforts` (
  `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
  `segment_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activity_id` bigint(22) DEFAULT NULL,
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
  PRIMARY KEY (`id`),
  KEY `segment_id` (`segment_id`,`athlete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `segment_efforts` WRITE;
/*!40000 ALTER TABLE `segment_efforts` DISABLE KEYS */;

INSERT INTO `segment_efforts` (`id`, `segment_id`, `name`, `activity_id`, `athlete_id`, `elapsed_time`, `moving_time`, `start_date`, `start_date_local`, `distance`, `average_cadence`, `average_watts`, `average_heartrate`, `max_heartrate`, `kom_rank`, `pr_rank`)
VALUES
	(188981804,1552604,'Winfrey Point to Garland',6981313,391930,890,890,'2010-12-05','2010-12-05',1969.59,NULL,NULL,NULL,NULL,NULL,NULL);

/*!40000 ALTER TABLE `segment_efforts` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table segments
# ------------------------------------------------------------

DROP TABLE IF EXISTS `segments`;

CREATE TABLE `segments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activity_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distance` float DEFAULT NULL,
  `average_grade` float DEFAULT NULL,
  `maximum_grade` float DEFAULT NULL,
  `elevation_high` float DEFAULT NULL,
  `elevation_low` float DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `climb_category` int(11) DEFAULT NULL,
  `private` tinyint(1) DEFAULT NULL,
  `total_elevation_gain` float DEFAULT NULL,
  `effort_count` int(11) DEFAULT NULL,
  `athlete_count` int(11) DEFAULT NULL,
  `hazardous` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `segments` WRITE;
/*!40000 ALTER TABLE `segments` DISABLE KEYS */;

INSERT INTO `segments` (`id`, `name`, `activity_type`, `distance`, `average_grade`, `maximum_grade`, `elevation_high`, `elevation_low`, `city`, `state`, `country`, `climb_category`, `private`, `total_elevation_gain`, `effort_count`, `athlete_count`, `hazardous`)
VALUES
	(1552604,'Winfrey Point to Garland','Run',1958.76,0,2,141.3,141.3,'Dallas','TX','United States',0,0,NULL,NULL,NULL,0);

/*!40000 ALTER TABLE `segments` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table starred_segments
# ------------------------------------------------------------

DROP TABLE IF EXISTS `starred_segments`;

CREATE TABLE `starred_segments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `segment_id` int(11) DEFAULT NULL,
  `athlete_id` int(11) DEFAULT NULL,
  `starred_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `segment_id` (`segment_id`,`athlete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `starred_segments` WRITE;
/*!40000 ALTER TABLE `starred_segments` DISABLE KEYS */;

INSERT INTO `starred_segments` (`id`, `segment_id`, `athlete_id`, `starred_date`)
VALUES
	(1,1552604,391930,'2013-10-07');

/*!40000 ALTER TABLE `starred_segments` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table state
# ------------------------------------------------------------

DROP TABLE IF EXISTS `state`;

CREATE TABLE `state` (
  `name_long` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Common Name',
  `name_short` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'USPS Abbreviation'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='US States';

LOCK TABLES `state` WRITE;
/*!40000 ALTER TABLE `state` DISABLE KEYS */;

INSERT INTO `state` (`name_long`, `name_short`)
VALUES
	('Alabama','AL'),
	('Alaska','AK'),
	('Arizona','AZ'),
	('Arkansas','AR'),
	('California','CA'),
	('Colorado','CO'),
	('Connecticut','CT'),
	('Delaware','DE'),
	('Florida','FL'),
	('Georgia','GA'),
	('Hawaii','HI'),
	('Idaho','ID'),
	('Illinois','IL'),
	('Indiana','IN'),
	('Iowa','IA'),
	('Kansas','KS'),
	('Kentucky','KY'),
	('Louisiana','LA'),
	('Maine','ME'),
	('Maryland','MD'),
	('Massachusetts','MA'),
	('Michigan','MI'),
	('Minnesota','MN'),
	('Mississippi','MS'),
	('Missouri','MO'),
	('Montana','MT'),
	('Nebraska','NE'),
	('Nevada','NV'),
	('New Hampshire','NH'),
	('New Jersey','NJ'),
	('New Mexico','NM'),
	('New York','NY'),
	('North Carolina','NC'),
	('North Dakota','ND'),
	('Ohio','OH'),
	('Oklahoma','OK'),
	('Oregon','OR'),
	('Pennsylvania','PA'),
	('Rhode Island','RI'),
	('South Carolina','SC'),
	('South Dakota','SD'),
	('Tennessee','TN'),
	('Texas','TX'),
	('Utah','UT'),
	('Vermont','VT'),
	('Virginia','VA'),
	('Washington','WA'),
	('West Virginia','WV'),
	('Wisconsin','WI'),
	('Wyoming','WY'),
	('Alabama','Alabama'),
	('Alaska','Alaska'),
	('Arizona','Arizona'),
	('Arkansas','Arkansas'),
	('California','California'),
	('Colorado','Colorado'),
	('Connecticut','Connecticut'),
	('Delaware','Delaware'),
	('Florida','Florida'),
	('Georgia','Georgia'),
	('Hawaii','Hawaii'),
	('Idaho','Idaho'),
	('Illinois','Illinois'),
	('Indiana','Indiana'),
	('Iowa','Iowa'),
	('Kansas','Kansas'),
	('Kentucky','Kentucky'),
	('Louisiana','Louisiana'),
	('Maine','Maine'),
	('Maryland','Maryland'),
	('Massachusetts','Massachusetts'),
	('Michigan','Michigan'),
	('Minnesota','Minnesota'),
	('Mississippi','Mississippi'),
	('Missouri','Missouri'),
	('Montana','Montana'),
	('Nebraska','Nebraska'),
	('Nevada','Nevada'),
	('New Hampshire','New Hampshire'),
	('New Jersey','New Jersey'),
	('New Mexico','New Mexico'),
	('New York','New York'),
	('North Carolina','North Carolina'),
	('North Dakota','North Dakota'),
	('Ohio','Ohio'),
	('Oklahoma','Oklahoma'),
	('Oregon','Oregon'),
	('Pennsylvania','Pennsylvania'),
	('Rhode Island','Rhode Island'),
	('South Carolina','South Carolina'),
	('South Dakota','South Dakota'),
	('Tennessee','Tennessee'),
	('Texas','Texas'),
	('Utah','Utah'),
	('Vermont','Vermont'),
	('Virginia','Virginia'),
	('Washington','Washington'),
	('West Virginia','West Virginia'),
	('Wisconsin','Wisconsin'),
	('Wyoming','Wyoming');

/*!40000 ALTER TABLE `state` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table stats
# ------------------------------------------------------------

DROP TABLE IF EXISTS `stats`;

CREATE TABLE `stats` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `athlete_id` int(11) NOT NULL,
  `activity_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Run' COMMENT 'Run, Ride, etc.',
  `duration` int(11) NOT NULL DEFAULT '1' COMMENT 'Duration in days',
  `stat_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'distance' COMMENT 'Can be: distance, elevation_gain, time',
  `stat` float NOT NULL DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `excluding_races` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `athlete_id` (`athlete_id`),
  KEY `activity_type` (`activity_type`),
  KEY `stat_type` (`stat_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `stats` WRITE;
/*!40000 ALTER TABLE `stats` DISABLE KEYS */;

INSERT INTO `stats` (`id`, `athlete_id`, `activity_type`, `duration`, `stat_type`, `stat`, `start_date`, `end_date`, `excluding_races`)
VALUES
	(1,391930,'Run',7,'distance',142654,'2015-04-26','2015-05-02',1);

/*!40000 ALTER TABLE `stats` ENABLE KEYS */;
UNLOCK TABLES;
