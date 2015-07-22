<?php

namespace Extractor;
use \Extractor;

require_once(__DIR__ . '/../Extractor.php');


/**
 * Class Gzip
 * Extracts a single file compressed as Gzip.
 * @package Extractor
 */
class Gzip implements Extractor {

    /**
     * @param $sSourceFile
     * @param $sDestinationDir
     * @throws \Exception
     * @return bool
     */
    public function extract($sSourceFile, $sDestinationDir) {
        $sGzip = @file_get_contents($sSourceFile);
        if (!$sGzip) {
            throw new \Exception($sSourceFile.' could not be reached.');
        }
        $sRest = substr($sGzip, -4);
        $iGZFileSize = end(unpack("V", $sRest));
        $rZp = @gzopen($sSourceFile, "r");
        if (!$rZp) {
            throw new \Exception($sSourceFile.' could not be opened.');
        }
        $sUnzippedData = @gzread($rZp, $iGZFileSize);
        @gzclose($rZp);
        // Check we have data
        if (strlen($sUnzippedData) > 0) {
            // Write data to local file
            $rLZFP = @fopen($sDestinationDir.DIRECTORY_SEPARATOR.str_replace('.gz', '', basename($sSourceFile)), "w+");
            if (false === @fwrite($rLZFP, $sUnzippedData)) {
                throw new \Exception($sSourceFile.' could not write unzipped data to '.$sDestinationDir);
            }
            @fclose($rLZFP);
        } else {
            throw new \Exception('Could not read zipped data from '.$sSourceFile);
        }
    }
}