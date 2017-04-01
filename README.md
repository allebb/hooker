# Hooker

A standalone PHP web-hook script for triggering application deployments with Git.

## Requirements

* PHP 5.4+.
* The ``shell_exec()`` function is required (Some shared hosting environments disable this!)

## License

This script is released under the [GPLv2](https://github.com/bobsta63/hooker/blob/master/LICENSE) license. Feel free to use it, fork it, improve it and contribute by open a pull-request!

## Installation

You can "install" and utilise this script in two ways:

* [__Single Site__](https://github.com/bobsta63/hooker#single-site-installation-single-site-configuration) - Include ``hooker.php`` in your existing projects' root directory and update the configuration array.
* [__Multiple Site__](https://github.com/bobsta63/hooker#virtual-host-installation-multiple-site-configuration) - Host as a separate virtual host and configure multiple "site" configurations.

### Single Site Installation (Single site configuration)

The single site installation involves hosting the ``hooker.php`` file (and an optional separate configuration file) in the public root of an existing website/application.

In a nutshell, in order to host a new site on a server that can utilise Hooker, the following steps/commands are required to achieve a working environment:

```shell
# Change to the root of our web server root "hosting" directory.
cd /var/www

# Create a new .ssh profile for the 'www-data' user and set the required permissions.
sudo mkdir .ssh
sudo chown www-data:www-data -R .ssh
sudo chmod 0700 .ssh

# Create a new SSH key for the ``www-data`` user to connect to your Git hosting provider with (in order to enable headless operation ensure that when asked to enter a passphrase you leave it empty - Just accept the defaults!):
sudo -u www-data ssh-keygen -t rsa -b 4096

# Copy the contents of the public key file and paste it in the
sudo cat /var/www/.ssh/id_rsa.pub

# Lets now make a site hosting directory and set the required permissions.
sudo mkdir mywebsite
sudo chown www-data:www-data -R mywebsite

# Now we change into the directory and clone the git repo that contains our site content.
cd mywebsite && sudo -u www-data git clone git@github.com/bobsta63/test.git .

# Lets now download the latest stable version of Hooker...
sudo -u www-data wget https://raw.githubusercontent.com/allebb/hooker/stable/hooker.php

# Optionally you can also download a seperate configuration file, but is optional!     
sudo -u www-data wget https://raw.githubusercontent.com/allebb/hooker/stable/hooker.conf.example.php
sudo -u www-data cp hooker.conf.example.php hooker.conf.php
```

The above steps have been fully tested on Ubuntu Server 14.04 LTS and should work fine for other versions of Linux and UNIX too; you may however find that you will need to substitute the web server user and group names from ``www-data`` to whatever your distribution/web server is using.

### Virtual Host Installation (Multiple site configuration)

The virtual host installation involves creating a new virtual host configuration of which then acts as a web-hook endpoint for multiple projects.

When using this method, you should create separate site configurations that then get triggered by specifying the site/application configuration with the ``app`` parameter eg. ``https://deploy.mysite.com/hooker.php?app=website1``.

A benefit of using the multiple site configuration over the single site configuration is the ability to utilise Git to keep Hooker updated periodically.

#### Creating the new virtualhost directory

In this example, we'll create a new Nginx vhost configuration, first of all we need to create a hosting directory to host our ``hooker.php`` file:

```shell
sudo mkdir /var/www/hooker
```

We'll use Git to download the latest (stable) version (we'll also be able to use ``sudo -u www-data git pull`` in future to apply updates):

```shell
cd /var/www/hooker    
sudo git clone -b stable https://github.com/allebb/hooker.git .
```

We'll now copy the example configuration file and use that to configure our individual sites:

```shell
sudo cp hooker.conf.example.php hooker.conf.php
``` 

At this point you should edit this file and configure your sites, for example it may look like this:

``/var/www/hooker/hooker.conf.php``

```php
<?php
/**
 * Hooker Configuration File
 */
return [
    'debug' => true,
    'sites' => [
        // Example basic HTML website. - http://deploy.mysite.com/hooker.php?app=my_basic_website&key=SomeRandomWordThatMustBePresentInTheKeyParam
        'my_basic_website' => [ // To deploy this site, you would have to call: 
            'debug' => false, // Optionally disable debugging for this specific site.
            'key' => 'SomeRandomWordThatMustBePresentInTheKeyParam', // You'll need to set ?key=SomeRandomWordThatMustBePresentInTheKeyParam when calling the script!
            'local_repo' => '/var/www/html-website', // The path to the site root (site should be owned by the webserver user, eg. 'chmod www-data:www-data -R /var/www/html-website')
            'branch' => 'master',
            'is_github' => true, // Website is on GitHub, will only deploy on GitHub 'push' and 'release' events.
        ],
        // Example Laravel Deployment Configuration. - http://deploy.mysite.com/hooker.php?app=laravel_app&key=32c9f55eea8526374731acca13c81aca
        'laravel_app' => [
            'key' => '32c9f55eea8526374731acca13c81aca',
            'local_repo' => '/var/www/my_awesome_app',
            'branch' => 'deploy-live',
            'pre_commands' => [ // Custom pre-commands, will put Laravel into Maintenance mode!
                'php {{local-repo}}/artisan down',
            ],
           'post_commands' => [ // Custom post-commands we will run "composer install", set the correct filesystem permissions, run migrations and then take it back out of maintenance mode!
                'cd {{local-repo}} && composer insall',
                'chmod 755 {{local-repo}}/storage',
                'php {{local-repo}}/artisan migrate --force',
                'php {{local-repo}}/artisan up',
            ],
        ],
    ],
];
```

### Create SSH profile for the www-data user and generate your "Deploy Key"

Lets change into the ``www-data`` user's home directory:
```shell
cd /var/www
```

We will now create a new ``.ssh`` directory (profile) for the 'www-data' user (as it is this user account that will be, behind the scenes connecting to Git) and set the required permissions.

```shell
mkdir .ssh
chown www-data:www-data -R .ssh
chmod 0700 .ssh
```
Now we'll generate a new SSH key-pair for the ``www-data`` user of which will be used to authenticate with your Git hosting service.

__In order to enable headless operation ensure that you use the default options (just keep pressing the ENTER key at the prompts) and when asked to enter a passphrase ensure that you leave it empty otherwise Hooker will not work correctly!__

```shell
sudo -u www-data ssh-keygen -t rsa -b 4096
```

The contents of the public key (``/var/www/.ssh/id_rsa.pub``) now needs to be copied and added to your Git hosting provider's "Deploy keys" section:

```shell
cat /var/www/.ssh/id_rsa.pub
```

### Set correct permissions on the site directory

Now, we need to set the correct ownership and permissions for this new site:

```shell
chown www-data:www-data -R /var/www/hooker
```

### Configure Nginx virtualhost configuration

This example Nginx virtualhost configuration can be added to your server - assuming you're using Nginx and PHP7.0-FPM (just make adjustments as required):

``/etc/nginx/sites-available/hooker.conf``

```
server {
    listen 80;
    #listen 443 ssl;
    root /var/www/hooker;
    server_name deploy.mysite.com;

    # Optionally link to LetsEncrypt SSL certs
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
        fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;
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

Now restart Nginx for the new virtualhost to take affect:

```shell
sudo service nginx restart
```

### Finished!

If all goes well, you should be able to access the 'ping' test page at: ``http://deploy.mysite.com/hooker.php?ping``, a successful installation should return the word 'PONG'.

## Configuration options

The following configuration options exists and are explained below:

#### debug

Type: ``boolean``

Default: true

Description: When set to __true__ runtime information will be outputted to the browser, this is especially useful for debugging purposes.

#### key

Type: ``string``

Default: false

Description: When not set as ``false``, this string must match the ``key`` parameter when calling the webhook, this can be set globally (for all sites) or, set it individually on a per-site basis.

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

Description: Sets the local repository URL (where to run the Git commands from, by default ``\_\_DIR\_\_`` uses the same directory as the hooker.php file) and therefore, out of the box this is configured for single site deployments.

#### user

Type: ``string``

Default: false

Description: When set, the ``{{ user }}`` tag can be used in commands when you require to ``sudo -u (user)``, the user that the script runs under (eg. ``www-data``) must be configured for sudo rights in the ``/etc/sudoers`` file if you requre to use this feature..

Example: ``root``

#### pre_commands

Type: ``array``

Default: ``[]``

Description: Array of commands to execute before running the ``deploy_commands``, you can use [the in-line tag replacements](https://github.com/allebb/hooker#dynamic-in-line-tags) for dynamic replacements.

#### deploy_commands

Type: ``array``

Default: ``['cd {{local-repo}} && git reset --hard HEAD && git pull']``

Description: Array of commands to execute on execution of the script, you can use [the in-line tag replacements](https://github.com/allebb/hooker#dynamic-in-line-tags) for dynamic replacements.

#### post_commands

Type: ``array``

Default: ``[]``

Description: Array of commands to execute after running the ``deploy_commands``, you can use [the in-line tag replacements](https://github.com/allebb/hooker#dynamic-in-line-tags) for dynamic replacements.

#### is_github

Type: ``boolean``

Default: false

Description: If set, this will ensure that the hook only deploys the code on the configured GitHub hook events in order to minimise unnecessary application downtime, bandwidth and server resources.

#### github_deploy_events

Type: ``array``

Default: ``['push', 'release']``

Description: List of configured hook event headers that the code will deploy on (when using the ``is_github`` option is enabled)

#### is_bitbucket

Type: ``boolean``

Default: false

Description: If set, this will ensure that the hook only deploys the code on the configured BitBucket hook events in order to minimise unnecessary application downtime, bandwidth and server resources.

#### bitbucket_deploy_events

Type: ``array``

Default: ``['repo:push']``

Description: List of configured hook event headers that the code will deploy on (when using the ``is_bitbucket`` option is enabled)

#### ip_whitelist

Type: ``array``

Default: ``['127.0.0.1', '::1']``

Description: A whitelist of IP addresses that are allowed to invoke a deployment, by default this will only allow hook execution from __localhost__.

#### git_bin

Type: ``string``

Default: ``git``

Description: The full path to the Git binary on the server (if your PATH is set correctly, the default ``git`` should work fine!)

#### sites

Type: ``array``

Default: ``[]``

Description: Enables per-site configuration override.

### Dynamic in-line tags

When adding custom pre-commands, commands and post-commands, there are a number of dynamics tags that will be replaced at run-time, these are as follows:

#### {{local-repo}}

The ``{{local-repo}}`` tag will output the site hosting directory (eg. ``/var/www/mysite``) as set in the ``local_repo`` configuration option value.

#### {{user}}

The ``{{user}}`` tag will output the currently set ``user`` configuration option value.

#### {{git-bin}}

The ``{{git-bin}}`` tag will output the path to the Git binary (eg. ``/usr/local/bin/git``) using the ``git-bin`` configuration option value.

#### {{branch}}

The ``{{branch}}`` tag will output the Git branch (eg. ``master``) using the ``branch`` configuration option value.

#### {{repo}}

The ``{{repo}}`` tag will output the Git repository URI (eg. ``git@github.com:bobsta63/test.git``) using the ``remote_repo`` configuration value.

## Configuring Services to use Hooker

The following examples shows how to setup web-hooks to trigger deployments from a couple of the most used Git hosting services.

### Configuring Hooker with GitHub web-hooks

TBC

### Configuring Hooker with BitBucket web-hooks

TBC

## Bugs

Please report any bugs on the [Issue Tracker](https://github.com/allebb/hooker/issues), please ensure that bug reports are clear and contain as much information as possible.

Bug reports will be looked at and resolved as soon as possible!


