<?php
/**
 *
 */
$sShortopts  = "";
$sShortopts .= "m:";  // Required value, mode
$sShortopts .= "v"; // Optional value, verbose

$aLongopts  = array();
$aOptions = getopt($sShortopts, $aLongopts);

$oLogger = new \Logger();
$oLogger->setVerbose(isset($aOptions['v'])?true:false);
try {
    $oGeoIpUpdater = new \GeoIpUpdater($oLogger);
    switch ($aOptions['m']) {
        case 'update' :
            $oGeoIpUpdater->update();
            break;
        case 'rollback' :
            $oGeoIpUpdater->rollback();
            break;
        default :
            echo "Please specifiy a mode : -m=update|rollback\n";
            break;
    }
} catch (\Exception $oException) {
    $oLogger->log("KO => ".$oException->getMessage());
}

class GeoIpUpdater {
    protected $_sDbPath = '/usr/share/GeoIP';

    protected $_sArchiveDbPath = '/usr/share/GeoIP_Archives';

    protected $_sTmpDbPath = '/tmp/GeoIP';

    protected $_oLogger;

    const iMaxArchivedDbVersions = 10;

    protected $_aIps = array(
        array('IP' => '95.211.238.65', 'COUNTRY_CODE' => 'NL'),
        array('IP' => '61.129.13.80', 'COUNTRY_CODE' => 'CN'),
        array('IP' => '37.59.192.1', 'COUNTRY_CODE' => 'FR')
    );

    protected $_aDbFiles = array(
        'http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz',
        'http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz',
        'http://geolite.maxmind.com/download/geoip/database/GeoLiteCity.dat.gz',
        'http://geolite.maxmind.com/download/geoip/database/GeoLiteCityv6-beta/GeoLiteCityv6.dat.gz',
        'http://download.maxmind.com/download/geoip/database/asnum/GeoIPASNum.dat.gz',
        'http://download.maxmind.com/download/geoip/database/asnum/GeoIPASNumv6.dat.gz'
    );

    public function __construct(\Logger $oLogger) {
        $this->_oLogger = $oLogger;

        if (!function_exists('geoip_database_info')) {
            throw new \Exception('GeoIp module is not installed.');
        }

        if (!is_dir($this->_sTmpDbPath)) {
            mkdir($this->_sTmpDbPath, 0777, true);
        } elseif (!is_writable($this->_sTmpDbPath)) {
            chmod($this->_sTmpDbPath, 0777);
        }

        if (!is_dir($this->_sArchiveDbPath)) {
            mkdir($this->_sArchiveDbPath, 0777, true);
        } elseif (!is_writable($this->_sArchiveDbPath)) {
            chmod($this->_sArchiveDbPath, 0777);
        }

        if (!is_dir($this->_sDbPath)) {
            throw new \Exception($this->_sDbPath.' is not a directory.');
        } elseif (!is_writable($this->_sDbPath)) {
            throw new \Exception($this->_sDbPath.' is not writable.');
        }
        $this->_oLogger->log('Current DB version is '.geoip_database_info());
    }

    public function __destruct() {
        $this->_emptyDir($this->_sTmpDbPath);
        $this->_oLogger->log('DB version is now '.geoip_database_info());
    }

    protected function _emptyDir($sDir, $bRemoveDir = false) {
        if (!$dh = @opendir($sDir)) return;
        while (false !== ($obj = readdir($dh))) {
            if ($obj=='.' || $obj=='..') continue;
            if (!@unlink($sDir.'/'.$obj)) $this->_emptyDir($sDir.'/'.$obj, true);
        }
        closedir($dh);
        if ($bRemoveDir === true) {
            @rmdir($sDir);
        }
    }

    public function update() {
        $this->_retreiveDbFiles();
        $this->_checkDbFiles();
        $this->_archiveDbFiles();
        $this->_loadDbFiles();
        if (!$this->_validateDbFiles()) {
            $this->rollback();
        }
    }

    protected function _validateDbFiles() {
        foreach ($this->_aIps as $aIp) {
            $this->_oLogger->log('Testing IP : '.$aIp['IP'].' against country code : '.$aIp['COUNTRY_CODE']);
            $sCode = geoip_country_code_by_name($aIp['IP']);
            if ($sCode != $aIp['COUNTRY_CODE']) {
                $this->_oLogger->log('Validation failed, returned country code is "'.$sCode.'"');
                return false;
            }
        }
        return true;
    }

    protected function _getCurrentDbVersion() {
        if (preg_match("/(\\d{8})/", geoip_database_info(), $aMatches)) {
            return  $aMatches[1];
        } else {
            throw new \Exception('Could not determine DB version from DB info : '.geoip_database_info());
        }
    }

