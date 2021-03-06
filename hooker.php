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
     * Set an optional key for added protection.
     * Example: qgW87T9RgJYKj2DucnChELXeJhLCFP8N
     */
    'key' => false,

    /**
     * Set an optional SSH deployment key path (private key)
     * Example: /var/www/.ssh/my-app.deploykey
     */
    'git_ssh_key_path' => '',

    /**
     * The remote repository (SSH URI) to checkout code form (used in the default `init_commands`).
     * Example: git@github.com:allebb/test-website.git
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
    'local_repo' => '/tmp',

    /**
     * Set a specific user to run the pre, deploy and post commands with (if false will run as the current user, normally 'www-data')
     * Example: jbloggs
     */
    'user' => false,

    /**
     * Initialise commands to run .
     */
    'init_commands' => [
        'rm -Rf {{local-repo}}/* && rm -Rf {{local-repo}}/.* > /dev/null',
        '{{git-ssh-key}}{{git-bin}} -C {{local-repo}} clone {{remote-repo}} .',
        '{{git-bin}} -C {{local-repo}} checkout {{branch}}',
    ],

    /**
     * Pre-deploy commands to run.
     */
    'pre_commands' => [],

    /**
     * Deployment commands.
     */
    'deploy_commands' => [
        'cd {{local-repo}} && {{git-bin}} reset --hard HEAD && {{git-ssh-key}}{{git-bin}} pull',
        //'cd {{local-repo}} && sudo -u {{user}} {{git-bin}} reset --hard HEAD && sudo -u {{user}} {{git-ssh-key}}{{git-bin}} pull',
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
     * If the repo is GitLab hosted, set to true, this will ensure
     * web-hook is only executed on configured events..
     */
    'is_gitlab' => false,

    /**
     * List of configured hooks that the code will deploy on (when using GitHub option)
     * @see https://docs.gitlab.com/ee/user/project/integrations/webhooks.html#events
     */
    'gitlab_deploy_events' => [
        'Push Hook',
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
    'git_bin' => '/usr/bin/git',

    /**
     * The default path to the PHP version used for deployments.
     */
    'php_bin' => '/usr/bin/php',

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

const HOOKER_VERSION = "2.1.0";

const HTTP_OK = 200;
const HTTP_UNAUTHORISED = 401;
const HTTP_FORBIDDEN = 403;
const HTTP_NOTFOUND = 404;
const HTTP_ERROR = 500;

$log = [];

header("Content-Type: text/plain");

handlePingRequest();

// Read current PHP version that this script is using.
$php_version = phpversion();

if (file_exists(__DIR__ . '/hooker.conf.php')) {
    $config_file = require_once __DIR__ . '/hooker.conf.php';
    $config = array_merge($config, $config_file);
    debugLog("Loading configuration from configuration override file (hooker.conf.php)", $config['debug']);
}

// Allows overriding from a JSON file instead (designed for UI based management interfaces to easily export configurations)
if (file_exists(__DIR__ . '/hooker.conf.json')) {
    $config_file = json_decode(file_get_contents(__DIR__ . '/hooker.conf.json'), true);
    $config = array_merge($config, $config_file);
    debugLog("Loading configuration from configuration override file (hooker.conf.json)", $config['debug']);
}

outputAsciiArtHeader($config['debug']);

if ((!function_exists('shell_exec'))) {
    setStatusCode(HTTP_ERROR);
    debugLog("The PHP function shell_exec() does not exist, aborting deployment!", $config['debug']);
    outputLog($config, true);
}

$git_output = trim(shell_exec($config['git_bin'] . ' --version 2>&1'));
if ((!strpos($git_output, 'version'))) {
    setStatusCode(HTTP_ERROR);
    debugLog("The 'git' binary was not found or could not be executed on your server, aborting deployment!",
        $config['debug']);
    outputLog($config, true);
}
debugLog("Hooker webservice running: v" . HOOKER_VERSION . " (on PHP v{$php_version})", $config['debug']);
debugLog("Git version detected: {$git_output}", $config['debug']);


$application = (isset($_GET['app'])) ? $_GET['app'] : false;
if (!$application || !isset($config['sites'][$application])) {
    debugLog("The requested site/application ({$application}) configuration was not found!", $config['debug']);
    setStatusCode(HTTP_NOTFOUND);
    outputLog($config, true);
}

$config = array_merge([
    'debug' => $config['debug'],
    'key' => $config['key'],
    'user' => $config['user'],
    'use_json' => $config['use_json'],
    'git_bin' => $config['git_bin'],
    'git_ssh_key_path' => $config['git_ssh_key_path'],
    'php_bin' => $config['php_bin'],
    'composer_bin' => $config['composer_bin'],
    'is_github' => $config['is_github'],
    'github_deploy_events' => $config['github_deploy_events'],
    'is_bitbucket' => $config['is_bitbucket'],
    'bitbucket_deploy_events' => $config['bitbucket_deploy_events'],
    'disable_init' => $config['disable_init'],
    'ip_whitelist' => $config['ip_whitelist'],
    'init_commands' => $config['init_commands'],
    'pre_commands' => $config['pre_commands'],
    'deploy_commands' => $config['deploy_commands'],
    'post_commands' => $config['post_commands'],
], $config['sites'][$application]);
debugLog("Application configuration detected and being used!", $config['debug']);

checkIpAuth($config);
checkKeyAuth($config);

if ($config['use_json']) {
    $localConfigPath = buildLocalRepoPath($config['local_repo']) . '/.hooker.json';
    debugLog("Deployment workflow is configured to use a local '.hooker.json' file, attempting to load this now from: {$localConfigPath}",
        $config['debug']);
    $config = array_merge($config, loadLocalHookerConf($localConfigPath, $config));
}

foreach ([$config['git_bin'], $config['php_bin'], $config['composer_bin']] as $binary) {
    if (!file_exists($binary)) {
        setStatusCode(HTTP_ERROR);
        debugLog("The executable path '{$binary}' does not exist, please check your server or adjust your configuration as required!",
            $config['debug']);
        outputLog($config, true);
    }
}

debugLog("Using tools installed at:", $config['debug']);
debugLog(" * Git: {$config['git_bin']}", $config['debug']);
debugLog(" * PHP: {$config['php_bin']}", $config['debug']);
debugLog(" * Composer: {$config['composer_bin']}", $config['debug']);

if (isset($_GET['init'])) {
    if ($config['disable_init']) {
        setStatusCode(HTTP_FORBIDDEN);
        debugLog("The Hooker configuration prevents initialisation for this application, first enable it and try again!",
            $config['debug']);
        outputLog($config, true);
    }

    debugLog("Local initialisation has been requested, running the initialisation commands...", $config['debug']);
    foreach (replaceCommandPlaceHolders($config, $config['init_commands']) as $execute) {
        $exec_output = trim(executeAndCaptureOutput($execute));
        debugLog("RUN [{$execute}]" . PHP_EOL . ":::::    RESULT    :::::" . PHP_EOL . $exec_output . PHP_EOL . "::::::::::::::::::::::::",
            $config['debug']);
    }
    debugLog("Local initialisation has been completed!", $config['debug']);
    setStatusCode(HTTP_OK);
    outputLog($config, true);
}

/**
 * GitHub Branch and Hook Event checking.
 */
if ($config['is_github']) {
    debugLog("Repository flagged as GitHub hosted.", $config['debug']);
    if (!in_array(requestHeader('X-Github-Event'), $config['github_deploy_events'])) {
        debugLog("The GitHub hook event (" . requestHeader('X-Github-Event') . ") was not found in the github_deploy_events list, skipping the deployment!",
            $config['debug']);
        outputLog($config, true);
    }

    if (requestHeader('Content-Type') == 'application/json') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            setStatusCode(HTTP_ERROR);
            debugLog("Unable to decode the JSON payload, check that the webhook is configured to use `application/json` as the Content type, skipping branch matching...",
                $config['debug']);
            outputLog($config, true);
        }

        if (!isset($payload['ref']) || $payload['ref'] !== 'refs/heads/' . $config['branch']) {
            setStatusCode(HTTP_OK);
            debugLog("Branch matching failed, configuration checking for '" . 'refs/heads/' . $config['branch'] . "' but GitHub sent: " . $payload['ref'] . "' instead! Skipping this deployment!",
                $config['debug']);
            debugLog("Skipping this deployment!", $config['debug']);
            outputLog($config, true);
        }

        debugLog("Branch matching successful, GitHub triggered the webhook for branch: " . $config['branch'] . ", continuing with the deployment...",
            $config['debug']);
    }
}

