# Hooker

Hooker is a lightweight PHP web application that can be used to trigger remote workflows on your Linux or UNIX based servers.

It has specifically been designed to simplify and automate application deployments using Git or Docker containers when you don't want or need the complexity of a full CI/CD setup but you can easily use it for a ton of other really useful tasks.

## Requirements

* A web server (the installation guide uses Nginx)
* PHP 5.4+.
* The ``shell_exec()`` function is required (Some shared hosting environments disable this!)

## License

This script is released under the [GPLv2](https://github.com/allebb/hooker/blob/master/LICENSE) license. Feel free to
use it, fork it, improve it and contribute by open a pull-request!

## Installation

The installation involves creating a new virtual host configuration of which then acts as a web-hook
endpoint for multiple configured projects.

You should create separate site configurations that then get triggered by specifying the site/application configuration with the ``app`` parameter eg. ``https://deploy.mysite.com/hooker.php?app=website1``.

#### Creating the new virtualhost directory

In this example, we'll create a new Nginx vhost configuration, first we need to create a hosting directory to host
our ``hooker.php`` file:

```shell
sudo mkdir /var/www/hooker
```

We'll use Git to download the latest (stable) version (we'll also be able to use ``sudo -u www-data git pull`` in future
to apply updates):

```shell
cd /var/www/hooker    
sudo git clone https://github.com/allebb/hooker.git .
sudo git checkout stable
```

We'll now copy the example configuration file and use that to configure our individual sites:

```shell
sudo cp hooker.conf.example.php hooker.conf.php
``` 

At this point you should edit this file and configure your sites, for example it may look like this:

``/var/www/hooker/hooker.conf.php``

```php
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
            'is_github' => true, // Using GitHub webhooks to trigger this workflow.
            'branch' => 'deploy-prod', // As long as the GitHub webhook request relates to changes on this git branch, we'll run the deployment workflow!
            //'pre_commands' => [
            //    // Uses the default (inherited deployment commands)
            //],
            //'deploy_commands' => [
            //    // Uses the default (inherited deployment commands eg. cd {{local-repo}} && {{git-bin}} reset --hard HEAD && {{git-bin}} pull)
            //],
            //'post_commands' => [
            //    // Uses the default (inherited deployment commands)
            //],
        ],

        // An example Laravel Deployment Configuration (Webhook example: http://deploy.mysite.com/hooker.php?app=my_other_website&key=32c9f55eea8526374731acca13c81aca)
        'my_other_website' => [
            'key' => '32c9f55eea8526374731acca13c81aca',
            'local_repo' => '/var/www/my-other-website',
            'user' => false,
            'php_bin' => '/usr/bin/php8.0',
            // Override the "default" PHP version used for this deployment/running Composer, this application needs PHP 8.0!
            //'composer_bin' => '/usr/bin/composer', // Need to override with a different Composer version?
            'pre_commands' => [
                '{{php-bin}} {{local-repo}}/artisan down', // Example of a pre-command to set our Laravel application into "maintenance mode".
                '{{php-bin}} {{local-repo}}/artisan config:cache', // We'll also clear the configuration cache before we pull the latest code from Git..
            ],
            //'deploy_commands' => [
            //    // Uses the default (inherited deployment command eg. cd {{local-repo}} && {{git-bin}} reset --hard HEAD && {{git-bin}} pull)
            //    // You can of course run other tasks here too, shell scripts, npm, nodejs etc. etc.
            //],
            'post_commands' => [
                'cd {{local-repo}} && {{php-bin}} {{composer-bin}} install --no-dev --no-suggest --no-progress --prefer-dist --optimize-autoloader',
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
        
        // An example Laravel Deployment Configuration using a local "hooker.json" repository configuration. (Webhook example: http://deploy.mysite.com/hooker.php?app=another_application&key=VgUjbEIPbOCpiRQa2UHjqiXcmbE8eIht)
        'another_application' => [
            'key' => 'VgUjbEIPbOCpiRQa2UHjqiXcmbE8eIht',
            'local_repo' => '/var/www/another_application',
            'use_json' => 'true', // This will read the configuration from a hooker.json file stored in your git repo. eg. /var/www/another_application/hooker.json
        ],


    ],
];
```