    public function rollback() {

        //Which version should be loaded? If current version is in the archives, we load the previous one, otherwise, we load latest archived version.
        $aArchivedDbVersions = $this->_scanDir($this->_sArchiveDbPath, 1);
        $iLevel = array_search($this->_getCurrentDbVersion(), $aArchivedDbVersions);
        if ($iLevel === false) {
            $iLevel = 0;
        } else {
            $iLevel++;
        }
        $aPreviousDbVersion = array_slice($aArchivedDbVersions, $iLevel, 1);
        if (is_array($aPreviousDbVersion) && count($aPreviousDbVersion)) {
            $sPreviousDbVersion = $aPreviousDbVersion[0];
            $this->_oLogger->log('Rolling back to version '.$sPreviousDbVersion);
            $sPreviousDbVersionPath = $this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sPreviousDbVersion;
            $aDbFiles = $this->_scanDir($sPreviousDbVersionPath);
            if (is_array($aDbFiles)) {
                foreach ($aDbFiles as $sDbFile) {
                    $this->_oLogger->log('Loading '.$sPreviousDbVersionPath.DIRECTORY_SEPARATOR.$sDbFile.' to '.$this->_sDbPath.DIRECTORY_SEPARATOR.$sDbFile);
                    copy($sPreviousDbVersionPath.DIRECTORY_SEPARATOR.$sDbFile, $this->_sDbPath.DIRECTORY_SEPARATOR.$sDbFile);
                }
            } else {
                throw new \Exception('No db file to rollback to!');
            }
        } else {
            throw new \Exception('No archive to rollback to!');
        }
    }

    protected function _scanDir($sDir, $iSort = null) {
        $aExcludeList = array(".", "..");
        return array_diff(scandir($sDir, $iSort), $aExcludeList);
    }

    protected function _loadDbFiles() {
        $aDbFiles = glob($this->_sTmpDbPath.DIRECTORY_SEPARATOR."*.dat");
        if (is_array($aDbFiles)) {
            foreach ($aDbFiles as $sDbFile) {
                $this->_oLogger->log('Loading '.$sDbFile.' to '.$this->_sDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
                copy($sDbFile, $this->_sDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
            }
        }
    }

    protected function _archiveDbFiles() {

        //Cleaning older versions
        $aArchivedDbVersions = $this->_scanDir($this->_sArchiveDbPath, 1);
        $aOlderDbVersions = array_slice($aArchivedDbVersions, self::iMaxArchivedDbVersions);
        if (is_array($aOlderDbVersions) && !empty($aOlderDbVersions)) {
            $this->_oLogger->log('There are '.count($aArchivedDbVersions).' archived versions, the oldest will be removed.');
            foreach ($aOlderDbVersions as $sOlderDbVersion) {
                if (is_dir($this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sOlderDbVersion)) {
                    $this->_oLogger->log('Removing '.$this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sOlderDbVersion);
                    $this->_emptyDir($this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sOlderDbVersion, true);
                }
            }
        }

        //Creating archive with current db files
        $sArchiveDbPath = $this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$this->_getCurrentDbVersion();
        if (is_dir($sArchiveDbPath)) {
            $this->_oLogger->log('Archive for '.$sArchiveDbPath.' already exists.');
        } else {
            $this->_oLogger->log('Archiving current DB files in '.$sArchiveDbPath);
            mkdir($sArchiveDbPath, 0777, true);
            $aDbFiles = glob($this->_sDbPath.DIRECTORY_SEPARATOR."*.dat");
            if (is_array($aDbFiles)) {
                foreach ($aDbFiles as $sDbFile) {
                    copy($sDbFile, $sArchiveDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
                }
            }
        }
    }

    protected function _retreiveDbFiles () {
        foreach ($this->_aDbFiles as $sDbFileSrc) {
            $this->_oLogger->log('Retreiving DB from '.$sDbFileSrc);
            $rZp=@gzopen($sDbFileSrc, "r");
            if (!$rZp) {
                throw new \Exception($sDbFileSrc.' could not be retreived.');
            }
            $sUnzippedData = @gzread($rZp, 2097152); // 2MB
            @gzclose($rZp);
            // Check we have data
            if (strlen($sUnzippedData) > 0) {
                // Write data to local file
                $sDbFileName = str_replace('.dat.gz', '.dat', basename($sDbFileSrc));
                $rLZFP=@fopen($this->_sTmpDbPath.DIRECTORY_SEPARATOR.$sDbFileName,"w+");
                @fwrite($rLZFP, $sUnzippedData);
                @fclose($rLZFP);
            } else {
                throw new \Exception($sDbFileSrc.' is empty.');
            }
        }
    }

    protected function _checkDbFiles () {
        $this->_oLogger->log('Checking tmp DB files in '.$this->_sTmpDbPath);
        $aDbFiles = glob($this->_sTmpDbPath.DIRECTORY_SEPARATOR."*.dat");
        if (is_array($aDbFiles)) {
            foreach ($aDbFiles as $sDbFile) {
                if (!filesize($sDbFile)) {
                    throw new \Exception($sDbFile.' is empty.');
                }
            }
        }
    }
}

class Logger {
    protected $_sContent;

    protected $_bVerbose = false;

    public function setVerbose($bVerbose) {
        $this->_bVerbose = $bVerbose;
    }

    public function log($sContent) {
        if ($this->_bVerbose === true) {
            echo $sContent."\n";
        }
        $this->_sContent .= $sContent;
    }
}