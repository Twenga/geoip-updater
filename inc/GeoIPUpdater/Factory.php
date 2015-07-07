<?php
namespace GeoIPUpdater;

use Extractor\Gzip;
use Extractor\PharData;

require_once(__DIR__.'/../GeoIPUpdater.php');
require_once('GeoIP/Lite.php');
require_once('GeoIP/Legacy.php');
require_once('GeoIP2.php');
require_once(__DIR__.'/../Extractor/Gzip.php');
require_once(__DIR__.'/../Extractor/PharData.php');

/**
 * Class Factory
 * @package GeoIPUpdater
 */
class Factory {

    /**
     * GeoIP DB types
     */
    const GEOIP_LITE = 'lite';
    const GEOIP_LEGACY = 'legacy';
    const GEOIP2 = 'geoip2';

    /**
     * @var array
     */
    protected static $aModes = array(
        self::GEOIP_LITE,
        self::GEOIP_LEGACY,
        self::GEOIP2
    );

    /**
     * @return array
     */
    static function getModes() {
        return self::$aModes;
    }

    /**
     * @param string $sType
     * @param $oLogger
     * @param $oFileSystem
     * @return GeoIP2|Legacy|Lite
     */
    static function forge($sType = null, $oLogger, $oFileSystem) {
        switch ($sType) {
            case static::GEOIP_LEGACY :
                $oInstance = new GeoIP\Legacy($oLogger, $oFileSystem, new PharData());
                $oInstance
                    ->setDbFiles(\Csv::csvToArray(DB_URL_LIST_CSV_LEGACY))
                    ->setValidationItems(\Csv::csvToArray(VALIDATION_LIST_CSV));
                break;
            case static::GEOIP2 :
                $oInstance = new GeoIP2($oLogger, $oFileSystem, new PharData());
                $oInstance->setDbFiles(\Csv::csvToArray(DB_URL_LIST_CSV_GEOIP2));
                break;
            default :
                $oInstance = new GeoIP\Lite($oLogger, $oFileSystem, new Gzip());
                $oInstance
                    ->setDbFiles(\Csv::csvToArray(DB_URL_LIST_CSV_LITE))
                    ->setValidationItems(\Csv::csvToArray(VALIDATION_LIST_CSV));
                break;
        }
        return $oInstance;
    }
}