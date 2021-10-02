# Copyright 1997-2001, Paul Johnson (pjcj@cpan.org)

# This software is free.  It is licensed under the same terms as Perl itself.

# The latest version of this software should be available from my homepage:
# http://www.pjcj.net

use strict;

require 5.004;

package Shell_Source;

use vars qw($VERSION);

$VERSION = "1.00"; # Hacked by CWP

use Carp;
use FileHandle;

my $shells =
{
    csh  => q!csh -f -c 'source "[[file]]"; env' |!,
    tcsh => q!tcsh -f -c 'source "[[file]]"; env' |!,
    sh   => q!sh -c '. "[[file]]"; env' |!,
    ksh  => q!ksh -c '. "[[file]]"; env' |!,
    zsh  => q!zsh -c '. "[[file]]"; env' |!,
    bash => q!bash -norc -noprofile -c '. "[[file]]"; env' |!,
    cmd  => q!cmd /v:on /c "[[file]] & set" |!,
#   cmd  => q!cmd /v:on /c "^"[[file]]^" & set" |!,
#   cmd  => q!cmd /v:on /c '"^"[[file]]^" & set"' |!,
#   cmd  => q!cmd /v:on /c '"[[file]]" & set' |!,
};

sub new
{
    my $class = shift;
    my $self = { @_ };
    croak "Must specify type of shell" unless $self->{shell};
    $self->{run} ||= $shells->{$self->{shell}};
    croak "Must specify how to run unknown shell $self->{shell}"
        unless $self->{run};
    push @{$self->{ignore}}, qw( 
      COLUMNS
      LINES
      PROMPT
      SHLVL
      HOME
      TEMP
      TMP
      TMPDIR
      TIMEFMT
      PWD
      _
    );
    bless $self, $class;
    $self->run if length $self->{file};
    $self
}

sub run
{
    my $self = shift;
#    my $file = shift || $self->{file};
    croak "Must specify file to source" unless length $self->{file};
	if($self->{shell} eq 'cmd' and $self->{file} =~ m,/,) {
      $self->{file} =~ s,^/,c:/cygwin/,i;
	  $self->{file} =~ s,c:/cygwin/cygdrive/c/,c:,i;
      $self->{file} =~ s,/,\\,g;
	}
    (my $run = $self->{run}) =~ s/\[\[file\]\]/$self->{file}/g;
    my $fh = $self->{fh}
           = FileHandle->new($run) or croak "Can't run $self->{shell}";
    $self->_parse;
    $fh->close or croak "Can't close $self->{shell}";
    $self
}

sub _parse
{
    my $self = shift;
    my $fh = $self->{fh};                         # FileHandle ready for reading
    my $env = 0;                           # for control of multi-line variables
    while (defined(my $line = <$fh>))
    {
        if ($line =~ /^(\w+)=(.*?)\r?$/)
        {
            my($var,$val) = ($1,$2);
            if(defined $ENV{$var} && $ENV{$var} =~ m,^/,) {
              $val =~ s,\\,/,g;
              $val =~ s,\b([a-z]):/,/cygdrive/$1/,ig;
              $val =~ s,/cygdrive/[cd]/cygwin/,/,ig;
              $val =~ s,;,:,g;
            }
            $env = 1;
            if ((!defined $ENV{$var} || $ENV{$var} ne $val) &&
                !grep {$var eq $_} @{$self->{ignore}})
            {
                $self->{env}{$var} = $val;
            }
        }
        else
        {
            push (@{$self->{output}}, $line) unless $env;
        }
    }
    $self
}

sub inherit
{
    my $self = shift;
    while (my ($key, $val) = each (%{$self->{env}}))
    {
        $ENV{$key} = $val;
    }
}

sub shell
{
    my $self = shift;
    my $shell = "";
    while (my ($key, $val) = each (%{$self->{env}}))
    {
        $shell .= qq($key="$val"; export $key\n);
    }
    $shell
}

sub output
{
    my $self = shift;
	if (defined $self->{output}) {
      my $out = join("\n", @{$self->{output}});
	  $out =~ s/[\r\n]+/\n/sg;
      return $out;
	}
}

sub env
{
    my $self = shift;
    $self->{env}
}

1;

__END__

=head1 NAME

Shell::Source - run programs and inherit environment changes

=head1 SYNOPSIS

 use Shell::Source;
 my $csh = Shell::Source->new(shell => "csh", file => "stuff.csh");
 $csh->inherit;
 print STDERR $csh->output;
 print $csh->shell;

=head1 DESCRIPTION

The Shell::Source allows arbitrary shell scripts, or other programs for
that matter, to be run and their environment to be inherited into a Perl
program.

Begin by creating a Shell::Source object, and specifying the shell it
will use.

If the shell is unknown to the module, you will also need to specify how
to run the shell in such a way that the output is a series of lines of
the form NAME=value.  For example, to run a csh script:

 my $csh = Shell::Source->new(shell => "csh",
                              file  => "stuff.csh",
                              run   => "csh -f -c 'source [[file]]; env' |");

However, for known shells this is not required.  Note that [[file]] will
be replaced with the filename of the program you want to run.

Output from running the program is returned from $csh->output.

Changes made to the environment by running the program may be inherited
by calling $csh->inherit.

The environment changes are available as a hash from $csh->env, or in
Bourne shell syntax from $csh->shell.

=head1 BUGS

Huh?

=head1 VERSION

Version 0.01 - 2nd August 2001

=head1 HISTORY

Created - Wednesday 26th November 1997 09:29:31 pm

=head1 LICENCE

Copyright 1997-2001, Paul Johnson (pjcj@cpan.org)

This software is free.  It is licensed under the same terms as Perl itself.

The latest version of this software should be available from my homepage:
http://www.pjcj.net

=cut
