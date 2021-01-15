# Hooker Configuration

Hooker uses an "inheritance" model for populating the configuration settings at runtime, most settings can be applied at the global level and then overridden in the ``sites`` array and, if you're using a ``hooker.json`` as part of your codebase, will then allow overriding too.

**Keep in mind that if you intend on using the ``hooker.json`` configuration method, The Hooker webservice will first check that a local ``hooker.json`` file exists and then uses the workflow steps within it, so you would have to effectively "hit" this endpoint twice for any ``hooker.json`` changes to take effect (if the new version has to be pulled from Git first) as the first time it's run, it will load the local file which in turn would then pull the latest changes from your repository and only then, on the next execution will it use the latest workflow instructions.**

## Configuration items

The following configuration options exist and are explained below:

### debug

Type: ``boolean``

Default: true

Description: When set to __true__ runtime information will be outputted to the browser, this is especially useful for
debugging purposes.

### key

Type: ``string``

Default: false

Description: When not set as ``false``, this string must match the ``key`` parameter when calling the webhook, this can
be set globally (for all sites) or, set it individually on a per-site basis.

Example: ``TPuR81cS0gwP2T``

### remote_repo

Type: ``string``

Default: empty

Description: This is currently not used but is reserved for future implementation.

Example: ``git@github.com:bobsta63/test-website.git``

### branch

Type: ``string``

Default: ``master``

Description: This is currently not used but is reserved for future implementation.

Example: ``deploy-live``

### local_repo

Type: ``string``

Default: ``\_\_DIR\_\_``

Description: Sets the local repository URL (where to run the Git commands from, by default ``\_\_DIR\_\_`` uses the same
directory as the hooker.php file) and therefore, out of the box this is configured for single site deployments.

### user

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

### pre_commands

Type: ``array``

Default: ``[]``

Description: Array of commands to execute before running the ``deploy_commands``, you can
use [the in-line tag replacements](#dynamic-in-line-tags) for dynamic replacements.

### deploy_commands

Type: ``array``

Default: ``['cd {{local-repo}} && git reset --hard HEAD && git pull']``

Description: Array of commands to execute on execution of the script, you can
use [the in-line tag replacements](#dynamic-in-line-tags) for dynamic replacements.

### post_commands

Type: ``array``

Default: ``[]``

Description: Array of commands to execute after running the ``deploy_commands``, you can
use [the in-line tag replacements](#dynamic-in-line-tags) for dynamic replacements.

### is_github

Type: ``boolean``

Default: false

Description: If set to ``true``, this will ensure that the hook will only execute the workflow if the GitHub hook events (see ``github_deploy_events``) match the GitHub web-hook event type that is received from the incoming GitHub webhook request. In addition, Hooker will only execute the workflow if the configured branch change that triggered the GitHub webhook matches too, this can be set using the ``branch`` option. This has been implemented
to minimise unnecessary application downtime, bandwidth and server resources (as GitHub webhooks will be sent for all kinds of events and all branches regardless). When setting up the webhook in GitHub ensure that the **Content type** dropdown is set to ``application/json``.

### github_deploy_events

Type: ``array``

Default: ``['push', 'release']``

Description: List of configured hook event headers that the code will deploy on (when using the ``is_github`` option is
enabled)

### is_bitbucket

Type: ``boolean``

Default: false

Description: If set, this will ensure that the hook only deploys the code on the configured BitBucket hook events in
order to minimise unnecessary application downtime, bandwidth and server resources.

### bitbucket_deploy_events

Type: ``array``

Default: ``['repo:push']``

Description: List of configured hook event headers that the code will deploy on (when using the ``is_bitbucket`` option
is enabled)

### ip_whitelist

Type: ``array``

Default: ``['127.0.0.1', '::1']``

Description: A whitelist of IP addresses that are allowed to invoke a deployment, by default this will only allow hook
execution from __localhost__.

### git_bin

Type: ``string``

Default: ``git``

Description: The full path to the Git binary on the server (if your PATH is set correctly, the default ``git`` should
work fine!)

### git_ssh_key_path

Type: ``string``

Default ``empty``

Description: You can set this value to provide a specific deployment (private) key that will be used when making Git SSH requests (this will dynamically populate the ``{{git-ssh-key}}`` placeholder).

If one is not specified (the value is empty), Git will automatically use the default ``/var/www/.ssh/id_rsa`` key when communicating with any SSH servers that require key based authentication as the generated
placeholder will return an empty string and thus omit exporting the runtime variable.

If you have Conductor installed on your server you can easily generate a key for your website/application by running the following command:

```shell
conductor genkey {appname}
```


### php_bin

Type: ``string``

Default: ``/usr/bin/php``

Description: The full path to the PHP binary on the server (if your PATH is set correctly, the default ``php`` should
work fine!). This setting is extremely useful if you are trying to deploy an application which requirements for older or
newer PHP versions that cause Composer to complain and fail to deploy, this can be caused by deprecated functions etc.
You can override this value for specific sites and applications too to resolve this particular issue.

### composer_bin

Type: ``string``

Default: ``/usr/bin/composer``

Description: The full path to the Composer binary on the server.

### sites

Type: ``array``

Default: ``[]``

Description: Enables per-site configuration override.

## Dynamic in-line tags

When adding custom pre-commands, commands and post-commands, there are a number of dynamics tags that will be replaced
at run-time, these are as follows:

### {{local-repo}}

The ``{{local-repo}}`` tag will output the site hosting directory (eg. ``/var/www/mysite``) as set in the ``local_repo``
configuration option value.

### {{user}}

The ``{{user}}`` tag will output the currently set ``user`` configuration option value.

### {{git-bin}}

The ``{{git-bin}}`` tag will output the path to the Git binary (eg. ``/usr/bin/git``) using the ``git_bin``
configuration option value.

### {{git-ssh-key}}

The ``{{git-ssh-key}}`` tag will dynamically generate the required export values to specify a private key on your server to authenticate with when
pulling code with Git.

If one is not specified, Git will automatically use the default ``/var/www/.ssh/id_rsa`` key when communicating with any SSH servers that require key based authentication.

If you have Conductor installed on your server you can easily generate a key for your website/application by running the following command:

```shell
conductor genkey {appname}
```

### {{php-bin}}

The ``{{php-bin}}`` tag will output the path to the PHP binary (eg. ``/usr/bin/php``) using the ``php_bin``
configuration option value.

### {{composer-bin}}

The ``{{composer-bin}}`` tag will output the path to the Composer binary (eg. ``/usr/bin/composer``) using
the ``composer_bin`` configuration option value.

### {{branch}}

The ``{{branch}}`` tag will output the Git branch (eg. ``master``) using the ``branch`` configuration option value.

### {{repo}}

The ``{{repo}}`` tag will output the Git repository URI (eg. ``git@github.com:allebb/test.git``) using
the ``remote_repo`` configuration value.
