<?php

namespace GeoIPUpdater;

use \GeoIpUpdater;

/**
 * Class GeoIP
 * @package GeoIPUpdater
 */
abstract class GeoIP extends GeoIpUpdater{

    /**
     * @var string
     */
    protected $_sDbFileExtension = ".dat";

    /**
     * @throws \Exception
     */
    protected function _checkEnv() {
        if (!function_exists('geoip_database_info')) {
            throw new \Exception('GeoIp module is not installed.');
        }
    }

    /**
     * When done, we remove tmp files and display the resulting DB version.
     */
    public function __destruct() {
        $this->_oFileSystem->emptyDir($this->_sTmpDbPath);
        $this->_oLogger->log('DB version is now '.geoip_database_info());
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
            $this->_oLogger->log('Skipping validation. There are no validation item to test in .'.VALIDATION_LIST_CSV);
        } else if (!$this->_validateIpList()) {
            $this->_oLogger->log('Validation items malformed. Each validation items must specify a GeoIP function, a host or IP and an expected result (ISO country code, region... See http://us1.php.net/manual/fr/ref.geoip.php)');
        } else {
            $this->_oLogger->log('There are '.count($this->_aValidationItems).' validation items to run.');
            foreach ($this->_aValidationItems as $aValidationItem) {
                $this->_oLogger->log('Testing function \''.$aValidationItem[0].'\' with argument : '.$aValidationItem[1].' and expecting result : '.$aValidationItem[2]);
                $sCmd = 'php -r "echo '.$aValidationItem[0].'(\''.$aValidationItem[1].'\');"';
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
                if (!isset($aIp[0]) || !isset($aIp[1]) || !isset($aIp[2])) {
                    return false;
                }
            }
        }
        return true;
    }
}