/**
 * BitBucket Branch and Hook Event checking.
 */
if ($config['is_bitbucket']) {
    debugLog("Repository flagged as BitBucket hosted.", $config['debug']);
    if (!in_array(requestHeader('X-Event-Key'), $config['bitbucket_deploy_events'])) {
        setStatusCode(HTTP_OK);
        debugLog("The BitBucket hook event (" . requestHeader('X-Event-Key') . ") was not found in the 'bitbucket_deploy_events' list, skipping the deployment!",
            $config['debug']);
        outputLog($config, true);
    }

    if (requestHeader('Content-Type') == 'application/json') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            setStatusCode(HTTP_ERROR);
            debugLog("Unable to decode the JSON payload, check that the webhook is configured to use `application/json` as the Content type, skipping branch matching...",
                $config['debug']);
            outputLog($config, true);
        }

        if (!isset($payload['ref']['push']['changes']['old']['name']) || $payload['ref']['push']['changes']['old']['name'] !== $config['branch']) {
            setStatusCode(HTTP_OK);
            debugLog("Branch matching failed, configuration checking for '" . 'refs/heads/' . $config['branch'] . "' but BitBucket sent: " . $payload['ref']['push']['changes']['old']['name'] . "' instead! Skipping this deployment!",
                $config['debug']);
            debugLog("Skipping this deployment!", $config['debug']);
            outputLog($config, true);
        }

        debugLog("Branch matching successful, BitBucket triggered the webhook for branch: " . $config['branch'] . ", continuing with the deployment...",
            $config['debug']);
    }
}

