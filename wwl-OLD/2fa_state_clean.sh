#!/bin/sh

BASEDIR="/home/whatwelo/public_html/www/cgi-bin/.2fa"
find $BASEDIR/state/ -type f -mmin +360 -exec rm -f {} \;
