<?php
/**
 * GeoIp Updater script
 * Gathers options
 * Forges a GeoIPUpdater object
 * Run the required method
 * Catches exceptions and triggers PHP errors
 */

//Making sure user errors are reported and that php writes errors to stderr
error_reporting(error_reporting() | E_USER_ERROR);
ini_set('display_errors', 'stderr');

//Including vital stuff
require_once('conf/config.php');
require_once('inc/GeoIPUpdater/Factory.php');
require_once('inc/Logger.php');
require_once('inc/FileSystem.php');
require_once('inc/Csv.php');
require_once('inc/Gzip.php');

//Checking for on/off switch
if (defined('DISABLE_GEOIP_UPDATER') && DISABLE_GEOIP_UPDATER === true) {
    die('GeoIP Updater is disabled. See inc/config.php to enable.');
}

//CLI options
$sShortopts = "m:t:";  // Required value, mode
$sShortopts .= "v"; // Optional value, verbose
$aOptions = getopt($sShortopts);

//Available GeoIP modes
$aGeoIPModes = \GeoIpUpdater\Factory::getModes();

//Start
try {
    //Get GeoIP type from t option
    if (isset($aOptions['t']) && !in_array($aOptions['t'], $aGeoIPModes)) {
        throw new \Exception('Unknown GeoIP DB type. Available types are : '.implode(', ', $aGeoIPModes));
    } else {
        $sType = isset($aOptions['t'])?$aOptions['t']:null;
    }

    //Setup logger
    $oLogger = new \Logger();
    $oLogger->setVerbose(isset($aOptions['v'])?true:false);

    //Forge a GeoIP Updater object
    $oGeoIpUpdater = \GeoIpUpdater\Factory::forge($sType, $oLogger, new \FileSystem());
    switch ($aOptions['m']) {
        case 'update' :
            $oGeoIpUpdater->update();
            break;
        case 'rollback' :
            $oGeoIpUpdater->rollback();
            break;
        default :
            echo "Please specify a mode. Usage : -m=[update|rollback]\n";
            break;
    }
} catch (\Exception $oException) {
    // We want exceptions to trigger errors that will make PHP return a 255 code to shell.
    trigger_error($oException->getMessage(), E_USER_ERROR);
}