<?php
/*
 * Tendril Connect - Flicks the light on and off
 * © 2012 Tendril
 *
 * This sample demonstrates using OAUTH 2.0 to authenticate a user via
 * Tendril Connect and retrieve consumption information via the REST
 * APIs.
 *
 * Please refer to the Tendril Connect API Primer on Authentication for
 * more information:
 *
 *    http://dev.tendrilinc.com/docs/auth
 *
 */

/*
 * Create a session.
 */
session_name('tendril_flicker');
session_start();

/*
 * Variables used for OAUTH with Tendril Connect:
 *
 *    connectURL            - URL for Tendril Connect
 *    client_id             - App Key/ID for this application
 *    client_secret         - App Secret for this application
 *    callbackURL           - URL for redirecting the user back after authentication
 *    extendedPermissions   - Permissions for resources that this app requires (TBD)
 *    refreshThreshold      - The minimum time for refreshing the access token
 */

$connectURL           = 'http://dev.tendrilinc.com';
$client_id            = 'YOUR APP KEY';
$client_secret        = 'YOUR APP SECRET';

$callbackURL          = 'http://localhost/flicker.php';

$extendedPermissions  = 'device';
$refreshThreshold     = 5;

/*
 * Parameter values used for making requests to Tendril Connect APIs.
 * Default values will be overridden by request paramters of the same name.
 */
$paramDefaults = array(
  'action' => 'get_status',
  'deviceId' => NULL,
  'locationId' => '62',
  'mode' => 'On',
  'set_volt' => ''
);

$params = array_filter($_REQUEST) + $paramDefaults;

/*
 * Set the timezone to use for date formats.
 * Set the timezone for date formats
 */
date_default_timezone_set('MST');

/*
 * Compute the remaining time for the access_token.
 * This will be used to refresh the token when it expires.
 */
$curr_time = strtotime("now");
$exp_time = $_SESSION['expires_time'];
$rem_time = $exp_time - $curr_time;

/*
 * Convenience function for making HTTP GET requests.
 * Replace with http_get or use one of the RESTful PHP classes if desired.
 */
function curl_get($url, array $get = NULL, array $headers = NULL, array $options = array())  {
  $defaults = array( 
    CURLOPT_URL => $url. (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get), 
    CURLOPT_HEADER => 0, 
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => TRUE, 
    CURLOPT_TIMEOUT => 4,
    $headers
  ); 
  
  $ch = curl_init(); 
  curl_setopt_array($ch, ($options + $defaults));
  
  if ( ! $result = curl_exec($ch)) { 
    trigger_error(curl_error($ch)); 
  } 
  curl_close($ch); 
  return $result; 
}

/*
 * Convenience function for making HTTP POST requests.
 * Replace with http_post or use one of the RESTful PHP classes if desired.
 */
function curl_post($url, array $post = NULL, array $headers = NULL, array $options = array()) {
    $defaults = array(
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_URL => $url,
        CURLOPT_FRESH_CONNECT => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FORBID_REUSE => 1,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_POSTFIELDS => http_build_query($post)
    );

    $_defaults = defaults;
    
    $ch = curl_init();
    curl_setopt_array($ch, ($options + $defaults));
    if( ! $result = curl_exec($ch)) {
        trigger_error(curl_error($ch));
    }
    curl_close($ch);
    return $result;
} 

/*
 * Convenience function for making HTTP POST requests for XML data.
 * Replace with http_post or use one of the RESTful PHP classes if desired.
 */
function curl_post_xml($url, $xml = NULL, array $headers = NULL, array $options = array()) {
    $defaults = array(
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_URL => $url,
        CURLOPT_FRESH_CONNECT => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FORBID_REUSE => 1,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_POSTFIELDS => $xml
    );

    $_defaults = defaults;
    
    $ch = curl_init();
    curl_setopt_array($ch, ($options + $defaults));
    if( ! $result = curl_exec($ch)) {
        trigger_error(curl_error($ch));
    }
    curl_close($ch);
    return $result;
} 

