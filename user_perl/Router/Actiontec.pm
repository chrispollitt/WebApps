#!/usr/bin/perl
#
# Actiontec Router module
#
# perl -d -MFoo_Bar -MActiontec -e '$n=q{192.168.1.254};$r=Actiontec->new($n,(foo($n.":Actiontec"))[1]);$r->reboot()'
# perl    -MFoo_Bar -MActiontec -e '$n=q{192.168.1.254};$r=Actiontec->new($n,(foo($n.":Actiontec"))[1]);$r->getlog()'

#####################################################
# set packagge name
package Router::Actiontec;

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
    @EXPORT      = qw();
}
our @EXPORT_OK;

# include modules
use Carp;
use HTTP::Cookies;
use LWP::UserAgent;
use Digest::MD5 qw(md5 md5_hex md5_base64);

########################
# Constructor
#
sub new {
  my($class, $ip, $passwd) = @_;
  my $self  = {};
  $self->{IP}     = $ip;
  $self->{PASSWD} = $passwd;
  $self->{UA}     = undef;
  bless($self, $class);
  
  # login
  $self->login();
  
  # return object instance
  return $self;
}

########################
# Destructor
#
sub DESTROY {
  my($self) = @_;

  # logout
  $self->logout();
  return;
}

########################
# Login
#
sub login {	
  my($self) = @_;

  # create object
  my $headers = HTTP::Headers->new(
    x_requested_with => 'XMLHttpRequest',
	Accept_Language  => 'en-CA',
	Accept           => 'text/html, */*',
    Accept_Encoding  => 'gzip, deflate',
    DNT              => '1',
	# Referer        => 'http://192.168.1.254/pages/tabFW/tabFW.html?tabJson=../maintenance/reboot/tab.json',
    # Host           => '192.168.1.254',
    # Cookie         => 'firstview=statusView; viewtype=list; SESSION=819950000',
  );
  my $cookies = HTTP::Cookies->new(
    hide_cookie2     => 1,
  );
  $cookies->set_cookie( 0, 'firstview', 'statusView', '/', $self->{IP}, undef); #, $path_spec, $secure, $maxage, $discard, \%rest );
  $cookies->set_cookie( 0, 'viewtype',  'list',       '/', $self->{IP}, undef); #, $path_spec, $secure, $maxage, $discard, \%rest );
  $self->{UA} = LWP::UserAgent->new(
    agent           => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
	cookie_jar      => $cookies,
	keep_alive      => 1,
	default_headers => $headers,
  );

  # get login page
  my $url1 = "http://" . $self->{IP} . "/";
  my $out1 = $self->{UA}->get($url1);
  if(!$out1->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }
  return; # HACK

  # send auth
  my $url2 = "http://" . $self->{IP} . "/login/login-page.cgi";
  my %form2 = (
    "AuthName"     => "admin",
	"AuthPassword" => $self->{PASSWD},
	"endstr"       => "empty",
  );
  my $out2 = $self->{UA}->post( $url2, \%form2 );
  if(!$out2->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }
  return;
}

########################
# Logout
#
sub logout {
  my($self, $skip) = @_;

  $skip = 1; # HACK
  # get logout page
  unless( defined($skip) || !defined( $self->{UA} )) {
	$self->{UA}->{cookie_jar}->set_cookie( 0, 'SESSION', '', '/', $self->{IP}, undef); #, $path_spec, $secure, $maxage, $discard, \%rest );
    my $url1 = "http://" . $self->{IP} . "/login-logout.cgi";
    my $out1 = $self->{UA}->get($url1);
    if(!$out1->is_success) {
      croak("unable to connect to router: $self->{IP}");
    }
  }
  $self->{UA} = undef;
  return;
}

########################
# Get WAN data
#
sub get_wan_data {
  my($self) = @_;

  # get wan data
  my $url1 = "http://" . $self->{IP} . "/pages/networkMap/GetnetworkMapInfo.html";
  my $out1 = $self->{UA}->get($url1);
  if(!$out1->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }

  # parse output
  my $data = {};
  $data->{IP}   = (map { /- IP Address:\<\/td\>\<td[^>]*\>([\d.]+)/ ? $1 : () } $out1->content)[0];
  $data->{GWAY} = (map { /- WAN Default Gateway:\<\/td\>\<td[^>]*\>([\d.]+)/ ? $1 : () } $out1->content)[0];
  $data->{DNS}  = (map { /- WAN DNS Server 1:\<\/td\>\<td[^>]*\>([\d.]+)/ ? $1 : () } $out1->content)[0];

  if(!defined $data->{IP}) {
    croak("unable to get wan data");
  }
  
  return($data);
}
  
########################
# Get LAN data
#
sub get_lan_data {
  my($self) = @_;

  # get lan data
  my $url1 = "http://" . $self->{IP} . "/pages/networkMap/GetnetworkMapInfo.html";
  my $out1 = $self->{UA}->get($url1);
  if(!$out1->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }

  # parse output
  my $data = {};
  my @clients = split(/[@|]/, $out1->content);
  while ($clients[0] !~  m{/}) { shift(@clients); }
  my @clients2 = ();

  # add to array
  for my $client (@clients) {
    last if($client !~ m{/});
    my(@info) = split(m{/}, $client);
    my($name, $mac, $ip ) = ($info[1],$info[3],$info[5]);
    # HACK! router does not handle Bridged VM machines
    if($name eq 'cmlaptop') {
      $ip = '192.168.1.64';
    }
    elsif ($ip eq '192.168.1.64') {
      next; # don't know real ip so just skip this VM
    }
    # we now return you to your reg sched prog
    push(@clients2, [$ip, $mac, $name]);
  }
  $data->{CLIENTS} = \@clients2;

  if(!defined $data->{CLIENTS}->[0]) {
    croak("unable to get lan data");
  }
  
  # return
  return($data);
}

