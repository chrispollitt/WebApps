#!/bin/bash
#
# Wrapper script for acme_tiny.py client to generate server certificates.
# It uses openssl to generate key and does not modify httpd config.
#

echo "===START==="

prog_name="${0##*/}"
PATH=".:/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin:/home/whatwelo/bin:/home/whatwelo/local/usr/bin"

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

function main() {
  #### start of user serviceable parts ###################################
  country="CA"
  state="BC"
  town="Vancouver"
  email="chris.pollitt@gmail.com"
  cpanel='cs16.uhcloud.com'
  ssl_dir="/home/whatwelo/local/usr/ssl/LetsEncrypt"
  keysize=2048
  ##
  do_init
  ## whatwelove.org #################
  host="whatwelove.org"
  omit='^(ul|u-l|universal-laughter|landmarkcourt)\.'
  ##
  cmnet_before
  gen_cert
  cmnet_after
  ## universal-laughter.com ########
  host="universal-laughter.com"
  omit='^(xxx)\.'
  ##
  gen_cert
  ## landmarkcourt.ca ##############
  host="landmarkcourt.ca"
  omit='^(xxx)\.'
  ##
  gen_cert
  ## u-l.ca ##############
  host="u-l.ca"
  omit='^(xxx)\.'
  ##
  gen_cert
  #### end of user serviceable parts ###################################
}

function do_init() {
  rca1="$ssl_dir/ca/root-ca1.crt"
  rca2="$ssl_dir/ca/root-ca2.crt"
  sca="$ssl_dir/ca/signing-ca.crt"
  chain="$ssl_dir/ca/signing-ca-chain.pem"
  chain1="$ssl_dir/ca/signing-ca-chain1.pem"
  chain2="$ssl_dir/ca/signing-ca-chain2.pem"
  account_key="$ssl_dir/conf/account.key"
  tmpdir="$ssl_dir/tmp"
  tmp_key="$tmpdir/privkey.pem"
  tmp_crt="$tmpdir/cert.pem"
  tmp_rca1="$tmpdir/root1.pem"
  tmp_rca2="$tmpdir/root2.pem"
  tmp_sca="$tmpdir/sign.pem"
  sslcnf="$tmpdir/openssl.cnf"
  export ACME_CPANEL="$cpanel"
  export ACME_IP="empty"
  export ACME_HOST="empty"
  
  mkdir -p "$ssl_dir"/{ca,certs,conf,crl,etc}
  rm -rf "$tmpdir"
  mkdir -p "$tmpdir"
  if [ ! -f "$account_key" ]; then
    openssl genrsa $keysize > $account_key
  fi
  roots_and_chains
}

function cmnet_before() {
  # switch cmnet ip
  cmnet=$(dig +short cmnet.$host|perl -pe 's/\s+//g')
  whatw=$(dig +short   web.$host|perl -pe 's/\s+//g')
  export ACME_IP="$whatw"
  export ACME_HOST="cmnet.$host"
  update_dyndns_test.php $whatw
}

function cmnet_after() {
  # switch cmnet ip back
  update_dyndns_test.php $cmnet
}

function gen_cert() {
  export ACME_BASE_DOMAIN="$host"
  domains=$(get_subdomains.php $cpanel $host |egrep -v "$omit"|perl -pe 's/\s+/,/g')
  domains="${host},www.${host},${domains%,}"
  csr="$ssl_dir/certs/$host.csr"
  key="$ssl_dir/certs/$host.key"
  cert="$ssl_dir/certs/$host.crt"
  domains=`echo $domains | sed -e 's/,/,DNS:/g'`
  
  # create ssl config file
  cat /home/whatwelo/local/usr/etc/ssl/openssl.cnf > "$sslcnf"
  echo "[SAN]" >> "$sslcnf"
  echo "subjectAltName=DNS:$domains" >> "$sslcnf"

  # create request
  openssl req \
    -new -newkey rsa:$keysize -sha256 -nodes \
    -keyout "$tmp_key" -out "$csr" \
    -subj "/C=$country/ST=$state/L=$town/O=$host/emailAddress=$email/CN=$host" \
    -reqexts "SAN" \
    -config "$sslcnf"
  
  # create domain key
  if [ ! -f "$key" ]; then
    openssl genrsa $keysize > $key
  fi
  
  # check csr
  if [ ! -f "$csr" ]; then
    echo >&2 "$0: error: no CSR for domain $host"
    exit 1
  fi
    
  # get the cert
  acme_tiny.py \
    --account-key $account_key \
    --csr $csr \
    > "$tmp_crt"
    
  # save key
  if [ -f "$tmp_key" ]; then
    mv "$tmp_key" "$key"
  fi
  
  # fix domain cert
  dos2unix "$tmp_crt"
  openssl x509 -in "$tmp_crt" -noout -text | \
    perl -lpe '@d=split(/,? ?DNS:/,$_);if($#d>0){$t=shift(@d);@d=grep{$_ ne "'"$host"'"}@d;$_=$t."DNS:'"$host"', DNS:".join(", DNS:",@d)}' \
    > "$cert"
  cat "$tmp_crt" >> "$cert"
  rm "$tmp_crt"  
}

function roots_and_chains() {
  # get their certs
#  curl --silent https://www.identrust.com/certificates/trustid/root-download-x3.html | \
#    perl -lne 's/\r//;if(s/\s*\<textarea[^>]*\>/-----BEGIN CERTIFICATE-----/ .. s/[ \t]*\<\/textarea\>/-----END CERTIFICATE-----/) {print $_}' > "$tmp_rca2"
  curl --silent https://www.identrust.com/node/935  > "$tmp_rca2".p7b
  openssl pkcs7 -inform der -print_certs -in "$tmp_rca2".p7b -out "$tmp_rca2"
  curl --silent https://letsencrypt.org/certs/isrgrootx1.pem > "$tmp_rca1"
  curl --silent https://letsencrypt.org/certs/lets-encrypt-x3-cross-signed.pem > "$tmp_sca"
  
  # fix root certs
  dos2unix "$tmp_rca1"
  openssl x509 -in "$tmp_rca1" -noout -text > "$rca1"
  cat "$tmp_rca1" >> "$rca1"
  rm "$tmp_rca1"
  
  dos2unix "$tmp_rca2"
  openssl x509 -in "$tmp_rca2" -noout -text > "$rca2"
  cat "$tmp_rca2" >> "$rca2"
  rm "$tmp_rca2"
  
  # fix sign cert
  dos2unix "$tmp_sca"
  openssl x509 -in "$tmp_sca" -noout -text > "$sca"
  cat "$tmp_sca" >> "$sca"
  rm "$tmp_sca"
  
  # create chains
  cat "$rca1" "$sca" > "$chain1"
  cat "$rca2" "$sca" > "$chain2"
  cp "$chain2" "$chain"  # xxx <-- move to chain1 eventually
}

main

echo "===DONE==="
