<?php


//DEFINE(DEBUG, TRUE);
DEFINE(DEBUG, FALSE);


//GetXMLTree and GetChildren code from http://whoooop.co.uk/2005/03/20/xml-to-array/

function XXXGetXMLTree ($xmldata)
{
	// we want to know if an error occurs
	ini_set ('track_errors', '1');

	$xmlreaderror = false;

	$parser = xml_parser_create ('ISO-8859-1');
	xml_parser_set_option ($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parser_set_option ($parser, XML_OPTION_CASE_FOLDING, 0);
	if (!xml_parse_into_struct ($parser, $xmldata, $vals, $index)) {
		$xmlreaderror = true;
		echo "error1";
	}
	xml_parser_free ($parser);

	if (!$xmlreaderror) {
		$result = array ();
		$i = 0;
		if (isset ($vals [$i]['attributes']))
			foreach (array_keys ($vals [$i]['attributes']) as $attkey)
			$attributes [$attkey] = $vals [$i]['attributes'][$attkey];

		$result [$vals [$i]['tag']] = array_merge ($attributes, GetChildren ($vals, $i, 'open'));
	}

	ini_set ('track_errors', '0');
	return $result;
}

function XXXGetChildren ($vals, &$i, $type)
{
	if ($type == 'complete') {
		if (isset ($vals [$i]['value']))
			return ($vals [$i]['value']);
		else
			return '';
	}

	$children = array (); // Contains node data

	/* Loop through children */
	while ($vals [++$i]['type'] != 'close') {
		$type = $vals [$i]['type'];
		// first check if we already have one and need to create an array
		if (isset ($children [$vals [$i]['tag']])) {
			if (is_array ($children [$vals [$i]['tag']])) {
				$temp = array_keys ($children [$vals [$i]['tag']]);
				// there is one of these things already and it is itself an array
				if (is_string ($temp [0])) {
					$a = $children [$vals [$i]['tag']];
					unset ($children [$vals [$i]['tag']]);
					$children [$vals [$i]['tag']][0] = $a;
				}
			} else {
				$a = $children [$vals [$i]['tag']];
				unset ($children [$vals [$i]['tag']]);
				$children [$vals [$i]['tag']][0] = $a;
			}

			$children [$vals [$i]['tag']][] = GetChildren ($vals, $i, $type);
		} else
			$children [$vals [$i]['tag']] = GetChildren ($vals, $i, $type);
		// I don't think I need attributes but this is how I would do them:
		if (isset ($vals [$i]['attributes'])) {
			$attributes = array ();
			foreach (array_keys ($vals [$i]['attributes']) as $attkey)
			$attributes [$attkey] = $vals [$i]['attributes'][$attkey];
			// now check: do we already have an array or a value?
			if (isset ($children [$vals [$i]['tag']])) {
				// case where there is an attribute but no value, a complete with an attribute in other words
				if ($children [$vals [$i]['tag']] == '') {
					unset ($children [$vals [$i]['tag']]);
					$children [$vals [$i]['tag']] = $attributes;
				}
				// case where there is an array of identical items with attributes
				elseif (is_array ($children [$vals [$i]['tag']])) {
					$index = count ($children [$vals [$i]['tag']]) - 1;
					// probably also have to check here whether the individual item is also an array or not or what... all a bit messy
					if ($children [$vals [$i]['tag']][$index] == '') {
						unset ($children [$vals [$i]['tag']][$index]);
						$children [$vals [$i]['tag']][$index] = $attributes;
					}
					$children [$vals [$i]['tag']][$index] = array_merge ($children [$vals [$i]['tag']][$index], $attributes);
				} else {
					$value = $children [$vals [$i]['tag']];
					unset ($children [$vals [$i]['tag']]);
					$children [$vals [$i]['tag']]['value'] = $value;
					$children [$vals [$i]['tag']] = array_merge ($children [$vals [$i]['tag']], $attributes);
				}
			} else
				$children [$vals [$i]['tag']] = $attributes;
		}
	}

	return $children;
}

function unserialize_xml($input, $callback = null, $recurse = false)
/* bool/array unserialize_xml ( string $input [ , callback $callback ] )
 * Unserializes an XML string, returning a multi-dimensional associative array, optionally runs a callback on all non-array data
 * Returns false on all failure
 * Notes:
    * Root XML tags are stripped
    * Due to its recursive nature, unserialize_xml() will also support SimpleXMLElement objects and arrays as input
    * Uses simplexml_load_string() for XML parsing, see SimpleXML documentation for more info
 */
{
    // Get input, loading an xml string with simplexml if its the top level of recursion
    $data = ((!$recurse) && is_string($input))? simplexml_load_string($input): $input;
    // Convert SimpleXMLElements to array
    if ($data instanceof SimpleXMLElement) $data = (array) $data;
    // Recurse into arrays
    if (is_array($data)) foreach ($data as &$item) $item = unserialize_xml($item, $callback, true);
    // Run callback and return
    return (!is_array($data) && is_callable($callback))? call_user_func($callback, $data): $data;
}

function aws_signed_request($region, $params, $public_key, $private_key)
{
    /*
    Copyright (c) 2009 Ulrich Mierendorff

    Permission is hereby granted, free of charge, to any person obtaining a
    copy of this software and associated documentation files (the "Software"),
    to deal in the Software without restriction, including without limitation
    the rights to use, copy, modify, merge, publish, distribute, sublicense,
    and/or sell copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
    THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
    FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
    DEALINGS IN THE SOFTWARE.
    */
    
    /*
    Parameters:
        $region - the Amazon(r) region (ca,com,co.uk,de,fr,jp)
        $params - an array of parameters, eg. array("Operation"=>"ItemLookup",
                        "ItemId"=>"B000X9FLKM", "ResponseGroup"=>"Small")
        $public_key - your "Access Key ID"
        $private_key - your "Secret Access Key"
    */

    // some paramters
    $method = "GET";
    $host = "ecs.amazonaws.".$region;
    $uri = "/onca/xml";
    
    // additional parameters
    $params["Service"] = "AWSECommerceService";
    $params["AWSAccessKeyId"] = $public_key;
    // GMT timestamp
    $params["Timestamp"] = gmdate("Y-m-d\TH:i:s\Z");
    // API version
    $params["Version"] = "2009-03-31";
    
    // sort the parameters
    ksort($params);
    
    // create the canonicalized query
    $canonicalized_query = array();
    foreach ($params as $param=>$value)
    {
        $param = str_replace("%7E", "~", rawurlencode($param));
        $value = str_replace("%7E", "~", rawurlencode($value));
        $canonicalized_query[] = $param."=".$value;
    }
    $canonicalized_query = implode("&", $canonicalized_query);
    
    // create the string to sign
    $string_to_sign = $method."\n".$host."\n".$uri."\n".$canonicalized_query;
    
    // calculate HMAC with SHA256 and base64-encoding
    $signature = base64_encode(hash_hmac("sha256", $string_to_sign, $private_key, True));
    
    // encode the signature for the request
    $signature = str_replace("%7E", "~", rawurlencode($signature));
    
    // create request
    $request = "http://".$host.$uri."?".$canonicalized_query."&Signature=".$signature;
    
    // do request
    $response = @file_get_contents($request);
    
    if ($response === False)
    {
        return False;
    }
    else
    {
        // parse XML
        $pxml = unserialize_xml($response);
        if ($pxml === False)
        {
            return False; // no xml
        }
        else
        {
            return $pxml;
        }
    }
}


?>
