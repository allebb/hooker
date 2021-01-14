<?php

/**
 * Hooker - A single file web-hook deployment tool.
 *
 * @author Bobby Allen <ballen@bobbyallen.me>
 * @license http://opensource.org/licenses/GPL-2.0
 * @link https://github.com/allebb/hooker
 * @link https://github.com/allebb/hooker/issues
 *
 */
$config = [
    /**
     * Enable debug mode (output errors and information in the response body).
     */
    'debug' => true,

    /**
     * Set an opitional key for added protection.
     * Example: qgW87T9RgJYKj2DucnChELXeJhLCFP8N
     */
    'key' => false,

    /**
     * The remote repository to pull/checkout form.
     * Example: git@github.com:allebb/test-website.git
     * @todo Project currently does not make use of this setting but is reserved for future functionality.
     */
    'remote_repo' => '',

    /**
     * Which branch to pull/checkout from.
     * Example: master
     * @todo Project currently does not make use of this setting but is reserved for future functionality.
     */
    'branch' => 'master',

    /**
     * Local repository/hosted directory.
     */
    'local_repo' => __DIR__,

    /**
     * Set a specific user to run the pre, deploy and post commands with (if false will run as the current user, normally 'www-data')
     * Example: jbloggs
     */
    'user' => false,

    /**
     * Pre-deploy commands to run.
     */
    'pre_commands' => [],

    /**
     * Deployment commands.
     */
    'deploy_commands' => [
        'cd {{local-repo}} && {{git-bin}} reset --hard HEAD && {{git-bin}} pull',
        //'cd {{local-repo}} && sudo -u {{user}} {{git-bin}} reset --hard HEAD && sudo -u {{user}} {{git-bin}} pull',
    ],

    /**
     * Post-deploy commands to run.
     */
    'post_commands' => [],

    /**
     * If the repo is GitHub hosted, set to true, this will ensure
     * web-hook is only executed on a 'push' event from GitHub.
     */
    'is_github' => false,

    /**
     * List of configured hooks that the code will deploy on (when using GitHub option)
     * @see https://developer.github.com/webhooks/#payloads
     */
    'github_deploy_events' => [
        'push',
        'release',
    ],

    /**
     * If the repo is BitBucket hosted, set to true, this will ensure
     * web-hook is only executed on configured events..
     */
    'is_bitbucket' => false,

    /**
     * List of configured hooks that the code will deploy on (when using GitHub option)
     * @see https://confluence.atlassian.com/bitbucket/event-payloads-740262817.html#EventPayloads-RepositoryEvents
     */
    'bitbucket_deploy_events' => [
        'repo:push',
    ],

    /**
     * Configurable whitelist of IP addresses that are allowed to trigger deployments.
     */
    'ip_whitelist' => [
        '127.0.0.1',
        '::1',
    ],

    /**
     * The path to the git binary.
     */
    'git_bin' => 'git',

    /**
     * The default path to the PHP version used for deployments.
     */
    'php_bin' => 'php',

    /**
     * The path to the composer binary.
     */
    'composer_bin' => '/usr/bin/composer',

    /**
     * Sites - If hosting a single instance of this script you can configure separate "site" configurations below.
     */
    'sites' => [],
];

/**
 *
 * ////////////////////////////////////////////////////////////////////////////////////// *
 * // End of user configuration - It is not recommended that you edit below this line! // *
 * ////////////////////////////////////////////////////////////////////////////////////// *
 *
 */

const HTTP_OK = 200;
const HTTP_UNAUTHORISED = 401;
const HTTP_NOTFOUND = 404;
const HTTP_ERROR = 500;

$log = [];

handlePingRequest();

// Read current PHP version that this script is using.
$php_version = phpversion();

if (file_exists(__DIR__ . '/hooker.conf.php')) {
    $config_file = require_once __DIR__ . '/hooker.conf.php';
    $config = array_merge($config, $config_file);
    debugLog("Loading configuration from configuration override file (hooker.conf.php)", $config['debug']);
}

if ((!function_exists('shell_exec'))) {
    setStatusCode(HTTP_ERROR);
    debugLog("The PHP function shell_exec() does not exist, aborting deployment!", $config['debug']);
    outputLog($config, true);
}

$git_output = shell_exec($config['git_bin'] . ' --version 2>&1');
if ((!strpos($git_output, 'version'))) {
    setStatusCode(HTTP_ERROR);
    debugLog("The 'git' binary was not found or could not be executed on your server, aborting deployment!",
        $config['debug']);
    outputLog($config, true);
}
debugLog("Git version detected: {$git_output}", $config['debug']);

$application = (isset($_GET['app'])) ? $_GET['app'] : false;
if ($application) {
    if (isset($config['sites'][$application])) {
        $config = array_merge([
            'debug' => $config['debug'],
            'key' => $config['key'],
            'user' => $config['user'],
            'git_bin' => $config['git_bin'],
            'php_bin' => $config['php_bin'],
            'composer_bin' => $config['composer_bin'],
            'is_github' => $config['is_github'],
            'github_deploy_events' => $config['github_deploy_events'],
            'is_bitbucket' => $config['is_bitbucket'],
            'bitbucket_deploy_events' => $config['bitbucket_deploy_events'],
            'ip_whitelist' => $config['ip_whitelist'],
            'pre_commands' => $config['pre_commands'],
            'deploy_commands' => $config['deploy_commands'],
            'post_commands' => $config['post_commands'],
        ], $config['sites'][$application]
        );
        debugLog("Application specific configuration detected and being used!", $config['debug']);
    } else {
        debugLog("The requested site/application ({$application}) configuration was not found!", $config['debug']);
        setStatusCode(HTTP_NOTFOUND);
        outputLog($config, true);
    }
}

