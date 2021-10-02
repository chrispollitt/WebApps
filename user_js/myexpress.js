///////////////////////////////////////////////////////////////////////////
// myexpress

"use strict";

// exports /////////////
exports.create_app   = create_app;
exports.MyPage       = MyPage;
exports.create_pages = create_pages;
//exports.stdres     = stdres;

// requires /////////////
// stadard
var express = require('express');
var glob    = require('glob');
var heredoc = require('heredoc');
var os      = require('os');
var path    = require('path');
// local   
var foobar  = require('Foo_Bar');
var phpjs   = require('phpjs');

// Create an app //////////
function create_app(dirname) {
  var app = express();
  app.disable('x-powered-by');
  app.appRoot = {};
  app.appRoot.ss = path.resolve(dirname).replace(/\\/g,'/');
  app.appRoot.cs = "/"+path.basename(app.appRoot.ss);
  
  return app;
}

// standard response /////////
function stdres(app, req, res, title, html) {
  console.log("req="+req.url);
  res.setHeader("Server", "CMNET/1.0");
  var str1;
  str1 = "<!DOCTYPE html>\n<html><head>";
  var libs = glob.sync(app.appRoot.ss+'/lib/*.js');
  for (var lib of libs) {
    var loc = app.appRoot.cs + lib.replace(app.appRoot.ss,"");
    str1 += "<script src='"+loc+"'></script>";
  }
  str1 += "<script src='"+app.appRoot.cs+"/int/library.js'></script>";
  str1 += "<title>";
  var str2 = "</title></head>\n<body onload='main1();'><h1>";
  var str3 = "</h1>";
  var str4 = "</body></html>\n";
  res.send(str1+title+str2+title+str3+html+str4);
  console.log("sent: "+title);
}

// create lib pages ////////
function create_lib_pages(app) {
  var host = os.hostname();
  var libs = glob.sync(app.appRoot.ss+'/lib/*.js');
  for (var lib of libs) {
    var loc = app.appRoot.cs + lib.replace(app.appRoot.ss,"");
    console.log("lib: " + lib);
    console.log("loc: " + loc);
    var pageDef = function(lib) {
      return function (req, res) {
        res.sendFile(lib.replace(/\//g,'\\'));
      };
    };
    app.get(loc, pageDef(lib));
  }
  app.get(app.appRoot.cs+'/int/library.js', function (req, res) {
    var user = foobar.foo(host+":web")[0];
    var pass = foobar.foo(host+":web")[1];
    var upenc = phpjs.base64_encode(user+":"+pass);
    var library = heredoc(function () {/*
// main1() /////////
function main1() {
  if(typeof(main) == 'function') { main(); }
}
// ajax() //////////
function ajax(url, id) {
  $("#"+id).html("Updating...");
  var timeout = 5000;
  console.debug( "[debug] pre-ajax" );
  var jqxhr = $.ajax({ 
    url:       url,
    type:      "GET",
    dataType:  "text", //"jsonp" "xml"
    timeout:   timeout,
    xhrFields: { withCredentials: true },
    beforeSend: function (request)
    {
*/}) +
"      request.setRequestHeader('Authorization', 'Basic "+upenc+"');" +
    heredoc(function () {/*
      
    }
  })
  .always(function(data, stat, err) {
    console.debug( "[debug] post-ajax" );
    if(stat == 'success') {
      console.debug( "[debug] data="+data );
      $("#"+id).html("Worked="+data);
    }
    else {
      console.debug( "[debug] error="+err );
      $("#"+id).html("Failed="+stat);
    }
  });
}
////////////////////
  */});
    res.type('.js');
    res.send(library);
  });
}

// MyPage class /////////
function MyPage(title,url,html) {
  this.title = title;
  this.url   = url;
  this.html  = html;
}

//////// ROOT pages //

function create_root_pages(app, pages) {
  app.get(app.appRoot.cs+'/', function (req, res) {
    var title = 'App List';
    var html = '<ul>';
    for(var i=0; i<pages.length; i++) {
      html += '<li><a href="' + app.appRoot.cs + pages[i].url + '">' + pages[i].title + '</a></li>';
    }
    html += '</ul>';
    stdres(app, req, res, title, html);
  });
  // redirect top level root
  app.get('/', function (req, res) {
    res.redirect(app.appRoot.cs+"/");
  });
}

//////// instantiate other pages via curried function //

function instantiate_app_pages(app, pages) {
  var pageDef = function(app, i) {
    return function (req, res) {
      var title = pages[i].title;
      var html =  pages[i].html;
      stdres(app, req, res, title, html);
    };
  };
  for(var i=0; i<pages.length; i++) {
    app.get(app.appRoot.cs + pages[i].url, pageDef(app, i));
  }
}

// create pages //////////
function create_pages(app, pages) {
  create_lib_pages(app);
  create_root_pages(app, pages);
  instantiate_app_pages(app, pages);
}

//////////////////////////////////////////////////////////////////
