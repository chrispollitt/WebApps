<?php
/*********************************************

What:
  cPanel php API (v2)
  https://documentation.cpanel.net/display/SDK/Guide+to+cPanel+API+2
Usage:
  require_once('cpanel.php');
  cpinit("cs16.uhcloud.com", "whatwelove.org");
  $subdomains = get_subdomains();

**********************************************/

require_once("Foo_Bar.php");
require_once("cpaneluapi.class.php"); 

$cphost = '';
$domain = '';
$cpuser = '';
$cppass = '';
$uapi   = '';

////////////////////////////////////
// initialize
function cpinit($host, $dom) {
  global $cphost;
  global $domain;
  global $cpuser;
  global $cppass;
  global $uapi;
  
  $cphost = $host;
  $domain = $dom;
  list($cpuser,$cppass) = foo($cphost.":cpanel");
  if ($cpuser == "") {
    fwrite(STDERR, "cphost not found in Foo_Bar\n");
    exit(1);
  }
  $uapi = new cpanelUAPI($cpuser, $cppass, $cphost); //instantiate the object
}

////////////////////////////////////
// call cpanel
function call_cpanel($cparray) {
  global $cpuser;
  global $cppass;
  global $cphost;
#fwrite(STDERR, "debug: func=".$cparray['cpanel_jsonapi_func']."\n");
#fwrite(STDERR, "debug: query=".http_build_query($cparray)."\n");
  // cpanel api url
  if(     $cparray['cpanel_jsonapi_func'] == "errlog" ) {
    $cpurl  = "https://".$cphost.":2083/cpsess1443216474/frontend/paper_lantern/stats/errlog.html";
  }
  elseif( $cparray['cpanel_jsonapi_func'] == "sysstats" ) {
//  $cpurl  = "https://".$cphost.":2083/cpsess1443216474/frontend/paper_lantern/index.html";
    $cpurl  = "https://".$cphost.":2083/cpsess1443216474/frontend/paper_lantern/resource_usage/resource_usage.live.pl#/current";
  }
  elseif( $cparray['cpanel_jsonapi_func'] == "sysinfo" ) {
    $cpurl  = "https://".$cphost.":2083/cpsess1443216474/frontend/paper_lantern/home/status.html";
  }
  else {
    $cpurl  = "https://".$cphost.":2083/cpsess1443216474/json-api/cpanel";
  }
  $cparray['cpanel_jsonapi_apiversion'] = 2; // should upgrade to UAPI
  
  $result   = false;
  $data     = array();
  TRY {
	  
    $header[0] = "Authorization: Basic " . base64_encode($cpuser.":".$cppass) . "\n\r";
    $curl = curl_init();                                // Create Curl Object
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);       // Allow self-signed certs
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);       // Allow certs that do not match the hostname
    curl_setopt($curl, CURLOPT_HEADER,0);               // Do not include header in output
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);       // Return contents of transfer on curl_exec
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);    // set the username and password
    curl_setopt($curl, CURLOPT_URL, $cpurl);            // set URL
    if(
      $cparray['cpanel_jsonapi_func'] == "errlog"    ||
      $cparray['cpanel_jsonapi_func'] == "sysstats"  ||
      $cparray['cpanel_jsonapi_func'] == "sysinfo"
    ) {
      curl_setopt($curl, CURLOPT_HTTPGET, TRUE);        // set method to GET
    } else {
      curl_setopt($curl, CURLOPT_POST, count($cparray));// set method to POST
      curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($cparray));   // set POST data
    }
    $response = curl_exec($curl);                       // run it!
    if ($response === false) {
      throw new Exception('curl_exec threw error: ' . curl_error($curl) ); 
    }
    curl_close($curl);
    // html output //////////////////////
    if( $cparray['cpanel_jsonapi_func'] == "errlog") {
#fwrite(STDERR, "debug: resp=".$response."\n");
      $inerrlog = 0;
      $response = preg_replace('/(\<[^>]+)\n([^>]+\>)/','$1$2',$response);
      foreach (explode("\n", $response) as $line) {
        if( !$inerrlog && preg_match('/\<textarea id="error_log-errors"/', $line) ) {
       //                               <textarea id="error_log-errors" class=
          $inerrlog = 1;
        }
		if($inerrlog) {
          $line2 = preg_replace('/\s*\<[^>]+\>\s*/','',$line);
          $data[] = $line2;
          $result = 1;
        }
        if( $inerrlog && preg_match('/\<.textarea\>/', $line) ) {
          $inerrlog = 0;
        }
      }
      $data[] = "";
	  }
    elseif( $cparray['cpanel_jsonapi_func'] == "sysstats" ) {
      $data[] = "[sysstats not implimented yet]";
      $result = 1;
    }
    elseif( $cparray['cpanel_jsonapi_func'] == "sysinfo" ) {
      $dom = new domDocument;
      libxml_use_internal_errors(true);
      @$dom->loadHTML($response);
      $dom->preserveWhiteSpace = false;
      $tables = $dom->getElementsByTagName('table');      
      $rows = $tables->item(1)->getElementsByTagName('tr');
      foreach ($rows as $row) {
        $cols = $row->getElementsByTagName('td');
        $var = trim($cols->item(0)->textContent);
        $val = trim($cols->item(1)->textContent);
        if($val && $val != "up") {
          $data[] = $var  . ": " . $val;
        }
      }
      $result = 1;
    }
    // json output /////////////////////
    else {
      $json = json_decode($response);
#fwrite(STDERR, "debug: json=".print_r($json,true)."\n");
      // errors
      if    ( sizeof($json->{'cpanelresult'}->{'data'}) == 0     ) {
        throw new Exception('cpanel error: ' . "empty data set");
      }
      elseif( array_key_exists('error', $json->{'cpanelresult'})  ) {
        throw new Exception('cpanel error: ' . $json->{'cpanelresult'}->{'error'}); 
      }
      elseif( array_key_exists('reason', $json->{'cpanelresult'}) ) {
        throw new Exception('cpanel error: ' . $json->{'cpanelresult'}->{'reason'}); 
      }
      // event result
      elseif ( array_key_exists('event', $json->{'cpanelresult'})             &&
               array_key_exists('result', $json->{'cpanelresult'}->{'event'}) &&
               sizeof($json->{'cpanelresult'}->{'data'}) == 1                 &&
               gettype($json->{'cpanelresult'}->{'data'}[0]) == "integer" ) {
        $data   = $json->{'cpanelresult'}->{'data'}[0];
        $result = $json->{'cpanelresult'}->{'event'}->{'result'};
      }      
      // result (top)
      elseif (   array_key_exists('result', $json->{'cpanelresult'}->{'data'}[0]) ) {
        // object result (second)
        if (gettype($json->{'cpanelresult'}->{'data'}[0]->{'result'}) == "object") {
          // status (second)
          if(array_key_exists('status', $json->{'cpanelresult'}->{'data'}[0]->{'result'})) {
            if ($json->{'cpanelresult'}->{'data'}[0]->{'result'}->{'status'} != 1) {
              throw new Exception('cpanel error "' . $json->{'cpanelresult'}->{'data'}[0]->{'result'}->{'statusmsg'}); 
            }
            $data   = $json->{'cpanelresult'}->{'data'};
            $result = $json->{'cpanelresult'}->{'data'}[0]->{'result'}->{'status'} == 1 ? true : false;
          }
          // unexpected result (second)
          else {
            throw new Exception('cpanel error: unexpected response 1');
          }
        }
        // int result (second)
        elseif (gettype($json->{'cpanelresult'}->{'data'}[0]->{'result'}) == "integer") {
          if ($json->{'cpanelresult'}->{'data'}[0]->{'result'} != 1) {
            throw new Exception('cpanel error "' . $json->{'cpanelresult'}->{'data'}[0]->{'output'}); 
          }
          $data   = $json->{'cpanelresult'}->{'data'};
          $result = $json->{'cpanelresult'}->{'data'}[0]->{'result'} == 1 ? true : false;
        }
        // unexpected (top)
        else {
          throw new Exception('cpanel error: unexpected response 2');
        }
      }
      // status (top)
      elseif(     array_key_exists('status', $json->{'cpanelresult'}->{'data'}[0]) ) {
        if ($json->{'cpanelresult'}->{'data'}[0]->{'status'} != 1) {
          throw new Exception('cpanel error "' . $json->{'cpanelresult'}->{'data'}[0]->{'statusmsg'}); 
        }
        $data   = $json->{'cpanelresult'}->{'data'};
        $result = $json->{'cpanelresult'}->{'data'}[0]->{'status'} == 1 ? true : false;
      }
      // neither!
      else {
        throw new Exception('no result or status'); 
      }
    }
  }
  CATCH(Exception $e) {
    trigger_error($e->getMessage() . ' for ' . $cparray['cpanel_jsonapi_func'], E_USER_ERROR); 
    $result = false;
  }
  
  return(array($result, $data));
}

