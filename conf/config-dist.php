<?php
/**
 * Paths
 */
define('GEOIP_DOCROOT', __DIR__.DIRECTORY_SEPARATOR."..");
define('GEOIP_INC', GEOIP_DOCROOT.DIRECTORY_SEPARATOR.'inc');
define('GEOIP_RESOURCES', GEOIP_DOCROOT.DIRECTORY_SEPARATOR.'resources');

//GeoIP Lite config
define('GEOIP_LITE_DB_PATH', '/usr/share/GeoIP');
define('GEOIP_LITE_DB_ARCHIVE_PATH', '/usr/share/GeoIP_Archives/Lite');
define('GEOIP_LITE_DB_TMP_PATH', '/tmp/GeoIP/Legacy');

//GeoIP Legacy config
define('GEOIP_LEGACY_DB_PATH', '/usr/share/GeoIP/Legacy');
define('GEOIP_LEGACY_DB_ARCHIVE_PATH', '/usr/share/GeoIP_Archives/Legacy');
define('GEOIP_LEGACY_DB_TMP_PATH', '/tmp/GeoIP');

//GeoIP2 config
define('GEOIP2_DB_PATH', '/usr/share/GeoIP2');
define('GEOIP2_DB_ARCHIVE_PATH', '/usr/share/GeoIP2_Archives');
define('GEOIP2_DB_TMP_PATH', '/tmp/GeoIP2');

/**
 * Disables GeoIp Updater
 */
define('DISABLE_GEOIP_UPDATER', false);

/**
 * Maximum number of archived DB files versions
 */
define('MAX_ARCHIVED_DB_VERSIONS', 10);

/**
 * CSV file that lists validation items.
 */
define('VALIDATION_LIST_CSV', GEOIP_RESOURCES.DIRECTORY_SEPARATOR.'validation_list.csv');

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

define('MAXMIND_PHP_API_AUTOLOAD', '/home/nseddiki/geoip_tests/vendor/autoload.php');