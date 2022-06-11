<?php

function myencode($input) {
  $output = '';
  $offset = 0;
  while ($offset >= 0) {
	  $output .= ord_utf8($input, $offset);
    $output .= "-";
  }
  return $output;
}

function mydecode($input) {
  $input   = preg_replace('/-$/', '', $input);
  $output  = '';
  $splitin = explode("-",$input);

  for($i = 0; $i < count($splitin); $i++) {
    $char = floor($splitin[$i]);
    $output .= chr_utf8($char);
  }
  return $output;
}
///////////////////////////////////////////

function ord_utf8($string, &$offset) {
  $code = ord(substr($string, $offset,1)); 
  if ($code >= 128) {        //otherwise 0xxxxxxx
    if ($code < 224) $bytesnumber = 2;                //110xxxxx
    else if ($code < 240) $bytesnumber = 3;        //1110xxxx
    else if ($code < 248) $bytesnumber = 4;    //11110xxx
    $codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
    for ($i = 2; $i <= $bytesnumber; $i++) {
      $offset ++;
      $code2 = ord(substr($string, $offset, 1)) - 128;        //10xxxxxx
      $codetemp = $codetemp*64 + $code2;
    }
    $code = $codetemp;
  }
  $offset += 1;
  if ($offset >= strlen($string)) $offset = -1;
  return $code;
}

function chr_utf8($u) {
  return mb_convert_encoding('&#' . intval($u) . ';', 'UTF-8', 'HTML-ENTITIES');
}

function xor_php($to_dec) {
	$the_res = "";
	$xor_key = 13;
  
	$offset = 0;
	while ($offset >= 0) {
		$the_res .= chr_utf8($xor_key ^ ord_utf8($to_dec, $offset));
	}
	return($the_res);
}
?>