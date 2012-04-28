<?php
/*
 * Tendril Connect - Authentication and Authorization Sample
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
session_name('tendril_meter_read');
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
$client_id            = '9fdbc236fd62312f70a83a3dd3f3fd1f';
$client_secret        = '35dc57e3c9ed53480447a46156244ec6';


$callbackURL          = 'http://localhost/meter_read';
$extendedPermissions  = 'account consumption';
$refreshThreshold     = 5;

$x_route               = 'sandbox';

/*
 * Parameter values used for making requests to Tendril Connect APIs.
 * Default values will be overridden by request paramters of the same name.
 */
$paramDefaults = array(
  'limitToLatest' => '20',
  'source' => 'ACTUAL',
  'fromDate' => '2012-03-01T00:00:00-07:00',
  'toDate' => '2012-04-15T00:00:00-07:00'
);

$params = array_filter($_REQUEST) + $paramDefaults;

/*
 * Source parameters used for select values in the UI.
 */
$sourceParams = array(
  'Actual' => 'ACTUAL',
  'Estimate' => 'ESTIMATE'
);

/*
 * Set the timezone to use for date formats.
 * Set the timezone for date formats
 */
date_default_timezone_set('MST');

/*
 * Compute the remaining time for the access_token.
 * This will be used to refresh the token when it expires.
 */