/*
 * Executed when the logout request parameter is set and the user is logged in.
 * Destroys the local session and calls logout on Tendril Connect.
 */
if (isset($_GET['logout']) and $_SESSION['loggedin']) {
  
  $_SESSION = array();
  session_destroy();
   
  // Logout of Tendril server to destroy session
  $url = $connectURL . '/oauth/logout?redirect_uri=' . $callbackURL;
  header("Location: $url", true, 303);
  die();
}

/*
 * Obtain user authorization from Tendril Connect.
 *
 * Executed when the 'Connect with Tendril' link is clicked.
 *
 * Redirects the user the the OAUTH authorize URL - /oauth/authorize
 *
 * HTTP Request Parameters:
 *
 *    response_type   - REQUIRED.  Value MUST be set to "code".
 *    client_id       - REQUIRED.  The client identifier (aka App Key/ID)
 *    redirect_uri    - OPTIONAL.  If not included then use the one specified by the app.
 *    scope           - OPTIONAL.  The scope of the access request (app permissions).
 *    state           - RECOMMENDED.  An opaque value used by the client to maintain state between the request and callback.
 *
 * References:
 *
 *    OAUTH 2.0 Spec - 4.1.1. Authorization Request
 */  
if (isset($_GET['signin'])) {
 
  $url = $connectURL;
  $url .= '/oauth/authorize';
  $url .= '?response_type=code';
  $url .= '&client_id=' . $client_id;
  $url .= '&redirect_uri=' . $callbackURL;
  $url .= '&scope=' . $extendedPermissions;
  $_SESSION['authorize_state'] = md5(uniqid(mt_rand(), true));
  $url .= '&state=' . $_SESSION['authorize_state'];
  $url .= '&x_dialog=true';
  header("Location: $url", true, 303);
  die();
}

/*
 * Refresh the access token if it has expired. 
 *
 * Executed when the refresh time falls below the specified threshold.
 *
 * HTTP GET request to OAUTH access_token URL - /oauth/access_token
 * 
 * HTTP Headers:
 *
 *    Accept          - The mime-type of the data that the client expects in the response.
 *    Content-Type    - The mime-type of the data in the client request.
 *
 * HTTP Request Parameters:
 *
 *    grant_type      - REQUIRED.  Value MUST be set to "refresh_token".
 *    refresh_token   - REQUIRED.  The refresh token issued to the client.
 *    scope           - OPTIONAL.  The scope of the access request (app permissions).
 *
 * HTTP Response Parameters:
 *
 *    access_token    - REQUIRED.  The access token issued by the authorization server.
 *    token_type      - REQUIRED.  The type of the token issued, case insensitive.
 *    expires_in      - OPTIONAL.  The lifetime in seconds of the access token.
 *    refresh_token   - OPTIONAL.  The refresh token which can be used to obtain new access tokens.
 *    scope           - OPTIONAL.  The scope of the access token.
 *
 * References:
 *
 *    OAUTH 2.0 Spec - 6.  Refreshing an Access Token
 *    OAUTH 2.0 Spec - 4.1.4.  Access Token Response
 *    OAUTH 2.0 Spec - 5.1.  Successful Response
 *
 */
if ($rem_time <= $refreshThreshold and $_SESSION['access_token']) {
  
  # Exchange the code that we have for an access token
  
  $url = $connectURL . '/oauth/access_token';

  $headers = array(
  /*
    'Accept: application/json',
    'Content-Type: application/json'
  */
  );
  
  $data = array(
    'grant_type'      => 'refresh_token',
    'refresh_token'   => $_SESSION['refresh_token'],
    'scope'           => $extendedPermissions
  );

  $options = array();
  
  $response = curl_get($url, $data, $headers, $options);
  //$response = curl_post($url, $data, $headers, $options);

  /*
   * Decode the JSON response into a PHP array.
   */ 
  $data = json_decode($response, true);
  $_SESSION['access_token']     = $data['access_token'];
  $_SESSION['token_type']       = $data['token_type'];
  $_SESSION['expires_in']       = $data['expires_in'];
  $_SESSION['refresh_token']    = $data['refresh_token'];
  $_SESSION['scope']            = $data['scope'];
  
  $timestamp                    = strtotime("now");
  $expires_time                 = $timestamp + $data['expires_in'];
  $_SESSION['expires_time']     = $expires_time;
  
}

