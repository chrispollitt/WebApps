#!/bin/echo Must_be_sourced:

fb_dir=$(dirname ${BASH_SOURCE[0]})
fb_dir=$(dirname $fb_dir)
fb_path="$fb_dir/user_php"

function foo() {
  local -a output
  local one="$1"
  local IFS=$(echo -ne "\n\t")
  # call script
  output=($(php-cli "$fb_path/Foo_Bar_w.php" "$one"))

  if [[ -n ${output[1]} ]]; then
    three="${output[0]}"
    two="${output[1]}"
  else
    three="failed"
    two="failed"
  fi
  
  echo -e "$three\t$two"
}

function bar() {
  local three="$1"
  local two="$2"
  local one="$3"
  local IFS=$(echo -ne "\n\t")
  # call script
  php-cli "$fb_path/Foo_Bar_w.php" "$three" "$two" "$one"

  echo $?
}

#  # require script
#  . ~/user_bash/Foo_Bar.bash
#  # add host
#  bar "user" "pass" "host"
#  # get host
#  declare -a up
#  up=($(foo "host"))
#  echo "user=${up[0]}"
#  echo "pass=${up[1]}"
