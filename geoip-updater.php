<?php
/**
 * GeoIp Updater script
 * Updates the GeoIpLite DB files. Stores older versions, allows rolling back.
 * Must be executed as root!
 */
require_once('conf/config.php');
require_once('inc/GeoIPUpdater.php');
require_once('inc/Logger.php');

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
    $oGeoIpUpdater = new \GeoIpUpdater($oLogger);
    switch ($aOptions['m']) {
        case 'update' :
            $oGeoIpUpdater->update();
            break;
        case 'rollback' :
            $oGeoIpUpdater->rollback();
            break;
        default :
            echo "Please specifiy a mode. Usage : -m=update|rollback\n";
            break;
    }
} catch (\Exception $oException) {
    $oLogger->log("KO => ".$oException->getMessage());
}