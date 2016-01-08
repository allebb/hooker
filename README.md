# Hooker

A standalone PHP web-hook script for triggering application deployments with Git.

## Requirements

* PHP 5.4+.
* The ``shell_exec()`` function is required (Some shared hosting environments disable this!)

## License

This script is released under the [GPLv2](https://github.com/bobsta63/hooker/blob/master/LICENSE) license. Feel free to use it, fork it, improve it and contribute by open a pull-request!

## Installation

You can "install" and utilise this script in two ways:

* Include ``hooker.php`` in your existing projects' root directory and update the configuration array.
* Host as a separate virtual host and configure multiple "site" configurations.

### Single Site Installation (Single site configuration)

The single site installation involves hosting the ``Hooker.php`` file (and an optional separate configuration file) in the public root of an existing website/application.

To download the latest stable version of the script, use ``wget`` to download it as follows:

```shell
cd /var/www/{your web project}
wget https://raw.githubusercontent.com/bobsta63/hooker/stable/hooker.php
```

This will now download the latest stable version of hooker into the current directory. To test that the script is visible to the
internet (and therefore to GitHub, BitBucket and any other providers) type into your browser:

``http://yourwebsite.com/hooker.php?ping``

If the script is available to the internet you should receive a 200 response and the words ``PONG`` appear on screen!

Now that you have it working, you now need to configure it for your purposes.

If you intend on just using the ``hooker.php`` file and do not intend on using a separate configuration file then you should edit the ``hooker.php`` file and edit the ``$config`` array found at the top of the file.

It is recommended that you create and manage a separate configuration file that will be used when present, the benefits of which will enable you to update the hooker.php file reguarly without having to re-enter your configuration settings each time but does come at the cost of having another non-project file in the root of your application/site.

You can download the example configuration file and edit to your requirements as follows (the configuration file should be in the same directory as ``hooker.php`` otherwise it will not be used):

```shell
wget https://raw.githubusercontent.com/bobsta63/hooker/stable/hooker.conf.php
```

When the ``hooker.conf.php`` file is present the, configuration file (``hooker.conf.php``) will __merge__ with the default configuration found at the top of the ``hooker.php`` file therefore you only need to override settings and not duplicate.

### Virtual Host Installation (Multiple site configuration)

The virtual host installation involves creating a new web server virtual host of which then acts as a web-hook endpoint for multiple projects.

When using this method, you should create separate site configurations that then get triggered by specifing the site/app configuration with the ``app`` parameter.

A benefit of using the Multiple site configuration over the single site configuration is the ability to utilise Git to keep Hooker updated periodically.

TBC

## Configuration options

The following configuration options exists and are explained below:

### debug

Type: ``boolean``

Default: false

Description: When set to __true__ debug information will be outputted to the browser.

### key

Type: ``string``

Default: false

Description: When not set as ``false``, this string must match the ``key`` parameter when calling the webhook, can can be set globally (for all sites) or, set it individually on a per-site basis.

Example: ``TPuR81cS0gwP2T``

### remote_repo

Type: ``string``

Default: empty

Description: This is currently not used but is reserved for future implementation.

Example: ``git@github.com:bobsta63/test-website.git``

### branch

Type: ``string``

Default: master

Description: This is currently not used but is reserved for future implementation.

Example: deploy-live

### local_repo

Type: ``string``

Default: __DIR__

Description: Sets the local repository URL (where to run the Git commands from, by default ``__DIR__`` uses the same directory as the hooker.php file) and therefore, out of the box this is configured for single site deployments.

### user

Type: ``string``

Default: false

Description: When set, the {{ user }} tag can be used in commands when you require to ``sudo -u (user)``, the user that the script runs under (eg. ``www-data``) must be configured for sudo rights in the ``/etc/sudoers`` file if you requre to use this feature..

Example: root

### pre_commands

Type: ``array``

Default: []

Description: Array of commands to execute before running the ``deploy_commands``, you can use the in-line tag replacements for dynamic replacements.

### deploy_commands

Type: ``array``

Default: ['cd {{local-repo}} && git reset --hard HEAD && git pull'],

Description: Array of commands to execute on execution of the script, you can use the in-line tag replacements for dynamic replacements.

### post_commands

Type: ``array``

Default: []

Description: Array of commands to execute after running the ``deploy_commands``, you can use the in-line tag replacements for dynamic replacements.

### is_github

Type: ``boolean``

Default: false

Description: If set, this will ensure that the hook only deploys the code on the configured GitHub hook events in order to minimise unnecessary application downtime, bandwidth and server resources.

### github_deploy_events

Type: ``array``

Default: ['push', 'release']

Description: List of configured hook event headers that the code will deploy on (when using the ``is_github`` option is enabled)

### is_bitbucket

Type: ``boolean``

Default: false

Description: If set, this will ensure that the hook only deploys the code on the configured BitBucket hook events in order to minimise unnecessary application downtime, bandwidth and server resources.

### bitbucket_deploy_events

Type: ``array``

Default: ['repo:push']

Description: List of configured hook event headers that the code will deploy on (when using the ``is_bitbucket`` option is enabled)

### ip_whitelist

Type: ``array``

Default: ['127.0.0.1', '::1']

Description: A whitelist of IP addresses that are allowed to invoke a deployment.

### git_bin

Type: ``string``

Default: git

Description: The full path to the Git binary on the server (if your PATH is set correctly, the default ``git`` should work fine!)

### sites

Type: ``array``

Default: []

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


