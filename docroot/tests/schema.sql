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
	(6713696,391930,'09/24/2011 Amarillo, TX',NULL,25743.2,8731,8753,98.4,'Run','2011-09-24','2011-09-24','(GMT-06:00) America/Chicago','Amarillo','TX','United States',NULL,NULL,0,0,0,2.948,3.44,NULL,NULL,NULL,156.9,169),
	(6682959,391930,'Palo Duro Canyon',NULL,19915.6,7612,7612,162.7,'Run','2012-03-31','2012-03-31','(GMT-06:00) America/Chicago','Canyon','TX','United States',NULL,NULL,0,0,0,2.616,3.679,NULL,NULL,NULL,NULL,NULL),
	(6981313,391930,'White Rock Marathon - Dallas, TX',NULL,42443.1,16662,16822,125.8,'Run','2010-12-05','2010-12-05','(GMT-06:00) America/Chicago','Dallas','TX','United States',0,0,0,0,1,2.523,4.152,NULL,NULL,NULL,NULL,NULL),
	(260346766,391930,'Treadmill incline workout',NULL,7023.4,2700,2700,0,'Run','2015-02-27','2015-02-26','(GMT-07:00) America/Denver',NULL,NULL,'United States',1,NULL,0,0,3,2.601,2.9,NULL,81.4,NULL,NULL,NULL),
	(264613438,391930,'Commuting',NULL,5954.6,1080,1080,0,'Ride','2015-03-08','2015-03-07','(GMT-07:00) America/Denver',NULL,NULL,'United States',NULL,NULL,1,1,0,5.513,0,NULL,NULL,NULL,NULL,NULL),
	(266239562,391930,'Long route',NULL,8387.6,1412,33381,34,'Ride','2015-03-10','2015-03-10','(GMT-07:00) America/Denver','Denver','Colorado','United States',NULL,1,0,0,0,5.94,11.8,NULL,NULL,82,NULL,NULL);

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
	(133586263,1262478,'Juniper Cliffside',6682959,391930,1672,1672,'2012-03-31','2012-03-31',4268.37,NULL,NULL,NULL,NULL,NULL,NULL),
	(133840859,1262454,'Sunflower',6682959,391930,419,419,'2012-03-31','2012-03-31',1208.11,NULL,NULL,NULL,NULL,NULL,NULL),
	(146920085,1333737,'Juniper Riverside',6682959,391930,319,319,'2012-03-31','2012-03-31',945.927,NULL,NULL,NULL,NULL,NULL,NULL),
	(347563727,2184496,'PD50 - Start to Middle',6682959,391930,2132,2132,'2012-03-31','2012-03-31',5945.33,NULL,NULL,NULL,NULL,NULL,NULL),
	(347564374,2184511,'PD50 - Middle to Finish',6682959,391930,1343,1343,'2012-03-31','2012-03-31',3784.42,NULL,NULL,NULL,NULL,NULL,NULL),
	(347564378,2184508,'PD50 - Water Crossing to Middle',6682959,391930,613,613,'2012-03-31','2012-03-31',1879.16,NULL,NULL,NULL,NULL,NULL,NULL),
	(347564805,2184525,'PD50 - Middle to Senoritas',6682959,391930,1442,1442,'2012-03-31','2012-03-31',3643.06,NULL,NULL,NULL,NULL,NULL,NULL),
	(44136730332,18490582,'Lighthouse Trailhead to GSL',6682959,391930,1092,1092,'2012-03-31','2012-03-31',2899,NULL,NULL,NULL,NULL,NULL,NULL),
	(125090541,1226420,'Paseo Del Rio',6682959,391930,537,537,'2012-03-31','2012-03-31',1662.41,NULL,NULL,NULL,NULL,NULL,NULL),
	(125091566,1226424,'Rojo Grande',6682959,391930,634,634,'2012-03-31','2012-03-31',1763.88,NULL,NULL,NULL,NULL,NULL,NULL),
	(188981804,1552604,'Winfrey Point to Garland',6981313,391930,890,890,'2010-12-05','2010-12-05',1969.59,NULL,NULL,NULL,NULL,NULL,NULL),
	(312004691,2007411,'The Mason-Dixon',6981313,391930,110,110,'2010-12-05','2010-12-05',311.488,NULL,NULL,NULL,NULL,NULL,NULL),
	(509301425,2779903,'WRL East Side Mile (start/end on markers)',6981313,391930,710,710,'2010-12-05','2010-12-05',1679.19,NULL,NULL,NULL,NULL,NULL,NULL),
	(1322723749,4792739,'Dallas Marathon 2011 (Swiss Ave Finish Seg))',6981313,391930,1714,1714,'2010-12-05','2010-12-05',3090.25,NULL,NULL,NULL,NULL,NULL,NULL),
	(1322727694,4792758,'Dallas Marathon 2011 (Final Seg to CB)',6981313,391930,1048,1048,'2010-12-05','2010-12-05',2293.29,NULL,NULL,NULL,NULL,NULL,NULL),
	(1347412651,4855679,'Spillway Descent- HOW FAST CAN YOU RUN?',6981313,391930,119,119,'2010-12-05','2010-12-05',303.051,NULL,NULL,NULL,NULL,NULL,NULL),
	(1514989705,5185380,'WR WOODBRIDGE',6981313,391930,54,54,'2010-12-05','2010-12-05',159.699,NULL,NULL,NULL,NULL,NULL,NULL),
	(1732200013,5584414,'Exall Lake North (via Trail/Lakeside Dr)',6981313,391930,520,520,'2010-12-05','2010-12-05',1660,NULL,NULL,NULL,NULL,NULL,NULL),
	(1732242599,5584462,'Turtle Creek (R E Lee to Avondale)',6981313,391930,359,359,'2010-12-05','2010-12-05',1163.1,NULL,NULL,NULL,NULL,NULL,NULL),
	(1778357922,5657354,'White Rock Lake (DM 2011)',6981313,391930,5647,5647,'2010-12-05','2010-12-05',14421.6,NULL,NULL,NULL,NULL,NULL,NULL);

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
