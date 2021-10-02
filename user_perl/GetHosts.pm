#!/usr/bin/perl

#####################################################
# set packagge name
package GetHosts;

# pragmas
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
  @EXPORT      = qw(get_apache_vhosts add_apache_vhosts call_php call_dyndns update_hosts get_data print_data);
}
our @EXPORT_OK;

# include modules
use FileHandle;
use Sys::Hostname;
use LWP::UserAgent;
use File::Copy;
use Digest::MD5 qw(md5 md5_hex md5_base64);
use Foo_Bar;
use Router::Actiontec;

# globals ############################################
our $Standard = [
  ['127.0.0.1',      'loopback',     'localhost'],
  ['x.x.x.x',        'router (wan)', 'cmnet'], # now done by the router via dyndns.whatwelove.org
  ['192.168.1.254',  'router (lan)', 'cmrouter'],
];
our $Static = [
  ['192.168.1.10',    'DOS',        'dosbox'],
  ['192.168.1.20',    'DOS',        'cpdos'],
  ['192.168.1.30',    'VAX',        'cpvax'],
  ['192.168.1.40',    'whatwelove clone',        'cs7'],
  ['192.168.123.101', 'Mesh',       'mesh'],
];
our $Friendly = {
  '745E1C232647'             => 'cmspeakers',
  'android-e36d9e799f230ad9' => 'cpmobile',
  'android-e2933388b3ae7a3e' => 'mhmobile',
};
our $Aliases = {
  'localhost'                => ['localhost.local'],
  'cmnet'                    => ['cmnet.whatwelove.org'],
};
add_apache_vhosts($Aliases);
our $PhpUrl    = 'https://www.whatwelove.org/pt/ip.php';
our $DynDnsUrl = 'https://dyndns.whatwelove.org/nic/update/?hostname=cmnet';
#######################################################

########################
# Get apache aliases
#
sub get_apache_vhosts {
  my $conf = '/etc/httpd/conf/extra/httpd-ssl.conf';
  my @vhosts = ();
  
  my $in = FileHandle->new($conf, "r");
  my(@lines) = $in->getlines;
  $in->close();
    
  for my $line (@lines) {
    if($line =~ /^ServerName ([\w.-]+):\d+/) {
      my $alias = $1;
      push (@vhosts , $alias)
    }
  }
  return @vhosts;
}

########################
# Add apache aliases
#
sub add_apache_vhosts {
  my($aliases) = @_;
  
  my $host = hostname();
  $aliases->{$host} = [];
  
  my @otheraliases = ();
  for my $key (keys %$aliases) {
    push(@otheraliases, @{$aliases->{$key}})
  }
  
  for my $vhost ( get_apache_vhosts() ) {
    push (@{$aliases->{$host}} , $vhost)
      unless($vhost eq $host or grep { $_ eq $vhost } @otheraliases);
  }
}

########################
# Call php to update externally stored ip
#
sub call_php {
  my($ext_ip) = @_;
  my $ret_ip;
  my($host,$file) = $PhpUrl =~ m,^\w+://([\w.]+)(/.*)$,;	
  
  # Create a user agent object
  my $ua = LWP::UserAgent->new;
  
  # create header object
  my $hea = HTTP::Headers->new(
    User_Agent => 'Wget/1.15 (cygwin)',
    Accept     => '*/*',
    Host       => $host,
    Connection => 'Keep-Alive',
  );
  my($usr,$pas)= foo("$host:web"); 
  $hea->authorization_basic($usr, $pas);

  # Create a request
  my $req = HTTP::Request->new('GET', $PhpUrl."?ip=".$ext_ip, $hea);

  # Pass request to the user agent and get a response back
  my $res = $ua->request($req);

  # Check the outcome of the response
  if (!$res->is_success) {
    die( "cannot connect to $host" . $res->status_line, "\n");
  }
  unless( $ret_ip = ($res->content =~ /ip=(\d+(?:\.\d+)+)/)[0] ) {
    die( "failed to get ip\n");
  }
  unless ($ret_ip eq $ext_ip) {
    die( "ip mismatch: $ext_ip != $ret_ip\n");
  }
#  $name = (`dig -x $ret_ip +short`)[0];
#  $name =~ s/\W+$//;
}

########################
# Call dyndns to update externally stored ip
#
sub call_dyndns {
  my($ext_ip) = @_;
  my $ret_ip;
  my $ret_code;
  my($host,$file) = $DynDnsUrl =~ m,^\w+://([\w.]+)(/.*)$,;	
  
  # Create a user agent object
  my $ua = LWP::UserAgent->new;
  $ua->ssl_opts( verify_hostname => 0 ); # ignore expired cert
  
  # create header object
  my $hea = HTTP::Headers->new(
    User_Agent => 'Wget/1.15 (cygwin)',
    Accept     => '*/*',
    Host       => $host,
    Connection => 'Keep-Alive',
  );
  my($usr,$pas)= foo("$host:web"); 
  $hea->authorization_basic($usr, $pas);

  # Create a request
  my $req = HTTP::Request->new('GET', $DynDnsUrl, $hea);

  # Pass request to the user agent and get a response back
  my $res = $ua->request($req);

  # Check the outcome of the response
  if (!$res->is_success) {
    die( "cannot connect to $host" . $res->status_line, "\n");
  }
  ($ret_code,$ret_ip) = ($res->content =~ /^(\w+) (\d+(?:\.\d+)+)/) ;
  unless( $ret_code =~ /^(good|nochg)$/) {
    die( "failed to update dns: $ret_code\n");
  }
  unless ($ret_ip eq $ext_ip) {
    die( "ip mismatch: $ext_ip != $ret_ip\n");
  }
}

