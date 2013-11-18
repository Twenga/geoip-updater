<?php

class Csv {

    /**
     * Utils
     * @param $sCsvFilePath
     * @return array
     * @throws Exception
     */
    static public function csvToArray($sCsvFilePath) {
        $aCsvContent = array();
        if (($rHandle = fopen($sCsvFilePath, "r")) !== FALSE) {
            while (($aData = fgetcsv($rHandle, 1000, ",", '"')) !== FALSE) {
                $aCsvContent[] = $aData;
            }
            fclose($rHandle);
        } else {
            throw new \Exception('File '.$sCsvFilePath.' not found.');
        }
        return $aCsvContent;
    }
}