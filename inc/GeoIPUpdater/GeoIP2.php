<?php

namespace GeoIPUpdater;

/**
 * Class GeoIP2
 * @package GeoIPUpdater
 */
class GeoIP2 extends \GeoIPUpdater {

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
            $aDatFiles = $this->_oFileSystem->glob($this->_sTmpDbPath.DIRECTORY_SEPARATOR.'extracted'.DIRECTORY_SEPARATOR."*".DIRECTORY_SEPARATOR."*".$this->_sDbFileExtension);
            foreach ($aDatFiles as $sDatFile) {
                $this->_oFileSystem->rename($sDatFile, $this->_sTmpDbPath.DIRECTORY_SEPARATOR.basename($sDatFile));
            }
            $this->_oFileSystem->emptyDir($this->_sTmpDbPath.DIRECTORY_SEPARATOR.'extracted');
            $this->_oFileSystem->rmdir($this->_sTmpDbPath.DIRECTORY_SEPARATOR.'extracted');
        }
    }

    /**
     * @return mixed|void
     * @throws \Exception
     */
    protected function _checkConfig() {
        if (!defined('MAXMIND_LICENSE_KEY') || MAXMIND_LICENSE_KEY == '') {
            throw new \Exception('Downloading GeoIP2 DB files requires a license key. See MAXMIND_LICENSE_KEY in conf/config.php');
        }
    }

    /**
     * There is no env dependencies
     * @throws \Exception
     */
    protected function _checkEnv() {
        return true;
    }

    /**
     * Calls GeoIP functions to check that it returns the expected results for a given pool of known IP addresses/hosts.
     * This method can't throw exceptions, we must roll back if validation fails.
     * If validation cannot be done (no item or invalid validation item), we skip it!
     * @return bool
     */
    protected function _validateDbFiles() {
        $this->_oLogger->log('DB files Validation.');
        if (empty($this->_aValidationItems)) {
            $this->_oLogger->log('Skipping validation. There are no validation item to test in '.VALIDATION_LIST_CSV_GEOIP2);
        } else if (!$this->_validateIpList()) {
            $this->_oLogger->log('Validation items malformed. Each validation items must specify a GeoIP2 API\'s Reader property a host or IP, an expected result and Maxmind MMDB file name to use. See README.md.');
        } else {
            $this->_oLogger->log('There are '.count($this->_aValidationItems).' validation items to run.');
            foreach ($this->_aValidationItems as $aValidationItem) {
                $this->_oLogger->log('Testing property \''.$aValidationItem[0].'\' with argument : '.$aValidationItem[1].' and expecting result : '.$aValidationItem[2]);
                $sCmd = 'php '.GEOIP_RESOURCES.'/validation_geoip2.php -f \''.MAXMIND_PHP_API_AUTOLOAD.'\' -d \''.trim($this->_sDbPath).'/'.trim($aValidationItem[3]).'\' -a \''.trim($aValidationItem[1]).'\' -r \''.trim($aValidationItem[2]).'\' -p \''.trim($aValidationItem[0]).'\'';
                $sResult = exec($sCmd);
                if ($sResult != $aValidationItem[2]) {
                    $this->_oLogger->log('Validation failed, result "'.$sResult.'" when expecting "'.$aValidationItem[2].'"');
                    return false;
                }
            }
        }
        $this->_oLogger->log('Validation OK.');
        return true;
    }

    /**
     * Checks that the list of IP's retrieved from the CSV file is well formed
     * @return bool
     */
    protected function _validateIpList() {
        if (is_array($this->_aValidationItems)) {
            foreach ($this->_aValidationItems as $aIp) {
                if (count($aIp) != 4) {
                    return false;
                }
            }
        }
        return true;
    }
}