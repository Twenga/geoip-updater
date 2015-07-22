<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$aOptions = getopt('d:a:f:p:r:');

if (!isset($aOptions['d'])) {
    throw new \Exception('Missing MaxMind database path. Use -d [database_path]');
}

if (!isset($aOptions['f'])) {
    throw new \Exception('Missing path for MaxMind GeoIp2\Database\Reader class. Use -f [api_path]');
}

if (!isset($aOptions['a'])) {
    throw new \Exception('Missing IP address. Use -a [ip_address]');
}

if (!isset($aOptions['p'])) {
    throw new \Exception('Missing GeoIP property path. Use -p [property_path]. Use -> to dig into properties.');
}

if (!isset($aOptions['r'])) {
    throw new \Exception('Missing expected result. Use -r [expected_result].');
}

require $aOptions['f'];
use GeoIp2\Database\Reader;
$oReader = new Reader($aOptions['d']);
$record = $oReader->city($aOptions['a']);

$sValue = getPropertyFromPath($record, $aOptions['p']);

if ($sValue != $aOptions['r']) {
    throw new \Exception('Property '.$aOptions['p'].' with value "'.$sValue.'" is not equal to expected result "'.$aOptions['r'].'"');
}
echo $sValue;

/**
 * @param $obj
 * @param $path_str
 * @return null
 */
function getPropertyFromPath($obj, $path_str)
{
    $val = null;

    $path = preg_split('/->/', $path_str);
    $node = $obj;
    while (($prop = array_shift($path)) !== null) {
        if (!is_object($obj) || !$node->$prop) {
            $val = null;
            break;

        }
        $val = $node->$prop;
        $node = $node->$prop;
    }
    return $val;
}