#!/usr/bin/perl
#
# CWP module
#

#
# To do:
#  * fix recursion
#  * allow full paths
#
# $pren.pl -gipn 'twentyfifteen' 'cwp2015' .
#   twentyfifteen/languages/twentyfifteen.po => twentyfifteen/languages/cwp2015.pot     
#   twentyfifteen                            => cwp2015                                 
# $pren.pl -gripn 'twentyfifteen' 'cwp2015' .
#   twentyfifteen/languages/twentyfifteen.po => twentyfifteen/languages/cwp2015.pot     

#####################################################
# set packagge name
package CWP;

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
    @EXPORT      = qw(psub pren psubren);
}
our @EXPORT_OK;

# include modules
use FileHandle;
use File::Glob qw(bsd_glob);
use File::Find;
use File::Basename;
use Carp;

############################
# Find files
our %find_cache = ();
sub find_files {
  my($targ, $flgs, $type) = @_;
  
  if(! exists $find_cache{$targ}) {
    finddepth(
      {
        no_chdir => 1,
        wanted   => sub {
          my $path = $File::Find::name;
          if ($path !~ m,/\., and $path !~ m,^\.[^/]*$, and $path !~ m,\~$,) {
            if(-d $path) {
              $path .= "/";
            }
            push(@{$find_cache{$targ}}, $path);
          }
        }
      },
      $targ
    );    
  }
  if ($type eq 'f') {
    return grep {! m,/$,} @{$find_cache{$targ}};
  }
  elsif($type eq 'd') {
    return grep {  m,/$,} @{$find_cache{$targ}};
  }
  else {
    return @{$find_cache{$targ}};
  }
}

############################
# Edit file
sub edit_file {
  my($targ, $regex, $repla, $flgs) = @_;
  
  # binary file?
  if(-B $targ) {
    print STDERR "info: skipping binary file: $targ\n";    
    return;
  }
  
  # read
  my $in  = FileHandle->new($targ, "r");
  my(@lines) = $in->getlines;
  $in->close();
  my @oldlines = @lines;
  # modify
  map { $_ = edit_string($_, $regex, $repla, $flgs) } @lines;
  # changed?
  if(join("",@lines) ne join("",@oldlines)) {
    # dry run
    if ($flgs->{n}) {
      print "match: $targ\n";
      for (my $i=0; $i <= $#lines; $i++) {
        if($oldlines[$i] ne $lines[$i]) {
          chomp($oldlines[$i]);
          chomp($lines[$i]);
          $oldlines[$i] =~ s/\t/        /g;
          $lines[$i]    =~ s/\t/        /g;
          if ($flgs->{n} == 2) {
            printf('%4d: %-80.80s%s', $i, $oldlines[$i], "\n");
          }
          else {
            printf('%4d: %-80.80s => %-80.80s%s', $i, $oldlines[$i], $lines[$i], "\n");
          }
        }
      }
    }
    # change it
    else {
      print "modifying: $targ\n";
      # backup
      rename($targ, "${targ}~");
      # write
      my $out = FileHandle->new($targ, "w");
      map { print $out $_ } @lines;
      $out->close();
    }
  }
}


############################
# preserve case
sub preserve_case {
  my ($old, $new) = @_;
  my $mask = uc $old ^ $old;
  my $diff = length($new) - length($old);
  if($diff > 0) {
    $mask .= substr($mask, -1) x ($diff);
  }
  my $new2 = uc $new | $mask;
  return substr($new2, 0, length($new));
}

############################
# substitute on string
sub edit_string {
  my($strin, $regex, $repla, $flgs) = @_;
  my $strou;
  my $opts = "";

  if($flgs->{i}) {
    $opts .= "i";
  }
  if(length($opts)) {
    $opts="(?$opts)";
  }
     
  $repla = q/sprintf('%s', "/ . $repla . q/")/;

  while($strin =~ /$opts$regex/p) {
    my($pre, $matchin, $post) = (${^PREMATCH}, ${^MATCH}, ${^POSTMATCH});
    my $matchou = $matchin;
    $matchou =~ s/$opts$regex/$repla/ee;
    if($flgs->{p}) {
      $matchou = preserve_case($matchin, $matchou);
    }
    $strin = $pre . $matchou . $post;
    last unless($flgs->{g});
  }
  $strou = $strin;
    
  return $strou;
}