/*
 * Handle the authorization response code from Tendril Connect.
 *
 * HTTP redirect to the callback URL - $callbackURL
 * 
 * HTTP Callback Parameters:
 *
 *    code            - REQUIRED.  The authorization code generated by the authorization server.
 *    state           - REQUIRED if present in the client authorization request.  The exact value received from the client.
 *
 * The application should exchange this code for an access token.
 * 
 * HTTP GET request to OAUTH access_token URL - /oauth/access_token
 * 
 * HTTP Headers:
 *
 *    Accept          - The mime-type of the data that the client expects in the response.
 *    Content-Type    - The mime-type of the data in the client request.
 *
 * HTTP Request Parameters:
 *
 *    grant_type      - REQUIRED.  Value MUST be set to "authorization_code".
 *    code            - REQUIRED.  The authorization code received from the authorization server.
 *    redirect_uri    - REQUIRED, if the "redirect_uri" parameter was included in the authorization request.  Must match.
 *    client_id       - REQUIRED.  The client identifier (aka App Key/ID)
 *    client_secret   - REQUIRED.  The client password (aka App Secret)
 *
 * HTTP Response Parameters:
 *
 *    access_token    - REQUIRED.  The access token issued by the authorization server.
 *    token_type      - REQUIRED.  The type of the token issued, case insensitive.
 *    expires_in      - OPTIONAL.  The lifetime in seconds of the access token.
 *    refresh_token   - OPTIONAL.  The refresh token which can be used to obtain new access tokens.
 *    scope           - OPTIONAL.  The scope of the access token.
 *
 * References:
 *
 *    OAUTH 2.0 Spec - 4.1.2.  Authorization Response
 *    OAUTH 2.0 Spec - 4.1.3.  Access Token Request
 *    OAUTH 2.0 Spec - 2.3.1.  Client Password
 */
if (isset($_GET['code'])) {

  # Store the code in the session
  $_SESSION['code'] = $_GET['code'];
  $_SESSION['check_state'] = $_GET['state'];

  // Verify the state by checking the received versus sent values.
  
  /*
   * Exchange the code that was received for an access token.
   */
  
  $url = $connectURL . '/oauth/access_token';

  $headers = array(
    /*
  
    'Accept: application/json',
    'Content-Type: application/json'
    'Access_Token:' . $_SESSION['access_token']
    */
  );
  
  $data = array(
    'grant_type'    => 'authorization_code',
    'code'          => $_GET['code'],
    'redirect_uri'  => $callbackURL,
    'client_id'     => $client_id,
    'client_secret' => $client_secret
  );

  $options = array();
  
  $response = curl_get($url, $data, $headers, $options);
  //$response = curl_post($url, $data, $headers, $options);

  /*
   * Decode the JSON response into a PHP array.
   */ 
  $data = json_decode($response, true);
  $_SESSION['access_token']     = $data['access_token'];
  $_SESSION['token_type']       = $data['token_type'];
  $_SESSION['expires_in']       = $data['expires_in'];
  $_SESSION['refresh_token']    = $data['refresh_token'];
  $_SESSION['scope']            = $data['scope'];
  
  // Set a session value to indicate the user is logged in
  $_SESSION['loggedin']         = true;
  
  $timestamp                    = strtotime("now");
  $expires_time                 = $timestamp + $data['expires_in'];
  $_SESSION['expires_time']     = $expires_time;
  
  /*
   * Get the location id for the user
   */
   
  $url = $connectURL;
  $url .= '/connect/user/current-user/';
   
  $headers = array(
    'Accept: application/json',
    'Content-Type: application/xml',
    'Access_Token:' . $_SESSION['access_token']
  );
  $data = array();
  $options = array();
  
  $response = curl_get($url, $data, $headers, $options);
    
  // Decode the JSON response into a PHP array
  $responseData = json_decode($response, true);

  // Get the locationId variable
  $_SESSION['locationId'] = $responseData['@id'];

  // Clears the URL
  $url = $callbackURL;
  header("Location: $url", true, 303);
  die();
}

