///////////////////////////////////////////////////////////////////////////
// myhttps

"use strict";

// exports /////////////
exports.myhttps_run = myhttps_run;

// requires /////////////
var os      = require('os');
var https   = require('https');
var fs      = require('fs');

// SSL config /////////
function myhttps_cfg(certRoot) {
  var ca     = [];
  var cert   = [];
  var incert = false;
  var chain  = fs.readFileSync(certRoot+'ca/signing-ca-chain.pem', 'utf8');
  chain  = chain.split( "\n");
  for (var i=0; i < chain.length; i++)  {
    var line=chain[i];
    if (line.match( /-BEGIN CERTIFICATE-/ )) {
      incert = true;
      cert.push( line);
    }
    else if (line.match( /-END CERTIFICATE-/ )) {
      cert.push( line);
      ca.push( cert.join( "\n"));
      cert = [];
      incert = false;
    }
    else if(incert) {
      cert.push( line);
    }
  }
  var options = {
    ca:    ca,
    key:   fs.readFileSync(certRoot+'certs/nodejs.key'),
    cert:  fs.readFileSync(certRoot+'certs/nodejs.crt')
  };
  return options;
}

// Start server ////////////////////////
function myhttps_run(app, port, certRoot) {
  var options = myhttps_cfg(certRoot);
  var host    = os.hostname();
  https.createServer(options, app).listen(port);
  console.log('Listening at https://'+host+':'+port);
}

//////////////////////////////////////////////////////////////////