############################
# rename files
sub renameifdiff {
  my($old, $new, $flgs) = @_;
  $old =~ s,^\./|/$,,g;
  $new =~ s,^\./|/$,,g;
  if($old ne $new) {
    if($flgs->{n} == 2) {
      printf('%-80.80s%s', $old, "\n");
    }
    elsif($flgs->{n}) {
      printf('%-40.40s => %-40.40s%s', $old, $new, "\n");
    }
    else {
      printf('%-40.40s => %-40.40s%s', $old, $new, "\n");
      rename($old, $new);
    }
  }
}

############################
# edit files in place
sub psub {
  my($targ, $regex, $repla, $flgs) = @_;

  # fix first file name
  if ( defined $targ->[0] and $targ->[0] eq '.') {
    if( $flgs->{r} ) {
      $targ->[0] = '*';
    }
    else {
      shift @{$targ};
      unshift(@{$targ}, bsd_glob('*'));
    }
  }
    
  # loop over files
  for my $file (@{$targ}) {
    # check for illegal chars in file xxx
    if ($file =~ m,/,) {
      print STDERR "error: skipping file with '/': $file\n";    
      next;
    }
    # file
    if ( -f $file  and !$flgs->{r} ) {
      my $file2 = $file;
      # do it
      edit_file($file2, $regex, $repla, $flgs);
    # recurse
    } elsif ( $flgs->{r} ) {
      # look for special glob chars
      if($file !~ /[*?{[]/) {
        print STDERR "warning: recursion on but no glob chars found: $file\n";
      }
      # set root
      my $root = ".";
      # loop over tree
      for my $file2 (find_files($root, $flgs, "f")) {
        # recursive glob
        my $dir = dirname($file2);
        my $glob = (grep {$_ eq $file2} bsd_glob("$dir/$file"))[0];
        if( !defined $glob or ! -f $glob) {
          next;
        }
        # do it
        edit_file($file2, $regex, $repla, $flgs);
      }
    # not found
    } else {
      if(! -e $file) {
        print STDERR "error: no such target: $file\n";
      }
      else {
        print STDERR "warning: recursion not on, skipping: $file\n";
      }
    }
  }
}

############################
# rename files
sub pren {
  my($targ, $regex, $repla, $flgs) = @_;

  # fix first file name
  if ( defined $targ->[0] and $targ->[0] eq '.') {
    if( $flgs->{r} ) {
      $targ->[0] = '*';
    }
    else {
      shift @{$targ};
      unshift(@{$targ}, bsd_glob('*'));
    }
  }
    
  # loop over files
  for my $file (@{$targ}) {
    # check for illegal chars in file xxx
    if ($file =~ m,/,) {
      print STDERR "error: skipping file with '/': $file\n";    
      next;
    }
    # exists
    if ( -e $file and !$flgs->{r} ) {
      my $file2 = $file;
      # do it
      my $new = dirname($file2) ."/". edit_string(basename($file2), $regex, $repla, $flgs);
      renameifdiff($file2, $new, $flgs);
    # recurse
    } elsif ( $flgs->{r} ) {
      # look for special glob chars
      if($file !~ /[*?{[]/) {
        print STDERR "warning: recursion on but no glob chars found: $file\n";
      }
      # set root
      my $root = ".";
      # do files first
      for my $file2 (find_files($root, $flgs, "f")) {
        # recursive glob
        my $dir = dirname($file2);
        my $glob = (grep {$_ eq $file2} bsd_glob("$dir/$file"))[0];
        if( !defined $glob or ! -f $glob) {
          next;
        }
        # do it
        my $new = dirname($file2) ."/". edit_string(basename($file2), $regex, $repla, $flgs);
        renameifdiff($file2, $new, $flgs);
      }
      # then do dirs (deepest first)
      for my $file2 (find_files($root, $flgs, "d")) {
        $file2 =~ s,/$,,;
        # recursive glob
        my $dir = dirname($file2);
        my $glob = (grep {$_ eq $file2} bsd_glob("$dir/$file"))[0];
        if( !defined $glob or ! -e $glob) {
          next;
        }
        # do it
        my $new = dirname($file2) ."/". edit_string(basename($file2), $regex, $repla, $flgs);
        renameifdiff($file2, $new, $flgs);
      }
    # not found
    } else {
      if(! -e $file) {
        print STDERR "error: no such target: $file\n";
      }
      else {
        print STDERR "warning: recursion not on, skipping: $file\n";
      }
    }
  }
}

############################
# Rename and edit in place
sub psubren {
  my($targ, $regex, $repla, $flgs) = @_;

  psub($targ, $regex, $repla, $flgs);
  pren($targ, $regex, $repla, $flgs);
}

#####################################################
# back to main package
package main;

# pragmas
use strict;
use warnings;

# include modules
use Getopt::Std;
use File::Basename;
#use CWP;

# set package vars
our $VERSION = '1.0';
our $me = basename($0);

############################
# usage
sub HELP_MESSAGE {
  print <<_EOF_;
Usage:
_EOF_
  if($me =~ /p(find|grep).pl/) {
    print "  $me [<flags>]    '<regex>' <paths>\n";
    print "  $me [<flags>] -r '<regex>' '<glob>'\n";
  } else {
    print "  $me [<flags>]    '<regex>' '<replacement>' <paths>\n";
    print "  $me [<flags>] -r '<regex>' '<replacement>' '<glob>'\n";
  }
  print <<_EOF_;
Where:
  -g = globally replace each line
  -i = ignore case
  -n = dry run (show what would happen)
  -p = preserve case
  -r = recurse into sub dirs (use quoted '<glob>')
  '<regex>'       = the perl5 regex
  '<replacement>' = the replacement string
   <paths>        = the paths to operate on
  '<glob>'        = quoted glob (use with -r)
_EOF_
  exit(1);
  
  # -#   print line number
  # -?   show help
  # -c   print match count
  # -g = globally replace each line@
  # -H   omit filename
  # -h   print filename only
  # -i = ignore case*
  # -L   print filename only (non matches)
  # -l   print filename only
  # -n = dry run (show what would happen)
  # -p = preserve case*
  # -r = recurse into sub dirs (use quoted '<glob>')
  # -v   invert match* 
  # 
  # ( *  filename, contents, or both? )
  # ( @  {s/x1/x2/g} or {1 while(s/x1/x2/)} )
}

############################
# main
sub psubren_main {
  my %flgs;
  $flgs{n} = 0;
  
  getopts('ginpr?', \%flgs) or HELP_MESSAGE();

  HELP_MESSAGE() if($flgs{'?'});
  
  if($me =~ /p(find|grep).pl/) {
    $flgs{n} = 2 ;
    HELP_MESSAGE() unless($#ARGV >= 1);
  } else {
    HELP_MESSAGE() unless($#ARGV >= 2);
  }
  
  my $regex = shift @ARGV;
  my $repla = $me =~ /p(find|grep).pl/ ? '' : shift @ARGV;
  my $targ  = \@ARGV;
  
  if($me =~ /p(sub|grep).pl/) {
    psub($targ, $regex, $repla, \%flgs);
  }
  elsif($me =~ /p(ren|find).pl/) {
    pren($targ, $regex, $repla, \%flgs);
  }
  elsif($me =~ /p(subren|grepfind).pl/) {
    psubren($targ, $regex, $repla, \%flgs);
  }
  else {
    print STDERR "illegal name: $me\n";
    exit(1);    
  }
}

##########################
# Return true
1;
