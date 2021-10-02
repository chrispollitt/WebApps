#!/usr/bin/perl
#
#
package html2text;

# pragmas
use strict;
use warnings;

# set package vars
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
    @EXPORT      = qw(html2text);
}
our @EXPORT_OK;

# include modules
use HTML::FormatText::Html2text;
############################
sub html2text {
  my($in) = @_;
  my $out = HTML::FormatText::Html2text->format_string($in);
  return $out;
}
############################
1; 
