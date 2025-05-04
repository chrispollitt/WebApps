<?php

# logging
$logto = "terminal";
require_once('logerrors.php');

# get dir contents
function GetDirectory($a_Path, &$a_files, &$a_folders) {
  $l_Directory = $a_Path;
  $l_files     = scandir($l_Directory);
  global $logger;
  
  for ($c = 0; $c < count($l_files); $c++) {
    if( preg_match('/^\./', $l_files[$c] )  ) {
      true; // skip leading dot dirs/files
    }
    elseif ( is_dir( $a_Path . $l_files[$c]) ) {
      $a_folders[] = $l_files[$c];
    } else {
      $a_files[] = $l_files[$c];
    }
  }
}

?>
<!DOCTYPE html>
<html lang="en" style="height:100%;">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Book Shelf</title>
<style media="screen" type="text/css">
<?php readfile("style.css") ?>
</style>
<script>

// globals
var screensize;

// init
function OnPageLoad() {
  // change css
  var mq = window.matchMedia( "(min-width: 9.5in)" );
  var ele = document.body;
  if(mq.matches) {
    screensize = 'desktop';
  }
  else {
    screensize = 'mobile';
    //ele.class     = screensize;
    //ele.className = screensize;
    ele.setAttribute("class",     screensize);
    //ele.setAttribute("className", screensize);
  }
  console.info("js  [notice] " +  screensize);
}
</script>
</head>
<body class="desktop" style="height:100%;" onload="OnPageLoad()">

<h1>My Book Shelf</h1>
<table style='height:auto;'>
  <tbody>
  <tr class='zerorow'>
    <td >Cover</td> 
    <td >Read online</td>
    <td >Download</td>
  </tr>
<?php
  $path = "./";
  GetDirectory($path, $l_Files, $l_Folders);

  // folder list
  $sep = " - ";
  for( $a = 0 ; $a<count($l_Folders) ; $a++ ) {
    $name = $l_Folders[$a] ;
    if( strpos($name, $sep) === false ) continue;
    list($name1, $name2) = explode($sep, $name);
    $p = $path . $name . "/{nph-,}index.{shtml,html,php,cgi}";
    $f = glob($p, GLOB_BRACE);
    if ( count($f) && is_file($f[0]) ) {
      $n = $f[0];
      $n = str_replace($path,"",$n);
echo("  <tr style='height:50px;'>\n");
echo("    <td class='zerocolumn'><img src='". $name . "/cover.jpg' alt='Cover' style='width:50px;'></td>\n");
echo("    <td style='height:100%;'><button style='width:100%; height:75px;' onclick='window.location.href=\"" . $n    . "\";'><b>" . $name1 . "</b><br />" . $name2 . "</button></td>\n");
echo("    <td style='height:100%;'><button style='width:100%; height:75px;' onclick='window.location.href=\"" . $name    . ".pdf\";'>PDF</button></td>\n");
echo("  </tr>\n");
    }
  }
?>  </tbody>
</table>

</body>
</html>
