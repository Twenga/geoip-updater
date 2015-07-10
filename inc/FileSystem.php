<?php

/**
 * Class FileSystem
 * File system utils
 */
class FileSystem {

    /**
     * Utils
     * Returns a list of files/folders within a given folder. It omits the '.' and '..' entries.
     * @param $sDir
     * @param null $iSort
     * @return array
     */
    public function scanDir($sDir, $iSort = null) {
        $aExcludeList = array(".", "..");
        return array_values(array_diff(scandir($sDir, $iSort), $aExcludeList));
    }

    /**
     * Utils : Recursively empties a dir, optionally removes the dir.
     * @param $sDir
     * @param bool $bRemoveDir
     */
    public function emptyDir($sDir, $bRemoveDir = false) {
        if (!$dh = @opendir($sDir)) return;
        while (false !== ($obj = readdir($dh))) {
            if ($obj=='.' || $obj=='..') continue;
            if (!@unlink($sDir.'/'.$obj)) self::emptyDir($sDir.'/'.$obj, true);
        }
        closedir($dh);
        if ($bRemoveDir === true) {
            @rmdir($sDir);
        }
    }

    /**
     * @param $sDir
     */
    public function rmdir($sDir) {
        rmdir($sDir);
    }

    /**
     * @param $sPath
     * @return bool
     */
    public function is_dir($sPath) {
        return is_dir($sPath);
    }

    /**
     * @param $sPath
     * @param int $iMode
     * @param bool $bRecursive
     * @return bool
     */
    public function mkdir($sPath, $iMode = 0777, $bRecursive = false) {
        return mkdir($sPath, $iMode, $bRecursive);
    }

    /**
     * @param $sPath
     * @return bool
     */
    public function is_writable($sPath) {
        return is_writable($sPath);
    }

    /**
     * @param $sPath
     * @param $iMode
     * @return bool
     */
    public function chmod($sPath, $iMode) {
        return chmod($sPath, $iMode);
    }

    /**
     * @param $Source
     * @param $sDestination
     * @param null $rContext
     * @return bool
     */
    public function copy($Source, $sDestination, $rContext = null) {
        return call_user_func_array('copy', func_get_args());
    }

    /**
     * @param $sPattern
     * @param null $iFlags
     * @return mixed
     */
    public function glob($sPattern, $iFlags = null) {
        return call_user_func_array('glob', func_get_args());
    }

    /**
     * @param $sPath
     * @param null $iFlags
     * @param null $rContext
     * @param null $iOffset
     * @param null $iMaxLen
     * @return mixed
     */
    public function file_get_contents($sPath, $iFlags = null, $rContext = null, $iOffset = null, $iMaxLen = null) {
        return call_user_func_array('file_get_contents', func_get_args());
    }

    /**
     * @param $sPath
     * @param $sData
     * @param null $iFlags
     * @param null $rContext
     * @return mixed
     */
    public function file_put_contents($sPath, $sData, $iFlags = null, $rContext = null) {
        return call_user_func_array('file_put_contents', func_get_args());
    }

    /**
     * @param $sPath
     * @return bool
     */
    public function file_exists ($sPath) {
        return file_exists($sPath);
    }

    /**
     * @param $sSource
     * @param $sDestination
     */
    public function rename($sSource, $sDestination) {
        rename($sSource, $sDestination);
    }

    /**
     * @param $sPath
     * @param $iMode
     * @param null $bUseIncludePath
     * @param null $rContext
     * @return mixed
     */
    public function fopen ($sPath, $iMode, $bUseIncludePath = null, $rContext = null) {
        return call_user_func_array('fopen', func_get_args());
    }

    /**
     * @param $rHandle
     * @param null $iLength
     * @return mixed
     */
    public function fgets ($rHandle, $iLength = null) {
        return call_user_func_array('fgets', func_get_args());
    }

    /**
     * @param $rHandle
     * @return mixed
     */
    public function fclose($rHandle) {
        return call_user_func_array('fclose', func_get_args());
    }
}