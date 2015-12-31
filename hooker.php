<?php
/**
 * Hooker - A single file web-hook deployment tool.
 *
 * @author Bobby Allen <bobbyallen.uk@gmail.com>
 * @link https://github.com/bobsta63/hooker
 * @link https://github.com/bobsta63/hooker/issues
 * @license http://opensource.org/licenses/GPL-2.0
 *
 */
$config = [
    
    /**
     * Enable debug mode - Will output errors to the screen.
     */
    'debug' => false,
];

/**
 * End or user configuration - It is not recommended that you edit below this line!
 */

if (file_exists('hooker.conf.php')) {
    $config_file = require_once 'hooker.conf.php';
    $config = array_merge($config, $config_file);
}