/**
 * GitLab Hook Event checking.
 */
if ($config['is_gitlab']) {
    debugLog("Repository flagged as GitLab hosted.", $config['debug']);
    if (!in_array(requestHeader('X-Gitlab-Event'), $config['gitlab_deploy_events'])) {
        setStatusCode(HTTP_OK);
        debugLog("The GitLab hook event (" . requestHeader('X-Gitlab-Event') . ") was not found in the 'gitlab_deploy_events' list, skipping the deployment!",
            $config['debug']);
        outputLog($config, true);
    }

    if (requestHeader('Content-Type') == 'application/json') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            setStatusCode(HTTP_ERROR);
            debugLog("Unable to decode the JSON payload, skipping branch matching...", $config['debug']);
            outputLog($config, true);
        }

        if (!isset($payload['ref']) || $payload['ref'] !== 'refs/heads/' . $config['branch']) {
            setStatusCode(HTTP_OK);
            debugLog("Branch matching failed, configuration checking for '" . 'refs/heads/' . $config['branch'] . "' but GitLab sent: " . $payload['ref'] . "' instead! Skipping this deployment!",
                $config['debug']);
            debugLog("Skipping this deployment!", $config['debug']);
            outputLog($config, true);
        }

        debugLog("Branch matching successful, GitHub triggered the webhook for branch: " . $config['branch'] . ", continuing with the deployment...",
            $config['debug']);
    }
}

foreach (
    replaceCommandPlaceHolders($config,
        array_merge($config['pre_commands'], $config['deploy_commands'], $config['post_commands'])) as $execute
) {
    $exec_output = trim(executeAndCaptureOutput($execute));
    debugLog("RUN [{$execute}]" . PHP_EOL . ":::::    RESULT    :::::" . PHP_EOL . $exec_output . PHP_EOL . "::::::::::::::::::::::::",
        $config['debug']);
}
setStatusCode(HTTP_OK);
outputLog($config);
echo "done!";

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
 * Executes a system command and returns the stdOut.
 * @param $execute
 * @return mixed
 */