########################
# Update /etc/hosts file
#
sub update_hosts {
  my(@lan) = @_;
	
  chdir("/cygdrive/c/Windows/System32/drivers/etc/");
  copy("hosts","hosts.new");
  copy("hosts","hosts.bak");
  open(FH, "< hosts.new");
  my @lines = <FH>;
  close(FH);
  open(FH, "> hosts.new");

  # print header
  print FH <<"_EOF_";
#! This file contains the mappings of IP addresses to host names. Each\r
#! entry should be kept on an individual line. The IP address should\r
#! be placed in the first column followed by the corresponding host name.\r
#! The IP address and the host name should be separated by at least one\r
#! space.\r
#!\r
#!IP Address     Host Name       # Note          OS      MAC Address\r
_EOF_

  # print discovered
  for my $lan (@lan) {
    my($ip, $mac, $name) = @$lan;
    my $t = (length($name) < 8) ? "\t\t" : "\t";
    print FH "$ip\t$name$t# $mac\r\n";
  }
  
  # print unaltered
  print "------unaltered lines------\n";
  for my $line (@lines) {
    if($line =~ /^([^#]\S+)\s+(\S+).*?#\s*(.*)[\s]*$/) {
      my($ip,$name,$comment) = ($1,$2,$3);
      next if(grep { 
        $_->[0] eq $ip      ||
        $_->[1] eq $comment ||
        $_->[2] =~ /^$name(?:\s+.*)?$/
      } @lan);
    }
    # write to file
    print FH $line unless($line =~ /^\#\!/);
    # print to screen
    print    $line unless($line =~ /^\#|^\s*$|^0\.0\.0\.0/);
  }
  close(FH);
  move("hosts.new", "hosts");
}


########################
# Get data from router
#
sub get_data {
  my $router = (map { $_->[1] eq 'router (lan)' ? $_->[0] : () } @$Standard)[0];
  my($usr,$pas)= foo("$router:Actiontec"); 
  my $Actiontec = Router::Actiontec->new($router, $pas);
  my @lan = ();

  # get wan data
  my $wan_data = $Actiontec->get_wan_data();
  
  # get lan data
  my $lan_data = $Actiontec->get_lan_data();

  # 1. standard
  for my $standard (@$Standard) {
    if($standard->[1] eq 'router (wan)') {
      $standard->[0] = $wan_data->{IP};
    }
    push(@lan, $standard);
  }
  
  # 2. static
  push(@lan, @$Static);
  
  # 3. discovered
  push(@lan, @{$lan_data->{CLIENTS}});
  
  #   add friendly & aliases
  for my $lan (@lan) {
    # friendly
    if(exists $Friendly->{$lan->[2]}) {
      $lan->[2] = $Friendly->{$lan->[2]}
    }
    # aliases
    if(exists $Aliases->{$lan->[2]}) {
      for my $alias (@{$Aliases->{$lan->[2]}}) {
        $lan->[2] .= " " . $alias;
      }
    }
  }
  
  return($wan_data->{IP}, @lan);
}

#########################
# Print data to console
#
sub print_data {
  my($ext_ip, @lan) = @_;

  print "ext_ip = $ext_ip\n";
  print "------discovered hosts------\n";
  for my $lan (@lan) {
    my($ip, $mac, $name) = @$lan;
    my $t = (length($name) < 8) ? "\t\t" : "\t";
    print "$ip\t$name$t# $mac\n";
  }
}

########################
# end with success
1;

#####################################################

__END__

#IP Address	Host Name	# Note		OS	MAC Address
127.0.0.1	localhost	# loopback      -
192.168.0.1	cmrouter	# D-Link DIR655	-	00:1c:f0:b8:cd:51
192.168.0.189	cpmobile	# Galaxy S4	Android	cc:3a:61:0e:b3:32
192.168.0.190	cmspeakers	# Pioneer Spks	-	
192.168.0.191	cpmacos		# cmlaptop VM	Mac OS  (vmware)	
192.168.0.193	cplnx		# cmlaptop VM	Linux   (vmware)
192.168.0.195	cmdesktop2	# Asus M11BB	Win 8	74:d0:2b:98:aa:90
192.168.0.196	cmlaptop	# Dell Vostro	Win 7	4c:eb:42:13:c8:51
192.168.0.197	cmprinter	# Okidata	-	00:80:87:d4:15:5b
192.168.0.198	cpwin98		# cmlaptop VM	Win 98  (ms vpc) - DUP
192.168.0.60	cpvax		# cmlaptop VM           (simh)
192.168.0.80	cpdos		# cmlaptop VM           (vmware)
64.180.144.192	d64-180-144-192.bchsia.telus.net cmnet  # cmrouter external

MISSING
192.168.0.192	cpwinxp		# cmlaptop VM	Win XP  (ms vpc) - DUP
192.168.0.192	cpwinxp		# cmlaptop VM	Win XP  (vmware)
192.168.0.???	cpwin98		# cmlaptop VM	Win 98  (vmware)	
192.168.0.???	cpwin3		# cmlaptop VM	Win 3   (vmware)
192.168.0.???	cpmac68k	# cmlaptop VM   System7 (basilisk)

NO NET
                cpm		# cmlaptop VM	cp/m     (applewin)
                apple2		# cmlaptop VM	apple2   (applewin)
                apple2gs	# cmlaptop VM	apple2   (activegs)
                dosbox		# cmlaptop VM	dos      (dosbox)
                
~~~~~~~~~~~~~~~~~~~~~~~

Wireless-G Router (laptop router)
  manufacturer: Linksys
  MAC:          00:1e:e5:60:cc:6b
  IP:           (none)


Xtreme N GIGABIT Router (cmrouter)
  manufacturer:  D-Link
  MAC:           00:1c:f0:b8:cd:51
  IP:            192.168.0.1


