<?php
/**
 * Class Logger
 * Simply builds a log from the passed content. Will echo content when verbose is enabled.
 */
class Logger {

    /**
     * @var
     */
    protected $_sContent;

    /**
     * @var bool
     */
    protected $_bVerbose = false;

    /**
     * Enables/disables verbose
     * @param $bVerbose
     */
    public function setVerbose($bVerbose) {
        $this->_bVerbose = $bVerbose;
    }

    /**
     * Adds some content to the log.
     * @param $sContent
     */
    public function log($sContent) {
        if ($this->_bVerbose === true) {
            echo $sContent."\n";
        }
        $this->_sContent .= $sContent;
    }
}