/*
 * Handle OAUTH errors received from Tendril Connect.
 *
 * HTTP Response Parameters:
 *
 *    error               - REQUIRED.  A single error code.
 *    error_description   - OPTIONAL.  A human-readable UTF-8 encoded text providing additional information.
 *    error_uri           - OPTIONAL.  A URI identifying a human-readable web page with information about the error.
 *
 * References:
 *
 *    OAUTH 2.0 Spec - 4.1.2.1.  Error Response
 *    OAUTH 2.0 Spec - 5.2.  Error Response
 */
if (isset($_GET['error'])) {
  # error:   
  # error_description: The user denied your request.
}

/*
 * Device action templates
 * Move to an external file?
 */
 
$setVoltDataRequestStr = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<setVoltDataRequest xmlns="http://platform.tendrilinc.com/tnop/extension/ems" deviceId="0" locationId="1">
  <data>
    <mode>Off</mode>
  </data>
</setVoltDataRequest>
XML;

function createSetVoltDataRequest($deviceId, $locationId, $mode) {
  global $setVoltDataRequestStr;
  $str = $setVoltDataRequestStr;
  $xml = new SimpleXMLElement($str);
  $xml['deviceId'] = $deviceId;
  $xml['locationId'] = $locationId;
  $xml->data->mode = $mode;
  return $xml;
}

$setThermostatDataRequestStr = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<setThermostatDataRequest
  xmlns="http://platform.tendrilinc.com/tnop/extension/ems"
  xmlns:ns2="http://iec.ch/TC57/2009/MeterReadings#" deviceId="0"
  locationId="1">
  <data>
    <setpoint>70</setpoint>
    <mode>Heat</mode>
    <temperatureScale>Fahrenheit</temperatureScale>
  </data>
</setThermostatDataRequest>
XML;

function createSetThermostatDataRequest($deviceId, $locationId, $mode, $setpoint, $temperatureScale) {
  global $setThermostatDataRequestStr;
  $str = $setThermostatDataRequestStr;
  $xml = new SimpleXMLElement($str);
  $xml['deviceId'] = $deviceId;
  $xml['locationId'] = $locationId;
  $xml->data->mode = $mode;
  $xml->data->setpoint = $setpoint;
  $xml->data->temperatureScale = $temperatureScale;
  return $xml;
}

$getVoltDataRequestStr = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<getVoltDataRequest xmlns="http://platform.tendrilinc.com/tnop/extension/ems" deviceId="0" locationId="1">
</getVoltDataRequest>
XML;

$getThermostatDataRequestStr = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<getThermostatDataRequest xmlns="http://platform.tendrilinc.com/tnop/extension/ems" deviceId="0" locationId="1">
</getThermostatDataRequest>
XML;

$getDataRequestArray = array(
  'Load Control' => $getVoltDataRequestStr,
  'Thermostat' => $getThermostatDataRequestStr
);

function createGetDataRequest($category, $deviceId, $locationId) {
  global $getDataRequestArray;
  $str = $getDataRequestArray[$category];
  echo 'Category: ' . $category;
  echo 'String: ' . $str;
  $xml = new SimpleXMLElement($str);
  $xml['deviceId'] = $deviceId;
  $xml['locationId'] = $locationId;
  return $xml;
}

