<?php

namespace GeoIPUpdater;

class GeoIP2 extends \GeoIPUpdater {

    /**
     * There is no env dependencies
     * @throws \Exception
     */
    protected function _checkEnv() {
        return true;
    }

    /**
     * No validation for GeoIP2
     * @return bool
     */
    protected function _validateDbFiles() {
        return true;
    }

    /**
     * When done, we remove tmp files and display the resulting DB version.
     */
    public function __destruct() {
        $this->_oFileSystem->emptyDir($this->_sTmpDbPath);
    }

    /**
     * Path to GeoIp DB files
     * @var string
     */
    protected $_sDbPath = GEOIP2_DB_PATH;

    /**
     * Path to Archived GeoIp DB files
     * @var string
     */
    protected $_sArchiveDbPath = GEOIP2_DB_ARCHIVE_PATH;

    /**
     * Path to a tmp dir where we temporarily store retrieved DB files
     * @var string
     */
    protected $_sTmpDbPath = GEOIP2_DB_TMP_PATH;

    /**
     * @var string
     */
    protected $_sDbFileExtension = ".mmdb";

    /**
     * Retrieves the DB files from MaxMind and stores them in the tmp folder.
     * Legacy type requires :
     * - Digging into the TAR archive to find the .dat files (that have dynamic names, including a timestamp)
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

            //Storing each tar.gz file in a tmp dir
            $this->_oLogger->log('Retrieving DB from '.$sDbFileSrc);
            $this->_oExtractor->extract(sprintf($sDbFileSrc, MAXMIND_LICENSE_KEY), $this->_sTmpDbPath.DIRECTORY_SEPARATOR.'extracted');

            //Renaming and extracting .dat files to temp path root, remove extracted dirs
            $aDatFiles = glob($this->_sTmpDbPath.DIRECTORY_SEPARATOR.'extracted'.DIRECTORY_SEPARATOR."*".DIRECTORY_SEPARATOR."*".$this->_sDbFileExtension);
            foreach ($aDatFiles as $sDatFile) {
                rename($sDatFile, $this->_sTmpDbPath.DIRECTORY_SEPARATOR.basename($sDatFile));
            }
            $this->_oFileSystem->emptyDir($this->_sTmpDbPath.DIRECTORY_SEPARATOR.'extracted');
            rmdir($this->_sTmpDbPath.DIRECTORY_SEPARATOR.'extracted');
        }
    }

    /**
     * @return mixed|void
     * @throws \Exception
     */
    protected function _checkConfig() {
        if (!defined('MAXMIND_LICENSE_KEY')) {
            throw new \Exception('Downloading GeoIP2 DBs requires a license key. See MAXMIND_LICENSE_KEY in conf/config.php');
        }
    }
}