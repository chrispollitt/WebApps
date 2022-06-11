<?php
  require_once("Foo_Bar.php");

	// SHELL LOGIN CREDENTIALS
	list($user,$pass) = foo("localhost:tinyshell");
	define('SHELL_USERNAME', $user);
	define('SHELL_PASSWORD', $pass);
	
	// MYSQL PLUGIN DEFAULT LOGIN CREDENTIALS
	list($user,$pass) = foo("whatwelo@localhost:mysql");
	define('MYSQL_DEFAULT_HOSTNAME', 'localhost');
	define('MYSQL_DEFAULT_USERNAME', $user);
	define('MYSQL_DEFAULT_PASSWORD', $pass);
	
	// ORACLE PLUGIN DEFAULT LOGIN CREDENTIALS
	define('ORACLE_DEFAULT_USERNAME', 'na');
	define('ORACLE_DEFAULT_PASSWORD', 'na');
	define('ORACLE_DEFAULT_HOSTNAME', 'na');
	
	// PATH
	putenv("PATH=/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin:/home/whatwelo/bin:/home/whatwelo/local/usr/bin");
	
	// PROFILE
	define('PROFILE', 'hostname');

?>
