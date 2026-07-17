#!/usr/bin/env bash
#
#  This script will automatically install and configure Hooker on a server that is running Conductor
#  You can find out more information about Conductor here: https://github.com/allebb/conductor
#
#

set -e

# Check the Conductor version/if it's installed
echo "Checking Conductor is installed..."
if conductor --version > /dev/null; then
    echo " * Conductor has been found, continuing..."
else
    echo " ! Conductor does not appear to be installed, exiting!"
    exit 1
fi

# Ask the user what directory name they want to host it under (the conductor application name), default to 'hooker'
read -p 'What directory should we deploy Hooker to? (eg: "hooker"): ' appname

# Ask the user what domain they want to host this on!
read -p 'What domain do you want Hooker to be hosted on? (eg: "deploy.mysite.com"): ' fqdn

# Call the CLI command (using sudo) to create the hosting environment.
if ! sudo conductor new "$appname" --fqdn="${fqdn}" --path="/" > /dev/null; then
    echo " ! Could not create an application hosting container using Conductor, exiting!"
    exit 1
fi

# Git clone (as www-data user) to the /var/conductor/application/{name} directory
echo "Installing Hooker..."
appdir="/var/conductor/applications/${appname}"
sudo find "$appdir" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
cd "$appdir"
sudo -u www-data git clone --branch stable --single-branch https://github.com/allebb/hooker.git .

# Configure a cache directory for Composer (Latest version of Conductor now handles this at installation).
#sudo mkdir /var/www/.cache
#sudo chown -R www-data:www-data /var/www/.cache

# Copy the hooker.conf.example file to hooker.conf.php
sudo -u www-data cp "${appdir}/hooker.conf.example-clean.php" "${appdir}/hooker.conf.php"

# Install the Hooker command line helper
sudo cp "${appdir}/utils/hooker" /usr/bin/hooker
sudo chmod +x /usr/bin/hooker

# Copy the default Nginx virtualhost configuration to the Conductor application configuration...
sudo cp "${appdir}/utils/auto-install-conductor_nginx.conf" "/etc/conductor/configs/${appname}.conf"

# Update the FQDN in the virtualhost configuration file
sudo sed -i "s/__FQDN__/${fqdn}/g" "/etc/conductor/configs/${appname}.conf"
sudo sed -i "s/__APPNAME__/${appname}/g" "/etc/conductor/configs/${appname}.conf"

# Test that the Nginx configuration passes validation...
if sudo nginx -t > /dev/null; then
    echo " * Nginx configuration passes validation..."
else
    echo " ! Something went wrong with the Nginx configuration validation, check your nginx configurations!"
    exit 1
fi

# Restart the Nginx service to make the web service available.
sudo service nginx restart
echo ""

echo "Now creating an SSH key (which should be used for automated Git functionality)..."
sudo mkdir -p /var/www/.ssh
sudo chown www-data:www-data -R /var/www/.ssh
if [ -f /var/www/.ssh/id_rsa ]; then
    echo " * An SSH key already exists at /var/www/.ssh/id_rsa, leaving it unchanged."
elif ! sudo -u www-data ssh-keygen -t rsa -b 4096 -N "" -C "hooker@${fqdn}" -q -f /var/www/.ssh/id_rsa > /dev/null; then
    echo " ! Could not create an SSH key, please create one manually for the www-data user!"
else
	echo ""
	echo "**If you intend on using per-application deployment keys you can skip this step!**"
	echo "Your SSH key has now been generated, you should copy and paste this key (/var/www/.ssh/id_rsa.pub) to your"
	echo "GitHub and/or other online version control systems that Hooker needs to connect with using SSH."
	echo ""
	echo "You can view the contents of the public key file by running:"
	echo ""
	echo "    cat /var/www/.ssh/id_rsa.pub"
	echo ""
fi
sudo chmod -R 0700 /var/www/.ssh

echo ""
read -r -p 'Do you want to enable the Web Admin panel? [y/N]: ' enable_webadmin
case "$enable_webadmin" in
    [Yy]|[Yy][Ee][Ss])
        while [ -z "${webadmin_username:-}" ]; do
            read -r -p 'Web Admin username: ' webadmin_username
        done

        while [ -z "${webadmin_password:-}" ]; do
            read -r -s -p 'Web Admin password: ' webadmin_password
            echo ""
        done

        sudo -u www-data php -r '
            $path = $argv[1];
            $username = var_export($argv[2], true);
            $password = var_export($argv[3], true);
            $source = "<?php\n\nreturn [\n\n";
            $source .= "    // Whether the webadmin feature is enabled. Set this to false to disable the webadmin feature entirely (and return a 404 for all webadmin requests).\n";
            $source .= "    '\''enable_webadmin_feature'\'' => true,\n\n";
            $source .= "    // The username and password for the webadmin feature. Change these to secure your installation.\n";
            $source .= "    '\''username'\'' => {$username},\n";
            $source .= "    '\''password'\'' => {$password},\n\n";
            $source .= "];\n";
            if (file_put_contents($path, $source) === false) {
                fwrite(STDERR, "Unable to update {$path}\n");
                exit(1);
            }
        ' "${appdir}/webadmin/auth.php" "$webadmin_username" "$webadmin_password"
        echo " * Web Admin panel enabled."
        ;;
    *)
        echo " * Web Admin panel left disabled."
        ;;
esac

echo "You can now configure your workflows/deployments by updating the global Hooker configuration:"
echo ""
echo "    vi /var/conductor/applications/${appname}/hooker.conf.php"
echo ""
echo "You can test that Hooker is working by checking that your browser displays 'PONG' when you"
echo "visit the following URL:"
echo ""
echo "    http://${fqdn}/hooker.php?ping"
echo ""
echo "Use the following URL format for triggering deployments on this server:"
echo ""
echo "    http://${fqdn}/hooker.php?app=[YOUR_APP_NAME]&key=[YOUR_SECRET_KEY]"
echo ""
echo "Visit the documentation on the GitHub project page in order to learn how to configure your"
echo "workflows and to secure your Hooker installation over HTTPS."
echo ""
echo ""
echo "You can automatically provision a LetsEncrypt SSL certificate for this domain by running this command:"
echo ""
echo "    conductor letsencrypt ${appname}"
echo ""
echo "Installation complete!"
exit 0
