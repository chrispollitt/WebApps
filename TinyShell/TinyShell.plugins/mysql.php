<?php
/*
	Copyright (c) 2010 Theis Mackeprang

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
*/

require("../plugin.php");

if (!defined('MYSQL_DEFAULT_USERNAME')) define('MYSQL_DEFAULT_USERNAME', 'root');
if (!defined('MYSQL_DEFAULT_PASSWORD')) define('MYSQL_DEFAULT_PASSWORD', '');
if (!defined('MYSQL_DEFAULT_HOSTNAME')) define('MYSQL_DEFAULT_HOSTNAME', 'localhost');

function get_mysql_error() {
	return "ERROR ".mysqli_errno($GLOBALS["___mysqli_ston"]).": ".mysqli_error($GLOBALS["___mysqli_ston"]);
}

function echoEnc($s) {
  echo myencode($s);
}

if (is_ajax()) {
	switch ($_POST['action']) {
	case "login":
		if (!$_POST['h']) $_POST['h'] = myencode(MYSQL_DEFAULT_HOSTNAME);
		if (!$_POST['u']) $_POST['u'] = myencode(MYSQL_DEFAULT_USERNAME);
		if (!$_POST['p']) $_POST['p'] = myencode(MYSQL_DEFAULT_PASSWORD);
		if (is_callable("mysqli_real_connect")) {
			if (@((($GLOBALS["___mysqli_ston"] = mysqli_init()) && (mysqli_real_connect($GLOBALS["___mysqli_ston"], mydecode($_POST["h"]),  mydecode($_POST["u"]),  mydecode($_POST["p"]), NULL, 3306, NULL,  65536))) ? $GLOBALS["___mysqli_ston"] : FALSE)) {
				$_SESSION['mysql'] = $_POST;
				$_SESSION['mysql']['login'] = 1;
				echoEnc( mysqli_get_server_info($GLOBALS["___mysqli_ston"]));
				mysqli_close($GLOBALS["___mysqli_ston"]);
				exit;
			}
			else die(myencode(get_mysql_error()));
		} else die(myencode("ERROR: PHP mysql extension is not enabled"));
		break;
	case "suggest":
		if (!$_SESSION['mysql']['login']) exit;
		@((($GLOBALS["___mysqli_ston"] = mysqli_init()) && (mysqli_real_connect($GLOBALS["___mysqli_ston"], mydecode($_SESSION['mysql']["h"]),  mydecode($_SESSION['mysql']["u"]),  mydecode($_SESSION['mysql']["p"]), NULL, 3306, NULL,  65536))) ? $GLOBALS["___mysqli_ston"] : FALSE) or exit;
		mysqli_select_db($GLOBALS["___mysqli_ston"], information_schema);
		mysqli_query($GLOBALS["___mysqli_ston"], "SET NAMES utf8");
    $start = mydecode($_POST['start']);
		preg_match("#^(.*?)([a-z0-9_.]*)$#i", $start, $start);
		$str = addslashes($start[2]);
		$len = strlen(utf8_decode($start[2]));
		$dba = addslashes($_SESSION['mysql']["db"]);
		$dot = strpos($start[2], ".");
		if ($dot === false) $sql = "
			(SELECT SCHEMA_NAME AS sug
			FROM SCHEMATA
			WHERE SUBSTRING(SCHEMA_NAME, 1, $len) = '$str')
			UNION
			(SELECT ROUTINE_NAME AS sug
			FROM ROUTINES
			WHERE SUBSTRING(ROUTINE_NAME, 1, $len) = '$str' AND ROUTINE_SCHEMA = '$dba')
			UNION
			(SELECT TABLE_NAME AS sug
			FROM TABLES
			WHERE SUBSTRING(TABLE_NAME, 1, $len) = '$str' AND TABLE_SCHEMA = '$dba')
			UNION
			(SELECT COLUMN_NAME AS sug
			FROM COLUMNS
			WHERE SUBSTRING(COLUMN_NAME, 1, $len) = '$str' AND TABLE_SCHEMA = '$dba')
			ORDER BY sug";
		else if (strpos($start[2], ".", $dot+1) === false) $sql = "
			(SELECT CONCAT(TABLE_NAME,'.',COLUMN_NAME) AS sug
			FROM COLUMNS
			WHERE SUBSTRING(CONCAT(TABLE_NAME,'.',COLUMN_NAME), 1, $len) = '$str')
			UNION
			(SELECT CONCAT(ROUTINE_SCHEMA,'.',ROUTINE_NAME) AS sug
			FROM ROUTINES
			WHERE SUBSTRING(CONCAT(ROUTINE_SCHEMA,'.',ROUTINE_NAME), 1, $len) = '$str')
			UNION
			(SELECT CONCAT(TABLE_SCHEMA,'.',TABLE_NAME) AS sug
			FROM TABLES
			WHERE SUBSTRING(CONCAT(TABLE_SCHEMA,'.',TABLE_NAME), 1, $len) = '$str')
			ORDER BY sug";
		else $sql = "
			SELECT CONCAT(TABLE_SCHEMA,'.',TABLE_NAME,'.',COLUMN_NAME) AS sug
			FROM COLUMNS
			WHERE SUBSTRING(CONCAT(TABLE_SCHEMA,'.',TABLE_NAME,'.',COLUMN_NAME), 1, $len) = '$str'
			ORDER BY sug";
		echoEnc($start[2]."\t");
		$q = mysqli_query($GLOBALS["___mysqli_ston"], $sql);
		$i = 0;
		while ($d = mysqli_fetch_assoc($q)) {
      echoEnc(($i++?"\n":"").$d['sug']);
    }
		mysqli_close($GLOBALS["___mysqli_ston"]);
		break;
	case "query":
		if (!$_SESSION['mysql']['login']) exit;
		@((($GLOBALS["___mysqli_ston"] = mysqli_init()) && (mysqli_real_connect($GLOBALS["___mysqli_ston"], mydecode($_SESSION['mysql']["h"]),  mydecode($_SESSION['mysql']["u"]),  mydecode($_SESSION['mysql']["p"]), NULL, 3306, NULL,  65536))) ? $GLOBALS["___mysqli_ston"] : FALSE) or die(myencode(get_mysql_error()."\n"));
		@mysqli_query($GLOBALS["___mysqli_ston"], "SET NAMES utf8");
		$sqls = array();
		$sql = trim(mydecode($_POST['sql']), "\r\n\t ;");
		// remove comments
		// replace escaped backslashes in strings
		// strip away strings
		// strip away multi statement blocks
		// replace escaped back
		// identify queries
		$strings = array();
		function save_sql_strings(&$a, $s) {
			$u = "#".uniqid()."#";
			$a[$u] = str_replace(
				array(
					'\"',
					'\\\\|'
				), array(
					'"',
					'\\\\'
				), $s);
			return $u;
		}
		$stmts = preg_split("#(\s*;\s*)+#", preg_replace_callback(
			array(
				"/#[^\n]*/",
				"#(\"|').*?(?<!\\\\)\\1#",
				"#\bBEGIN_MULTI_STMT\b(.*)\bEND_MULTI_STMT\b#us"
			), 
      array(
				function($m){return "";},
				function($m){return save_sql_strings($strings, $m[0]);},
				function($m){return save_sql_strings($strings, $m[1]);}
			),
			str_replace('\\\\','\\\\|',$sql)
		));
		$sqls = array();
		// insert strings and begin..end blocks back in:
		foreach ($stmts as $stmt) {
			while (strpos($stmt, "#") !== false) $stmt = strtr($stmt, $strings);
			$sqls[] = $stmt;
		}
		// execute queries:
		$qn = 0; foreach ($sqls as $sql) {
			// process:
			if ($qn++) echoEnc("\n");
			switch (strtoupper(substr($sql, 0, 4))) {
			case "QUIT":
			case "EXIT":
			case "CLEA": // clear
				echoEnc("Cannot execute this in a multi-statement query");
				break;
			case "HELP":
				echoEnc("No help here, check TinyShell's homepage");
				break;
			case "DELI": // delimiter
				echoEnc("Delimiter change not supported\nHint: Use BEGIN_MULTI_STMT [SQL routine statement] END_MULTI_STMT");
				break;
			case "USE ":
			case "CONN":
				$db = preg_split("#\s+#", $sql); $db = $db[1];
				if ($db && @mysqli_select_db($GLOBALS["___mysqli_ston"], $db)) {
					$_SESSION['mysql']["db"] = $db;
					echoEnc("Database changed");
				} 
        else {
          echoEnc(get_mysql_error());
        }
				break;
			default:
				if ($_SESSION['mysql']["db"]) mysqli_select_db($GLOBALS["___mysqli_ston"], $_SESSION['mysql']["db"]);
				$time = microtime(true);
				$q = @mysqli_query($GLOBALS["___mysqli_ston"], $sql);
				$time = microtime(true)-$time;
				if (mysqli_error($GLOBALS["___mysqli_ston"])) echoEnc( get_mysql_error() );
				else if ($q !== false && $q !== true) { // result resource returned
					$rows = array();
					$cols = array();
					while ($d = mysqli_fetch_assoc($q)) {
						foreach ($d as $k => $c) {
							if ($c === null) $d[$k] = $c = "NULL";
							$cols[$k] = max($cols[$k], strlen(utf8_decode($c)));
						}
						$rows[] = $d;
					}
					$totalrows = count($rows);
					if ($totalrows) {
						// fields
						foreach ($cols as $field => $length) $cols[$field] = max(strlen(utf8_decode($field)), $length);
						$rowlength = array_sum($cols)+count($cols)*3+1;
						echoEnc( "+".str_repeat("-", $rowlength-2)."+\n");
						foreach ($cols as $field => $length) echoEnc( "| ".utf8_encode(str_pad(utf8_decode($field), $cols[$field], " ", STR_PAD_RIGHT))." ");
						
						echoEnc( "|\n");
						echoEnc( "+".str_repeat("-", $rowlength-2)."+\n");
						// rows
						foreach ($rows as $row) {
							foreach ($row as $field => $value) echoEnc( "| ".utf8_encode(str_pad(utf8_decode($value), $cols[$field], " ", STR_PAD_RIGHT))." ");
							echoEnc( "|\n");
						}
						echoEnc( "+".str_repeat("-", $rowlength-2)."+\n");
					}
					echoEnc( $totalrows." row".($totalrows > 1 ? "s" : "")." in set");
				} else echoEnc( "Query OK, ".mysqli_affected_rows($GLOBALS["___mysqli_ston"])." rows affected");
				echoEnc( " (".number_format($time, 2, ".", ",")." sec)");
			}
			echoEnc( "\n");
		}
		mysqli_close($GLOBALS["___mysqli_ston"]);
		break;
	}
	exit;
}
?>

