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
    /**
     * Set an opitional key for added protection.
     * Example: qgW87T9RgJYKj2DucnChELXeJhLCFP8N
     */
    'key' => false,
    /**
     * The remote repository to pull/checkout form.
     * Example: git@github.com:bobsta63/test-website.git
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
    'local_repo' => __DIR__,
    /**
     * Set a specific user to run the pre, deploy and post commands with (if false will run as the current user, normally 'www-data')
     * Example: jbloggs
     */
    'user' => false,
    /**
     * Pre-deploy commands to run.
     */
    'pre_commands' => [
    ],
    /**
     * Deployment commands.
     */
    'deploy_commands' => [
        'cd {{local-repo}} && git reset --hard HEAD && git pull',
    //'cd {{local-repo}} && sudo -u {{user}} git reset --hard HEAD && sudo -u {{user}} git pull',
    ],
    /**
     * Post-deploy commands to run.
     */
    'post_commands' => [
    ],
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
     * Sites - If hositng a single instance of this script you can configure seperate "site" configurations below.
     */
    'sites' => [
    //// Example basic HTML website.
    //'my_example_website' => [
    //    'debug' => true, // Output debug info
    //    'key' => 'SomeRandomWordThatMustBePresentInTheKeyParam',
    //    'remote_repo' => 'git@github.com:bobsta63/test-website.git',
    //    'local_repo' => '/var/www/html-website', // Use current directory
    //    'branch' => 'master',
    //    'user' => false,
    //    'is_github' => true,
    //    'pre_commands' => [
    //      // Use the default (inheritated deployment commands)
    //    ],
    //    'deploy_commnads' => [
    //      // Use the default (inheritated deployment commands)
    //    ],
    //   'post_commands' => [
    //      // Use the default (inheritated deployment commands)
    //    ],
    //],
    // // Example Laravel Deployment Configuration.
    //'my_other_website' => [
    //    'key' => '32c9f55eea8526374731acca13c81aca',
    //    'remote_repo' => 'git@github.com:bobsta63/my-other-website-repo.git',
    //    'local_repo' => '/var/www/my-other-website',
    //    'branch' => 'deploy-live',
    //    'user' => false,
    //    'pre_commands' => [
    //        'php {{local-repo}}/artisan down',
    //    ],
    //    'deploy_commnads' => [
    //         // Use the default (inheritated deployment commands)
    //    ],
    //   'post_commands' => [
    //        'cd {{local-repo}} && composer insall',
    //        'chmod 755 {{local-repo}}/storage',
    //        'php {{local-repo}}/artisan migrate --force',
    //        'php {{local-repo}}/artisan down',
    //    ],
    //],
    ],
];

/**
 * End or user configuration - It is not recommended that you edit below this line!
 */
handlePingRequest();

if (file_exists(__DIR__ . '/hooker.conf.php')) {
    $config_file = require_once __DIR__ . '/hooker.conf.php';
    $config = array_merge($config, $config_file);
    debugLog("Loading configuration from configuration override file (hooker.conf.php)", $config['debug']);
}

if ((!function_exists('shell_exec'))) {
    debugLog("The PHP function shell_exec() does not exist, aborting deployment!", $config['debug']);
    exit;
}

$git_output = shell_exec($config['git_bin'] . ' --version 2>&1');
if ((!strpos($git_output, 'version'))) {
    debugLog("The 'git' binary was not found or could not be executed on your server, aborting deployment!", $config['debug']);
    exit;
}
debugLog("Git version detected: {$git_output}", $config['debug']);

if (isset($_REQUEST['app'])) {
    if (isset($config['sites'][$_REQUEST['app']])) {
        $config = array_merge([
            'debug' => $config['debug'],
            'key' => $config['key'],
            'user' => $config['user'],
            'git_bin' => $config['git_bin'],
            'is_github' => $config['is_github'],
            'github_deploy_events' => $config['github_deploy_events'],
            'is_bitbucket' => $config['is_bitbucket'],
            'bitbucket_deploy_events' => $config['bitbucket_deploy_events'],
            'ip_whitelist' => $config['ip_whitelist'],
            'pre_commands' => $config['pre_commands'],
            'deploy_commands' => $config['deploy_commands'],
            'post_commands' => $config['post_commands'],
            ], $config['sites'][$_REQUEST['app']]
        );
        debugLog("Application specific configurtion detected and being used!", $config['debug']);
    } else {
        debugLog("The requested site/application ({$_REQUEST['app']}) configuration was not found!'", $config['debug']);
        exit;
    }
}

checkIpAuth($config);
checkKeyAuth($config);

if ($config['is_github']) {
    if (!in_array(requestHeader('X-Github-Event'), $config['github_deploy_events'])) {
        debugLog("The GitHub hook event (" . requestHeader('X-Github-Event') . ") was not found in the github_deploy_events list, aborting the deployment!", $config['debug']);
        exit;
    }
}

if ($config['is_bitbucket']) {
    if (!in_array(requestHeader('X-Event-Key'), $config['bitbucket_deploy_events'])) {
        debugLog("The BitBucket hook event (" . requestHeader('X-Event-Key') . ") was not found in the 'bitbucket_deploy_events' list, aborting the deployment!", $config['debug']);
        exit;
    }
}

foreach (replaceCommandPlaceHolders($config) as $execute) {
    $exec_output = shell_exec($execute . ' 2>&1');
    debugLog("Executing command {$execute}", $config['debug']);
    debugLog($exec_output, $config['debug']);
}
echo "done";

/**
 * Responds to the PING request if requested.
 */
function handlePingRequest()
{
    if (isset($_REQUEST['ping'])) {
        echo 'PONG';
        exit;
    }
}

/**
 * Log messages out to the user.
 * @param string $message
 */
function debugLog($message, $output = false)
{
    if ($output) {
        echo date("c") . ' - ' . $message . PHP_EOL;
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
    $cmd_tags = [
        '{{local-repo}}' => $config['local_repo'],
        '{{user}}' => $config['user'],
        '{{git-bin}}' => $config['git_bin'],
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
 * @return void
 */
function checkKeyAuth($config)
{
    $provided_key = isset($_REQUEST['key']) ? $_REQUEST['key'] : false;
    if ($config['key'] && ($config['key'] !== $provided_key)) {
        echo "Key auth failed!";
        exit;
    }
}

function checkIpAuth($config)
{
    $remote_ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $remote_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    if ((count($config['ip_whitelist']) > 0) && (!in_array($remote_ip, $config['ip_whitelist']))) {
        debugLog("IP ({$remote_ip}) is not permitted on the whitelist, aborting deloyment!", $config['debug']);
        echo "IP whitelist pass failed!";
        exit;
    }
    debugLog("IP ({$remote_ip}) authorised by whitelist.", $config['debug']);
}