########################
# Reboot router
#
sub reboot {
  my($self) = @_;

  # reboot.html   (get sessionKey)
  #   GET /pages/maintenance/reboot/reboot.html   (first time)
  #   REF http://192.168.1.254/pages/tabFW/tabFW.html?tabJson=../maintenance/reboot/tab.json
  #   COO firstview=statusView; viewtype=list; SESSION=819950000
  my $url1 = "http://" . $self->{IP} . "/pages/maintenance/reboot/reboot.html";
  my $out1 = $self->{UA}->get($url1);
  if(!$out1->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }
  my $sessionKey = (map { /sessionKey='(\d+)'/ ? $1 : () } $out1->content)[0];

  # reboot-rebootinfo.cgi   (necessary?)
  #   GET /pages/tabFW/reboot-rebootinfo.cgi?sessionKey=1737222792
  #   REF http://192.168.1.254/index.html
  #   COO tabJson=..%2Fmaintenance%2Freboot%2Ftab.json; tabIndex=0; firstview=statusView; viewtype=list; SESSION=819950000
  my $url2 = "http://" . $self->{IP} . "/pages/tabFW/reboot-rebootinfo.cgi?sessionKey=" . $sessionKey;
  my $out2 = $self->{UA}->get($url2);
  if(!$out2->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }

  # json (why?)
  #   GET /pages/maintenance/reboot/tab.json
  #   REF http://192.168.1.254/pages/tabFW/reboot-rebootinfo.cgi?sessionKey=1737222792
  #   COO firstview=statusView; viewtype=list; SESSION=819950000
  my $url3 = "http://" . $self->{IP} . "/pages/maintenance/reboot/tab.json";
  my $out3 = $self->{UA}->get($url3);
  if(!$out3->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }

  # reboot.html - again (why?)
  #   GET /pages/maintenance/reboot/reboot.html (2nd time)
  #   REF http://192.168.1.254/pages/tabFW/reboot-rebootinfo.cgi?sessionKey=1737222792
  #   COO firstview=statusView; viewtype=list; SESSION=819950000
  my $url4 = "http://" . $self->{IP} . "/pages/maintenance/reboot/reboot.html";
  my $out4 = $self->{UA}->get($url4);
  if(!$out4->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }

  # reboot-rebootpost.cgi (does this do the actual reboot?)
  #   POS /pages/tabFW/reboot-rebootpost.cgi    (no post data)
  #   REF http://192.168.1.254/pages/tabFW/reboot-rebootinfo.cgi?sessionKey=1737222792
  #   COO tabJson=..%2Fmaintenance%2Freboot%2Ftab.json; tabIndex=0; firstview=statusView; viewtype=list; SESSION=819950000
  my $url5 = "http://" . $self->{IP} . "/pages/tabFW/reboot-rebootpost.cgi";
  my $out5 = $self->{UA}->post($url5);
  #if(!$out5->is_success) { # this call never returns
  #  croak("unable to connect to router: $self->{IP}");
  #}

  # wait and return
  print "Waiting for router to reboot...\n";
  sleep(60);
  $self->logout(1);
  return;
}

########################
# Get log
#
sub getlog {
  my($self) = @_;

  my $url1 = "http://" . $self->{IP} . "/pages/systemMonitoring/log/log-securityLog.cmd";
  my $out1 = $self->{UA}->get($url1);
  if(!$out1->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }
  my $output = $out1->content;
  $output =~ s/[\r\n]+//g;
  $output =~ s,\</t[rd]\b[^>]*\>,,g;
  my @log =  split(/\<tr\b[^>]*\>/, $output);
  for my $i (0..4) {shift @log;}
  my @headers = qw/ID DATE TIME WHAT LEVEL MESSAGE/;
  printf('%4s %-11s %-8s %-12s %-8s %-s'."\n", @headers);
  for my $log (@log) {
    my @fields = split(/\<td\b[^>]*\>/, $log);
	shift @fields;
	map {
	  s:\</table\b[^>]*\>.*:: ,
	  s:^\s+|\s+$::g
	} @fields;
	printf('%4s %-20s %-12s %-8s %-s'."\n", @fields);
  }

# WHAT         LEVEL    MESSAGE
# 
# Firewall     info     ACL:, IN=ptm0.1 OUT=n/a SRC=999.999.999.999
#                         DST=108.180.114.189 LEN=999 TOS=0x00 PREC=0x00
#                         TTL=999 DF PROTO=ICMP TYPE=8 CODE=0 ID=999
#                         SEQ=999 MARK=0x8000000
# Certificate  notice   Certificate ( cpecert ) Added
# Account      notice   Add "admin" to user configuration list
# Account      notice   Add "tech"  to user configuration list
# Account      warn     User admin login from 192.168.1.64 successful
# Account      warn     User admin logout
# Account      warn     User 99999 session timeout
  
}

##########################
# Return true
1;
