<?php
/**
 * @file
 * A configuration class for the UDP client class for StatsD.
 *
 * Adapted from the examples/php-example.php file in the StatsD package.
 */

namespace OSInet\StatsdPhpClient;

class Config
{
    private static $_instance;
    private $_data;

    private function __construct()
    {
        $this->_data = parse_ini_file('statsd.ini', true);
    }

    public static function getInstance()
    {
        if (!self::$_instance) self::$_instance = new self();

        return self::$_instance;
    }

    public function isEnabled($section)
    {
        return isset($this->_data[$section]);
    }

    public function getConfig($name)
    {
        $name_array = explode('.', $name, 2);

        if (count($name_array) < 2) return;

        list($section, $param) = $name_array;

        if (!isset($this->_data[$section][$param])) return;

        return $this->_data[$section][$param];
    }
}
