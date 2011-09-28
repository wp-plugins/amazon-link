<?php

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
    $regions = array('ca'    => array( 'host' => 'ecs.amazonaws.ca', 'uri' => '/onca/xml'), 
                     'cn'    => array( 'host' => 'webservices.amazon.cn', 'uri' => '/onca/xml'),
                     'com'   => array( 'host' => 'ecs.amazonaws.com', 'uri' => '/onca/xml'),
                     'co.uk' => array( 'host' => 'ecs.amazonaws.co.uk', 'uri' => '/onca/xml'),
                     'de'    => array( 'host' => 'ecs.amazonaws.de', 'uri' => '/onca/xml'),
                     'es'    => array( 'host' => 'webservices.amazon.es', 'uri' => '/onca/xml'),
                     'fr'    => array( 'host' => 'ecs.amazonaws.fr', 'uri' => '/onca/xml'),
                     'it'    => array( 'host' => 'webservices.amazon.it', 'uri' => '/onca/xml'),
                     'jp'    => array( 'host' => 'ecs.amazonaws.jp', 'uri' => '/onca/xml'));

    if (!array_key_exists($region, $regions))
       $region = "com";

    // some paramters
    $method = "GET";
    $host = $regions[$region]['host'];
    $uri = $regions[$region]['uri'];
    
    // additional parameters
    $params["Service"] = "AWSECommerceService";
    $params["AWSAccessKeyId"] = $public_key;
    // GMT timestamp
    $params["Timestamp"] = gmdate("Y-m-d\TH:i:s\Z");
    // API version
    $params["Version"] = "2011-08-01";
    
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
    
     //echo "<!-- REQ: "; print_r($request); echo "-->";
    // do request
    $response = @file_get_contents($request);
     //echo "<!--RESP:"; print_r($response); echo "-->";

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
