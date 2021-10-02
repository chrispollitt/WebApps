#!/bin/echo Must_be_used:
#
# FooBar module
#

#####################################################
# set packagge name
package Foo_Bar;

# pragmas
use v5.10;
use strict;
use warnings;

BEGIN {
  use Exporter   ();
  our ($VERSION, @ISA, @EXPORT, @EXPORT_OK, %EXPORT_TAGS);
  @ISA         = qw(Exporter);
  
  # set the version for version checking
  $VERSION     = 1.00;
  # named collections of vars & subs to be EXPORT_OK'ed as a group
  %EXPORT_TAGS = ( );
  # manually exported on user request
  @EXPORT_OK   = qw( );
  # auto exported
  @EXPORT      = qw(foo bar);
}
our @EXPORT_OK;

use File::Basename;

our $fb_path = dirname(dirname(__FILE__)) . "/user_php";

########################
# get value
#
sub foo {
  # return values
  my @o;
  chomp(@o = qx,php-cli $fb_path/Foo_Bar_w.php "$_[0]",);
  return(@o);
}

########################
# set value
#
sub bar {
  system(qq,php-cli $fb_path/Foo_Bar_w.php "$_[0]" "$_[1]" "$_[2]",);
  # return status
  return ($? == 0);
}

##########################
# Return true
1;

__END__

==> t.sh <==
echo "I="$SHELL
echo "0="$0

==> t.js <==
console.log('I='+process.argv[0]);
console.log('0='+process.argv[1]);

==> t.pl <==
print "I=" . $^X . "\n";
print "0=" . $0  . "\n";

==> t.php <==
echo "I=" . PHP_BINARY . "\n";
echo "0=" . $argv[0]   . "\n";

==> t.py <==
import sys
print sys.executable
print sys.argv[0]
