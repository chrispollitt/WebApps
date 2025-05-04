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

if (is_ajax()) {
	if ($_POST["action"] == 'system') {
	  $val = mydecode($_POST['val']);
	  //$logger->log('sys val=' . $val, PEAR_LOG_NOTICE);
		unset($out);
		if( !ini_get('safe_mode') ) {
		  $out = @shell_exec($val." 2>&1");
		}
		else {
      // unfortunately error stream cannot be redirected in safe_mode
			// since this will be escaped by escapeshellcmd
			//echo "sys: PHP safe_mode_exec_dir is: ".ini_get('safe_mode_exec_dir')."\n";
			exec($val, $out);
			$out = $out ? implode("\n", $out)."\n" : "";
		}
	  //$logger->log('sys out=' . trim($out), PEAR_LOG_NOTICE);
	  echo myencode($out);
	}
	exit;
}
?>


/**
 * Run command on system
**/
TinyShell.plugins.sys = new Class({
	description: "Run commands on the server",
	run : function(terminal, args, line) {
		this.t = terminal;
		if (line.length > 4) {
		  var val = line.substring(4);
		  // console.info("js  [notice] sys val=" + val);
		  terminal.ajax_request(this.print, "<?php echo $_AJAX_URL?>", "action=system&val="+myencode(val));
		}
		else {
		  this.t.print("sys: usage: sys command").resume();
		}
	},
	print : function(response_e) {
	  var response = mydecode(response_e);
	  // console.info("js  [notice] sys out=" + response);
	  this.t.print(response);
	  this.t.resume();
	}
});
