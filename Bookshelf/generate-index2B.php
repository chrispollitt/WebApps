<?php

error_reporting(E_STRICT);

# logging
$logto = "terminal";
require_once('logerrors.php');

# read toc.dat
$chapters = unserialize(file_get_contents('toc.dat'));

# get title
$title = basename( getcwd() );
list($title1, $title2) = explode(" - ", $title);

?>
<!DOCTYPE html>
<html lang="en" style="height:100%; overflow:hidden;">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=$title?></title>
<style media="screen" type="text/css">
<?php readfile("../style.css") ?>
</style>
<script>

// globals
var table;
var tocdiv;
var toccell;
var bookframe;
var bookdoc;
var chapters;
var screensize;

//////// hide browser incompats /////////
function iframeRef( frameRef ) {
    return frameRef.contentWindow
        ? frameRef.contentWindow.document
        : frameRef.contentDocument
}
function scrollEle( docRef ) {
    return docRef.scrollingElement 
        ? docRef.scrollingElement 
        : docRef.documentElement
}
/////////////////////////////////////////

// init
function OnPageLoad() {
  // set globals
  table     = document.getElementById("table");
  toccell   = document.getElementById("toccell");
  tocdiv    = document.getElementById("tocdiv");
  bookframe = document.getElementById("bookframe");
  bookdoc   = iframeRef(bookframe);
<?php
  $js_array = json_encode($chapters);
  echo "  chapters = " . $js_array . ";\n";
?>

  // change css
  var w = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
  var h = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
  var mq = window.matchMedia( "(min-width: 9.5in)" );
  var body = document.body;
  if(mq.matches) {
    screensize = 'desktop';
    table.setAttribute(     "style", "height:" + (h -  25) + "px");
    tocdiv.setAttribute(    "style", "height:" + (h - 100) + "px");
    bookframe.setAttribute( "style", "height:" + (h - 100) + "px");
  }
  else {
    screensize = 'mobile';
    body.setAttribute(      "class", screensize);
//    table.setAttribute(     "style", "height:" + (h -  25) + "px");
//    tocdiv.setAttribute(    "style", "height:" + (h - 100) + "px");
//    bookframe.setAttribute( "style", "height:" + (h - 100) + "px");
  }
  console.info("js  [notice] w=" + w + " h="+h+" s="+ screensize);

  // get chapter markers
  for (var i=0;i<chapters.length;i++) {
    var anchor  = chapters[i][0];
    // in bookdoc
    var element = bookdoc.getElementById(anchor);
    var offset  = element.offsetTop; 
    chapters[i][2] = offset;
    // in toc
    element = document.getElementById(anchor);
    offset  = element.offsetTop;
    chapters[i][3] = offset;
  }

  // jump to correct chapter
  var chapter = window.location.href;
  if(chapter.indexOf('#') != -1) {
    chapter = chapter.replace(/^.*\#/, '');
    JumpToChapter(chapter, 0);
  }

  bookdoc.onscroll = OnScrollEvent;
}

// on iframe scroll, look where we are
function OnScrollEvent() {
  var range   = 500;
  var scroll  = scrollEle(bookdoc).scrollTop;
  for (var i=chapters.length-1;i>=0;i--) {
    var anchor  = chapters[i][0];
    var offset  = chapters[i][2];
    if (scroll >= offset-5 && scroll < offset+range) {
      var url = window.location.href;
      var canchor = url.replace(/^.*\#/, '');
      if (canchor !== anchor) {
        console.info("js  [notice] scroll=" + anchor);
        JumpToChapter(anchor, 1);
      }
      break;
    }
  }
}

// jump to a chapter
function JumpToChapter(chapter, soft) {
  // debug
  var jtype = soft ? 'soft' : 'hard';
  console.info("js  [notice] "+jtype+"jump=" + chapter);
  // jump to chapter in bookdoc (contents.html)
  if(soft != 1) {
    bookdoc.onscroll = null;
    bookdoc.location.replace(('' + bookdoc.location).split('#')[0] + '#' + chapter);
    bookdoc.onscroll = OnScrollEvent;
  }
  // hilight chapter marker in TOC
  for (var i=0;i<chapters.length;i++) {
    var anchor = chapters[i][0];
    var button = document.getElementById(anchor);
    // this is the matching chapter
    if(anchor == chapter) {
      var ctop = tocdiv.scrollTop;
      var above = 50;
      var below = toccell.clientHeight-above;
      var offset  = chapters[i][3];
      // jump to chapter in toc
      if(
        ( offset < ( ctop + above ) ) ||
        ( offset > ( ctop + below ) )
      ) {
        var ntop = ( ( offset - above ) < 0 ) ? 0 : ( offset - above );
        var top;
        var a = (ntop > ctop) ? 1 : -1;
        tocdiv.scrollTop = ntop;
      }
      // set bg color
      button.style.backgroundColor ='yellow';
    }
    // this is some other chapter
    else {
      // restore bg color
      button.style.backgroundColor ='buttonface';
    }
  }
  // update address bar
  window.history.replaceState(undefined,undefined, '#' + chapter);
}

</script>
</head>
<body class="desktop" style="height:100%; overflow:hidden;" onload="OnPageLoad()">

<table id="table">
  <tbody style="height: 100%;">
    <tr class='zerorow'>
      <td class='zerocolumn'><span class='h2'>Contents</span></td> 
      <td class='bookcolumn'><span class='h1'><?=$title1?></span><br /><span class='h2'><?=$title2?></span></td>
    </tr>
    <tr class='growrow'>
      <td id='toccell' class='toccell'><div id='tocdiv' class='tocdiv'>
        <ul style='list-style-type:none;'>
<?php 
$clevel=0;
foreach($chapters as $chapter) {
  $oclevel=$clevel;
  list($anchor,$ctitle,$clevel) = $chapter;
  if($clevel > $oclevel) {
#    echo("        <ul style='list-style-type:none;'>\n");
  }
?>
        <li><button id='<?=$anchor?>'; style='width:100%; height:100%;' onclick="JumpToChapter('<?=$anchor?>', 0);return false;"><?=$ctitle?></button></li>
<?php
  if($clevel < $oclevel) {
#    echo("        </ul>\n");
  }
}
?>
        </ul>
      </div></td>
      <td class='bookcell'><iframe id="bookframe" class='bookframe' src="contents.html"></iframe></td>
  </tr>
  </tbody>
</table>

</body>
</html>
