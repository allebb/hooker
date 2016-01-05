# Hooker

A standalone PHP web-hook script for triggering application deployments with Git.

## Requirements

* PHP 5.4+.
* The ``shell_exec()`` function is required (Some shared hosting environments disable this!)

## License

This script is released under the [GPLv2](https://github.com/bobsta63/hooker/blob/master/LICENSE) license. Feel free to use it, fork it, improve it and contribute by open a pull-request!

## Bugs

Please report any bugs on the [Issue Tracker](https://github.com/bobsta63/hooker/issues), please ensure that bug reports are clear and contain as much information as possible.

Bug reports will be looked at and resolved as soon as possible!

## Installation

You can "install" and utilise this script in two ways:

* Include ``hooker.php`` in your existing projects' root directory and update the configuration array.
* Host as a separate virtual host and configure multiple "site" configurations.

### Single Site Installation (Single site configuration)

The single site installation involves installing Hooker.php (and optional separate configuration file) into the public root of existing website/application.

### Virtual Host Installation (Multiple site configuration)

The virtual host installation involves creating a new web server virtual host of which then acts as a deployment 

This script is designed to be downloaded as a single file and run within your current website/application's root directory, 
it is recommended that you simply download the main script in this repository on a per site basis, on a Linux based server you
can change into your site's root directory and download using ``cURL`` or ``wget`` like so:

```shell
cd /var/www/{your web project}
wget https://raw.githubusercontent.com/bobsta63/hooker/stable/hooker.php
```

This will now download the latest stable version of hooker into the current directory. To test that the script is visible to the
internet (and therefore to GitHub, BitBucket and any other providers) type into your browser:

``http://yourwebsite.com/hooker.php?ping``

If the script is available to the internet you should receive a 200 response and the words ``PONG`` appear on screen!

Now that you have it working, you now need to configure it for your purposes.

## Configuration

To ensure that the script has a smallest foot print as possible you can configure the settings either in the head of ``hooker.php`` so
that you only have a single file present on your server or, and as recommended create a separate configuration file (to enable easier
 upgrades in future).

The configuration details are documented below.

### Using with GitHub web hooks

TBC

### Using with BitBucket web hooks

TBC


