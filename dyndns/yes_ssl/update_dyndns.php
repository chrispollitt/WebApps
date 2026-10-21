<?php
if($_SERVER["REQUEST_METHOD"] == "OPTIONS") {exit;}
/*

What:
  Run your own dynamic DNS server
From: 
  http://andrwe.org/linux/own-ddns
  https://help.dyn.com/remote-access-api/return-codes/
  https://documentation.cpanel.net/display/SDK/
Config: 
  $user_domain = array( 'user' => array('subdomain') );
  $dyndns = "dyndns.example.org"
Test: 
  wget --no-check-certificate --http-user="whatwelo" --http-passwd="pass" -q https://dyndns.whatwelove.org/nic/update/?hostname=cmnet

*/

// requires
$logto = "file";
require_once("logerrors.php");
require_once('cpanel.php');

// configuration of user and domain
$user_domain = array( 'whatwelo' => array('cmnet') );
// main domain for dynamic DNS
$dyndns = "whatwelove.org";
// cpanel api url
$cphost = 'cp1.uhcloud.com';

//////////////////////////////////////
// short sanity check for given IP
function checkip($ip) {
  if (!preg_match("/^(\d{1,3}\.){3}\d{1,3}$/", $ip) ) {
    return false;
  }
  if ( $ip == "0.0.0.0" || $ip == "255.255.255.255" ) {
    return false;
  }
  $iptupel = explode(".", $ip);
  foreach ($iptupel as $value) {
    if ($value < 0 || $value > 255)
      return false;
  }
  return true;
}

///////////////////////////////
// main function
function main() {
  global $user_domain;
  global $dyndns;
  global $cphost;
  
  debug( "agent: " . $_SERVER['HTTP_USER_AGENT']);
  // retrieve IP
  $ip = $_SERVER['REMOTE_ADDR'];
  // retrieve user
  if ( isset($_SERVER['REMOTE_USER']) ) {
    $user = $_SERVER['REMOTE_USER'];
  }
  else if ( isset($_SERVER['PHP_AUTH_USER']) ) {
    $user = $_SERVER['PHP_AUTH_USER'];
  }
  else {
    error( "No user given by connection from $ip");
    echo "badauth\n";
    return;
  }
  
  // check for given domain
  if ( isset($_POST['hostname']) ) {
    $subdomain = $_POST['hostname'];
  }
  else if ( isset($_GET['hostname']) ) {
    $subdomain = $_GET['hostname'];
  }
  else {
    error( "User $user from $ip didn't provide any domain");
    echo "nohost\n";
    return;
  }
  
  // check for needed variables
  if ( !isset($subdomain) || !isset($ip) || !isset($user) ) {
    error( "DDNS change for $user from $ip with $subdomain failed because of missing values");
    echo "badauth\n";
    return;
  }
  // short sanity check for given IP
  if ( !checkip($ip) ) {
    error( "IP $ip for $user from $ip with $subdomain was wrong");
    echo "badauth\n";
    return;
  }
  // short sanity check for given domain
  if ( !preg_match("/^[\w\d_\*\.-]+$/", $subdomain) ) {
    error( "Domain $subdomain for $user from $ip with $subdomain was wrong");
    echo "badauth\n";
    return;
  }
  // check whether user is allowed to change domain
  if ( !in_array("*", $user_domain[$user]) && !in_array($subdomain, $user_domain[$user]) ) {
    error( "Domain $subdomain is not allowed for $user from $ip");
    echo "badauth\n";
    return;
  }
  // update dns ////////////////////////////////
  cpinit($cphost, $dyndns);
  $rec = get_subdomains( $subdomain );
  if ($rec === false) {
    error( "Subdomain $subdomain not found for $user from $ip");
    echo "nohost\n";
    return;
  }
  $oldip = $rec->{'address'};
  $retval1 = update_dns($subdomain, $ip);
  // check whether DNS update was successful
  if ($retval1 === false) {
    error( "Changing dns for $subdomain.$dyndns to $ip failed: $retval1");
    echo "dnserr\n";
  }
  debug( "Changing dns for $subdomain.$dyndns to $ip succeeded: $retval1");
  echo "$retval1 $ip\n";
  // update mysql remote access ////////////////
  if($retval1 == "good") {
    $retval2 = del_mysql_host($oldip);
    $retval3 = add_mysql_host($ip);
    // check whether mysql_ra update was successful
    if ($retval3 === false) {
      error( "Changing mysql_ra to $ip failed: $retval3");
    }
    debug( "Changing mysql_ra to $ip succeeded: $retval3");
  }
}

// call main
main();
?>
