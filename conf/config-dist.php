<?php
/**
 * Config template
 */
define('GEOIP_DOCROOT', __DIR__.DIRECTORY_SEPARATOR."..");
define('GEOIP_INC', GEOIP_DOCROOT.DIRECTORY_SEPARATOR.'inc');
define('GEOIP_DB_PATH', '/usr/share/GeoIP');
define('GEOIP_DB_ARCHIVE_PATH', '/usr/share/GeoIP_Archives');
define('GEOIP_DB_TMP_PATH', '/tmp/GeoIP');

define('DISABLE_GEOIP_UPDATER', false);
define('MAX_ARCHIVED_DB_VERSIONS', 10);

define('IP_LIST_CSV', GEOIP_INC.DIRECTORY_SEPARATOR.'ip_list.csv');
define('DB_URL_LIST_CSV', GEOIP_INC.DIRECTORY_SEPARATOR.'db_url_list.csv');