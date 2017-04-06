<?php
/**
 * Hooker Configuration File
 */
return [
    'debug' => true,
    //'sites' => [
    //// Example basic HTML website.
    //'my_example_website' => [
    //    'debug' => true, // Output debug info
    //    'key' => 'SomeRandomStringThatMustBePresentInTheKeyParam',
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
    //        'cd {{local-repo}} && composer install --no-dev',
    //        'chmod 755 {{local-repo}}/storage',
    //        'php {{local-repo}}/artisan migrate --force',
    //        'php {{local-repo}}/artisan up',
    //        'php {{local-repo}}/config:cache',
    //        'php {{local-repo}}/cache:clear',
    //        'php {{local-repo}}/routes:cache',
    //        'php {{local-repo}}php queue:restart
    //    ],
    //],
    //],
];
