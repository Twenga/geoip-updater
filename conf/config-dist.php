<?php
/**
 * Main paths
 */
define('GEOIP_DOCROOT', __DIR__.DIRECTORY_SEPARATOR."..");
define('GEOIP_INC', GEOIP_DOCROOT.DIRECTORY_SEPARATOR.'inc');
define('GEOIP_RESOURCES', GEOIP_DOCROOT.DIRECTORY_SEPARATOR.'resources');

/**
 * 3 Paths for each type of DB files :
 * - Final path where DB files will be loaded. THIS PATH MUST ALREADY EXIST.
 * - Archive path where each version of DB files will be archived (see MAX_ARCHIVED_DB_VERSIONS
 * to configure the number of stored archives)
 * - TMP path for storing intermediate files
 */
//GeoIP Lite
define('GEOIP_LITE_DB_PATH', '/usr/share/GeoIP');
define('GEOIP_LITE_DB_ARCHIVE_PATH', '/usr/share/GeoIP_Archives/Lite');
define('GEOIP_LITE_DB_TMP_PATH', '/tmp/GeoIP');

//GeoIP Legacy
define('GEOIP_LEGACY_DB_PATH', '/usr/share/GeoIP/Legacy');
define('GEOIP_LEGACY_DB_ARCHIVE_PATH', '/usr/share/GeoIP_Archives/Legacy');
define('GEOIP_LEGACY_DB_TMP_PATH', '/tmp/GeoIP/Legacy');

//GeoIP2
define('GEOIP2_DB_PATH', '/usr/share/GeoIP2');
define('GEOIP2_DB_ARCHIVE_PATH', '/usr/share/GeoIP2_Archives');
define('GEOIP2_DB_TMP_PATH', '/tmp/GeoIP2');

/**
 * Maximum number of archived DB files versions
 */
define('MAX_ARCHIVED_DB_VERSIONS', 10);

/**
 * Disables GeoIp Updater
 */
define('DISABLE_GEOIP_UPDATER', false);

/**
 * CSV file that lists validation items.
 */
define('VALIDATION_LIST_CSV', GEOIP_RESOURCES.DIRECTORY_SEPARATOR.'validation_list.csv');
define('VALIDATION_LIST_CSV_GEOIP2', GEOIP_RESOURCES.DIRECTORY_SEPARATOR.'validation_list_geoip2.csv');

/**
 * CSV file that lists the DB files sources
 */
define('DB_URL_LIST_CSV_LITE', GEOIP_RESOURCES.DIRECTORY_SEPARATOR.'db_url_list_lite.csv');
define('DB_URL_LIST_CSV_LEGACY', GEOIP_RESOURCES.DIRECTORY_SEPARATOR.'db_url_list_legacy.csv');
define('DB_URL_LIST_CSV_GEOIP2', GEOIP_RESOURCES.DIRECTORY_SEPARATOR.'db_url_list_geoip2.csv');

/**
 * Required for retrieving paid versions : Legacy, GeoIP2
 */
define('MAXMIND_LICENSE_KEY', '');

/**
 * Path to MaxMind PHP GeoIP2 API. This should point to a composer autoload.php.
 */
define('MAXMIND_PHP_API_AUTOLOAD', '');