/*
 * Request action handler
 */

function handleDeviceQueryRequest($requestId) {
  global $connectURL;
  $url = $connectURL;
  $url .= '/connect/device-action/';
  $url .= $requestId;
  
  $headers = array(
    'Accept: application/json',
    'Content-Type: application/xml',
    'Access_Token:' . $_SESSION['access_token']
  );
  $data = array();
  $options = array();
  
  $requestState = '';
  $maxWait = 20; // 20 seconds
  $delay = 0.5; // 1/2 second
  $maxCount = ($maxWait / $delay);
  $i = 0;
  $responseData = NULL;

  while ($requestState !== 'Completed' && $i < $maxCount) {
    // Sleep for delay period       
    usleep($delay * 1000000);
    
    // Check the response
    $response = curl_get($url, $data, $headers, $options);
    echo $i . ' response: ' . $response . '<br><br>';
    
    // Decode the JSON response into a PHP array
    $responseData = json_decode($response, true);

    // Get the requestState variable
    $requestState = $responseData['requestState'];

    $i++;
  }
  
  return $responseData;
}

/*
 * Issue a request to Tendril Connect API(s) when the user is logged in.
 * In this example the Tendril Cost & Consumption API is used to retrieve
 * usage data for a specified period for the authenticated user.
 *
 * HTTP GET request to Tendril Cost & Consumption API:
 *
 *    /connect/user/{user-id}/account/{account-id}/consumption/{resolution}
 * 
 * HTTP Headers:
 *
 *    Accept          - The mime-type of the data that the client expects in the response.
 *    Content-Type    - The mime-type of the data in the client request.
 *    Access_Token    - REQUIRED.  The access token issued by the authorization server.
 *
 * HTTP Path Parameters:
 *
 *    user-id         - REQUIRED.  The user ID.  Use 'current-user' for the authorized user.
 *    account-id      - REQUIRED.  The account ID.  Use 'default-account' for the authorized user's account.
 *    resolution      - REQUIRED.  The resolution for the returned data.  Values must be uppercase and one of HOURLY/DAILY/MONTHLY/RANGE.
 *
 * HTTP Matrix Parameters:
 *
 *    from            - OPTIONAL.  The start date and time for the returned data using RFC 3339 date format (YYYY-MM-DD"T"hh:mm:ssZ)
 *    to              - OPTIONAL.  The end date and time for the returned data using RFC 3339 date format (YYYY-MM-DD"T"hh:mm:ssZ)
 *
 * HTTP Response Parameters:
 *
 *    cost            - Total cost for the entire range.
 *    consumption     - Total consumption for the entire range in kWh.
 *    component       - Cost and consumption data point.
 *    ...             - Please refer to the documentation for additional response parameters.
 *
 * References:
 *
 *    Tendril Connect Overview: Metering                  - http://dev.tendrilinc.com/docs/metering
 *    Tendril Connect API Reference: Cost & Consumption   - http://dev.tendrilinc.com/docs/consumption
 */
