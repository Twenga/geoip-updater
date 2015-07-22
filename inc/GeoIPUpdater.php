<?php
/**
 * Class GeoIpUpdater
 */
abstract class GeoIpUpdater {

    /**
     * Path to GeoIp DB files
     * @var string
     */
    protected $_sDbPath;

    /**
     * Path to Archived GeoIp DB files
     * @var string
     */
    protected $_sArchiveDbPath;

    /**
     * Path to a tmp dir where we temporarily store retrieved DB files
     * @var string
     */
    protected $_sTmpDbPath;

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
     * @var Extractor
     */
    protected $_oExtractor;

    /**
     * @var string
     */
    protected $_sDbFileExtension = ".dat";

    /**
     * Checks required folders.
     * Checks whether the GeoIP extension is loaded.
     * @param Logger $oLogger
     * @param FileSystem $oFileSystem
     * @param Extractor $oExtractor
     */
    public function __construct(\Logger $oLogger, \FileSystem $oFileSystem, \Extractor $oExtractor) {
        $this->_oLogger = $oLogger;
        $this->_oFileSystem = $oFileSystem;
        $this-> _checkEnv();
        $this-> _checkConfig();
        $this->_setUpFileSystem();
        $this->_oExtractor = $oExtractor;
    }

    /**
     * Array of DB files. Can be URL's to remote files
     * @param array $aDbFiles
     * @return $this
     */
    public function setDbFiles(array $aDbFiles) {
        $this->_aDbFiles = $aDbFiles;
        return $this;
    }

    /**
     * List of validation items (function, arg, expected result)
     * @param array $aValidationItems
     * @return $this
     */
    public function setValidationItems(array $aValidationItems) {
        $this->_aValidationItems = $aValidationItems;
        return $this;
    }

    /**
     * Update mode
     * - Archives current db files if needed
     * - Retrieves DB files from MaxMind
     * - Checks that DB files are not empty
     * - Archives the new DB files
     * - Loads new DB files
     * - Validates that GeoIp works with newly loaded files using the validation items
     * If validation fails :
     * - Rolls back to previous version
     * - Blacklists the faulty version
     */
    public function update() {
        $this->_archiveDbFiles($this->_sDbPath); //Archiving current DB files
        $this->_retrieveDbFiles();
        $this->_archiveDbFiles($this->_sTmpDbPath); //Archiving new DB files
        $this->_loadDbFiles($this->_sTmpDbPath); //Loading new DB files
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

        //Listing archives
        $aArchivedDbFolders = $this->_oFileSystem->scanDir($this->_sArchiveDbPath, 1);
        if (!is_array($aArchivedDbFolders) || empty($aArchivedDbFolders)) {
            throw new \Exception('No archive to rollback to in '.$this->_sArchiveDbPath);
        }

        //Figuring out which version we should roll back to
        //If current version is in the archives, we load the previous one,
        //Otherwise, we load latest archived version.
        $sCurrentVersion = $this->_getDbVersion($this->_sDbPath);

        $iLevel = 0; //Represents a n-level archive
        foreach ($aArchivedDbFolders as $iIndex => $sArchivedDbFolder) {
            $aArchivedDbFolder = explode('_', $sArchivedDbFolder);
            $sArchivedDbVersion = isset($aArchivedDbFolder[1])?$aArchivedDbFolder[1]:null;
            if ($sArchivedDbVersion == $sCurrentVersion) {
                $iLevel = $iIndex+1;
                break;
            }
        }

        //Actually loading the archive
        $aPreviousDbVersion = array_slice($aArchivedDbFolders, $iLevel, 1);
        if (!is_array($aPreviousDbVersion) || empty($aPreviousDbVersion)) {
            throw new \Exception('No more archive to rollback to from '.$this->_sArchiveDbPath);
        }
        $sPreviousDbVersion = $aPreviousDbVersion[0];
        $this->_oLogger->log('Rolling back to version '.$sPreviousDbVersion);
        $sPreviousDbVersionPath = $this->_sArchiveDbPath.DIRECTORY_SEPARATOR.$sPreviousDbVersion;
        $this->_loadDbFiles($sPreviousDbVersionPath);
    }