checkIpAuth($config);
checkKeyAuth($config);


foreach ([$config['git_bin'], $config['php_bin'], $config['composer_bin']] as $binary) {
    if (!file_exists($binary)) {
        setStatusCode(HTTP_ERROR);
        debugLog("The executable path '{$binary}' does not exist, please check your server or adjust your configuration as required!",
            $config['debug']);
        outputLog($config, true);
    }
}
debugLog("Using tools installed at:", $config['debug']);
debugLog(" - Git: {$config['git_bin']}", $config['debug']);
debugLog(" - PHP: {$config['php_bin']}", $config['debug']);
debugLog(" - Composer: {$config['composer_bin']}", $config['debug']);

if ($config['is_github']) {
    debugLog("Repository flagged as GitHub hosted.", $config['debug']);
    if (!in_array(requestHeader('X-Github-Event'), $config['github_deploy_events'])) {
        debugLog("The GitHub hook event (" . requestHeader('X-Github-Event') . ") was not found in the github_deploy_events list, skipping the deployment!",
            $config['debug']);
        outputLog($config, true);
    }
}

if ($config['is_bitbucket']) {
    debugLog("Repository flagged as BitBucket hosted.", $config['debug']);
    if (!in_array(requestHeader('X-Event-Key'), $config['bitbucket_deploy_events'])) {
        debugLog("The BitBucket hook event (" . requestHeader('X-Event-Key') . ") was not found in the 'bitbucket_deploy_events' list, skipping the deployment!",
            $config['debug']);
        outputLog($config, true);
    }
}

foreach (replaceCommandPlaceHolders($config) as $execute) {
    $exec_output = shell_exec($execute . ' 2>&1');
    debugLog("Executing command: {$execute}", $config['debug']);
    debugLog($exec_output, $config['debug']);
}

outputLog($config);
echo "done";

/**
 * Responds to the PING request.
 * return void
 */
function handlePingRequest()
{
    if (isset($_GET['ping'])) {
        echo 'PONG';
        exit;
    }
}

/**
 * Log messages out to the user.
 * @param string $message
 * @param boolean $output
 * @return void
 */
function debugLog($message, $output = false)
{
    if ($output) {
        $GLOBALS['log'][] = date("c") . ' - ' . $message;
    }
}

/**
 * Retrieve a request header key.
 * @param string $key The header key to return the value for.
 * @param mixed $default Optional default return value.
 * @return string
 */
function requestHeader($key, $default = false)
{
    if (!function_exists('getallheaders')) {
        function getallheaders()
        {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-',
                        ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        }
    }

    $request_headers = getallheaders();
    if (isset($request_headers[$key])) {
        return $request_headers[$key];
    }
    return $default;
}

/**
 * In-line replacement of command tags.
 * @param array $config The configuration input array.
 * @return array The prepared command array.
 */
function replaceCommandPlaceHolders($config)
{
    $command_array = [];
    $cmd_tags = [
        '{{local-repo}}' => $config['local_repo'],
        '{{user}}' => $config['user'],
        '{{git-bin}}' => $config['git_bin'],
        '{{php-bin}}' => $config['php_bin'],
        '{{composer-bin}}' => $config['composer_bin'],
        '{{branch}}' => $config['branch'],
        '{{repo}}' => $config['remote_repo'],
    ];
    foreach (array_merge($config['pre_commands'], $config['deploy_commands'], $config['post_commands']) as $commands) {
        $command_array[] = str_replace(array_keys($cmd_tags), $cmd_tags, $commands);
    }
    return $command_array;
}

/**
 * Checks Key Parameter
 * @param $config
 * @return void
 */
function checkKeyAuth($config)
{
    $provided_key = isset($_GET['key']) ? $_GET['key'] : false;
    if ($config['key'] && ($config['key'] !== $provided_key)) {
        setStatusCode(HTTP_UNAUTHORISED);
        debugLog('Key auth failed!', true);
        outputLog($config, true);
    }
    debugLog("Key Auth successful", $config['debug']);
}

/**
 * Checks the client's IP address against the whitelist.
 * @param array $config
 * @return void
 */
function checkIpAuth($config)
{
    $remote_ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $remote_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    if ((count($config['ip_whitelist']) > 0) && (!in_array($remote_ip, $config['ip_whitelist']))) {
        debugLog("IP ({$remote_ip}) is not permitted on the whitelist, aborting deployment!", $config['debug']);
        setStatusCode(HTTP_UNAUTHORISED);
        outputLog($config, true);
    }
    debugLog("IP ({$remote_ip}) authorised by whitelist.", $config['debug']);
}

/**
 * Sets the HTTP response code.
 * @param int $status
 * @return void
 */
function setStatusCode($status = HTTP_OK)
{
    http_response_code($status);
}

/**
 * Outputs the debug log to the client.
 * @param array $config
 * @param boolean $exit
 * @return string
 */
function outputLog($config, $exit = false)
{
    if ($config['debug']) {
        echo implode(PHP_EOL, $GLOBALS['log']) . PHP_EOL;
    }
    if ($exit) {
        exit;
    }
}
