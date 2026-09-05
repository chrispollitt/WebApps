#!/bin/bash

#set -x

################################################### 
# Truncate log files
###################################################
PATH="/home/whatwelo/local/usr/bin:$PATH"
declare -a dirs
declare -a files
me="/home/whatwelo/local/usr/bin/truncate_logs.sh"
dirs=('/home/whatwelo/public_html' '/tmp')
files=('phplog.txt' 'error_log')
age="+30"
lines=25

# Find files #######################################
if [[ -z $1 ]]; then

# change to cronlog dir
cd /home/whatwelo/cronlog
# save output to log
exec > truncate_logs.log 2>&1 < /dev/null

file_list=""
for file in "${files[@]}"; do
  file_list="${file_list}-iname '$file' -o "
done
file_list="\( ${file_list%????} \)"

# truncate
eval find "${dirs[@]}" $file_list -execdir $me '\{\}' '\;'

# delete
#eval find "${dirs[@]}" $file_list -mtime $age -execdir rm '\{\}' '\;'

# Truncate them ###################################
else

file="${PWD%/.}/${1#./}"

echo "file='$file'"
tail -n $lines "$file" | sponge "$file"

fi

##################################################
