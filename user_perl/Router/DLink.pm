#!/usr/bin/perl
#
# D-Link Router module
#

#####################################################
# set packagge name
package Router::DLink;

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
}

########################
# Login
#
sub login {	
  my($self) = @_;

  # create object
  $self->{UA} = LWP::UserAgent->new;

  # get login page
  my $req0 = HTTP::Request->new(GET => "http://$self->{IP}/");
  my $out0 = $self->{UA}->request($req0);
  if(!$out0->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }

  # send auth
  my $auth = $self->_calc_auth($out0);
  my $req1 = HTTP::Request->new(GET => "http://$self->{IP}/post_login.xml?" . $auth);
  my $out1 = $self->{UA}->request($req1);
  if(!$out1->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }
}

########################
# Logout
#
sub logout {	
  my($self, $skip) = @_;

  # get logout page
  unless(defined $skip || !defined $self->{UA}) {
    my $req0 = HTTP::Request->new(GET => "http://$self->{IP}/logout.cgi");
    my $out0 = $self->{UA}->request($req0);
    if(!$out0->is_success) {
      croak("unable to connect to router: $self->{IP}");
    }
  }
  $self->{UA} = undef;
}

########################
# Calculate auth string
#
sub _calc_auth {
  my($self, $in) = @_;

  my $salt      = (map { /salt\s*=\s*"(\S+)"/   ? $1 : () } $in->content)[0];
  my $auth_code = '';
  my $auth_id   = (map { /auth_id\s*=\s*(\S+)"/ ? $1 : () } $in->content)[0];
  my $passwd    = $self->{PASSWD};
  
  for (my $i = length($passwd); $i < 16; $i++) {
    $passwd .= chr(1);
  }
  my $input = $salt . $passwd;
  for (my $i = length($input); $i < 63; $i++) {
    $input .= chr(1);
  }
  $input .= chr(1);
  my $hash = md5_hex($input);
  my $login_hash = $salt . $hash; 
  my $auth_url = "&auth_code=" . $auth_code . "&auth_id=" . $auth_id;
  my $auth = "hash=" . $login_hash . $auth_url;

  # hash=77e86a832b45acb11102e8b7e91cf2b0a4a226a7&auth_code=&auth_id=9195B
  return $auth
}

########################
# Get WAN data
#
sub get_wan_data {
  my($self) = @_;

  # get wan data
  my $req2 = HTTP::Request->new(GET => "http://$self->{IP}/wan_connection_status.xml");
  my $out2 = $self->{UA}->request($req2);
  if(!$out2->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }

  # parse output
  my $data = {};
  $data->{IP}   = (map { /wan_ip_address_0\>([\d.]+)/ ? $1 : () } $out2->content)[0];
  $data->{GWAY} = (map { /wan_gateway_0\>([\d.]+)/ ? $1 : () } $out2->content)[0];
  $data->{DNS}  = (map { /wan_primary_dns_0\>([\d.]+)/ ? $1 : () } $out2->content)[0];

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
  my $req3 = HTTP::Request->new(GET => "http://$self->{IP}/dhcp_clients.xml");
  my $out3 = $self->{UA}->request($req3);
  if(!$out3->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }

  # parse output
  my $data = {};
  my @clients = split(/\<client\>/, $out3->content);
  shift(@clients);
  my @clients2 = ();

  # add to array
  for my $client (@clients) {
    my($ip, $mac, $name) = $client =~ m{ip_address\>([^<]*)\</ip_address\>\<mac\>([^<]*)\</mac\>\<host_name\>([^<]*)};
    push(@clients2, [$ip, $mac, $name]);
  }  
  $data->{CLIENTS} = \@clients2;

  if(!defined $data->{CLIENTS}->[0]) {
    croak("unable to get lan data");
  }
  
  # retrun
  return($data);
}

########################
# Reboot router
#
sub reboot {
  my($self) = @_;

  my $req4 = HTTP::Request->new(GET => "http://$self->{IP}/reboot.cgi?reset=false");
  my $out4 = $self->{UA}->request($req4);
  if(!$out4->is_success) {
    croak("unable to connect to router: $self->{IP}");
  }
  else {
    print "Waiting for router to reboot...\n";
    sleep(60);
    $self->logout(1);
  }
}

##########################
# Return true
1;

