#!/home/whatwelo/bin/env2 php-cli -d include_path=.:/home/whatwelo/pear:/home/whatwelo/php:/home/whatwelo/user_php
<?php
/*

What:
  Update subdomain certs via cPanel
Notes:
  * Does not install top level certs, only subdomain ones
  * Expects the same cert for all subdomains (via subjectAltName)

*/

// requires
$logto = "file";
require_once("logerrors.php");
require_once('cpanel.php');


/////////////////////////////////////
// get list of subs
function main($cphost, $cert_file) {
  // check arggs
  if ($cphost == "") {
    fwrite(STDERR, "cphost not set\n");
    exit(1);
  }
  if ($cert_file == "") {
    fwrite(STDERR, "cert_file not set\n");
    exit(1);
  }
  
  cpinit($cphost, 'domain');
  update_certs($cert_file);
  
  // return
  return;
}

// call main
main($argv[1], $argv[2]);
?>
