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
     *@var FileSystem
     */
    protected $_oFileSystem;

    /**
     * @var Csv
     */
    protected $_oCsv;

    /**
     * File that holds the version for a given set of DB files
     */
    const DB_VERSION_FILE_NAME = 'hash';

    /**
     * File that contains a list of blacklisted versions.
     */
    const BLACKLIST_FILE_NAME = 'blacklist.txt';

    /**
     * List of validation items (consisting of a GeoIp function name, a host/IP and an expected result) that will be
     * used for validating the newly loaded DB files.
     * @var array
     */
    protected $_aValidationItems = array();

    /**
     * List of files provided by MaxMind
     * @var array
     */
    protected $_aDbFiles = array();

    /**
     * Checks required folders.
     * Checks whether the GeoIP extension is loaded.
     * @param Logger $oLogger
     * @param FileSystem $oFileSystem
     * @throws Exception
     */
    public function __construct(\Logger $oLogger, \FileSystem $oFileSystem) {
        $this->_oLogger = $oLogger;
        $this->_oFileSystem = $oFileSystem;
        if (!function_exists('geoip_database_info')) {
            throw new \Exception('GeoIp module is not installed.');
        }
        if (!$this->_oFileSystem->is_dir($this->_sTmpDbPath)) {
            $this->_oFileSystem->mkdir($this->_sTmpDbPath, 0777, true);
        } elseif (!is_writable($this->_sTmpDbPath)) {
            $this->_oFileSystem->chmod($this->_sTmpDbPath, 0777);
        }
        if (!$this->_oFileSystem->is_dir($this->_sArchiveDbPath)) {
            $this->_oFileSystem->mkdir($this->_sArchiveDbPath, 0777, true);
        } elseif (!is_writable($this->_sArchiveDbPath)) {
            $this->_oFileSystem->chmod($this->_sArchiveDbPath, 0777);
        }
        if (!$this->_oFileSystem->is_dir($this->_sDbPath)) {
            throw new \Exception($this->_sDbPath.' is not a directory.');
        } elseif (!is_writable($this->_sDbPath)) {
            throw new \Exception($this->_sDbPath.' is not writable.');
        }
    }

    /**
     * @param array $aDbFiles
     * @return $this
     */
    public function setDbFiles(array $aDbFiles) {
        $this->_aDbFiles = $aDbFiles;
        return $this;
    }

    /**
     * @param array $aValidationItems
     * @return $this
     */
    public function setValidationItems(array $aValidationItems) {
        $this->_aValidationItems = $aValidationItems;
        return $this;
    }

    /**
     * When done, we remove tmp files and display the resulting DB version.
     */
    public function __destruct() {
        $this->_oFileSystem->emptyDir($this->_sTmpDbPath);
        $this->_oLogger->log('DB version is now '.geoip_database_info());
    }

    /**
     * Update mode
     * Archives current db files if needed
     * Retrieves DB files from Maxmind
     * Checks that DB files are not empty
     * Archives the new DB files
     * Loads new DB files
     * Validates that GeoIp works with newly loaded files using the validation items
     * If validation fails, rolls back to previous version and blacklists the faulty version
     *
     * @param \Gzip $oGzip
     */
    public function update(\Gzip $oGzip) {
        $this->_archiveDbFiles($this->_sDbPath);
        $this->_retrieveDbFiles($oGzip);
        $this->_checkDbFiles();
        $this->_archiveDbFiles($this->_sTmpDbPath);
        $this->_loadDbFiles($this->_sTmpDbPath);
        if (!$this->_validateDbFiles()) {
            $this->_rollbackToPrevious();
            $this->_blackListVersion($this->_getDbVersion($this->_sTmpDbPath)); //blacklisted loaded DB version
        }
    }

    /**
     * Rollback mode
     * Rolls back until it finds a valid archived version.
     */
    public function rollback() {
        $this->_rollbackToPrevious();
        while (!$this->_validateDbFiles()) {
            $this->_rollbackToPrevious();
        }
    }

    /**
     * Rollbacks to current version-1
     * @throws Exception
     */
    public function _rollbackToPrevious() {
        $this->_oLogger->log('Roll back');

        //Which version should be loaded? If current version is in the archives, we load the previous one, otherwise, we load latest archived version.
        $aArchivedDbFolders = $this->_oFileSystem->scanDir($this->_sArchiveDbPath, 1);
        $sCurrentVersion = $this->_getDbVersion($this->_sDbPath);
        $this->_oLogger->log('Current version : '.$sCurrentVersion);
        $iLevel = 0;
        foreach ($aArchivedDbFolders as $iIndex => $sArchivedDbFolder) {
            $aArchivedDbFolder = explode('_', $sArchivedDbFolder);
            $sArchivedDbVersion = isset($aArchivedDbFolder[1])?$aArchivedDbFolder[1]:null;
            if ($sArchivedDbVersion == $sCurrentVersion) {
                $iLevel = $iIndex+1;
                break;
            }
        }

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
            $this->_oLogger->log('Skipping validation. Each validation item in '.DB_URL_LIST_CSV.' must be specify a geoip function, a host or IP and a result (ISO country code, region... See http://us1.php.net/manual/fr/ref.geoip.php)');
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

    /**
     * Moves the temporary DB files to their final destination. This actually updates the DB files.
     * Also checks that the new version is different from current version before loading.
     * @param string $sPath
     * @throws Exception
     */
    protected function _loadDbFiles($sPath) {
        $this->_oLogger->log('Loading DB files.');

        //Checking current DB files are up to date.
        $sNewVersion = $this->_getDbVersion($sPath);
        $sCurrentVersion = $this->_getDbVersion($this->_sDbPath);
        if ($sNewVersion == $sCurrentVersion) {
            throw new \Exception('Current version '.$sCurrentVersion.' is up to date.');
        }

        //Loading files
        $aDbFiles = $this->_oFileSystem->glob($sPath.DIRECTORY_SEPARATOR."*");
        if (is_array($aDbFiles) && count($aDbFiles) > 1) {
            if (!in_array($sPath.DIRECTORY_SEPARATOR.self::DB_VERSION_FILE_NAME, $aDbFiles)) {
                throw new \Exception('Cannot load DB files from '.$sPath.', there is no hash file.');
            }
            foreach ($aDbFiles as $sDbFile) {
                $this->_oLogger->log('Loading '.$sDbFile.' to '.$this->_sDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
                $this->_oFileSystem->copy($sDbFile, $this->_sDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
            }
        } else {
            throw new \Exception('Cannot load DB files from '.$sPath.', there are no files!.');
        }
    }

    /**
     * Makes an archive folder for the new DB files. If the archive already exists, it skips it.
     */
    protected function _archiveDbFiles($sDbPath) {
        $this->_oLogger->log('Archiving DB files from '.$sDbPath);

        //Clean first
        $this->_cleanArchives();
        $sVersionToArchive = $this->_getDbVersion($sDbPath);
        $sArchiveDbPath = $this->_sArchiveDbPath.DIRECTORY_SEPARATOR.date('YmdHis').'_'.$sVersionToArchive;
        $aExistingArchives = $this->_oFileSystem->glob($this->_sArchiveDbPath.DIRECTORY_SEPARATOR.'*_'.$sVersionToArchive);
        if (is_array($aExistingArchives) && count($aExistingArchives) > 0) {
            $this->_oLogger->log('Archive for version '.$sVersionToArchive.' already exists in '.implode(' ', $aExistingArchives));
        } else {
            $this->_oLogger->log('Archiving to '.$sArchiveDbPath);
            $this->_oFileSystem->mkdir($sArchiveDbPath, 0777, true);
            $aDbFiles = $this->_oFileSystem->glob($sDbPath.DIRECTORY_SEPARATOR."*");
            if (is_array($aDbFiles)) {
                foreach ($aDbFiles as $sDbFile) {
                    $this->_oFileSystem->copy($sDbFile, $sArchiveDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
                }
            }
        }
    }

    /**
     * Cleans up the archive dir, keeping the number of archives version to its maximum.
     */
    protected function _cleanArchives() {
        $aArchivedDbVersions = $this->_oFileSystem->scanDir($this->_sArchiveDbPath, 1);
        $aOlderDbVersions = array_slice($aArchivedDbVersions, MAX_ARCHIVED_DB_VERSIONS);
        if (is_array($aOlderDbVersions) && !empty($aOlderDbVersions)) {
            $this->_oLogger->log('There are '.count($aArchivedDbVersions).' archived versions, the oldest will be removed.');
            foreach ($aOlderDbVersions as $sOlderDbVersion) {
                if ($this->_oFileSystem->is_dir($this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sOlderDbVersion)) {
                    $this->_oLogger->log('Removing '.$this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sOlderDbVersion);
                    $this->_oFileSystem->emptyDir($this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sOlderDbVersion, true);
                }
            }
        }
    }

    /**
     * Retrieves the DB files from MaxMind and stores them in the tmp folder.
     * @throws Exception
     */
    protected function _retrieveDbFiles(\Gzip $oGzip) {
        $this->_oLogger->log('DB files retrieval.');
        if (empty($this->_aDbFiles)) {
            throw new \Exception('There are no DB file listed in '.DB_URL_LIST_CSV);
        }
        $this->_oLogger->log('There are '.count($this->_aDbFiles).' files to retrieve.');
        foreach ($this->_aDbFiles as $aDbFileSrc) {
            $sDbFileSrc = $aDbFileSrc[0];
            $this->_oLogger->log('Retrieving DB from '.$sDbFileSrc);
            $oGzip->unzip($sDbFileSrc, $this->_sTmpDbPath.DIRECTORY_SEPARATOR.str_replace('.gz', '', basename($sDbFileSrc)),"w+");
        }
    }

    /**
     * Basic check of the retrieved DB files.
     * @throws Exception
     */
    protected function _checkDbFiles() {
        $this->_oLogger->log('Checking tmp DB files (blacklist and size) in '.$this->_sTmpDbPath);

        //Size
        $aDbFiles = $this->_oFileSystem->glob($this->_sTmpDbPath.DIRECTORY_SEPARATOR."*.dat");
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
        $this->_oLogger->log('Building version for DB files in '.$sPath);
        if ($this->_oFileSystem->is_dir($sPath)) {
            $VersionFilePath = $sPath.DIRECTORY_SEPARATOR.self::DB_VERSION_FILE_NAME;

            //Check for version file, if it exists, return version
            if ($this->_oFileSystem->file_exists($VersionFilePath)) {
                $sVersion = $this->_oFileSystem->file_get_contents($VersionFilePath);
                $this->_oLogger->log('Version is '.$sVersion);
            } else {
                $this->_oLogger->log('No hash file found.');
                $aDbFiles = $this->_oFileSystem->glob($sPath.DIRECTORY_SEPARATOR."*.dat");
                if (!is_array($aDbFiles) || count($aDbFiles) < 1) {
                    throw new \Exception('Could not get DB version, there are no .dat files.');
                }
                $sDbContents = '';
                foreach ($aDbFiles as $sDbFile) {
                    $sDbContents .= $this->_oFileSystem->file_get_contents($sDbFile);
                }
                $sVersion = sha1($sDbContents);
                $this->_oLogger->log('Calculated version '.$sVersion.' and writing it to hash file '.$VersionFilePath);
                if (!@$this->_oFileSystem->file_put_contents($VersionFilePath, $sVersion)) {
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
        if ($this->_oFileSystem->file_exists($sBlackistFilePath)) {
            $handle = $this->_oFileSystem->fopen($sBlackistFilePath, 'r');
            while (($buffer = $this->_oFileSystem->fgets($handle)) !== false) {
                if (strpos($buffer, $sVersion) !== false) {
                    $bBlacklisted = true;
                }
            }
            $this->_oFileSystem->fclose($handle);
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
        if (!@$this->_oFileSystem->file_put_contents($sBlackistFilePath, $sVersion."\n", FILE_APPEND)) {
            $this->_oLogger->log('Could not blacklist version '.$sVersion.' in '.$sBlackistFilePath);
        }

        //Removing blacklisted archive
        $aArchives = $this->_oFileSystem->glob($this->_sArchiveDbPath.DIRECTORY_SEPARATOR."*_".$sVersion);
        if (is_array($aArchives)) {
            foreach ($aArchives as $sArchivePath) {
                if (strpos($sArchivePath, $sVersion)) {
                    $this->_oLogger->log('Removing archive for '.$sVersion);
                    $this->_oFileSystem->emptyDir($sArchivePath, true);
                }
            }
        }
    }
}