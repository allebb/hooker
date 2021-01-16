<?php

/**
 * Hooker Configuration File
 */
return [

    // Enable output of debugging information.
    'debug' => true,

    // Need to customise the default Git path?
    // 'git_bin' => '/usr/bin/git',

    // Need to customise the default PHP version used for running Composer and other PHP specific tasks?
    // ** This path can also be overridden by individual sites/applications as required in the sites array below. **
    //'php_bin' => '/usr/bin/php7.4',

    // Need to customise the default Composer path?
    // ** This path can also be overridden by individual sites/applications as required in the sites array below. **
    //'composer_bin' => '/usr/bin/composer',

    // By default we'll allow any server(s) to "hit" these deployment hooks but you can add specific IP addresses
    // to the whitelist if you wish...
    'ip_whitelist' => [],

    'sites' => [

        // An example basic HTML website (Webhook example: http://deploy.mysite.com/hooker.php?app=my_basic_website&key=SomeRandomWordThatMustBePresentInTheKeyParam).
        'my_example_website' => [
            'debug' => false, // Override and disable the output of debug info for this specific deployment workflow?
            'key' => 'SomeRandomStringThatMustBePresentInTheKeyParam',
            'local_repo' => '/var/www/html-website', // Use current directory
            'is_github' => true, // Use GitHub webhooks to trigger this workflow.
            'branch' => 'master', // As long as the GitHub webhook request relates to changes on this git branch, we'll run the deployment workflow!
            'git_ssh_key_path' => '/var/www/.ssh/html-website.deploykey', // If using Conductor, you can easily generate one by running `conductor genkey {appname}`
            //'pre_commands' => [
            //    // Uses the default (inherited deployment commands)
            //],
            //'deploy_commands' => [
            //    // Uses the default (inherited deployment commands eg. cd {{local-repo}} && {{git-ssh-key}}{{git-bin}} reset --hard HEAD && {{git-ssh-key}}{{git-bin}} pull)
            //],
            //'post_commands' => [
            //    // Uses the default (inherited deployment commands)
            //],
        ],

        // An example Laravel Deployment Configuration (Webhook example: http://deploy.mysite.com/hooker.php?app=my_other_website&key=32c9f55eea8526374731acca13c81aca)
        'my_other_website' => [
            'key' => '32c9f55eea8526374731acca13c81aca',
            'local_repo' => '@conductor', // This will auto-resolve to /var/conductor/applications/my_other_website
            'git_ssh_key_path' => '@conductor', // This will auto-resolve and use the private key at /var/www/.ssh/my_other_website.deploykey
            'user' => false,
            'php_bin' => '/usr/bin/php8.0',
            // Override the "default" PHP version used for this deployment/running Composer, this application needs PHP 8.0!
            //'composer_bin' => '/usr/bin/composer', // Need to override with a different Composer version?
            'pre_commands' => [
                '{{php-bin}} {{local-repo}}/artisan down', // Example of a pre-command to set our Laravel application into "maintenance mode".
                '{{php-bin}} {{local-repo}}/artisan config:clear', // We'll also clear the configuration cache before we pull the latest code from Git..
            ],
            //'deploy_commands' => [
            //    // Uses the default (inherited deployment command eg. cd {{local-repo}} && {{git-bin}} reset --hard HEAD && {{git-ssh-key}}{{git-bin}} pull)
            //    // You can of course run other tasks here too, shell scripts, npm, nodejs etc. etc.
            //],
            'post_commands' => [
                'cd {{local-repo}} && {{php-bin}} {{composer-bin}} install --no-dev --no-progress --prefer-dist --optimize-autoloader',
                'chmod 755 {{local-repo}}/storage',
                '{{php-bin}} {{local-repo}}/artisan migrate --force',
                '{{php-bin}} {{local-repo}}/artisan config:cache',
                '{{php-bin}} {{local-repo}}/artisan cache:clear',
                '{{php-bin}} {{local-repo}}/artisan route:cache',
                '{{php-bin}} {{local-repo}}/artisan up',
                //'{{php-bin}} {{local-repo}}/artisan queue:restart', // Using a job queue? Restart it so it uses the latest version of your code!
                //'{{php-bin}} {{local-repo}}/artisan horizon:terminate', // Using Horizon for your queues instead??
            ],
        ],

        // An example Configuration using a local "hooker.json" repository configuration. (Webhook example: http://deploy.mysite.com/hooker.php?app=another_application&key=VgUjbEIPbOCpiRQa2UHjqiXcmbE8eIht)
        'another_application' => [
            'key' => 'VgUjbEIPbOCpiRQa2UHjqiXcmbE8eIht',
            'local_repo' => '@conductor', // This will auto-resolve to /var/conductor/applications/another_application
            'git_ssh_key_path' => '@conductor', // This will auto-resolve and use the private key at /var/www/.ssh/another_application.deploykey
            'use_json' => 'true', // This will read the configuration from a hooker.json file stored in your git repo. eg. /var/www/another_application/hooker.json
        ],


    ],
];
