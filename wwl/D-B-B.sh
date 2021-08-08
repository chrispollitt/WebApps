#!/bin/bash
PATH="/home/whatwelo/local/usr/bin:$PATH"
echo "ulimit -u=$(ulimit -u)"
rclone sync dropbox:/ /home/whatwelo/.D-B-B