/////////////////////////////////////
// get subdomains
function get_subdomains($sub=false) {
  global $cphost;
  global $domain;
  global $uapi;
  $skip = "autoconfig|autodiscover|cpcalendars|cpcontacts|cpanel|ftp|localhost|webdisk|webmail|whm";
  
  $list1 = array();

  // get ip info //
  $cparray = array(
    "cpanel_jsonapi_module" => "ZoneEdit",
    "cpanel_jsonapi_func"   => "fetchzone",
    "domain"                => $domain,
  );
  list($result, $data) = call_cpanel($cparray);
  if ($result === true) {
    $recs = $data[0]->{'record'};
    $index = -1;
    for ($i=0; $i < count($recs) ;$i++) {
      if (
        $recs[$i]->{'type'} == 'A' &&
         preg_match("/$domain\.$/", $recs[$i]->{'name'}) &&
        !preg_match("/\.[^.]+\.$domain\.$/", $recs[$i]->{'name'})  &&
        !preg_match("/^($skip)\.$domain\.$/", $recs[$i]->{'name'})
      ) {
        $name = trim($recs[$i]->{'name'},".");
        // get detailed info ///////////////////////////
        if (
          $sub !== false &&
          (
            ($name == "$sub.$domain") ||
            ($sub == "www" && $name == $domain)
          )
        ) {
          $res1 = $recs[$i];
          // get more info //
          $uapi->scope = 'DomainInfo';
          $subdomain = $sub == "www" ? $domain : "$sub.$domain";
          $res2 = $uapi->single_domain_data(array('domain' => "$subdomain"));
#fwrite(STDERR, "debug: res2=".print_r($res2,true)."\n");
          $res1->{'documentroot'} = $res2->{'data'}->{'documentroot'};
          // return data
          return( $res1 );
        }
        // add to list /////////////////////////////////
        elseif($name != $domain) {
          $list1[] = $name;
        }
      }
    }
  }

  // return results
  if ($sub !== false) {
    return false;
  }
  else {
    return $list1;
  }
}