### Create an SSH profile for the www-data user and generate your "Deploy Key"

Lets change into the ``www-data`` user's home directory:

```shell
cd /var/www
```

We will now create a new ``.ssh`` directory (profile) for the 'www-data' user (as it is this user account that will be,
behind the scenes connecting to Git) and set the required permissions.

```shell
mkdir .ssh
chown www-data:www-data -R .ssh
chmod 0700 .ssh
```

Now we'll generate a new SSH key-pair for the ``www-data`` user of which will be used to authenticate with your Git
hosting service.

__In order to enable headless operation ensure that you use the default options (just keep pressing the ENTER key at the
prompts) and when asked to enter a passphrase ensure that you leave it empty otherwise Hooker will not work correctly!__

```shell
sudo -u www-data ssh-keygen -t rsa -b 4096
```

The contents of the public key (``/var/www/.ssh/id_rsa.pub``) now needs to be copied and added to your Git hosting
provider's "Deploy keys" section:

```shell
cat /var/www/.ssh/id_rsa.pub
```

### Set correct permissions on the site directory

Now, we need to set the correct ownership and permissions for this new site:

```shell
chown www-data:www-data -R /var/www/hooker
```

### Configure Nginx virtualhost configuration

This example Nginx virtualhost configuration can be added to your server - assuming you're using Nginx and PHP7.0-FPM (
just make adjustments as required):

``/etc/nginx/sites-available/hooker.conf``

