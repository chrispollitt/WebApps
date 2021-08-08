#/bin/bash
##########################################################
# Update whatwelove.org's ssl certs from LetsEncrypt.org #
##########################################################

prog_name="${0##*/}"
PATH="/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin:/home/whatwelo/bin:/home/whatwelo/local/usr/bin"
ssl_dir="/home/whatwelo/local/usr/ssl/LetsEncrypt"

function trap_error () {
  local name="$prog_name"        # name of the script
  local lastline="$1"            # arg 1: last line of error occurence
  local lastcmd="$2"             # arg 2: last cmd
  local lasterr="$3"             # arg 3: error code of last cmd
  echo "${name}: line ${lastline}: cmd='${lastcmd}' status=${lasterr}"
  exit 1
}
trap 'trap_error ${LINENO} "${BASH_COMMAND}" "${PIPESTATUS[*]}"' ERR
set -E

# change to cronlog dir
cd /home/whatwelo/cronlog

# save output to log
exec > update_ssl.log 2>&1 < /dev/null
echo "#### BEGIN update_ssl ####"

# get new cert
echo "##### create_ca_ssl_certs-le.sh #####"
create_ca_ssl_certs-le.sh # calls acme_tiny.py

# upload cert to apache config via cpanel
cd $ssl_dir
echo "##### update_certs.php whatwelove #####"
update_certs.php cs16.uhcloud.com certs/whatwelove.org.crt
echo "##### update_certs.php universal-laughter #####"
update_certs.php cs16.uhcloud.com certs/universal-laughter.com.crt
echo "##### update_certs.php landmarkcourt.ca #####"
update_certs.php cs16.uhcloud.com certs/landmarkcourt.ca.crt
echo "##### update_certs.php u-l.ca #####"
update_certs.php cs16.uhcloud.com certs/u-l.ca.crt

# save cert to dropbox
echo "##### update_dropbox.py #####"
update_dropbox.py

# email log
echo "##### email log to webmaster #####"
mailx -s "update_ssl.log" webmaster@whatwelove.org < /home/whatwelo/cronlog/update_ssl.log

# end
echo "#### END update_ssl ####"

