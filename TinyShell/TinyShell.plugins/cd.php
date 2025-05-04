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
	if ($_POST["action"] == 'cd') {
		if (strlen($_POST["ndir"])) {
		        $ndir = mydecode($_POST["ndir"]);
			if (is_dir($ndir)) {
			  chdir($ndir);
			  die(myencode("0".getcwd()));
			} 
			else {
			  die(myencode("1"));
			}
		} else {
			@chdir($_SERVER["DOCUMENT_ROOT"]);
			die(myencode("0".getcwd()));
		}
	}
	exit;
}
?>

/**
 * Change directory
**/
TinyShell.plugins.cd = new Class({
	description: "Change directory",
	run : function(terminal, args) {
		this.t = terminal;
		this.dir = args[0] || "";
		terminal.ajax_request(this.print, "<?php echo $_AJAX_URL?>", "action=cd&ndir="+myencode(this.dir));
	},
	print : function(response_e) {
	  var response = mydecode(response_e);
		if (response.substring(0,1) == "1") this.t.print("TinyShell: cd: No such directory `"+this.dir+"'");
		else this.t.dir = response.substring(1);
		this.t.resume();
	}
});
