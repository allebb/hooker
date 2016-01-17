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
mkdir .ssh
chown www-data:www-data -R .ssh
chmod 0700 .ssh

# Create a new SSH key for the ``www-data`` user to connect to your Git hosting provider with (in order to enable headless operation ensure that when asked to enter a passphrase you leave it empty - Just accept the defaults!):
sudo -u www-data ssh-keygen -t rsa -b 4096

# Copy the contents of the public key file and paste it in the
cat /var/www/.ssh/id_rsa.pub

# Lets now make a site hosting directory and set the required permissions.
mkdir mywebsite
chown www-data:www-data -R mywebsite

# Now we change into the directory and clone the git repo that contains our site content.
cd mywebsite && sudo -u www-data git clone git@github.com/bobsta63/test.git .

# Lets now download the latest stable version of Hooker...
sudo -u www-data wget https://raw.githubusercontent.com/bobsta63/hooker/stable/hooker.php

# Optionally you can also download a seperate configuration file, but is optional!
sudo -u www-data wget https://raw.githubusercontent.com/bobsta63/hooker/stable/hooker.conf.example.php
sudo -u www-data cp hooker.conf.example.php hooker.conf.php
```

The above steps have been fully tested on Ubuntu Server 14.04 LTS and should work fine for other versions of Linux and UNIX too you may however find that you will need to substitute the web server user and group names from ``www-data`` to whatever your distribution/web server is using.

### Virtual Host Installation (Multiple site configuration)

The virtual host installation involves creating a new web server virtual host of which then acts as a web-hook endpoint for multiple projects.

When using this method, you should create separate site configurations that then get triggered by specifying the site/application configuration with the ``app`` parameter.

A benefit of using the multiple site configuration over the single site configuration is the ability to utilise Git to keep Hooker updated periodically.

TBC

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

Description: Array of commands to execute before running the ``deploy_commands``, you can use the in-line tag replacements for dynamic replacements.

#### deploy_commands

Type: ``array``

Default: ``['cd {{local-repo}} && git reset --hard HEAD && git pull']``

Description: Array of commands to execute on execution of the script, you can use the in-line tag replacements for dynamic replacements.

#### post_commands

Type: ``array``

Default: ``[]``

Description: Array of commands to execute after running the ``deploy_commands``, you can use the in-line tag replacements for dynamic replacements.

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

## Configuring Services to use Hooker 

The following examples shows how to setup web-hooks to trigger deployments from a couple of the most used Git hosting services.

### Configuring Hooker with GitHub web-hooks

TBC

### Configuring Hooker with BitBucket web-hooks

TBC

## Bugs

Please report any bugs on the [Issue Tracker](https://github.com/bobsta63/hooker/issues), please ensure that bug reports are clear and contain as much information as possible.

Bug reports will be looked at and resolved as soon as possible!


