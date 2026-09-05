#!/home/whatwelo/bin/env2 php-cli -d include_path=.:/home/whatwelo/pear:/home/whatwelo/php:/home/whatwelo/user_php -f
<?php
/*

What:
  get error log from cPanel

*/

// requires
$logto = "file";
require_once("logerrors.php");
require_once('cpanel.php');

/////////////////////////////////////
// get list of subs
function main($cphost, $domain) {
  $list = array();
  
  if ($cphost == "") {
    fwrite(STDERR, "cphost not set\n");
    exit(1);
  }
  if ($domain == "") {
    fwrite(STDERR, "domain not set\n");
    exit(1);
  }
  // get list of zone entries
  cpinit($cphost, $domain);
  $list = get_stats();
  
  return(implode("\n", $list) . "\n");
}

// call main
echo main($argv[1], $argv[2]);
?>
