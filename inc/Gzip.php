<?php

class Gzip {

    /**
     * @param $sSource
     * @param $sDestination
     * @return bool
     * @throws Exception
     */
    public function unzip($sSource, $sDestination) {
        $sGzip = file_get_contents($sSource);
        if (!$sGzip) {
            throw new \Exception($sSource.' could not be reached.');
        }
        $sRest = substr($sGzip, -4);
        $iGZFileSize = end(unpack("V", $sRest));
        $rZp = @gzopen($sSource, "r");
        if (!$rZp) {
            throw new \Exception($sSource.' could not be opened.');
        }
        $sUnzippedData = @gzread($rZp, $iGZFileSize);
        @gzclose($rZp);
        // Check we have data
        if (strlen($sUnzippedData) > 0) {
            // Write data to local file
            $rLZFP = @fopen($sDestination, "w+");
            @fwrite($rLZFP, $sUnzippedData);
            @fclose($rLZFP);
            return true;
        } else {
            throw new \Exception($sSource.' could not be read.');
        }
    }
}