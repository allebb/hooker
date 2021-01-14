<?php
/**
 * Hooker Configuration File
 */
return [
    // Enable output of debugging information.
    'debug' => true,
    // You can set a default PHP version to be used by all sites/applications (otherwise will use 'php' by default)
    // this version can also be overridden by individual sites/applications as required.
    //'php_bin' => 'php7.4',
    // You can set the default Composer installation path (otherwise will default to '/usr/bin/composer' by default)
    //'composer_bin' => '/usr/bin/composer',
    // By default we'll allow any server(s) to "hit" these deployment hooks but you can add specific IP addresses
    // to the whitelist if you wish...
    'ip_whitelist' => [],
    //'sites' => [
    //  // Example basic HTML website.
    //  'my_example_website' => [
    //      'debug' => true, // Output debug info
    //      'key' => 'SomeRandomStringThatMustBePresentInTheKeyParam',
    //      'remote_repo' => 'git@github.com:allebb/test-website.git',
    //      'local_repo' => '/var/www/html-website', // Use current directory
    //      'branch' => 'master',
    //      'user' => false,
    //      'is_github' => true,
    //      'pre_commands' => [
    //          // Uses the default (inherited deployment commands)
    //      ],
    //      'deploy_commands' => [
    //          // Uses the default (inherited deployment commands)
    //      ],
    //      'post_commands' => [
    //          // Uses the default (inherited deployment commands)
    //      ],
    //  ],
    //  // Example Laravel Deployment Configuration.
    //  'my_other_website' => [
    //      'key' => '32c9f55eea8526374731acca13c81aca',
    //      'remote_repo' => 'git@github.com:allebb/my-other-website-repo.git',
    //      'local_repo' => '/var/www/my-other-website',
    //      'branch' => 'deploy-live',
    //      'user' => false,
    //      'php_bin' => 'php8.0', // Overrides the PHP version used for this deployment.
    //      'pre_commands' => [
    //          'php {{local-repo}}/artisan down',
    //          'php {{local-repo}}/artisan config:cache',
    //      ],
    //      'deploy_commands' => [
    //          // Use the default (inherited deployment commands)
    //      ],
    //      'post_commands' => [
    //          'cd {{local-repo}} && {{php-bin}} {{composer-bin}} install --no-dev --no-suggest --no-progress --prefer-dist --optimize-autoloader',
    //          'chmod 755 {{local-repo}}/storage',
    //          '{{php-bin}} {{local-repo}}/artisan migrate --force',
    //          '{{php-bin}} {{local-repo}}/artisan config:cache',
    //          '{{php-bin}} {{local-repo}}/artisan cache:clear',
    //          '{{php-bin}} {{local-repo}}/artisan route:cache',
    //          '{{php-bin}} {{local-repo}}/artisan up',
    //          //'{{php-bin}} {{local-repo}}/artisan horizon:terminate',
    //          '{{php-bin}} {{local-repo}}/artisan queue:restart',
    //      ],
    //  ],
    //],
];