//////////////////////////////////
// delete certs for a domain
function delete_certs($domain) {
  global $cphost;
  global $uapi;

  // check arggs
  if ($domain == "") {
    fwrite(STDERR, "domain not set\n");
    exit(1);
  }

  $uapi->scope = 'SSL';

  // get list of keys
  $res = $uapi->list_keys();
#fwrite(STDERR, "debug: res=".print_r($res,true)."\n");
  foreach($res->{'data'} as $dom) {
    $pat = '/\Q' . $domain . '\E/i';
    if(preg_match($pat, $dom->{'friendly_name'} )) {
      echo "deleting ssl key=" . $dom->{'friendly_name'} . ", id=" . $dom->{'id'} . "\n";
      $id = $dom->{'id'};
      $res = $uapi->delete_key( array('id' => $id));
#fwrite(STDERR, "debug: res=".print_r($res,true)."\n");
      echo "status=" . $res->{'status'} . "\n";
    }
  }

  // get list of certs
  $res = $uapi->list_certs();
#fwrite(STDERR, "debug: res=".print_r($res,true)."\n");
  foreach($res->{'data'} as $dom) {
    if($dom->{'subject.commonName'} == $domain) {
      echo "deleting domain ssl cert=" . $dom->{'subject.commonName'} . ", id=" . $dom->{'id'} . "\n";
      $id = $dom->{'id'};
      $res = $uapi->delete_cert( array('id' => $id));
#fwrite(STDERR, "debug: res=".print_r($res,true)."\n");
      echo "status=" . $res->{'status'} . "\n";
    }
  }

  // return
  return;
}

