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

if (is_ajax(false)) {
	switch ($_POST["action"]) {
		case 'ticket':
			die(ticket_request());
			break;
		case 'login':
			if (ticket_validate($_POST['hash'], SHELL_USERNAME.SHELL_PASSWORD)) {
				// notice you can change the ticket validation
				// to validate something else, and the still keep
				// the following to prove authorization
				$_SESSION['login']['username'] = SHELL_USERNAME;
				$_SESSION['login']['password'] = SHELL_PASSWORD;
				$_SESSION['login']['IP'] = $_SERVER['REMOTE_ADDR'];
				$_SESSION['login']['UA'] = $_SERVER['HTTP_USER_AGENT'];
				die("0");
			}
			die("1");
			break;
	}
	exit;
}
?>

/**
 * Login
**/
TinyShell.plugins.login = new Class({
	description: "Login to TinyShell",
	username: '',
	run : function(terminal, args) {
		this.t = terminal;
		this.t.set_protocol("Login as: ").read_line(this.set_username);
	},
	set_username: function(terminal, line) {
		this.username = line;
		this.get_password();
	},
	get_password: function() {
		this.t.print("Using keyboard-interactive authentication.");
		this.t.set_protocol("Password: ", "password").read_line(this.set_password);
	},
	set_password: function(terminal, line) {
		this.password = line;
		this.t.ajax_request(this.use_ticket, "<?php echo $_AJAX_URL?>", "action=ticket");
	},
	use_ticket : function(ticket) {
		this.t.ajax_request(this.validate_auth, "<?php echo $_AJAX_URL?>", "action=login&hash="+encodeURIComponent(this.t.ticket_hash(ticket, this.username+this.password)));
	},
	validate_auth: function(response) {
		if (response != "0") {
			this.t.print("Access denied");
			this.get_password();
		} else {
			this.t.user = this.username;
			this.t.pass = this.password;
			this.t.print("Login: <?=date("r")?> from <?=gethostbyaddr($_SERVER["REMOTE_ADDR"])?>");
			this.t.print();
			this.t.print("Type 'help' to get started with TinyShell.");
			this.t.print();
			// run PROFILE
			var line = "<?=PROFILE?>";
			var args = new Array();
			this.t.execute('sys', args, 'sys '+line);
			// done
			this.t.resume();
		}
	}
});
