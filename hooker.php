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
     * Set an opitional key for added protection.
     * Example: qgW87T9RgJYKj2DucnChELXeJhLCFP8N
     */
    'key' => false,
    /**
     * The remote repository to pull/checkout form.
     * Example: git@github.com:bobsta63/hooker.git
     */
    'remote_repo' => '',
    /**
     * Which branch to pull/checkout from.
     * Example: master
     */
    'branch' => 'master',
    /**
     * Local repository/hosted directory.
     */
    'local_repo' => '',
    /**
     * Enable debug mode - Will output errors to the screen.
     */
    'debug' => false,
    /**
     * Set a specific user to run the pre and post commands with (if false will run as the current user)
     * Example: wwwdata
     */
    'sudo_as' => false,
    /**
     * Pre-deploy commands to run.
     */
    'pre_commands' => [
    ],
    /**
     * Deployment commands.
     */
    'deploy_commands' => [
        'git reset --hard HEAD',
        'git pull',
    ],
    /**
     * Post-deploy commands to run.
     */
    'post_commands' => [
    ],
    /**
     * The path to the git binary.
     */
    'git_bin' => 'git',
    /**
     * Sites - If hositng a single instance of this script you can configure seperate "site" configurations below.
     */
    'sites' => [
    //'my_example_website' => [
    //    'key' => false,
    //    'repo' => '',
    //    'branch' => 'master',
    //    'sudo_as' => false,
    //    'pre_commands' => [
    //    ],
    //    'post_commands' => [
    //    ],
    //],
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

if ((!function_exists('shell_exec')) && $config['debug']) {
    echo 'The PHP function shell_exec() does not exist!';
    exit;
}

$git_output = shell_exec($config['git_bin'] . ' --version 2>&1');
if ((!strpos($git_output, 'version')) && $config['debug']) {
    echo "The 'git' binary was not found or could not be executed on your server!";
    exit;
}

if (isset($_REQUEST['app'])) {
    if (isset($config['sites'][$_REQUEST['app']])) {
        $config = array_merge($config, $config['sites'][$_REQUEST['app']]);
        echo "Deploying " . $_REQUEST['app'];
    }
    echo "The requested \"app\" configuration was not found!";
    exit;
}

$cmd_tags = [
    '{{ local-repo }}' => $config['local_repo'],
    '{{ user }}' => $config['local_repo'],
    '{{ git-bin }}' => $config['git_bin'],
    '{{ branch }}' => $config['branch'],
    '{{ repo }}' => $config['remote_repo'],
];
