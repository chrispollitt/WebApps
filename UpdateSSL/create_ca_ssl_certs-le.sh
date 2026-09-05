#!/bin/bash
#
# Wrapper script for acme_tiny.py client to generate server certificates.
# It uses openssl to generate key and does not modify httpd config.
#

echo "===START==="

prog_name="${0##*/}"
PATH=".:/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin:/home/whatwelo/bin:/home/whatwelo/local/usr/bin"
certs_url="https://letsencrypt.org"

root_nam0="ISRG Root X1"

root_sel0='//strong[text()="'"$root_nam0"'"]/following-sibling::ul//li[contains(text(), "Certificate details (self-signed)")]/a[text()="pem"]/@href'

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
  source /home/whatwelo/local/usr/bin/ssl_hosts.conf
  do_init
  # Loop over the hosts and process each one
  for i in "${!hosts[@]}"; do
    host=${hosts[$i]}
    omit=${omits[$i]}
    if ! gen_cert; then
      echo "Error processing $host, continuing with next..."
    fi
  done
}

function do_init() {
  rca="$ssl_dir/ca/root-ca.crt"
  account_key="$ssl_dir/conf/account.key"
  tmpdir="$ssl_dir/tmp"
  tmp_links="$tmpdir/cert-links.html"
  tmp_key="$tmpdir/privkey.pem"
  tmp_crt="$tmpdir/cert.pem"
  tmp_rca="$tmpdir/root.pem"
  tmp_intermediate="$tmpdir/intermediate.pem"
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
  fetch_root
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
  domains=$(get_subdomains.php $cpanel $host | egrep -v "$omit" | perl -pe 's/\s+/,/g')
  domains="${host},www.${host},${domains%,}"
  csr="$ssl_dir/certs/$host.csr"
  key="$ssl_dir/certs/$host.key"
  cert="$ssl_dir/certs/$host.crt"
  domains=$(echo $domains | sed -e 's/,/,DNS:/g')

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

  # get the cert with retry mechanism
  max_retries=5
  retry_delay=5  # Start with a 5-second delay
  success=0

  for attempt in $(seq 1 $max_retries); do
    if acme_tiny.py --account-key $account_key --csr $csr > "$tmp_crt"; then
      success=1
      break
    else
      echo "Attempt $attempt failed, retrying in $retry_delay seconds..."
      sleep $retry_delay
      retry_delay=$((retry_delay * 2))  # Exponential backoff
    fi
  done

  # Check if we exceeded max retries
  if [ $success -ne 1 ]; then
    echo "Exceeded maximum retries. Please try again later."
    exit 1
  fi

  # save key
  if [ -f "$tmp_key" ]; then
    mv "$tmp_key" "$key"
  fi

  # acme_tiny returns leaf + whatever intermediate(s) LE's default chain
  # needs (may be more than one, e.g. EE <- YR2 <- Root YR <- ISRG Root X1);
  # keep everything after the leaf and name the chain file after the
  # immediate signer, instead of assuming a fixed name or a fixed depth
  dos2unix "$tmp_crt"
  awk '/-----BEGIN CERTIFICATE-----/{n++} n>=2' "$tmp_crt" > "$tmp_intermediate"
  if [ ! -s "$tmp_intermediate" ]; then
    echo >&2 "$0: error: no intermediate certificate returned by ACME for $host"
    exit 1
  fi
  sign_nam=$(openssl x509 -in "$tmp_intermediate" -noout -subject -nameopt multiline | awk -F'= ' '/commonName/{print $2}' | tr -d '[:space:]')
  chain="$ssl_dir/ca/signing-ca-chain$sign_nam.pem"
  if [ ! -f "$chain" ]; then
    cat "$rca" "$tmp_intermediate" > "$chain"
    echo "created $chain (issued by Let's Encrypt $sign_nam)"
  fi

  # trim tmp_crt down to just the leaf cert
  perl -lpi -e 'if( /^-----END CERTIFICATE-----/) {print $_;exit}' "$tmp_crt"

  # fix domain cert
  openssl x509 -in "$tmp_crt" -noout -text | \
    perl -lpe '@d=split(/,? ?DNS:/,$_);if($#d>0){$t=shift(@d);@d=grep{$_ ne "'"$host"'"}@d;$_=$t."DNS:'"$host"', DNS:".join(", DNS:",@d)}' \
    > "$cert"
  cat "$tmp_crt" >> "$cert"
  rm -f "$tmp_crt" "$tmp_intermediate"
}

function fetch_root() {
  # the root CA is stable and rarely rotates, so this is the one thing
  # still safe to fetch by name from the certificates page
  curl --silent "${certs_url}/certificates/" > "$tmp_links"
  root_url=$(cat "$tmp_links" | getHTMLele.php "$root_sel0")
  echo "root_url=$root_url"

  # get its cert
  curl --silent "${certs_url}$root_url" > "$tmp_rca"

  # fix root cert
  dos2unix "$tmp_rca"
  openssl x509 -in "$tmp_rca" -noout -text > "$rca"
  cat "$tmp_rca" >> "$rca"
  rm "$tmp_rca"
}

main

echo "===DONE==="
