<?php
/**
 * Class GeoIpUpdater
 */
class GeoIpUpdater {

    /**
     * Path to GeoIp DB files
     * @var string
     */
    protected $_sDbPath = GEOIP_DB_PATH;

    /**
     * Path to Archived GeoIp DB files
     * @var string
     */
    protected $_sArchiveDbPath = GEOIP_DB_ARCHIVE_PATH;

    /**
     * Path to a tmp dir where we temporarily store retreived DB files
     * @var string
     */
    protected $_sTmpDbPath = GEOIP_DB_TMP_PATH;

    /**
     * Logger object
     * @var Logger
     */
    protected $_oLogger;

    /**
     * Maximum number of archived DB files versions
     */
    const iMaxArchivedDbVersions = 10;

    /**
     * List of IP addresses that will be used for validating the newly loaded DB files.
     * @var array
     */
    protected $_aIps = array();

    /**
     * List of files provided by MaxMind
     * @var array
     */
    protected $_aDbFiles = array();

    /**
     * Checks required folders.
     * Checks whether the GeoIP extension is loaded.
     * @param Logger $oLogger
     * @throws Exception
     */
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

        //Loading IP and URL's lists
        $this->_aIps = $this->_csvToArray(IP_LIST_CSV);
        $this->_aDbFiles = $this->_csvToArray(DB_URL_LIST_CSV);
    }

    /**
     * When done, we remove tmp files and display the resulting DB version.
     */
    public function __destruct() {
        $this->_emptyDir($this->_sTmpDbPath);
        $this->_oLogger->log('DB version is now '.geoip_database_info());
    }

    /**
     * Update mode
     */
    public function update() {
        $this->_retreiveDbFiles();
        $this->_checkDbFiles();
        $this->_archiveDbFiles();
        $this->_loadDbFiles();
        if (!$this->_validateDbFiles()) {
            $this->rollback();
        }
    }

    /**
     * Roll back mode
     * @throws Exception
     */
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

    /**
     * Calls GeoIP functions to check that it returns the expected results for a given pool of known IP addresses.
     * @return bool
     */
    protected function _validateDbFiles() {
        $this->_oLogger->log('There are '.count($this->_aIps).' IP addresses to test.');
        foreach ($this->_aIps as $aIp) {
            $this->_oLogger->log('Testing IP : '.$aIp[0].' against country code : '.$aIp[1]);
            $sCode = geoip_country_code_by_name($aIp[0]);
            if ($sCode != $aIp[1]) {
                $this->_oLogger->log('Validation failed, returned country code is "'.$sCode.'"');
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the current DB files version
     * @return mixed
     * @throws Exception
     */
    protected function _getCurrentDbVersion() {
        if (preg_match("/(\\d{8})/", geoip_database_info(), $aMatches)) {
            return  $aMatches[1];
        } else {
            throw new \Exception('Could not determine DB version from DB info : '.geoip_database_info());
        }
    }

    /**
     * Moves the temporary DB files to their final destination. This actually updates the DB files.
     */
    protected function _loadDbFiles() {
        $aDbFiles = glob($this->_sTmpDbPath.DIRECTORY_SEPARATOR."*.dat");
        if (is_array($aDbFiles)) {
            foreach ($aDbFiles as $sDbFile) {
                $this->_oLogger->log('Loading '.$sDbFile.' to '.$this->_sDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
                copy($sDbFile, $this->_sDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
            }
        }
    }

    /**
     * Makes an archive folder for the current DB files. If the archive already exists, it skips it. It also cleans up
     * the archive dir, keeping the number of archives version to its maximum.
     */
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

    /**
     * Retreives the DB files from MaxMind and stores them in the tmp folder.
     * @throws Exception
     */
    protected function _retreiveDbFiles () {
        $this->_oLogger->log('There are '.count($this->_aDbFiles).' DB files to retreive.');
        foreach ($this->_aDbFiles as $aDbFileSrc) {

            $sDbFileSrc = $aDbFileSrc[0];

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

    /**
     * Basic check of the retreived DB files.
     * @throws Exception
     */
    protected function _checkDbFiles () {
        $this->_oLogger->log('Checking tmp DB files in '.$this->_sTmpDbPath);
        $aDbFiles = glob($this->_sTmpDbPath.DIRECTORY_SEPARATOR."*.dat");
        if (is_array($aDbFiles)) {
            foreach ($aDbFiles as $sDbFile) {
                if (!filesize($sDbFile)) {
                    throw new \Exception($sDbFile.' is empty.');
                }
            }
        } else {
            throw new \Exception('There are no file to check.');
        }
    }

    /**
     * Utils
     * Returns a list of files/folders within a given folder. It omits the '.' and '..' entries.
     * @param $sDir
     * @param null $iSort
     * @return array
     */
    protected function _scanDir($sDir, $iSort = null) {
        $aExcludeList = array(".", "..");
        return array_diff(scandir($sDir, $iSort), $aExcludeList);
    }

    /**
     * Utils : Recursively empties a dir, optionally removes the dir.
     * @param $sDir
     * @param bool $bRemoveDir
     */
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

    /**
     * Utils
     * @param $sCsvFile
     * @return array
     * @throws Exception
     */
    protected function _csvToArray($sCsvFile) {
        $aCsvContent = array();
        if (($handle = fopen(GEOIP_DOCROOT.DIRECTORY_SEPARATOR.$sCsvFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",", '"')) !== FALSE) {
                $aCsvContent[] = $data;
            }
            fclose($handle);
        } else {
            throw new \Exception('File '.GEOIP_DOCROOT.DIRECTORY_SEPARATOR.$sCsvFile.' not found.');
        }
        return $aCsvContent;
    }
}