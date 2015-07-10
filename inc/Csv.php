<?php

class Csv {

    protected static $_sSeparator = ",";
    protected static $_sEnclosure = '"';

    /**
     * Utils
     * @param $sCsvFilePath
     * @return array
     * @throws Exception
     */
    static public function csvToArray($sCsvFilePath) {
        $aCsvContent = array();
        if (($rHandle = fopen($sCsvFilePath, "r")) !== FALSE) {
            while (($aData = fgetcsv($rHandle, 1000, self::$_sSeparator, self::$_sEnclosure)) !== FALSE) {
                $aCsvContent[] = $aData;
            }
            fclose($rHandle);
        } else {
            throw new \Exception('File '.$sCsvFilePath.' not found.');
        }
        return $aCsvContent;
    }
}