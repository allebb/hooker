<?php
/**
 * Hooker Configuration File
 */
return [
    'debug' => true,
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
    //      'pre_commands' => [
    //          'php {{local-repo}}/artisan down',
    //          'php {{local-repo}}/artisan config:cache',
    //      ],
    //      'deploy_commands' => [
    //          // Use the default (inherited deployment commands)
    //      ],
    //      'post_commands' => [
    //          'cd {{local-repo}} && composer install --no-dev --no-suggest --no-progress --prefer-dist --optimize-autoloader',
    //          'chmod 755 {{local-repo}}/storage',
    //          'php {{local-repo}}/artisan migrate --force',
    //          'php {{local-repo}}/artisan config:cache',
    //          'php {{local-repo}}/artisan cache:clear',
    //          'php {{local-repo}}/artisan route:cache',
    //          'php {{local-repo}}/artisan up',
    //          //'php {{local-repo}}/artisan horizon:terminate',
    //          'php {{local-repo}}/artisan queue:restart',
    //      ],
    //  ],
    //],
];
