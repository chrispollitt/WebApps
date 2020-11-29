#!/home/whatwelo/bin/env2 php-cli -d include_path=.:/home/whatwelo/pear:/home/whatwelo/php:/home/whatwelo/user_php
<?php
/*

What:
  test update_dns

*/

// requires
$logto = "terminal";
require_once("logerrors.php");
require_once('cpanel.php');

// main domain for dynamic DNS
$dyndns = "whatwelove.org";
// cpanel api url
$cphost = 'cs16.uhcloud.com';
$subdomain = 'cmnet';
$ip = $argv[1];

cpinit($cphost, $dyndns);
// update dns ////////////////////////////////
$rec = get_subdomains($subdomain );
$oldip = $rec->{'address'};
$retval1 = update_dns($subdomain , $ip);
// check whether DNS update was successful
echo "retval1=$retval1\n";
// update mysql remote access ////////////////
if($retval1 == "good") {
  $retval2 = del_mysql_host( $oldip);
  echo "retval2=$retval1\n";
  $retval3 = add_mysql_host($ip);
  echo "retval3=$retval1\n";
}
?>