/**
 * MySQL CLI
**/
TinyShell.plugins.mysql = new Class({
	description: "Advanced MySQL command line client",
	user : '',
	password : '',
	host: '',
	terminal : {},
	autocomplete : function(r) {
    r = mydecode(r);
		r = r.toLowerCase().split("\t"); // we want case insensitiveness, completing word with lowercase
		this.terminal.cursor_autocomplete(r[1], r[0]);
	},
	run : function(terminal, args) {
		this.terminal = terminal;
		
		for (var i = 0; i < args.length; i++) {
			// host
			if (args[i].substring(0,2).toLowerCase() == "-h") {
				if (args[i].length == 2) { // arg == '-h'
					if (args.length > i+1) this.host = args[i+1];
					else {
						terminal.print("mysql: option ´-h´ requires an argument\n").resume();
						return;
					}
				}
				else this.host = args[i].substring(2);
			}
			if (args[i].substring(0,7).toLowerCase() == "--host=") this.host = args[i].substring(7);
			// user
			if (args[i].substring(0,2).toLowerCase() == "-u") {
				if (args[i].length == 2) { // arg == '-u'
					if (args.length > i+1) this.user = args[i+1];
					else {
						terminal.print("mysql: option ´-u´ requires an argument\n").resume();
						return;
					}
				}
				else this.user = args[i].substring(2);
			}
			if (args[i].substring(0,7).toLowerCase() == "--user=") this.user = args[i].substring(7);
			// pwd
			if (args[i].substring(0,2).toLowerCase() == "-p") this.password = args[i].substring(2).length ? args[i].substring(2) : false;
			if (args[i].substring(0,11).toLowerCase() == "--password=") this.password = args[i].substring(11);
		}
		if (this.password === false) terminal.set_protocol("Enter password: ", "password").read_line(this.auth);
		else this.auth();
	},
	auth: function(terminal, line, args, argc) {
		if (argc) this.password = line;
		this.terminal.ajax_request(this.validate_auth, "<?php echo $_AJAX_URL?>", "action=login&h="+encodeURIComponent(myencode(this.host))+"&u="+encodeURIComponent(myencode(this.user))+"&p="+encodeURIComponent(myencode(this.password)));
	},
	validate_auth: function(response) {
    response = mydecode(response);
		if (response.substring(0,5) == "ERROR") this.terminal.print(response+"\n").resume();
		else {
			this.terminal.print("Welcome to the MySQL monitor.  Commands end with ;").print("Your MYSQL connection id is not static").print("Server version: "+response).print().print("Type 'clear' to clear the buffer.").print();
			// implement tabbing:
			this.onkeydown = function(e) {
				if (e.key == 'tab' && !e.shift) {
					e.stop();
					e.stop_default = true;
					this.terminal.ajax_request(this.autocomplete, "<?php echo $_AJAX_URL?>", "action=suggest&start="+encodeURIComponent(myencode(this.terminal.cursor_read_word()))); // use uppercased string, usefull for case sensitive databases
					return false;
				}
				return true;
			}
			// read sql:
			this.get_command();
		}
	},
	get_command: function() {
		this.terminal.set_protocol("mysql> ").read_line(this.parse_command);
	},
	parse_command: function(terminal, line, args) {
		if (!args.length) args = [''];
		var sarg = args[0].replace(/;/g, "");
		if (sarg == "quit" || sarg == "exit" || sarg == "bye") terminal.print("Bye").resume();
		else if (sarg == "clear") {
			terminal.clear_history();
			this.get_command();
		} else {
			terminal.ajax_request(this.command_output, "<?php echo $_AJAX_URL?>", "action=query&sql="+encodeURIComponent(myencode(line)));
		}
	},
	command_output: function(response) {
    response = mydecode(response);
		this.terminal.print(response);
		this.get_command();
	}
});