    /**
     * Makes an archive folder for a set of DB files. If the archive already exists, it skips it.
     * @param $sPath
     * @throws Exception
     */
    protected function _archiveDbFiles($sPath) {
        $this->_oLogger->log('Archiving DB files from '.$sPath);

        //Clean first
        $this->_cleanArchives();
        $sVersionToArchive = $this->_getDbVersion($sPath);
        if ($sVersionToArchive) {
            $sArchiveDbPath = $this->_sArchiveDbPath.DIRECTORY_SEPARATOR.date('YmdHis').'_'.$sVersionToArchive;
            $aExistingArchives = $this->_oFileSystem->glob($this->_sArchiveDbPath.DIRECTORY_SEPARATOR.'*_'.$sVersionToArchive);
            if (is_array($aExistingArchives) && count($aExistingArchives) > 0) {
                $this->_oLogger->log('Archive for version '.$sVersionToArchive.' already exists in '.implode(' ', $aExistingArchives));
            } else {
                $this->_oLogger->log('Archiving to '.$sArchiveDbPath);
                $this->_oFileSystem->mkdir($sArchiveDbPath, 0777, true);
                $aDbFiles = $this->_oFileSystem->glob($sPath.DIRECTORY_SEPARATOR."*");
                if (is_array($aDbFiles)) {
                    foreach ($aDbFiles as $sDbFile) {
                        $this->_oFileSystem->copy($sDbFile, $sArchiveDbPath.DIRECTORY_SEPARATOR.basename($sDbFile));
                    }
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
     * Basic check of the retrieved DB files.
     * @param $sPath
     * @throws Exception
     */
    protected function _checkDbFiles($sPath) {
        $this->_oLogger->log('Checking DB files (blacklist and size) in '.$sPath);

        //Size
        $aDbFiles = $this->_oFileSystem->glob($sPath."*".$this->_sDbFileExtension);
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
        $sVersion = $this->_getDbVersion($sPath);
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
        $this->_oLogger->log('Getting version for DB files in '.$sPath);
        if ($this->_oFileSystem->is_dir($sPath)) {
            $VersionFilePath = $sPath.DIRECTORY_SEPARATOR.self::DB_VERSION_FILE_NAME;

            //Check for version file, if it exists, return version
            if ($this->_oFileSystem->file_exists($VersionFilePath)) {
                $sVersion = $this->_oFileSystem->file_get_contents($VersionFilePath);
                $this->_oLogger->log('Version in hash file is '.$sVersion);
            } else {
                $this->_oLogger->log('No hash file found.');
                $aDbFiles = $this->_oFileSystem->glob($sPath.DIRECTORY_SEPARATOR."*".$this->_sDbFileExtension);
                if (!is_array($aDbFiles) || count($aDbFiles) < 1) {
                    return false;
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
        if (!$sVersion) {
            return false;
        }
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

    /**
     * Checks and prepares directories
     * @throws Exception
     */
    protected function _setUpFileSystem() {
        //Tmp
        if (!$this->_oFileSystem->is_dir($this->_sTmpDbPath)) {
            $this->_oFileSystem->mkdir($this->_sTmpDbPath, 0777, true);
        } elseif (!is_writable($this->_sTmpDbPath)) {
            $this->_oFileSystem->chmod($this->_sTmpDbPath, 0777);
        }

        //Archive
        if (!$this->_oFileSystem->is_dir($this->_sArchiveDbPath)) {
            $this->_oFileSystem->mkdir($this->_sArchiveDbPath, 0777, true);
        } elseif (!is_writable($this->_sArchiveDbPath)) {
            $this->_oFileSystem->chmod($this->_sArchiveDbPath, 0777);
        }

        //DB path
        if (!$this->_oFileSystem->is_dir($this->_sDbPath)) {
            throw new \Exception($this->_sDbPath.' is not a directory.');
        } elseif (!is_writable($this->_sDbPath)) {
            throw new \Exception($this->_sDbPath.' is not writable.');
        }
    }

    /**
     * Moves the temporary DB files to their final destination. This actually updates the DB files.
     * Also checks that the new version is different from current version before loading.
     * @param $sPath
     * @throws Exception
     */
    protected function _loadDbFiles($sPath) {
        $this->_oLogger->log('Loading DB files.');

        //Checking current DB files are up to date.
        $sNewVersion = $this->_getDbVersion($sPath);
        $sCurrentVersion = $this->_getDbVersion($this->_sDbPath);
        if ($sNewVersion != $sCurrentVersion) {
            //Loading files
            $aDbFiles = $this->_oFileSystem->glob($sPath.DIRECTORY_SEPARATOR."*".DIRECTORY_SEPARATOR);
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
            };
        } else {
            $this->_oLogger->log('DB files are already up to date.');
        }
        $this->_checkDbFiles($sPath.DIRECTORY_SEPARATOR);
    }

    /**
     * Retrieves the DB files from MaxMind and stores them in the tmp folder.
     * @throws Exception
     */
    abstract protected function _retrieveDbFiles();

    /**
     * Calls GeoIP functions to check that it returns the expected results for a given pool of known IP addresses/hosts.
     * This method can't throw exceptions, we must roll back if validation fails.
     * If validation cannot be done (no item or invalid validation item), we skip it!
     * @return bool
     */
    abstract protected function _validateDbFiles();

    /**
     * Checks whether GeoIP functions are available for validation
     * @throws Exception
     */
    abstract protected function _checkEnv();

    /**
     * @return mixed
     */
    abstract protected function _checkConfig();
}