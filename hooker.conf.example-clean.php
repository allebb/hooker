<?php

/**
 * Hooker Configuration File
 */
return [
    'ip_whitelist' => [],
    'sites' => [

        // (Webhook example: http://deploy.mysite.com/hooker.php?app=first_application&key=SomeRandomWordThatMustBePresentInTheKeyParam).
        'first_application' => [
            'key' => 'SomeRandomStringThatMustBePresentInTheKeyParam',
            'local_repo' => '/var/www/html-website',
            'git_ssh_key_path' => '/var/www/.ssh/html-website.deploykey', // If using Conductor, you can easily generate one by running `conductor genkey {appname}`
            'is_github' => true,
            'branch' => 'master',
            'pre_commands' => [
                '{{php-bin}} {{local-repo}}/artisan down',
                '{{php-bin}} {{local-repo}}/artisan config:clear',
            ],
            //'deploy_commands' => [
            //  'cd {{local-repo}} && {{git-bin}} reset --hard HEAD && {{git-ssh-key}}{{git-bin}} pull',
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

        // (Webhook example: http://deploy.mysite.com/hooker.php?app=second_application&key=VgUjbEIPbOCpiRQa2UHjqiXcmbE8eIht)
        // This example uses a local ``.hooker.json`` for it's workflow configuration (this must be present in the root of your Git repository).
        'second_application' => [
            'key' => 'VgUjbEIPbOCpiRQa2UHjqiXcmbE8eIht',
            'local_repo' => '@conductor', // This will auto-resolve to /var/conductor/applications/second_application
            'git_ssh_key_path' => '@conductor', // This will auto-resolve and use the private key at /var/www/.ssh/second_application.deploykey
            'is_github' => false,
            'branch' => 'deploy-prod',
            'use_json' => 'true',
        ],


    ],
];