if ($_SESSION['loggedin']) {

  // Starting time
  $start_time = microtime(true);

  $action = $params['action'];
  $deviceId = $params['deviceId'];
  $locationId = $_SESSION['locationId'];
  $mode = $params['mode'];

  $getStatus = TRUE; //FALSE;
  
  if (!isset($deviceId)) {
    $deviceId = $_SESSION['deviceId'];
  } else {
    $_SESSION['deviceId'] = $deviceId;
    $getStatus = TRUE;
  }

  $devices = NULL;
  $device = NULL;
  $category = NULL;
  $requestIds = array();
  
  if (isset($_SESSION['devices'])) {
    $devices = $_SESSION['devices'];
    if (!isset($deviceId)) {
      $deviceId = $devices[0]['deviceId'];
    }
    foreach ($_SESSION['devices'] as $key => $value) {
      if ($deviceId === $value['deviceId']) {
        $device = $value;
        $_SESSION['device'] = $value;
        $category = $device['category'][0];
      }
    }
  }

  /*
   * If the devices aren't found, get them via the list action
   */
  if (!isset($devices)) {
    $action = 'list';
  }
  
  /*
  if (!isset($device)) {
    $device = $_SESSION['device'];
  }
  */
  
  if (isset($device)) {
    $category = $device['category'][0];
  }

  /*
  echo 'action: ' . $action . '<br>';
  echo 'deviceId: ' . $deviceId . '<br>';
  echo 'locationId: ' . $locationId . '<br>';
  echo 'mode: ' . $mode . '<br>';
  echo 'device: ' . $device['name'] . '<br>';
  echo 'category: ' . $category . '<br>';
  */
  
  if (!is_null($action)) {
    if ($action === 'list') {
      // Get a list of the user's devices
      $url = $connectURL;
      $url .= '/connect/user/current-user/account/default-account/location/default-location/network/default-network/device;include-extended-properties=true';
      
      $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Access_Token:' . $_SESSION['access_token']
      );
    
      $data = array();
      $options = array();
      $response = curl_get($url, $data, $headers, $options);
      //echo 'response: ' . $response . '<br>';
      // Decode the JSON response into a PHP array.
      $responseData = json_decode($response, true);
      $_SESSION['devices'] = $responseData['device'];
      $devices = $_SESSION['devices'];
      
      if (!isset($deviceId)) {
        $deviceId = $devices[0]['deviceId'];
      }
      foreach ($_SESSION['devices'] as $key => $value) {
        if ($deviceId === $value['deviceId']) {
          $device = $value;
          $_SESSION['device'] = $value;
          $category = $device['category'][0];
        }
      }
      
    } else if ($action === 'get_status') {
      $getStatus = TRUE;
    } else if (strpos($action, "set_volt") !== FALSE) {
      $mode = ($action === 'set_volt_on') ? 'On' : 'Off';
      $xml = createSetVoltDataRequest($deviceId, $locationId, $mode);
  
      $url = $connectURL;
      $url .= '/connect/device-action';
    
      $headers = array(
        'Accept: application/json',
        'Content-Type: application/xml',
        'Access_Token:' . $_SESSION['access_token']
      );
      
      $options = array();
      $response = curl_post_xml($url, $xml->asXML(), $headers, $options);
      //echo 'response: ' . $response . '<br>';
      
      // Decode the JSON response into a PHP array
      $responseData = json_decode($response, true);

      // Get the requestId variable
      $requestId = $responseData['@requestId'];
      $requestIds[$responseData['@requestId']] = FALSE;

      //$getStatus = FALSE;
      
    } else if (strpos($action, "set_thermostat") !== FALSE) {
      $point = $_POST['set_thermostat_point'];
      $mode = $_POST['set_thermostat_mode'];
      $scale = $_POST['set_thermostat_scale'];
      
      $xml = createSetThermostatDataRequest($deviceId, $locationId, $mode, $point, $scale);

      $url = $connectURL;
      $url .= '/connect/device-action';
    
      $headers = array(
        'Accept: application/json',
        'Content-Type: application/xml',
        'Access_Token:' . $_SESSION['access_token']
      );
      
      $options = array();
      $response = curl_post_xml($url, $xml->asXML(), $headers, $options);
      //echo 'setThermostat response: ' . $response . '<br>';
      
      // Decode the JSON response into a PHP array
      $responseData = json_decode($response, true);

      // Get the requestId variable
      $requestId = $responseData['@requestId'];
      $requestIds[$responseData['@requestId']] = FALSE;

      //$getStatus = FALSE;
    }
    
  }

  if ($getStatus === TRUE) {

    $xml = createGetDataRequest($category, $deviceId, $locationId);
    
    $url = $connectURL;
    $url .= '/connect/device-action';
  
    $headers = array(
      'Accept: application/json',
      'Content-Type: application/xml',
      'Access_Token:' . $_SESSION['access_token']
    );
    
    $options = array();
    $response = curl_post_xml($url, $xml->asXML(), $headers, $options);
    //echo $response . '<br>';
    // Decode the JSON response into a PHP array.
    $responseData = json_decode($response, true);
    $requestId = $responseData['@requestId'];
    $requestIds[$responseData['@requestId']] = FALSE;
    
  }
  
  /*
   * Iterate over the requestIds and handle them before returning
   */
  foreach ($requestIds as $key => $value) {
    if ($value === FALSE) {
      $responseData = handleDeviceQueryRequest($key);
      //print_r($responseData);
      $device['lastStatus'] = $responseData['result'];
    }
  }
  
  $end_time = microtime(true);
  $total_time = round(($end_time - $start_time), 2);
  //echo 'total time: '. $total_time . ' s<br>';
}


