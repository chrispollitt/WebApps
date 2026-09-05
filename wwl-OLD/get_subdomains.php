#!/home/whatwelo/bin/env2 php-cli -d include_path=.:/home/whatwelo/pear:/home/whatwelo/php:/home/whatwelo/user_php
<?php
/*

What:
  get list of subdomains from cPanel

*/

// requires
$logto = "file";
require_once("logerrors.php");
require_once('cpanel.php');

/////////////////////////////////////
// get list of subs
function main($cphost, $domain, $sub) {
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
  if ($sub == "") {
    $list = get_subdomains();
    natsort( $list );
    return(implode("\n", $list) . "\n");
  }
  else {
    $sub = get_subdomains( $sub );
    if ($sub !== false) {
      return(json_encode($sub) . "\n");
    }
    else {
      return("false\n");
    }
  }
}

// call main
echo main($argv[1], $argv[2], isset($argv[3]) ? $argv[3] : "");
?>
