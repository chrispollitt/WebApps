#!/bin/bash
#
# Make test call to cmnet on 6767
#

host="cmpi"
port="6767"

##########################

. ~/user_bash/Foo_Bar.bash
declare -a up
url_base="https://$host:$port"
up=($(foo "${host}:web"))
user="${up[0]}"
pass="${up[1]}"
wget_args="--quiet --no-check-certificate --output-document=- --user=$user --password=$pass"
######
out1=$(wget $wget_args "$url_base/")
if [[ $out1 == *Authentication* ]]; then
  if [[ $out1 == *@* ]]; then
    host2="whatwelove.org:2fa"
    up=($(foo "$host2"))
    user2="${up[0]}"
    pass2="${up[1]}"
    token="$pass2"
  else
    token=$($HOME/bin/get2fa_code.py)
  fi  
  out2=$(wget $wget_args --save-cookies cookies.txt --post-data="token=$token" "$url_base/auth/auth?/%3f")
else
  echo "error: unexpected out1: $out1"
  exit 1
fi
wget_args="$wget_args --load-cookies cookies.txt"
out3=$(wget $wget_args "$url_base/")
echo "out3=$out3"