function executeAndCaptureOutput($execute)
{
    return shell_exec($execute . ' 2>&1');
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
 * @param array $commands Array of commands to process.
 * @return array The prepared command array.
 */
function replaceCommandPlaceHolders($config, $commands)
{
    $command_array = [];
    $cmd_tags = [
        '{{local-repo}}' => buildLocalRepoPath($config['local_repo']),
        '{{remote-repo}}' => $config['remote_repo'],
        '{{branch}}' => $config['branch'],
        '{{user}}' => $config['user'],
        '{{git-bin}}' => $config['git_bin'],
        '{{git-ssh-key}}' => buildSshKeyExportVariable($config['git_ssh_key_path']),
        '{{php-bin}}' => $config['php_bin'],
        '{{composer-bin}}' => $config['composer_bin'],
    ];
    foreach ($commands as $cmds) {
        $command_array[] = str_replace(array_keys($cmd_tags), $cmd_tags, $cmds);
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
        setStatusCode(HTTP_UNAUTHORISED);
        debugLog("IP ({$remote_ip}) is not permitted on the whitelist, aborting deployment!", $config['debug']);
        outputLog($config, true);
    }
    debugLog("IP ({$remote_ip}) authorised by whitelist.", $config['debug']);
}

/**
 * Constructs the local path to the application hosted directory.
 * @param $path
 * @return string
 */
function buildLocalRepoPath($path)
{
    if (strtolower($path) == '@conductor') {
        return '/var/conductor/applications/' . $_GET['app'];
    }
    return $path;
}

/**
 * Constructs the GIT_SSH_COMMAND export string if a valid key file has been found.
 * @param string $keyfile
 * @return string
 */
function buildSshKeyExportVariable($keyfile = '')
{
    if (strtolower($keyfile) == '@conductor') {
        $keyfile = '/var/www/.ssh/' . $_GET['app'] . '.deploykey';
    }

    if (file_exists($keyfile)) {
        return "GIT_SSH_COMMAND='ssh -i \"{$keyfile}\" -o IdentitiesOnly=yes' ";
    }
    return "";
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

/**
 * Output a fancy ASCII art header on the debug information.
 * @param boolean $show Output the generated ASCII art header.
 * @return void
 */
function outputAsciiArtHeader($show = false)
{
    $logo = [
        "",
        "             __  __            __                     ",
        "            / / / /___  ____  / /_____  _____         ",
        "           / /_/ / __ \/ __ \/ //_/ _ \/ ___/         ",
        "          / __  / /_/ / /_/ / ,< /  __/ /             ",
        "         /_/ /_/\____/\____/_/|_|\___/_/  v" . HOOKER_VERSION,
        "                                                      ",
        "              ..automated deployments, made easy!     ",
        "                                                      ",
    ];
    foreach ($logo as $line) {
        debugLog($line, $show);
    }
}

/**
 * Reads a local (versioned) Hooker Configuration file (.hooker.json) and merges with the current default configuration.
 * @param string $path The file system path to the local repository.
 * @param array $baseConfiguration The configuration array to merge with.
 * @return array
 */
function loadLocalHookerConf($path, $baseConfiguration = [])
{
    $disabledOverrides = [
        'disable_init',
        'remote_repo',
        'local_repo',
        'branch',
        'key',
        'user',
    ];
    if (!file_exists($path)) {
        debugLog("The `.hooker.json` file was not found at: {$path}, please fix and try again!", true);
        outputLog($baseConfiguration, true);
    }

    $localConfiguration = json_decode(file_get_contents($path), true);
    if (!$localConfiguration) {
        setStatusCode(500);
        debugLog("The `.hooker.json` file syntax is invalid (it must be valid JSON), please fix and try again!", true);
        outputLog($baseConfiguration, true);
    }
    $mergedConfig = array_merge($baseConfiguration, $localConfiguration);
    debugLog("The `.hooker.json` workflow has been loaded successfully!", $baseConfiguration['debug']);
    return array_diff_key($mergedConfig, array_flip($disabledOverrides));
}

