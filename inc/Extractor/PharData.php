<?php

namespace Extractor;
use \Extractor;
/**
 * Class PharData
 * @package Extractor
 */
class PharData implements Extractor {

    /**
     * Extracts files from Gzipped tarball
     * @param $sSourceFile
     * @param $sDestinationDir
     * @throws \Exception
     * @return bool
     */
    public function extract($sSourceFile, $sDestinationDir) {

        //Retrieve file content and copy it locally
        $sTmpTarGz = '/tmp/phardata_'.time().'_'.rand().'.tar.gz'; //Trying to generate a somewhat unique temp file name
        $sContents = file_get_contents($sSourceFile);
        file_put_contents($sTmpTarGz, $sContents);

        //UnGzipping
        $oPhar = new \PharData($sTmpTarGz);
        $oPhar->decompress();

        $sTmpTar = str_replace('.gz', '', $sTmpTarGz);

        //UnTARing
        $oPhar = new \PharData($sTmpTar);
        $oPhar->extractTo($sDestinationDir, null, true);

        //Cleaning up
        unlink($sTmpTarGz);
        unlink($sTmpTar);
    }
}