```
server {
    listen 80;
    root /var/www/hooker;
    server_name deploy.mysite.com;

    # Wish to secure and host your deployment web service using LetsEncrypt SSL?
    #listen 443 ssl;
    #ssl_certificate /etc/letsencrypt/live/deploy.mysite.com/fullchain.pem;
    #ssl_certificate_key /etc/letsencrypt/live/deploy.mysite.com/privkey.pem;
    #ssl_protocols TLSv1 TLSv1.1 TLSv1.2;
    #ssl_prefer_server_ciphers on;
    #ssl_ciphers AES256+EECDH:AES256+EDH:!aNULL;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri /index.php =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Once created, you will need to symlink it to the ``/etc/nginx/sites-enabled`` directory like so:

```shell
cd /etc/nginx/sites-enabled
ln -s ../sites-available/hooker.conf .
```

Now restart Nginx for the new virtualhost configuration to take affect:

```shell
sudo service nginx restart
```

### Finished!

If all goes well, you should be able to access the 'ping' test page at: ``http://deploy.mysite.com/hooker.php?ping``, a
successful installation should return the word 'PONG'.

## Updating

To update the version of Hooker simple run the following command(s):

```shell
cd /var/www/hooker
sudo -u www-data git pull
```

**Remember to check and update your ``hooker.conf.php`` and local ``hooker.json`` files with any new configuration options (where applicable), an overview of the "Configuration options" can be found in the next section.**


## Configuration options

The following configuration options exists and are explained below:

#### debug

Type: ``boolean``

Default: true

Description: When set to __true__ runtime information will be outputted to the browser, this is especially useful for
debugging purposes.

#### key

Type: ``string``

Default: false

Description: When not set as ``false``, this string must match the ``key`` parameter when calling the webhook, this can
be set globally (for all sites) or, set it individually on a per-site basis.

Example: ``TPuR81cS0gwP2T``

#### remote_repo

Type: ``string``

Default: empty

Description: This is currently not used but is reserved for future implementation.

Example: ``git@github.com:bobsta63/test-website.git``

#### branch

Type: ``string``

Default: ``master``

Description: This is currently not used but is reserved for future implementation.

Example: ``deploy-live``

#### local_repo

Type: ``string``

Default: ``\_\_DIR\_\_``

Description: Sets the local repository URL (where to run the Git commands from, by default ``\_\_DIR\_\_`` uses the same
directory as the hooker.php file) and therefore, out of the box this is configured for single site deployments.

#### user

Type: ``string``

Default: false

Description: When set, the ``{{ user }}`` tag can be used in commands when you require to ``sudo -u (user)``, the user
that the script runs under (eg. ``www-data``) must be configured for sudo rights in the ``/etc/sudoers`` file if you
require to use this feature..

Example: ``root``

### use_json

Type: ``boolean``

Default: false

Description: When set to `true`, the site deployment configuration is loaded from a ``hooker.json`` file found in the
root of your ``local_repo`` path.

#### pre_commands

Type: ``array``

Default: ``[]``

Description: Array of commands to execute before running the ``deploy_commands``, you can
use [the in-line tag replacements](https://github.com/allebb/hooker#dynamic-in-line-tags) for dynamic replacements.

#### deploy_commands

Type: ``array``

Default: ``['cd {{local-repo}} && git reset --hard HEAD && git pull']``

Description: Array of commands to execute on execution of the script, you can
use [the in-line tag replacements](https://github.com/allebb/hooker#dynamic-in-line-tags) for dynamic replacements.

#### post_commands

Type: ``array``

Default: ``[]``

Description: Array of commands to execute after running the ``deploy_commands``, you can
use [the in-line tag replacements](https://github.com/allebb/hooker#dynamic-in-line-tags) for dynamic replacements.

#### is_github

Type: ``boolean``

Default: false

Description: If set to ``true``, this will ensure that the hook will only execute the workflow if the GitHub hook events (see ``github_deploy_events``) match the GitHub web-hook event type that is received from the incoming GitHub webhook request. In addition, Hooker will only execute the workflow if the configured branch change that triggered the GitHub webhook matches too, this can be set using the ``branch`` option. This has been implemented
to minimise unnecessary application downtime, bandwidth and server resources (as GitHub webhooks will be sent for all kinds of events and all branches regardless). When setting up the webhook in GitHub ensure that the **Content type** dropdown is set to ``application/json``.

#### github_deploy_events

Type: ``array``

Default: ``['push', 'release']``

Description: List of configured hook event headers that the code will deploy on (when using the ``is_github`` option is
enabled)

#### is_bitbucket

Type: ``boolean``

Default: false

Description: If set, this will ensure that the hook only deploys the code on the configured BitBucket hook events in
order to minimise unnecessary application downtime, bandwidth and server resources.

#### bitbucket_deploy_events

Type: ``array``

Default: ``['repo:push']``

Description: List of configured hook event headers that the code will deploy on (when using the ``is_bitbucket`` option
is enabled)

#### ip_whitelist

Type: ``array``

Default: ``['127.0.0.1', '::1']``

Description: A whitelist of IP addresses that are allowed to invoke a deployment, by default this will only allow hook
execution from __localhost__.

#### git_bin

Type: ``string``

Default: ``git``

Description: The full path to the Git binary on the server (if your PATH is set correctly, the default ``git`` should
work fine!)

#### php_bin

Type: ``string``

Default: ``php``

Description: The full path to the PHP binary on the server (if your PATH is set correctly, the default ``php`` should
work fine!). This setting is extremely useful if you are trying to deploy an application which requirements for older or
newer PHP versions that cause Composer to complain and fail to deploy, this can be caused by deprecated functions etc.
You can override this value for specific sites and applications too to resolve this particular issue.

#### composer_bin

Type: ``string``

Default: ``/usr/bin/composer``

Description: The full path to the Composer binary on the server.

#### sites

Type: ``array``

Default: ``[]``

Description: Enables per-site configuration override.

### Dynamic in-line tags

When adding custom pre-commands, commands and post-commands, there are a number of dynamics tags that will be replaced
at run-time, these are as follows:

#### {{local-repo}}

The ``{{local-repo}}`` tag will output the site hosting directory (eg. ``/var/www/mysite``) as set in the ``local_repo``
configuration option value.

#### {{user}}

The ``{{user}}`` tag will output the currently set ``user`` configuration option value.

#### {{git-bin}}

The ``{{git-bin}}`` tag will output the path to the Git binary (eg. ``/usr/bin/git``) using the ``git_bin``
configuration option value.

#### {{php-bin}}

The ``{{php-bin}}`` tag will output the path to the PHP binary (eg. ``/usr/bin/php``) using the ``php_bin``
configuration option value.

#### {{composer-bin}}

The ``{{composer-bin}}`` tag will output the path to the Composer binary (eg. ``/usr/bin/composer``) using
the ``composer_bin`` configuration option value.

#### {{branch}}

The ``{{branch}}`` tag will output the Git branch (eg. ``master``) using the ``branch`` configuration option value.

#### {{repo}}

The ``{{repo}}`` tag will output the Git repository URI (eg. ``git@github.com:allebb/test.git``) using
the ``remote_repo`` configuration value.

## Using a hooker.json configuration file

Instead of having to edit and update the ``hooker.conf.php`` each time you wish to make a change to the deployment
workflow, a ``hooker.json`` file can be committed to your Git repository and will be used to define the worflow steps,
the syntax is as follows:

```json
{
  "debug": true,
  "php_bin": "/usr/bin/php8.0",
  "composer_bin": "/usr/bin/composer",
  "pre_commands": [
    "{{php-bin}} {{local-repo}}/artisan down",
    "{{php-bin}} {{local-repo}}/artisan config:cache"
  ],
  "#deploy_commands": [],
  "post_commands": [
    "cd {{local-repo}} && {{php-bin}} {{composer-bin}} install --no-dev --no-suggest --no-progress --prefer-dist --optimize-autoloader",
    "chmod 755 {{local-repo}}/storage",
    "{{php-bin}} {{local-repo}}/artisan migrate --force",
    "{{php-bin}} {{local-repo}}/artisan config:cache",
    "{{php-bin}} {{local-repo}}/artisan cache:clear",
    "{{php-bin}} {{local-repo}}/artisan route:cache",
    "{{php-bin}} {{local-repo}}/artisan up"
  ]
}
```

**Notice in the above JSON file example that the ``deploy_commands`` JSON key has been commented out (with a hash), this is important as an empty array here will override the default ``git pull`` commands, only uncomment this if you need to do custom tasks/customise the git pull command here.**

**Keep in mind that the Hooker webservice will first check that a local ``hooker.json`` file exists and then uses the workflow steps within it, so you would have to effectively "hit" this endpoint twice for any ``hooker.json`` changes to take effect as the first time it's run, it will load the local file which in turn would then pull the latest changes from your repository and only then, on the next execution will it use the latest workflow instructions.**

**For this to work, your Hooker configuration file MUST specify the ``local_repo`` and ``key`` properties,
the ``use_json`` must also be set to ``true``.**

For security reasons, when using a ``hooker.json`` file some overrides are not available and will need to be set in the main Hooker web service configuration file (``hooker.conf.php``). These settings are: ``remote_repo``, ``branch``, ``local_repo``, ``key`` and ``user``.

## Configuring Services to use Hooker

The following examples shows how to set up web-hooks to trigger deployments from a couple of the most used Git hosting
services.

### Configuring Hooker with GitHub web-hooks

TBC

### Configuring Hooker with BitBucket web-hooks

TBC

## Bugs

Please report any bugs on the [Issue Tracker](https://github.com/allebb/hooker/issues), please ensure that bug reports
are clear and contain as much information as possible.

Bug reports will be looked at and resolved as soon as possible!