$curr_time = date_timestamp_get(date_create());
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
    'scope'           => $extendedPermissions,
    'route'           => $x_route
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
  $_SESSION['route']            = $data['route'];
  
  $date = date_create();
  $timestamp                    = date_timestamp_get($date);
  $expires_time                 = $timestamp + $data['expires_in'];
  $_SESSION['expires_time']     = $expires_time;
  
  //$url = $callbackURL;
  //header("Location: $url", true, 303);
  //die();  
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
    'client_secret' => $client_secret,
    'route'         => $x_route
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
  $_SESSION['route']            = $data['route'];
  
  // Set a session value to indicate the user is logged in
  $_SESSION['loggedin']         = true;
  
  $date = date_create();
  $timestamp                    = date_timestamp_get($date);
  $expires_time                 = $timestamp + $data['expires_in'];
  $_SESSION['expires_time']     = $expires_time;
  
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

  /*
   * Get the user's external account id if necessary.
   */
   if (!$_SESSION['externalAccountId']) {
     $url = $connectURL;
     $url .= '/connect/user/current-user/account/default-account';
     
     $headers = array(
       'Accept: application/json',
       'Content-Type: application/json',
       'Access_Token:' . $_SESSION['access_token']
     );

     $data = array();
     $options = array();

     $response = curl_get($url, $data, $headers, $options);
     
     $responseData = json_decode($response, true);
     
     $_SESSION['updated'] = date_timestamp_get(date_create());
     
     
     if ( $responseData['@externalAccountId'] ) {
       $_SESSION['externalAccountId'] = $responseData['@externalAccountId'];
     }

   }
   
  $url = $connectURL;
  $url .= '/connect/meter/read';
  $url .= ';external-account-id=' . $_SESSION['externalAccountId'];
  $url .= ';from=' . $params['fromDate'];
  $url .= ';to=' . $params['toDate'];
  $url .= ';limit-to-latest=' . $params['limitToLatest'];
  $url .= ';source=' . $params['source'];
  
  $headers = array(
    'Accept: application/json',
    'Content-Type: application/json',
    'Access_Token:' . $_SESSION['access_token']
  );
  
  $data = array();
  $options = array();
  
  $response = curl_get($url, $data, $headers, $options);
  /*
   * Decode the JSON response into a PHP array.
   */ 
  $responseData = json_decode($response, true);
  $_SESSION['updated']     = date_timestamp_get(date_create());
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
    <title>Meter Read</title>
  </head>
  <body>
    <h3>Tendril Connect Sample - OAUTH and Meter Readings API</h3>
    <?php if($_SESSION['loggedin']) { ?>
    <div>
      <a href="?logout">Log out</a>
    </div>
    <?php } ?>
    <?php if(isset($_GET['error'])) { ?>
      <p class="error-msg">Error: <?= $_GET['error'] ?></p>
      <p class="error-msg">Error Description: <?= $_GET['error_description'] ?></p>
      <p class="error-msg">Error URI: <?= $_GET['error_uri'] ?></p>
    <?php } ?>
    <?php if($_SESSION['loggedin']) { ?>
    <div>
      <hr>
      <ul>
        <li>updated: <?= $_SESSION['updated'] ?></li>
        <li>access_token: <?= $_SESSION['access_token'] ?></li>
        <li>token_type: <?= $_SESSION['token_type'] ?></li>
        <li>expires_in: <?= $_SESSION['expires_in'] ?></li>
        <li>refresh_token: <?= $_SESSION['refresh_token'] ?></li>
        <li>x_route: <?=$_SESSION['route'] ?></li>
      </ul>
      <hr>
      <h4>Settings</h4>
      <form name="params" method="post">
        <p>
          <label for="fromDate">From</label>
          <input type="text" name="fromDate" id="fromDate" value="<?= $params['fromDate'] ?>">
          <label for="toDate">To</label>
          <input type="text" name="toDate" id="toDate" value="<?= $params['toDate'] ?>">
          <label for="limitToLatest">Limit</label>
          <input type="text" name="limitToLatest" id="limitToLatest" value="<?= $params['limitToLatest'] ?>">
          <label for="resolution">Source</label>
          <select name="source">
          <?php
            foreach ($sourceParams as $key => $value) {
              if ($value == $params['source']) {
          ?>
            <option value="<?= $value ?>" selected="selected"><?= $key ?></option>
          <?php } else { ?>
            <option value="<?= $value ?>"><?= $key ?></option>
          <?php } } ?>
          </select> 
        </p>
        <p>
          <input type="hidden" name="showInfo" value="<?= $_SESSION['showInfo'] ?>"/>
          <input type="submit" value="Update"/>
        </p>
      </form>
      <hr>
      <h4>Readings</h4>
        <ul>
      <?php
        $meterReadings = $responseData['MeterReading'];
        foreach ($meterReadings as $meterReading) {
          $readings = $meterReading['Readings'];
          foreach ($readings as $reading) {
      ?>
          <li>
            Reading: <?= $reading['value'] ?> on: <?= date('m/d/Y h:i:s a', strtotime($reading['timeStamp'])) ?>
          </li>
      <?
          }
        }         
      ?>
        </ul>      
      <p>
        response: <?= $response ?>
      </p>
      <p>
        responseData: <?= $responseData ?>
      </p>
      <p>
        <?= $responseData['MeterReading'][0]['Readings'][0]['value'] ?>
      </p>
    </div>
    <hr>
    <h4 onclick="toggleInfo();" style="cursor: pointer">Info (Click to toggle)</h4>
    <div id="info" style="display: <?= $_SESSION['showInfo'] == 'true' ? 'block' : 'none' ?>;">
      <ul style="list-style: none; display: inline-block; margin: 0px; padding: 5px; border: 1px solid #000000">
        <li>url: <?= $url ?></li>
        <li>access_token: <?= $_SESSION['access_token'] ?></li>
        <li>token_type: <?= $_SESSION['token_type'] ?></li>
        <li>expires_in: <?= $_SESSION['expires_in'] ?></li>
        <li>refresh_token: <?= $_SESSION['refresh_token'] ?></li>
        <li>scope: <?= $_SESSION['scope'] ?></li>
        <li>authorize_state: <?= $_SESSION['authorize_state'] ?></li>
        <li>check_state: <?= $_SESSION['check_state'] ?></li>
        <li>expires_time (timestamp): <?= $_SESSION['expires_time'] ?></li>
        <li>Current Time: <?php echo(date('m/d/Y h:i:s a', $curr_time)); ?></li>
        <li>Expires Time: <?php echo(date('m/d/Y h:i:s a', $exp_time)); ?></li>
        <li>Refresh Seconds: <?php echo($rem_time); ?></li>
      </ul>
    </div>
    <?php } else { ?>
      <a class="btn connect" href="?signin" title="Connect with Tendril"><img src="http://dev.tendrilinc.com/images/connect_green_175x22.png"/></a>
    <?php } ?>
    <script>
      function toggleInfo() {
        var d = document.getElementById('info');
        d.style.display = d.style.display == 'none' ? 'block' : 'none';
        var show = document.forms['params'].elements['showInfo'].value;
        document.forms['params'].elements['showInfo'].value = show === 'true' ? 'false' : 'true';
      }
    </script>
  </body>
</html>