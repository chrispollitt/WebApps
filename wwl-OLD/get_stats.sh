#!/bin/bash

php-cli -f ~/github/chrispollitt/WebApps/wwl/get_stats.php cs16.uhcloud.com whatwelove.org|egrep 'Disk /home|IOPS:|Memory Used:|CPU Usage:|Number of Processes:|Server Load:'

