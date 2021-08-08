#!/bin/bash
#
# /bin/bash /home/whatwelo/local/usr/bin/cleanmail.sh
#

domains=". universal-laughter.com/chris"
user="whatwelo"
days="+30"
unset debug

##############################################

exec </dev/null

for domain in $domains; do
  for dir in new cur; do
    if [[ $domain == . ]]; then
      mail="/home/$user/mail/$dir"
    else
      mail="/home/$user/mail/$domain/$dir"
    fi
    cd "$mail"
    if [[ $PWD != $mail ]]; then
      echo "cd failed"
      continue
    fi
    if [[ -n $debug ]]; then
      echo "$PWD"
      find . -type f
    fi
    find . -type f -mtime $days -exec rm -f {} \;
  done
done
