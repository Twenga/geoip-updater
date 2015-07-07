<?php

namespace GeoIPUpdater\GeoIP;

require_once(__DIR__.'/../GeoIP.php');

use \GeoIpUpdater\GeoIP;

/**
 * Class Lite
 * @package \GeoIpUpdater\GeoIP
 */
class Lite extends GeoIP {

    /**
     * Path to GeoIp DB files
     * @var string
     */
    protected $_sDbPath = GEOIP_LITE_DB_PATH;

    /**
     * Path to Archived GeoIp DB files
     * @var string
     */
    protected $_sArchiveDbPath = GEOIP_LITE_DB_ARCHIVE_PATH;

    /**
     * Path to a tmp dir where we temporarily store retrieved DB files
     * @var string
     */
    protected $_sTmpDbPath = GEOIP_LITE_DB_TMP_PATH;

    /**
     * Retrieves the DB files from MaxMind and stores them in the tmp folder.
     * @throws \Exception
     */
    protected function _retrieveDbFiles() {
        $this->_oLogger->log('DB files retrieval.');
        if (empty($this->_aDbFiles)) {
            throw new \Exception('There are no DB file to retrieve. Please check the DB URL CSV file in the resources dir.');
        }
        $this->_oLogger->log('There are '.count($this->_aDbFiles).' files to retrieve.');
        foreach ($this->_aDbFiles as $aDbFileSrc) {
            $sDbFileSrc = $aDbFileSrc[0];
            $this->_oLogger->log('Retrieving DB from '.$sDbFileSrc);
            $this->_oExtractor->extract($sDbFileSrc, $this->_sTmpDbPath);
        }
    }

    /**
     * @return mixed|void
     * @throws \Exception
     */
    protected function _checkConfig() {
        return true;
    }
}