/*
 * Toggle the info display.
 */
if (isset($_REQUEST['showInfo'])) {
  $_SESSION['showInfo'] = $_REQUEST['showInfo'];
}

?>
<html>
  <head>
    <title>Tendril Connect Sample - Flicker</title>
    <style>
      .darkClass
      {
          background-color: #606060;
          filter:alpha(opacity=50); /* IE */
          opacity: 0.5; /* Safari, Opera */
          -moz-opacity:0.50; /* FireFox */
          z-index: 20;
          height: 100%;
          width: 100%;
          background-repeat:no-repeat;
          background-position:center;
          position:absolute;
          top: 0px;
          left: 0px;
          display: none;
      }
    </style>
  </head>
  <body>
    <div id="darkLayer" class="darkClass"></div>
    <div style="margin:10px 0px;">
      <h3 style="display: inline; margin: 0px 10px 0px 0px;">Tendril Connect Sample - Flicker</h3>
      <?php if($_SESSION['loggedin']) { ?>
      <a href="?logout">Log out</a>
      <?php } ?>
    </div>
    <?php if(isset($_GET['error'])) { ?>
      <p class="error-msg">Error: <?= $_GET['error'] ?></p>
      <p class="error-msg">Error Description: <?= $_GET['error_description'] ?></p>
      <p class="error-msg">Error URI: <?= $_GET['error_uri'] ?></p>
    <?php } ?>
    <?php if($_SESSION['loggedin']) { ?>
    <div>
      <form name="actions" method="post" onsubmit="submitForm()">
        <button name="action" value="list">Refresh Devices</button>
        <select name="deviceId" onchange="submitForm()">
        <?php
          foreach ($_SESSION['devices'] as $key => $value) {
            $name = ($value['name'] == 'null') ? $value['category'][0] : $value['name'];
            $name .=  ' (' . $value['marketingName'] . ')';
            if ($value['deviceId'] == $deviceId) {
        ?>
          <option value="<?= $value['deviceId'] ?>" selected="selected"><?= $name ?></option>
        <?php } else { ?>
          <option value="<?= $value['deviceId'] ?>"><?= $name ?></option>
        <?php } } ?>
        </select>
        
        <div name="details">
        <?php
        
          foreach ($_SESSION['devices'] as $key => $value) {
            if ($value['deviceId'] == $deviceId) {
              if (isset($device['lastStatus'])) {
                $value['lastStatus'] = $device['lastStatus'];
              }
            }
            $name = ($value['name'] == 'null') ? $value['category'][0] : $value['name'];
            $name .=  ' (' . $value['marketingName'] . ')';
            if ($value['deviceId'] == $deviceId) {
        ?>
          <div>
        <?php } else { ?>
          <div style="display: none">
        <?php }?>
          <table>
            <tr>
              <td>Name</td>
              <td><?= $value['name'] ?></td>
            </tr>
            <tr>
              <td>Marketing Name</td>
              <td><?= $value['marketingName'] ?></td>
            </tr>
            <tr>
              <td>Device Id</td>
              <td><?= $value['deviceId'] ?></td>
            </tr>
            <tr>
              <td>Network Id</td>
              <td><?= $value['networkId'] ?></td>
            </tr>
            <tr>
              <td>Category</td>
              <td><?= $value['category'][0] ?></td>
            </tr>
            <?php
              $status = '';
              if ($value['category'][0] === 'Load Control') {
                $status = $value['lastStatus']['mode'];
                $action = $status === 'On' ? 'set_volt_off' : 'set_volt_on';
                $title = $status === 'On' ? 'Off' : 'On';
                $image = $status === 'On' ? 'images/modlet_off_200x320.png' : 'images/modlet_on_200x320.png';
                $background = $status === 'On' ? '#FFFFFF' : '#C0C0C0';
            ?>
            <script type="text/javascript">
              document.body.style.background = "<?= $background ?>";
            </script>
            <tr>
              <td>Status</td>
              <td><?= $status ?>&nbsp;<button name="action" value="<?= $action ?>">Turn <?= $title ?></button></td>
            </tr>
            <tr>
              <td colspan="2">
                <img id="switch" src="<?= $image ?>" onclick="clickSwitch(event, this, '<?= $action ?>');"/>
                <input type="hidden" name="action" value="<?= $action ?>">
              </td>
            </tr>
            <?php
              } else if ($value['category'][0] === 'Thermostat') {                                  
                $setpoint = $value['lastStatus']['setpoint'];
                $mode = $value['lastStatus']['mode'];
                $currentTemp = $value['lastStatus']['currentTemp'];
                $temperatureScale = $value['lastStatus']['temperatureScale'];
            ?>
            <tr>
              <td>Current Temp</td>
              <td><?= $currentTemp ?>&nbsp;<?= $temperatureScale ?></td>
            </tr>
              <td>Temperature Scale</td>
              <td>
                <select name="set_thermostat_scale">
                  <option value="Celsius" selected="<?= $temperatureScale === 'Celsius' ? 'selected' : '' ?>">Celsius</option>
                  <option value="Fahrenheit" selected="<?= $temperatureScale === 'Fahrenheit' ? 'selected' : '' ?>">Fahrenheit</option>
                </select>
            </tr>
            <tr>
              <td>Set Point</td>
              <td><input name="set_thermostat_point" value="<?= $setpoint ?>"/></td>
            </tr>
            <tr>
              <td>Mode</td>
              <td><input name="set_thermostat_mode" value="<?= $mode ?>"/></td>
            </tr>
            <tr>
              <td>&nbsp;</td>
              <td><button name="action" value="set_thermostat">Set</button></td>
            </tr>
            <?php
              }
            ?>
            <tr>
              <td>&nbsp;</td>
              <td><button name="action" value="get_status">Refresh</button></td>
            </tr>
          </table>
          </div>
        <?php } ?>
        </div>
      </form>
    </div>
    <?php } else { ?>
      <a class="btn connect" href="?signin" title="Connect with Tendril"><img src="http://dev.tendrilinc.com/images/connect_green_175x22.png"/></a>
    <?php } ?>
    <script type="text/javascript">
    var submitForm = function(e) {
      //document.getElementById("darkLayer").style.display = "block";
      //document.body.style.background = "#F0F0F0";
      document.body.style.cursor = "wait";
      document.actions.submit();
    }
    var clickSwitch = function(event, obj, action) {
      obj = document.getElementById("switch");
      var rect = obj.getBoundingClientRect();
      var offsetX = event.offsetX ? event.offsetX : event.pageX;
      var offsetY = event.offsetY ? event.offsetY : event.pageY;
      var x = offsetX - rect.left;
      var y = offsetY - rect.top;
      
      if (x >= 84 && x <= 114 && y >= 54 && y <= 86) {
        submitForm();
      }
    }
    </script>
  </body>
</html>