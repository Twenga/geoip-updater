<?php
/**
 * GeoIp Updater script
 * Updates the GeoIpLite DB files. Stores older versions, allows rolling back.
 * Must be executed as root!
 */
require_once('conf/config.php');
require_once('inc/GeoIPUpdater.php');
require_once('inc/Logger.php');
require_once('inc/FileSystem.php');
require_once('inc/Csv.php');
require_once('inc/Gzip.php');

//Making sure user errors are reported and that php writes errors to stderr
error_reporting(error_reporting() | E_USER_ERROR);
ini_set('display_errors', 'stderr');

//Checking for switch
if (defined('DISABLE_GEOIP_UPDATER') && DISABLE_GEOIP_UPDATER === true) {
    die('GeoIP Updater is disabled. See inc/config.php to enable.');
}

//CLI options
$sShortopts  = "";
$sShortopts .= "m:";  // Required value, mode
$sShortopts .= "v"; // Optional value, verbose
$aOptions = getopt($sShortopts);

//Start
try {
    $oLogger = new \Logger();
    $oLogger->setVerbose(isset($aOptions['v'])?true:false);
    $oGeoIpUpdater = new \GeoIpUpdater($oLogger, new \FileSystem());
    $oGeoIpUpdater->setDbFiles(\Csv::csvToArray(DB_URL_LIST_CSV))
        ->setValidationItems(\Csv::csvToArray(VALIDATION_LIST_CSV));
    switch ($aOptions['m']) {
        case 'update' :
            $oGeoIpUpdater->update(new \Gzip());
            break;
        case 'rollback' :
            $oGeoIpUpdater->rollback();
            break;
        default :
            echo "Please specifiy a mode. Usage : -m=update|rollback\n";
            break;
    }
} catch (\Exception $oException) {
    trigger_error($oException->getMessage(), E_USER_ERROR); // We want exceptions to trigger errors that will make PHP return a 255 code to shell.
}