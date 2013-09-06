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
     * File that holds the version for a given set of DB files
     */
    const DB_VERSION_FILE_NAME = 'hash';

    /**
     * File that contains a list of blacklisted versions.
     */
    const BLACKLIST_FILE_NAME = 'blacklist.txt';

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
        $this->_retrieveDbFiles();
        $this->_checkDbFiles();
        $this->_archiveDbFiles();
        $this->_loadDbFiles($this->_sTmpDbPath);
        if (!$this->_validateDbFiles()) {
            $this->_rollbackToPrevious();
            $this->_blackListVersion($this->_getDbVersion($this->_sTmpDbPath)); //blacklisted loaded DB version
        }
    }

    /**
     * Rollback mode
     */
    public function rollback() {
        $this->_rollbackToPrevious();
        while (!$this->_validateDbFiles()) {
            $this->_rollbackToPrevious();
        }
    }

    /**
     * Rollback mode
     * @throws Exception
     */
    public function _rollbackToPrevious() {
        //Which version should be loaded? If current version is in the archives, we load the previous one, otherwise, we load latest archived version.
        $aArchivedDbFolders = $this->_scanDir($this->_sArchiveDbPath, 1);
        $CurrentVersion = $this->_getDbVersion($this->_sDbPath);
        $this->_oLogger->log('Current version : '.$CurrentVersion);
        $iLevel = 0;
        foreach ($aArchivedDbFolders as $iLevel => $sArchivedDbFolder) {
            $aArchivedDbFolder = explode('_', $sArchivedDbFolder);
            $sArchivedDbVersion = isset($aArchivedDbFolder[1])?$aArchivedDbFolder[1]:null;
            if ($sArchivedDbVersion == $CurrentVersion) {
                break;
            }
        }
        $iLevel++;
        $aPreviousDbVersion = array_slice($aArchivedDbFolders, $iLevel, 1);
        if (is_array($aPreviousDbVersion) && count($aPreviousDbVersion)) {
            $sPreviousDbVersion = $aPreviousDbVersion[0];
            $this->_oLogger->log('Rolling back to version '.$sPreviousDbVersion);
            $sPreviousDbVersionPath = $this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sPreviousDbVersion;
            $this->_loadDbFiles($sPreviousDbVersionPath);
        } else {
            throw new \Exception('No archive to rollback to!');
        }
    }

    /**
     * Calls GeoIP functions to check that it returns the expected results for a given pool of known IP addresses.
     * @return bool
     */
    protected function _validateDbFiles() {

        if(empty($this->_aIps)) {
            $this->_oLogger->log('There are no IP addresses to test in .'.IP_LIST_CSV);
        } else {
            $this->_oLogger->log('There are '.count($this->_aIps).' IP addresses to test.');
            foreach ($this->_aIps as $aIp) {
                $this->_oLogger->log('Testing IP : '.$aIp[0].' against country code : '.$aIp[1]);
                $sCmd = 'php -r "echo geoip_country_code_by_name(\''.$aIp[0].'\');"';
                $sCode = exec($sCmd, $aOutput);
                if ($sCode != $aIp[1]) {
                    $this->_oLogger->log('Validation failed, returned country code is "'.$sCode.'"');
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Moves the temporary DB files to their final destination. This actually updates the DB files.
     * Alos checks that the new version is different from current version before loading.
     * @param string $sPath
     * @throws Exception
     */
    protected function _loadDbFiles($sPath) {
        //Checking current DB files are up to date.
        $sNewVersion = $this->_getDbVersion($sPath);
        $sCurrentVersion = $this->_getDbVersion($this->_sDbPath);
        if ($sNewVersion == $sCurrentVersion) {
            throw new \Exception('Current version '.$sCurrentVersion.' is up to date.');
        }

        //Loading files
        $aDbFiles = glob($sPath.DIRECTORY_SEPARATOR."*");
        if (is_array($aDbFiles) && count($aDbFiles) > 1) {
            if (!in_array($sPath.DIRECTORY_SEPARATOR.self::DB_VERSION_FILE_NAME, $aDbFiles)) {
                throw new \Exception('Cannot load DB files from '.$sPath.', there is no hash file.');
            }
            foreach ($aDbFiles as $sDbFile) {
                $this->_oLogger->log('Loading '.$sDbFile.' to '.$this->_sDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
                copy($sDbFile, $this->_sDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
            }
        } else {
            throw new \Exception('Cannot load DB files from '.$sPath.', there are no files!.');
        }
    }

    /**
     * Makes an archive folder for the current DB files. If the archive already exists, it skips it. It also cleans up
     * the archive dir, keeping the number of archives version to its maximum.
     */
    protected function _archiveDbFiles() {
        //Cleaning older versions
        $aArchivedDbVersions = $this->_scanDir($this->_sArchiveDbPath, 1);
        $aOlderDbVersions = array_slice($aArchivedDbVersions, MAX_ARCHIVED_DB_VERSIONS);
        if (is_array($aOlderDbVersions) && !empty($aOlderDbVersions)) {
            $this->_oLogger->log('There are '.count($aArchivedDbVersions).' archived versions, the oldest will be removed.');
            foreach ($aOlderDbVersions as $sOlderDbVersion) {
                if (is_dir($this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sOlderDbVersion)) {
                    $this->_oLogger->log('Removing '.$this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sOlderDbVersion);
                    $this->_emptyDir($this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sOlderDbVersion, true);
                }
            }
        }

        //Creating archive with newly retrieved db files
        $sVersionToArchive = $this->_getDbVersion($this->_sTmpDbPath);
        $sArchiveDbPath = $this->_sArchiveDbPath.DIRECTORY_SEPARATOR.date('YmdHis').'_'.$sVersionToArchive;
        $aExistingArchives = glob($this->_sArchiveDbPath.DIRECTORY_SEPARATOR.'*_'.$sVersionToArchive);
        if (is_array($aExistingArchives) && count($aExistingArchives) > 0) {
            $this->_oLogger->log('Archive for version'.$sVersionToArchive.' already exists in '.implode(' ', $aExistingArchives));
        } else {
            $this->_oLogger->log('Archiving DB files from '.$this->_sTmpDbPath.' in '.$sArchiveDbPath);
            mkdir($sArchiveDbPath, 0777, true);
            $aDbFiles = glob($this->_sTmpDbPath.DIRECTORY_SEPARATOR."*"); //archiving all files.
            if (is_array($aDbFiles)) {
                foreach ($aDbFiles as $sDbFile) {
                    copy($sDbFile, $sArchiveDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
                }
            }
        }
    }

    /**
     * Retrieves the DB files from MaxMind and stores them in the tmp folder.
     * @throws Exception
     */
    protected function _retrieveDbFiles () {
        if (empty($this->_aDbFiles)) {
            throw new \Exception('There are no DB file listed in '.DB_URL_LIST_CSV);
        }
        $this->_oLogger->log('There are '.count($this->_aDbFiles).' DB files to retrieve.');
        foreach ($this->_aDbFiles as $aDbFileSrc) {
            $sDbFileSrc = $aDbFileSrc[0];
            $this->_oLogger->log('Retrieving DB from '.$sDbFileSrc);
            $rZp=@gzopen($sDbFileSrc, "r");
            if (!$rZp) {
                throw new \Exception($sDbFileSrc.' could not be retrieved.');
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
        $this->_oLogger->log('Checking tmp DB files (blacklist and size) in '.$this->_sTmpDbPath);

        //Size
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

        //Blacklist
        $sVersion = $this->_getDbVersion($this->_sTmpDbPath);
        if ($this->_isVersionBlackListed($sVersion)) {
            throw new \Exception('Version '.$sVersion.' has been blacklisted.');
        }
    }

    /**
     * For a given set of DB files, in a given directory, this will figure out a version for this set and save it into a
     * version file, within the directory. It returns the version.
     * @param $sPath
     * @return string
     * @throws Exception
     */
    protected function _getDbVersion($sPath) {
        //$this->_oLogger->log('Getting version for DB files in '.$sPath);
        if (is_dir($sPath)) {
            $VersionFilePath = $sPath.DIRECTORY_SEPARATOR.self::DB_VERSION_FILE_NAME;

            //Check for version file, if exists, return content
            if (file_exists($VersionFilePath)) {
                $sVersion = file_get_contents($VersionFilePath);
                //$this->_oLogger->log('Found version '.$sVersion.' in '.$VersionFilePath);
            } else {
                $this->_oLogger->log('Building version from DB files.');
                $aDbFiles = glob($sPath.DIRECTORY_SEPARATOR."*.dat");
                if (!is_array($aDbFiles) || count($aDbFiles) < 1) {
                    throw new \Exception('Could not get DB version, there are no .dat files in '.$sPath);
                }
                $sDbContents = '';
                foreach ($aDbFiles as $sDbFile) {
                    $sDbContents .= file_get_contents($sDbFile);
                }
                $sVersion = sha1($sDbContents);
                $this->_oLogger->log('Calculated version '.$sVersion.' and writing it to file'.$VersionFilePath);
                if (!@file_put_contents($VersionFilePath, $sVersion)) {
                    $this->_oLogger->log('Could not write version to file '.$VersionFilePath);
                }
            }
            return $sVersion;
        } else {
            throw new \Exception($sPath.' is not a directory, cannot get DB version.');
        }
    }

    /**
     * Checks whether a version has been blacklisted
     * @param $sVersion
     * @return bool
     */
    protected function _isVersionBlackListed($sVersion) {
        $sBlackistFilePath = GEOIP_DOCROOT.DIRECTORY_SEPARATOR.self::BLACKLIST_FILE_NAME;
        $bBlacklisted = false;
        if (file_exists($sBlackistFilePath)) {
            $handle = fopen($sBlackistFilePath, 'r');
            while (($buffer = fgets($handle)) !== false) {
                if (strpos($buffer, $sVersion) !== false) {
                    $bBlacklisted = true;
                }
            }
            fclose($handle);
        }
        return $bBlacklisted;
    }

    /**
     * Blacklists a version
     * @param $sVersion
     * @return bool
     */
    protected function _blackListVersion($sVersion) {
        //Writing version in blacklist file
        $this->_oLogger->log('Blacklisting '.$sVersion);
        $sBlackistFilePath = GEOIP_DOCROOT.DIRECTORY_SEPARATOR.self::BLACKLIST_FILE_NAME;
        if (!@file_put_contents($sBlackistFilePath, $sVersion."\n", FILE_APPEND)) {
            $this->_oLogger->log('Could not blacklist version '.$sVersion.' in '.$sBlackistFilePath);
        }

        //Removing blacklisted archive
        $aArchives = glob($this->_sArchiveDbPath.DIRECTORY_SEPARATOR."*_".$sVersion);
        if (is_array($aArchives)) {
            foreach ($aArchives as $sArchivePath) {
                if (strpos($sArchivePath, $sVersion)) {
                    $this->_oLogger->log('Removing archive for '.$sVersion);
                    $this->_emptyDir($sArchivePath, true);
                }
            }
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
        return array_values(array_diff(scandir($sDir, $iSort), $aExcludeList));
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
     * @param $sCsvFilePath
     * @return array
     * @throws Exception
     */
    protected function _csvToArray($sCsvFilePath) {
        $aCsvContent = array();
        if (($handle = fopen($sCsvFilePath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",", '"')) !== FALSE) {
                $aCsvContent[] = $data;
            }
            fclose($handle);
        } else {
            throw new \Exception('File '.$sCsvFilePath.' not found.');
        }
        return $aCsvContent;
    }
}