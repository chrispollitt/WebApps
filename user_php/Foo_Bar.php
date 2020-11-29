<?php

// do the foo() operation ////////////////////////////////
function foo($one) {
  // vars //
  $fb_pwinfo = posix_getpwuid(posix_geteuid());
  $fb_home   = $fb_pwinfo['dir'];
  $fb_file   = "$fb_home/.foo/bar";
  // read file
  if(! file_exists($fb_file)) {
    trigger_error('fb file missing: '.$fb_file, E_USER_WARNING);
    return(["failed","failed"]);
  }
  $four = file($fb_file , FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  // look for entry
  $qone = preg_quote($one, '/');
  $five = array_values(preg_grep( "/\t$qone$/", $four));
  if(count($five) != 1) {
    trigger_error('fb host missing: '.$one, E_USER_WARNING);
    return(["failed","failed"]);
  }
  list($three, $etwo, $one) = explode("\t",$five[0]);
  // decrypt
  $two = fb_decrypt($etwo, $one);
  if(strlen($two)==0) {
    trigger_error('fb decrypt failed: '.$one, E_USER_WARNING);
    return(["failed","failed"]);
  }

  return([$three,$two]);  
}

// do the bar() operation ////////////////////////////////
function bar($three,$two,$one) {
  // vars //
  $fb_pwinfo = posix_getpwuid(posix_geteuid());
  $fb_home   = $fb_pwinfo['dir'];
  $fb_file   = "$fb_home/.foo/bar";
  // encrypt
  $success = false;
  $etwo    = fb_encrypt($two, $one);
  // read file
  if( file_exists($fb_file) ) {
    $four  = file($fb_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $qone = preg_quote($one, '/');
    $four  = array_values(preg_grep( "/\t$qone$/", $four, PREG_GREP_INVERT));
  }
  elseif(!file_exists(dirname($fb_file))) {
    mkdir(dirname($fb_file));
  }
  // update/delete entry
  if($three != "-delete") {
    $four[]  = "$three\t$etwo\t$one";
  }
  // write file
  sort($four);
  $contents = implode(PHP_EOL, $four);
  $contents .= PHP_EOL;
  $success = file_put_contents ($fb_file, $contents, LOCK_EX );
  chmod(dirname($fb_file), 0700);
  chmod($fb_file, 0600);
  
  return($success);
}

// encrypt() ///////////////////////////////////////////////
function fb_encrypt($data, $salt) {
  $padding = 16 - (strlen($data) % 16);                    // calc pad len
  $ct      =  $data . str_repeat(chr($padding), $padding); // padded data 
  list($key, $iv) = fb_get_key_iv($salt);
  // encrypt
  $encrypt = openssl_encrypt(
    $ct,
    'aes-256-cbc',
    $key,
    true, // OPENSSL_RAW_DATA,  
    $iv);
  // encode
  $eencrypt = base64_encode($encrypt);
  // return
  return $eencrypt;
}

// decrypt() //////////////////////////////////////////////////
function fb_decrypt($edata, $salt) {
  // decode
  $data        = base64_decode($edata);
  // ct
  list($key, $iv) = fb_get_key_iv($salt);
  // decrypt
  $cleartext   = openssl_decrypt(
    $data,
    'aes-256-cbc',
    $key,
    true,
    $iv);
  // return
  $cleartext = rtrim($cleartext,"\x00..\x1F");
  return($cleartext);
}

// get key & iv //////////////////////////////////////////////
function fb_get_key_iv($salt) {
  // vars //
  $fb_pwinfo = posix_getpwuid(posix_geteuid());
  $fb_home   = $fb_pwinfo['dir'];
  $fb_file   = "$fb_home/.foo/bar";
  $fb_passwd = "$fb_home/.ssh-OLD/id_rsa";
  // get password (choose one)
  if(! file_exists($fb_passwd)) {
    trigger_error('fb file missing: '.$fb_passwd, E_USER_WARNING);
    return(["failed","failed"]);
  }
  $password = file_get_contents($fb_passwd);

  // hashing rounds
  $rounds  = 6;
  // hashed, computed password
  $data00  = $password . $salt;
  $hash    = array();
  $hash[0] = sha256($data00, true);
  $result  = $hash[0];
  // do hashing rounds
  for ($i = 1; $i < $rounds; $i++) {
    $hash[$i] = sha256($hash[$i - 1].$data00, true);
    $result      .= $hash[$i];
  }
  // final key
  $key         = substr($result, 0, 32);
  // iv
  $iv          = substr($result, 32, 16);
  // return
  return([$key, $iv]);
}

// sha256() ////////////////////////////////////////////
function sha256($a, $b) {
  return hash('SHA256', $a, $b);
}

////////////////////////////////////////////////////////

?>
