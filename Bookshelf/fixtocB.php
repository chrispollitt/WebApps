<?php

error_reporting(E_STRICT);
mb_internal_encoding("UTF-8");

#################################################
# TODO:
#  * www.xxx.com      --> <a href="http://www.xxx.com
################################################

# logging
$logto = "terminal";
require_once('logerrors.php');

# save backup
if(file_exists( 'contents.html~')) {
  unlink('contents.html~');
}
copy('contents.html', 'contents.html~');

# read contents.html
$contents    = file_get_contents('contents.html~');

$newcontents = "";
$conarray    = preg_split ( '~\<a~i', $contents);

# fix doctype
$conarray[0] = preg_replace('~\<(\!DOCTYPE|html|head|meta)[^>]*\>~i', '', $conarray[0]);
$head = <<<_HEAD_
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
_HEAD_;
$conarray[0] = $head . $conarray[0];

# fix cover image

$firstP  = '\<body\>\<div[^>]*\>';
$first   = '<body><div class="calibre" id="calibre_link-268">';
$secondP = '\<p[^>]*\>\<img[^>]*src="[^"]+\.jpg"[^>]*/\>\</p\>';
$second  = '<p class="calibre_"><img src="images/000002.jpg" class="calibre_1" /></p>';
$third   = '<p class="calibre_"><img src="cover.jpg" alt="Cover" style="width:100%;" /></p>';
$fourth = '<div class="mbp_pagebreak"></div>';
# --exists
if    ( preg_match("~$firstP$secondP~i", $conarray[0]) ) {
  $logger->log("cover fixed");
  $conarray[0] = preg_replace("~$firstP$secondP~i", "$first$third", $conarray[0]);
  # assume $fourth is already there
}
# --does not
elseif( preg_match("~$firstP~i", $conarray[0]) ) {
  $logger->log("cover added");
  $conarray[0] = preg_replace("~$firstP~i", "$first$third$fourth", $conarray[0]);
}
# --problem!
else {
  $logger->log("warning: '<body><div ...' not standard, no cover inserted!");
}

# get chapters
$level = 0;
$leveled = 0;
$consize = count($conarray);

# handle first line
$line = $conarray[0];
$level += preg_match_all('~\<[ou]l~i' , $line);
$level -= preg_match_all('~\</[ou]l~i', $line);
$newcontents .= $line;

# loop over rest
for ($i=1; $i<$consize; $i++) {
  # get line
  $line = $conarray[$i];
  # add stripped "<a"
  $line = "<a" . $line;
  # split on newline
  $lines = preg_split('~[\r\n]+~', $line);
  # get chapter names and anchors
  $anchor  = preg_filter('~^.*?href="#([^"]+)".*$~i', '$1', $lines[0]);
  $chapter = preg_filter('~\<[^>]+\>~', '', $lines[0]);
  $chapter = trim($chapter);
  # non-legit chapter/anchor
  if (strlen($chapter)==0 || !preg_match('~^[a-z0-9_-]+$~i', $anchor)) {
    $logger->log("skipping link: " . substr($lines[0],0,25));
  }
  # ok
  else {
    # legit chapter
    if($level || preg_match('~^(preface|introduction|foreword|notes|prologue|acknowledge?ments|appendix(?:\s+\w)?|afterward|epilogue|conclusion|postscript|endnotes|bibliography|glossary|about.the.author|chapter|\d)~i', $chapter)) {
      # found chapter
      $logger->log("anchor='$anchor' name='$chapter'");
      # strip off "Charter " text
      $chapter = preg_replace('~chapter +~i', '', $chapter);
      # save info         0       1        2
      $chapters[] = array($anchor,$chapter,$level);
      if ($level) {
        $leveled = 1;
      }
      # join number to title       $1        $2      $3      $4
      $newline = preg_replace('~(\<a.*?\>)([^<]+)(\<.*?\>)([^<]+)~i', '$1$2 $4$3', $lines[0]);
      $newline = preg_replace('~ +~', ' ',  $newline);
      $lines[0] = $newline;
      $logger->log("fixed: " . $newline);
    }
    # part
    elseif(preg_match('~^(dedication|part|book|section)~i', $chapter)) {
      $logger->log("found section, skipping: " . $chapter);
    }
    # non-legit chapter
    else {
      $logger->log("found other link, bailing: " . $chapter);
      $newcontents .= implode("\n", $lines);
      $newcontents .= "<a" . implode("<a", array_slice($conarray, $i+1, $consize-1));
      break;
    }
  }
  $newcontents .= implode("\n", $lines);
  # get level
  $level -= preg_match_all('~\</[ou]l~i', $line);
  if($leveled && $level==0) {
    $logger->log("end of top level list, bailing: " . $chapter);
      $newcontents .= "<a" . implode("<a", array_slice($conarray, $i+1, $consize-1));
    break;
  }
  $level += preg_match_all('~\<[ou]l~i' , $line);
}

