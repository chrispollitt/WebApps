
function myencode(input) {
  var output = '';

  for(var i = 0; i < input.length; i++) {
    var char= input.charCodeAt(i);
    output += char;
    output += "-";
  }
  return output;
}

function mydecode(input) {
  input = input.replace(/-$/,'');
  var output = '';
  var splitin = input.split("-");

  for(var i = 0; i < splitin.length; i++) {
    var char = parseInt(splitin[i]);
    output += String.fromCharCode(char);
  }
  return output;
}

//////////////////////////////////////////////////

function xor_js(to_enc) {
	var the_res = "";
	var xor_key = 13;

	for(var i=0; i<to_enc.length; ++i) {
		the_res += String.fromCharCode(xor_key ^ to_enc.charCodeAt(i));
	}
	return(the_res);
}
