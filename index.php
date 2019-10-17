<?php

/**
 * GA Proxy
 * @package  gaProxy
 * @author   David Vallejo <thyngster@gmail.com> @thyng
 * @version 1.1
*/

class gaProxy {

    // Config Section

    // Set this to true, if you want to remove the last Ip's Octet
    private $anonymizeIp = false;

    // Only uncomment this if you want to send the data to a different Property than the one defined on the hit
    // private $propertyId = 'UA-XXXXXXX-UU';

    // Set the cookie domain name. Use a leading dot, if not set, the root domain will be calculated
    // private $cookieDomain = '.yourdomain.com';

    // Where to send a copy of the hit
    private $gaEndPoint = 'https://www.google-analytics.com/collect';

    // No need to change anything below
    // Set CORS Headers
    public function setCORS() {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 1800");
    }

    public function getIpAddress() {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ? : ($_SERVER['HTTP_X_FORWARDED_FOR'] ? : $_SERVER['HTTP_CLIENT_IP']);
        if ($this->anonymizeIp == true) return preg_replace('/\.\d{1,3}$/', '.0', $ipAddress);
        else return $ipAddress;
    }

    public function setCookie($cid = null, $cookieName = "_ga") {
        if(!$this->cookieDomain){
		// Find the root domain from current server_name
		$extract = new LayerShifter\TLDExtract\Extract();
		$result = $extract->parse($_SERVER['SERVER_NAME']);
		$this->cookieDomain = '.'.$result->getRegistrableDomain();
        }
        if ($cid) setcookie($cookieName, $cid, time() + (365 * 24 * 60 * 60 * 2) , "/", $this->cookieDomain, 0);
    }

    public function processPayload() {
	require 'vendor/autoload.php';
	// Grab current payload data
       	$rawRequestPayload = file_get_contents('php://input');
	// Parse Payload
        parse_str(base64_decode($rawRequestPayload) , $parsedPayload);

	// Get Cookie Name
        $cookieName = $parsedPayload["ckn"];
	// Unset non-core payload keys
        unset($parsedPayload["ckn"]);

	// Set CORS headers
        $this->setCORS();
	// Update Cookie Expiration
        $this->setCookie($_COOKIE[$cookieName], $cookieName);

	// if the propertyID is set, let's send a copy of the hit using the measurement protocol
	if($this->propertyId){
		// Guzzle Client, to do Async non-blocking requests
	        $client = new \GuzzleHttp\Client();
		// override the payload TID parameter
        	if ($this->propertyId) $parsedPayload["tid"] = $this->propertyId;
 		// Set the user Agent ( for OS/Browsers Reports )
	        if ($_SERVER["HTTP_USER_AGENT"]) $parsedPayload["ua"] = $_SERVER["HTTP_USER_AGENT"];
		// Set the user IP address ( for GEO reports )
	        $parsedPayload["uip"] = $this->getIpAddress();
	        $url = $this->gaEndPoint . '?' . http_build_query($parsedPayload, '', '&');
        	$promise = $client->requestAsync('get', $url, ['headers' => ['User-Agent' => $parsedPayload["ua"], 'Accept-language' => $parsedPayload["ul"]]]);
	        $response = $promise->wait();

	}
	// return a 1x1 pixel image
	header('Content-Type: image/gif');
	die(hex2bin('47494638396101000100900000ff000000000021f90405100000002c00000000010001000002020401003b'));
    }
}
$gaProxy = new gaProxy();
$gaProxy->processPayload();
