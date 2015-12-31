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
     * The remote repository to pull/checkout form.
     * Example: git@github.com:bobsta63/hooker.git
     */
    'repo' => '',
    
    /**
     * Which branch to pull/checkout from.
     * Example: master
     */
    'branch' => 'master',
    /**
     * Enable debug mode - Will output errors to the screen.
     */
    'debug' => false,
    /**
     * Pre-deploy commands to run.
     */
    'post_commands' => [
    ],
    /**
     * Post-deploy commands to run.
     */
    'post_commands' => [
    ],
];

/**
 * End or user configuration - It is not recommended that you edit below this line!
 */
if (isset($_REQUEST['ping'])) {
    echo 'PONG';
    exit;
}

if (file_exists('hooker.conf.php')) {
    $config_file = require_once 'hooker.conf.php';
    $config = array_merge($config, $config_file);
}

if((!function_exists('shell_exedc')) && $config['debug']){
    echo 'The PHP function shell_exec() does not exist!';
    exit;
}