/////////////////////////////////////
// update certs
function update_certs($crt_file) {
  global $cphost;
  global $domain;
  $list1 = array();
  
  // check arggs
  if ($cphost == "") {
    fwrite(STDERR, "cphost not set\n");
    exit(1);
  }
  if ($crt_file == "") {
    fwrite(STDERR, "crt_file not set\n");
    exit(1);
  }
  
  // --- CRT ---
  $crt = "";
  $crt_fh = fopen($crt_file, "r");
  if ($crt_fh === false) {
    fwrite(STDERR, "cannot read crt_file: $crt_file\n");
    exit(1);
  }
  $incrt = false;
  while ($line = fgets($crt_fh)) {
    $match = preg_replace("/.*Subject:.* CN=([*\w.-]+).*/", '$1', $line);
    if($match != $line) {
      $domain = rtrim($match);
      $fulldomain = $domain;
      $domain = preg_replace("/^.*\.([^.]+\.[^.]+)$/","$1",$domain);
      continue;
    }
    $pat = "/^(\s+)\bDNS:([\w.-]+)(?:, )?(.*)$/";
    $match = preg_replace($pat, '$2', $line);
    while($match != $line) {
      $dns = rtrim($match);
      if(strncmp ("www.", $dns, 4) != 0) {
        $list2[] = $dns;
      }
      $line = preg_replace($pat, '$1$3', $line);
      $match = preg_replace($pat, '$2', $line);
    }
    if($line == "-----BEGIN CERTIFICATE-----\n") {
      $incrt = true;
      $crt .= $line;
    }
    else if($line == "-----END CERTIFICATE-----\n") {
      $incrt = false;
      $crt .= $line;
    }
    else if($incrt) {
      $crt .= $line;
    }
  }
  fclose($crt_fh);
  
  // --- KEY ---
  $key      = "";
  $key_file = preg_replace("/\.crt$/",".key",$crt_file);
  $key_fh = fopen($key_file, "r");
  if ($key_fh === false) {
    fwrite(STDERR, "cannot read key_file: $key_file\n");
    exit(1);
  }
  $inkey = false;
  $encrypted = false;
  while ($line = fgets($key_fh)) {
    if(preg_match("/^-----BEGIN (ENCRYPTED )?(RSA )?PRIVATE KEY-----/", $line)) {
      $inkey = true;
      $key .= $line;
      if(preg_match("/ENCRYPTED/", $line)) {
        $encrypted = true;
      }
    }
    else if(preg_match("/^-----END (ENCRYPTED )?(RSA )?PRIVATE KEY-----/", $line)) {
      $inkey = false;
      $key .= $line;
    }
    else if($inkey) {
      $key .= $line;
    }
  }
  fclose($key_fh);
  if($encrypted) {
    $key2 = $key;
    openssl_pkey_export($key2, $key);
  }

  // --- CAB ---
  $cab      = "";
  $cab_file = preg_replace("/certs\/[\w.-]+\.crt$/","ca/signing-ca-chain.pem",$crt_file);
  if($cab_file == $crt_file) {
    fwrite(STDERR, "error in generating cab file name\n");
    exit(1);
  }
  $cab_fh = fopen($cab_file, "r");
  if ($cab_fh === false) {
    fwrite(STDERR, "cannot read cab_file: $cab_file\n");
    exit(1);
  }
  $incab = false;
  while ($line = fgets($cab_fh)) {
    if($line == "-----BEGIN CERTIFICATE-----\n") { 
      $incab = true;
      $cab .= $line;
    }
    else if($line == "-----END CERTIFICATE-----\n") {
      $incab = false;
      $cab .= $line;
    }
    else if($incab) {
      $cab .= $line;
    }
  }
  fclose($cab_fh);
  
  // ---get list of subdomain entries from cpanel---
  $list1 = get_subdomains( );
  
  // special case: matchall
  if($fulldomain == "*.$domain" || preg_match("/\bmatchall.$domain.crt$/", $crt_file) ) {
    if($fulldomain != "*.$domain") {
      fwrite(STDERR, "crt is matchall but CN is not *\n");
      exit(1);
    }
    else if (!preg_match("/\bmatchall.$domain.crt$/", $crt_file)) {
      fwrite(STDERR, "CN is * but crt is not matchall\n");
      exit(1);
    }
    else {
      // need to remove some!
      echo "Match all: Enter comma seperated list to update: ";
      $ans = fgets(STDIN);
      $list2 = preg_split("/[,| ;:]/", $ans);
    }
  }
  
  // add cert to subdomains
  foreach ($list2 as $sub) {
    if($sub != $domain && !in_array($sub, $list1) ) {
      echo "error: sub=$sub, not found, skipping\n";
    }
    else {
      // delete existing cert(s)
      delete_certs($sub);
      // update record
      $cparray = array(
        "cpanel_jsonapi_module" => "SSL",
        "cpanel_jsonapi_func"   => "installssl",
        "domain"                => $sub,
        "crt"                   => $crt,
        "key"                   => $key,
        "cabundle"              => $cab,
      );
      list($result, $data) = call_cpanel( $cparray);
      if ($result === true) {
        echo "info: sub=$sub, installed\n";
      }
      else {
        echo "error: sub=$sub, failed\n";
      }
    }
  }
  
  // return
  return;
}

