<?php

require_once("Foo_Bar.php");

if($argc == 4) {
  $result = bar($argv[1],$argv[2],$argv[3]);
  exit(!$result);
}
elseif($argc == 2) {
  list($three, $two) = foo($argv[1]);
  echo("$three\n$two\n");
}
else {
  echo("failed\nfailed\n");
}

?>