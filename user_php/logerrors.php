<?php
/*

  What: Log messages
  From: 
    https://pear.github.io/Log/#the-error-log-handler
	pear install Log
  Config: 
    $logto = file, terminal, syslog, browser-console, browser-html, browser-plain
    require_once("logerrors.php");
  Test:
    trigger_error("This is a test", E_USER_NOTICE);
    global $logger;
    $logger->log("This is another test", PEAR_LOG_ERR);
  
*/

putenv ( 'USER=whatwelo' );
putenv ( 'HOME=/home/whatwelo' );

// load Log package
require_once 'Log.php';

/*
globals
  $logger       
  $logto        
  $php_errormsg 
*/

// set up handeling
function errorSetup() {
  global $logto;
  global $logger;

  // set default value
  if(empty($logto)) {
    $logto = "file";
  }
  // create log instance
  // -- to file
  if($logto == "file") {
	if( is_writable(".") ) {
      $logfile = realpath(".") . '/phplog.txt';
	} else {
      $logfile =  '/tmp/phplog.txt';
	}
//    if( file_exists($logfile) ) {
//      unlink($logfile);
//    }
    $conf = array(
      'mode'       => 0600,
      'timeFormat' => '%F_%X'
    );
    $logger = Log::singleton('file', $logfile, '', $conf);
  }
  // -- to terminal
  elseif($logto == "terminal") {
    $conf = array(
      'stream' => STDERR,
      'timeFormat' => '%F_%X'
    );
    $logger = Log::singleton('console', '', '', $conf);
  }
  // -- to browser console
  elseif($logto == "browser-console" || $logto == "browser") {
    $conf = array(
      'timeFormat' => '%F_%X'
    );
    $logger = Log::singleton('firebug', '', 'php', $conf);
  }
  // -- to browser html
  elseif($logto == "browser-html") {
    $conf = array(
      'timeFormat' => '%F_%X'
    );
    $logger = Log::singleton('display', '', 'php', $conf);
  }
  // -- to browser plain
  elseif($logto == "browser-plain") {
    $conf = array(
      'lineFormat' => '%3$s: %4$s',
      'linebreak'  => "\n",
      'rawText'    => True,
      'timeFormat' => '%F_%X'
    );
    $logger = Log::singleton('display', '', 'php', $conf);
  }
  // -- to syslog
  elseif($logto == "syslog") {
    $conf = array(
      'lineFormat' => '%3$s: %4$s',
      'timeFormat' => '%F_%X'
    );
    $logger = Log::singleton('syslog', LOG_USER, 'php', $conf);
  }
  // -- unknown place
  else {
    trigger_error('bad value for $logto: "' . $logto . '"', E_USER_ERROR);
  }
//  trigger_error('errorSetup done, type=' . gettype($logger), E_USER_NOTICE);
//  $logger->log('errorSetup test', PEAR_LOG_NOTICE);
}

// reroute messages from php to firebug
function errorHandler($code, $message, $file, $line) {
    global $logger;
    global $php_errormsg;

	// skip annoying erros
	if(preg_match('/Cannot modify header information/', $message)) {
		return;
	}
	
    /* Map the PHP error to a Log priority. */
    switch ($code) {
    case E_WARNING:
    case E_USER_WARNING:
        $priority = PEAR_LOG_WARNING;
        break;
    case E_NOTICE:
    case E_USER_NOTICE:
        $priority = PEAR_LOG_NOTICE;
        break;
    case E_ERROR:
    case E_USER_ERROR:
        $priority = PEAR_LOG_ERR;
        break;
    default:
        $priority = PEAR_LOG_INFO;
    }
    // get name of initital script
    $stack = debug_backtrace();
    $firstFrame = $stack[count($stack) - 1];
    $initialFile = $firstFrame['file'];
    $php_errormsg = $message;
	// log error
    $logger->log($message . ' in ' . basename($initialFile) . ':' . basename($file) . ' at line ' . $line, $priority);
	// print stack if xdebug_is_enabled
	if(
	  function_exists("xdebug_is_enabled") && 
	  xdebug_is_enabled() &&
	  $priority == PEAR_LOG_ERR
	) {
		xdebug_print_function_stack();
	}			
}

// capure fatal errors
function handleShutdown()  {
  global $logger;
  global $logto;
  
  $error = error_get_last();
  if($error !== NULL) {
    $priority = PEAR_LOG_ERR;
    $message = $error['message'] ." in ".$error['file']." at line ".$error['line'];
    $logger->log($message, $priority);
  }
  else {
    if (!strstr($logto, "browser")) {
      $priority = PEAR_LOG_NOTICE;
      $message = "====END====";
      $logger->log($message, $priority);
    }
  }
}

// define debug function
function debug($message) {
  global $logger;
  $priority = PEAR_LOG_DEBUG;
  $logger->log($message, $priority);
}
// define info function
function info($message) {
  global $logger;
  $priority = PEAR_LOG_INFO;
  $logger->log($message, $priority);
}
// define warn function
function warn($message) {
  global $logger;
  $priority = PEAR_LOG_WARNING;
  $logger->log($message, $priority);
}
// define error function
function error($message) {
  global $logger;
  $priority = PEAR_LOG_ERR;
  $logger->log($message, $priority);
}

// setup
//$logger = new stdClass();
errorSetup();
register_shutdown_function('handleShutdown');
set_error_handler('errorHandler');

// put START message (hope we're inside <html> at this point!)
if (!strstr($logto, "browser")) {
    trigger_error('====START====', E_USER_NOTICE);
//  $logger->log( "====START====", PEAR_LOG_NOTICE);
}
?>
