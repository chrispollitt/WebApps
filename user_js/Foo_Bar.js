// this is a javascript wrapper for Foo::Bar Perl module
// server side only (nodejs)

var exec    = require('child_process').execSync;
var process = require('process');
var path    = require('path');

exports.foo = foo;
exports.bar = bar;

var fb_path = path.dirname(path.dirname( __filename )) + "/user_php";

function foo(one) {
  var two;
  var three;
  // call script
  var cmd  = "php-cli "+fb_path+"/Foo_Bar_w.php '"+one+"'";
  var opts = {};
  opts.env = process.env;
  var result = exec(cmd, opts);
  if(result) {
    // why an array of char codes?
    var result_str = "";
    for(var i=0; i< result.length; i++) {
      result_str += String.fromCharCode(result[i]);
    }
    // split on newline
    var output = result_str.split("\n");
    three = output[0];
    two   = output[1];
  } else {
    three = "failed";
    two   = "failed";
    console.log("foo() error");
  }
  return([three,two]);  
}

function bar(three,two,one) {
  // call script
  var cmd  = "php-cli "+fb_path+"/Foo_Bar_w.php '"+three+"' '"+two+"' '"+one+"'";
  var opts = {};
  opts.env = process.env;
  exec(cmd, opts);
}
