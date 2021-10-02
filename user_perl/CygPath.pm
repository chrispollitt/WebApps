#!/bin/echo Must_be_used:
#
# CygPath module
#

#####################################################
# set packagge name
package CygPath;

# pragmas
use Modern::Perl '2018';
use experimental 'signatures';
use strict;
use warnings; no warnings  'experimental';

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
  @EXPORT      = qw(win2cyg cyg2win);
}
our @EXPORT_OK;

sub win2cyg ($path) {
  my ($package, $filename, $line) = caller;
  if($path =~ m,[/], or $path =~ m,\bcygdrive\b,i) {
    warn("path already in cyg format at $filename line $line\n");
    return($path);
  }
  $path =~ s,\\,/,g;
  $path =~ s,(^|;)([a-z]):/,$1/cygdrive/$2/,ig;
  $path =~ s,/cygdrive/[cd]/cygwin/,/,ig;
  $path =~ s,;,:,g;
  return $path;
}

sub cyg2win ($path) {
  my ($package, $filename, $line) = caller;
  if($path =~ m,[\\], or $path =~ m,(^|;)([a-z]):/,i) {
    warn("path already in win format at $filename line $line\n");
    return($path);
  }
  $path =~ s,:,;,g;
  $path =~ s,/cygdrive/([a-z])\b/,$1:/,ig;
  $path =~ s,(^|;)/,${1}c:/cygwin/,ig;
  $path =~ s,/,\\,g;
  return $path;
}

1;

__END__

perl -MCygPath -e 'print "p=(".cyg2win($ARGV[0]).")\n"' '/cygdrive/c/foo/bar.txt:/cygdrive/z/baz/fuz.pdf:/tmp/ree.doc'
p=(c:\foo\bar.txt;z:\baz\fuz.pdf;c:\cygwin\tmp\ree.doc)

perl -MCygPath -e 'print "p=(".win2cyg($ARGV[0]).")\n"' 'c:\foo\bar.txt;z:\baz\fuz.pdf;c:\cygwin\tmp\ree.doc'
p=(/cygdrive/c/foo/bar.txt:/cygdrive/z/baz/fuz.pdf:/tmp/ree.doc)
