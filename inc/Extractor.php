<?php

/**
 * Interface Extractor
 */
interface Extractor {

    /**
     * @param $sSourceFile
     * @param $sDestinationDir
     * @return mixed
     */
    function extract($sSourceFile, $sDestinationDir);

}