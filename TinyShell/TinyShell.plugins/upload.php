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
if ($_POST['action'] = 'form'):?>
<?php echo "<?xml version='1.0' encoding='utf-8'?>\n"?>
<?php if($_SERVER["REQUEST_METHOD"] == "OPTIONS") {exit;} ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="da-DK" lang="da-DK">
<head>
	<!--
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
	-->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Type-Script" content="text/javascript" />
	<title>TinyShell Upload @ <?=ucwords($_SERVER['SERVER_NAME'])?></title>
	<link rel="shortcut icon" type="image/x-icon" href="../favicon.ico" />
	<link rel="icon" type="image/x-icon" href="../favicon.ico" />
	<link rel="stylesheet" type="text/css" href="../style.css" />
	<link rel="stylesheet" type="text/css" href="../theme.css" />
</head>
<body onload="window.setTimeout(function(){window.scrollTo(0,0);},100)">
	<form method='post' enctype='multipart/form-data'>
<?php
	if ($_POST['formaction'] == 'upload' && $_FILES['file'] && $_FILES['file']['name']) {
		if (!$_FILES['file']['error'] && is_readable($_FILES['file']['tmp_name']) && @file_put_contents($_FILES['file']['name'], file_get_contents($_FILES['file']['tmp_name'])) !== false) {
?>
<p>
	The file `<?=$_FILES['file']['name']?>' was successfully uploaded at <?=date("H:i")?>
</p>
<?php
		} else {
?>
<p>
	Failed to upload the file `<?=$_FILES['file']['name']?>' uploaded at <?=date("H:i")?>
</p>
<?php
		}
	}
?>
		<p>
			Upload file (max <?=ini_get('post_max_size')?>b) to:
			<br />
			<strong><?=getcwd()?></strong>
		</p>
		<p>
			<input type='file' name='file' />
			<input type='hidden' name='formaction' value='upload' />
			<input type='submit' value='Upload' />
		</p>
	</form>
</body>
</html>
<?php endif;

exit;
}
	
?>

/**
 * Upload file
**/
TinyShell.plugins.upload = new Class({
	description: "Upload files to the server",
	run : function(terminal, args) {
		terminal.print("<a href='"+terminal.create_url("<?php echo $_AJAX_URL?>", "action=form")+"' target='_blank'>Click here to upload files</a>", true);
		terminal.resume();
	}
});
