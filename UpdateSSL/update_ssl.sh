#!/bin/bash
##########################################################
# Update whatwelove.org's SSL certs from LetsEncrypt.org #
##########################################################

prog_name="${0##*/}"
PATH="/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin:/home/whatwelo/bin:/home/whatwelo/local/usr/bin"
ssl_dir="/home/whatwelo/local/usr/ssl/LetsEncrypt"

function trap_error () {
  local name="$prog_name"        # name of the script
  local lastline="$1"            # arg 1: last line of error occurrence
  local lastcmd="$2"             # arg 2: last cmd
  local lasterr="$3"             # arg 3: error code of last cmd
  echo "${name}: line ${lastline}: cmd='${lastcmd}' status=${lasterr}"
  email_log FAIL
  exit 1
}
trap 'trap_error ${LINENO} "${BASH_COMMAND}" "${PIPESTATUS[*]}"' ERR

function mainf() {
  set -E
  source /home/whatwelo/local/usr/bin/ssl_hosts.conf
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

  for host in "${hosts[@]}"; do
    update_cert $host
  done
  
  # save cert to dropbox
  #echo "##### update_dropbox.py #####"
  #update_dropbox.py
  # end
  echo "#### END update_ssl ####"
  errors=$(grep -Ei 'error:|cmd=' /home/whatwelo/cronlog/update_ssl.log)
  if [[ -n $errors ]]; then
    RESULT=FAIL
  else
    RESULT=SUCCESS
  fi
  email_log $RESULT 
}


function update_cert() {
  local host="$1"
  echo "##### update_certs.php $host #####"
  update_certs.php cp1.uhcloud.com certs/${host}.crt
}

function email_log() {
  # email log
  echo "##### email log to webmaster #####"
  echo "Log output" | mailx -a /home/whatwelo/cronlog/update_ssl.log -s "update_ssl.log: $1" webmaster@whatwelove.org
}

mainf
