#!/usr/bin/env bash
#
#  This script will automatically install and configure Hooker on a server that is running Conductor
#  You can find out more information about Conductor here: https://github.com/allebb/conductor
#
#

# Check the Conductor version/if it's installed
echo "Checking Conductor is installed..."
conductor --version > /dev/null
if [ $? -eq 0 ]; then
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
sudo conductor new $appname --fqdn="${fqdn}" --path="/"

# Git clone (as www-data user) to the /var/conductor/application/{name} directory
sudo -u www-data git clone git@github.com:allebb/hooker.git /var/conductor/applications/$appname
sudo -u www-data git checkout stable

# Copy the hooker.conf.example file to hooker.conf.php
sudo -u www-data cp /var/conductor/applications/$appname/hooker.example.php /var/conductor/applications/$appname/hooker.conf.php

# Update the FQDN in the virtualhost configuration file
sudo sed -i "s|__FQDN__|$fqdn|" /etc/conductor/configs/$appname.conf

# Copy the default Nginx virtualhost configuration to the Conductor application configuration...
sudo cp /var/conductor/applications/$appname/utils/auto-install-conductor_nginx.conf /etc/conductor/configs/$appname.conf

# Test that the Nginx configuration passes validation...
sudo nginx -t
if [ $? -eq 0 ]; then
    echo " * Nginx configuration passes validation..."
else
    echo " ! Something went wrong with the Nginx configuration validation, check your nginx configurations!"
    exit 1
fi

# Restart the Nginx service to make the web service available.
sudo service nginx restart
echo "Installation complete!"
exit 0