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
if (isset($_REQUEST['ping'])) {
    echo 'PONG';
    exit;
}

if (file_exists(__DIR__ . '/hooker.conf.php')) {
    $config_file = require_once __DIR__ . '/hooker.conf.php';
    $config = array_merge($config, $config_file);
    log("Loading configuration from configuration override file (hooker.conf.php)", $config['debug']);
}

if ((!function_exists('shell_exec'))) {
    log("The PHP function shell_exec() does not exist!", $config['debug']);
    exit;
}

$git_output = shell_exec($config['git_bin'] . ' --version 2>&1');
if ((!strpos($git_output, 'version'))) {
    log("The 'git' binary was not found or could not be executed on your server!", $config['debug']);
    exit;
}

if (isset($_REQUEST['app'])) {
    if (isset($config['sites'][$_REQUEST['app']])) {
        $config = array_merge([
            'debug' => $config['debug'],
            'key' => $config['key'],
            'user' => $config['user'],
            'git_bin' => $config['git_bin'],
            'is_github' => $config['is_github'],
            'github_deploy_events' => $config['github_deploy_events'],
            'pre_commands' => $config['pre_commands'],
            'deploy_commands' => $config['deploy_commands'],
            'post_commands' => $config['post_commands'],
            ], $config['sites'][$_REQUEST['app']]
        );
        log("Application specific configurtion detected and being used!", $config['debug']);
    } else {
        log("The requested site/application ({$_REQUEST['app']}) configuration was not found!'", $config['debug']);
        exit;
    }
}

checkKeyAuth($config);

if ($config['is_github']) {
    if (!in_array(requestHeader('X-Github-Event'), $config['github_deploy_events'])) {
        log("The GitHub hook event (" . requestHeader('X-Github-Event') . ") was not found in the github_deploy_events list.", $config['debug']);
        exit;
    }
}

if ($config['is_bitbucket']) {
    if (!in_array(requestHeader('X-Event-Key'), $config['bitbucket_deploy_events'])) {
        log("The BitBucket hook event (" . requestHeader('X-Event-Key') . ") was not found in the 'bitbucket_deploy_events' list.", $config['debug']);
        exit;
    }
}

foreach (replaceCommandPlaceHolders($config) as $execute) {
    shell_exec($execute);
    log("Executing {{$execute}}", $config['debug']);
}
echo "ok";

/**
 * Log messages out to the user.
 * @param string $message
 */
function log($message, $output = false)
{
    if ($output) {
        echo date("c") . ' - ' . $message;
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
        echo "Authentication failed!";
        exit;
    }
}
