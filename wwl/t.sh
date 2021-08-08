#/bin/bash

exec >/home/whatwelo/tmp/t.log 2>&1 </dev/null

"$@" &
disown