# fix external links
$newcontents = preg_replace('~href="(http[^"]+)"~i', 'href="#" onclick="parent.location=\'https://href.li/?$1\';return false;"', $newcontents);

# fix internal links
$newcontents = preg_replace('~href="#(\w[^"]+)"~i', 'href="#" onclick="parent.JumpToChapter(\'$1\', 0);return false;"', $newcontents);

# get new chapter anchor names
$contents = $newcontents;
$i=1;
$j=1;
# #    BEFORE    AFTER   
# 0    Orig_anc  New_anc
# 1    Name      Name
# 2    Level     Level
# 3    -         Orig_anc
# 4    -         Chapt#
foreach ($chapters as $index => $chapter) {
  $chapters[$index][3] =  $chapters[$index][0];
  # known chapter name
  if(preg_match('~^(dedication|preface|introduction|foreword|notes|prologue|acknowledge?ments|appendix(?:\s+\w)?|afterward|epilogue|conclusion|postscript|endnotes|bibliography|glossary|about.the.author)~i', $chapter[1], $match)) {
    $chapters[$index][0] = strtolower(preg_replace('~\s+~', '',$match[1]));
    # continue chapter numbering
    # chapter
    if($chapters[$index][2] < 2) {
      $chapters[$index][4] = 0;
    }
    # subchapter
    else {
      $chapters[$index][4] = $j;
      $j++;
    }
  }
  # generic chapter name
  else {
    # chapter
    if($chapters[$index][2] < 2) {
      $chapters[$index][4] = $i;
      $chapters[$index][0] = 'chapter' . $i;
      $i++;
    }
    # sub chapter
    else {
      $chapters[$index][4] = $j;
      $chapters[$index][0] = 'subchapter' . $j;
      $j++;
    }
  }
  $logger->log($chapters[$index][3] . " -> " . $chapters[$index][0]);
}

# update anchors in text
foreach ($chapters as $index => $chapter) {
  # get parts
  $new = $chapter[0];
  $nam = $chapter[1];
  $lvl = $chapter[2];
  $old = $chapter[3];
  $num = $chapter[4];
  # update anchor
  $contents = preg_replace("~\b$old\b~", $new, $contents);
  # shorten name
  #list($left,, $right) = imageftbbox( 12, 0, arial.ttf, $nam);
  #$width = $right - $left;
  if(mb_strlen($nam) > 55) {
    $nam = mb_substr($nam, 0, 55) . "...";
    $chapters[$index][1] = $nam;
  }
  # update subchapter name if missing number
  if($lvl > 1 && !preg_match("~^\d+~", $nam)) {
    $chapters[$index][1] = $num . " " . $nam;
  }
}

# write to new file
if(strlen($contents)>0) {
  # remove old
  unlink('contents.html');
  # write new
  file_put_contents('contents.html', $contents);
  # write TOC file
  file_put_contents('toc.dat', serialize($chapters));
}
else {
  $logger->log("error: contents empty, bailing!");
}


