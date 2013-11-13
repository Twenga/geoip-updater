<?php
/**
 * Paths
 */
define('GEOIP_DOCROOT', __DIR__.DIRECTORY_SEPARATOR."..");
define('GEOIP_INC', GEOIP_DOCROOT.DIRECTORY_SEPARATOR.'inc');
define('GEOIP_DB_PATH', '/usr/share/GeoIP');
define('GEOIP_DB_ARCHIVE_PATH', '/usr/share/GeoIP_Archives');
define('GEOIP_DB_TMP_PATH', '/tmp/GeoIP');

/**
 * Allows to disable GeoIp Updater
 */
define('DISABLE_GEOIP_UPDATER', false);

/**
 * Maximum number of archived DB files versions
 */
define('MAX_ARCHIVED_DB_VERSIONS', 10);

/**
 * CSV file that lists validation items.
 */
define('VALIDATION_LIST_CSV', GEOIP_INC.DIRECTORY_SEPARATOR.'validation_list.csv');

/**
 * CSV file that lists the DB files sources
 */
define('DB_URL_LIST_CSV', GEOIP_INC.DIRECTORY_SEPARATOR.'db_url_list.csv');