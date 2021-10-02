

------


for user in Chris  cyg_server whatwelo; do
  update module and wrappers
    s/\bfoo.?bar\./Foo_Bar./i
    add usage to end of host -> host:usage (web, mysql, cpanel, windows)
  sftp ~/user_*/* remote host
  regen passwds
done

--Chris--
Admin     c6 192.168.0.1:web
Admin     ea cmrouter:web
Chris     3d universal-laughter:??
Chris     4f cmlaptop:web
Chris     fa cmnet.whatwelove.org:??
whatwelo  0c www.whatwelove.org:web
whatwelo  18 cs7.uhcloud.com:cpanel
whatwelo  f1 whatwelove.org:web
x         10 ./cyg_server:windows

--cyg_server--
  Chris     5w localhost:tinyshell
  Chris     8g cmlaptop:web
  whatwelo  xx whatwelove.org:web
  root      UA localhost:mysql
  ul        lw ul@localhost:mysql
  pma       mA pma@localhost:mysql

  --whatwelo--
  Chris	         f7 cmnet.whatwelove.org:web
  whatwelo	     rc cs7.uhcloud.com:cpanel
  whatwelo       ff localhost:tinyshell
  whatwelo_chris ff localhost:mysql

===============================================

--cmlaptop--

/home/Chris/bin/add_service.sh:run_pass=$(perl -MFoo::Bar -e '($u,$p)= foo("'"$run_user"'");print "$p\n"')
/home/Chris/bin/isinternetup.pl:use Foo::Bar;
/home/Chris/bin/isinternetup.pl:  my $foobar = (Foo::Bar::foo($lan_defgway))[1];
/home/Chris/bin/isinternetup.pl:  my $dlink = new DLink($lan_defgway, $foobar);
/home/Chris/bin/new_wordpress_site.sh:  . ~/user_bash/Foo_Bar.bash
/home/Chris/src/Misc/preflight.sh:. ~/user_bash/Foo-Bar.bash

/srv/nodejs/experiment/index.js:var foobar  = require('foobar');
/srv/nodejs/experiment/index.js:  var user = foobar.foo(host)[0];
/srv/nodejs/experiment/index.js:  var pass = foobar.foo(host)[1];
/srv/nodejs/experiment/index.js:  foobar.bar("user","pass","host");
/srv/nodejs/experiment/index.js:  console.log("foo="+foobar.foo("cmlaptop")[0]);
/srv/nodejs/experiment/test.js:var foobar  = require('foobar');
/srv/nodejs/experiment/test.js:  var user = foobar.foo(host)[0];
/srv/nodejs/experiment/test.js:  var pass = foobar.foo(host)[1];

  /srv/www/vhosts/cmnet/htdocs/pt/ts/config.php:  require_once("Foo-Bar.php");
  /srv/www/vhosts/cmnet/htdocs/pt/up.php:require_once("Foo-Bar.php");
  /srv/www/vhosts/chris/htdocs/wp-config.php:require_once("Foo-Bar.php");
  /srv/www/vhosts/universal-laughter/htdocs/wp/wp-config.php:require_once("Foo-Bar.php");
  /srv/www/vhosts/cmlaptop/htdocs/phpMyAdmin/config.inc.php:require_once("Foo-Bar.php");

  /etc/profile.d/ssl.sh:export CMNET_AUTH=$(perl -MFoo::Bar -e '($u,$p)= foo("cmlaptop:web");print "$u:$p\n"')
  /etc/profile.d/ssl.sh:export WWL_AUTH=$(perl -MFoo::Bar -e '($u,$p)= foo("whatwelove.org:web");print "$u:$p\n"')

---wwl---

  www/pt/ts/config.php:  require_once("Foo-Bar.php");
  www/pt/fb.php:require_once('Foo-Bar.php');
  cmnet/index.php:require_once('Foo-Bar.php');
  dyndns/yes_ssl/update_dyndns.php:require_once('Foo-Bar.php');
  user_php/cpanel.php:require_once('Foo-Bar.php');