/////////////////////////////////////
// update dns
function update_dns($sub, $ip) {
  global $cphost;
  global $domain; 
  $result = false;
  
  // ---get list of subdomain entries from cpanel---
  $rec = get_subdomains( $sub );
  
  if ($rec !== false) {
    if($rec->{'address'} == $ip) {
      $result = "nochg";
    }
    else {
      // update record
      $line = $rec->{'line'};
      $cparray = array(
        "cpanel_jsonapi_module" => "ZoneEdit",
        "cpanel_jsonapi_func"   => "edit_zone_record",
        "domain"                => $domain,
        "line"                  => $line,
        "class"                 => "IN",
        "type"                  => "A",
        "name"                  => "$sub.$domain.",
        "ttl"                   => 60,
        "address"               => $ip,
      );
      list($result, $data) = call_cpanel($cparray);
      if ($result !== false) {
        $result = "good";
      }
      else {
        trigger_error( "update failed", E_USER_NOTICE);
      }
    }
  }
  
  return($result);
}

/////////////////////////////////////
// del mysql host
function del_mysql_host($ip) {
  global $cphost;
  global $domain;
  $result = false;
  
  // update record
  $cparray = array(
    "cpanel_jsonapi_module" => "MysqlFE",
    "cpanel_jsonapi_func"   => "deauthorizehost",
    "domain"                => $domain,
    "host"                  => $ip,
  );
  list($result, $data) = call_cpanel($cparray);
  if ($result !== false) {
    $result = "good";
  }
  else {
    trigger_error( "update failed", E_USER_NOTICE);
  }
  
  return($result);
}

/////////////////////////////////////
// add mysql host
function add_mysql_host($ip) {
  global $cphost;
  global $domain;
  $result = false;
  
  // update record
  $cparray = array(
    "cpanel_jsonapi_module" => "MysqlFE",
    "cpanel_jsonapi_func"   => "authorizehost",
    "domain"                => $domain,
    "host"                  => $ip,
  );
  list($result, $data) = call_cpanel($cparray);
  if ($result !== false) {
    $result = "good";
  }
  else {
    trigger_error( "update failed", E_USER_NOTICE);
  }
  
  return($result);
}

/////////////////////////////////////
// get error log
function get_error_log() {
  global $cphost;
  global $domain;
  $cparray = array(
    "cpanel_jsonapi_module" => "Stats",
    "cpanel_jsonapi_func"   => "errlog",
    "domain"                => $domain,
  );
  list($result, $data) = call_cpanel($cparray);
  if ($result === false) {
    trigger_error( "get error log failed", E_USER_NOTICE);
  }
  
  return($data);
}

/////////////
// get system stats and info
function get_stats() {
  global $cphost;
  global $domain;
  global $uapi;

  // Get ResourceUsage
  $uapi->scope = 'ResourceUsage';
  $res = $uapi->get_usages();
  foreach($res->{'data'} as $usage) {
    // description, error, formatter, id, maximum, url, usage
    $data1[] = $usage->{'description'} . ": " . $usage->{'usage'};
  }

  // Get sysinfo
  $cparray = array(
    "cpanel_jsonapi_module" => "Stats",
    "cpanel_jsonapi_func"   => "sysinfo",
    "domain"                => $domain,
  );
  list($result, $data2) = call_cpanel($cparray);
  if ($result === false) {
    trigger_error( "get stats2 failed", E_USER_NOTICE);
  }

  $data = array_merge($data1,$data2);
  sort($data);
  return($data);
}

?>
