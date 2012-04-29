<?php
/*
 * Tendril Connect - Authentication and Authorization Sample
 * © 2012 Tendril
 *
 * This sample demonstrates using OAUTH 2.0 to authenticate a user via
 * Tendril Connect using password authentication.
 *
 * Please refer to the Tendril Connect API Primer on Authentication for
 * more information:
 *
 *    http://dev.tendrilinc.com/docs/auth
 *
 */

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

$start_time = microtime(true);

session_name('tendril_oauth_password');
session_start();
   
$connectURL           = 'https://dev.tendrilinc.com';
$client_id            = 'YOUR APP KEY';
$client_secret        = 'YOUR APP SECRET';


/*
$connectURL           = 'http://localhost:3000';
$client_id            = '9d4007d6d4d9646df71dfc54356f0271';
$client_secret        = '163679753b141a5795e4f9750982d657';
$externalAccountId    = 'shiflet';
*/

$callbackURL          = 'http://localhost/oauth2-password.php';
$extendedPermissions  = 'account,billing,consumption'; //'account billing consumption';
$refreshThreshold     = 5;


/*
 * Indicates the route the the specified Connect server
 */
#$x_route              = 'greenbutton';
#$x_route              = 'sandbox';
#$x_route              = 'essent';
$x_route               = 'sandbox';

/*
 * Parameter values used for making requests to Tendril Connect APIs.
 * Default values will be overridden by request paramters of the same name.
 */
$paramDefaults = array(
  'limitToLatest' => '20',
  'source' => 'ACTUAL',
  'fromDate' => '2012-01-01T00:00:00-07:00',
  'toDate' => '2012-01-31T00:00:00-07:00',
  'username' => 'kurt.cobain@tendril.com',
  'password' => 'password'
);

$params = array_filter($_REQUEST) + $paramDefaults;

/*
 * Error messages that map to errors from OAuth, etc.
 */
 $errorMessages = array(
  'invalid_grant'     => 'The provided authorization grant is invalid, expired, or revoked. ',
  'invalid_client'    => 'The credentials that you entered do not match an existing account.',
  'missing_username'  => 'The email address you entered is missing or incorrect.',
  'missing_password'  => 'The password address you entered is missing or incorrect.',
  'invalid_protocol'  => 'This resource must be accessed via HTTPS.'
);

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
 * Convenience function for making HTTP GET requests.
 * Replace with http_get or use one of the RESTful PHP classes if desired.
 */
function curl_get($url, array $get = NULL, array $headers = NULL, array $options = array())  {    
  $defaults = array( 
    CURLOPT_URL => $url. (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get), 
    CURLOPT_HEADER => 0, 
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => TRUE, 
    CURLOPT_TIMEOUT => 60,
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
function curl_post($url, array $post = NULL, array $headers = NULL, array $options = array())
{
    $defaults = array(
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_URL => $url,
        CURLOPT_FRESH_CONNECT => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FORBID_REUSE => 1,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_POSTFIELDS => http_build_query($post)
    );

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
if (isset($_POST['logout']) and $_SESSION['loggedin']) {
  
  $url = $connectURL . '/oauth/logout';

  $headers = array(
    'Accept: application/json'
  );
  
  $headers = array();
  $data = array();
  $options = array();
  
  $response = curl_post($url, $data, $headers, $options);

  /*
   * Destroy the session
   */
  $_SESSION = array();
  session_destroy();
}

/*
 * Password authorization (0-legged?)
 *
 * OAuth 2.0 Spec - 4.3.  Resource Owner Password Credentials Grant
 *
 * Similar to Twitter XAuth: https://dev.twitter.com/docs/oauth/xauth
 *
 *
 */
if (isset($_POST['login'])) {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);
  
  $url = $connectURL . '/oauth/access_token';

  $headers = array(
    'Accept: application/json'
  );
  
  $headers = array();
  
  $data = array(
    'grant_type'    => 'password',
    'username'      => $username,
    'password'      => $password,
    'scope'         => $extendedPermissions,
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'route'         => $x_route
  );

  $options = array();
  
  $response = curl_post($url, $data, $headers, $options);

  /*
   * Decode the JSON response into a PHP array.
   */
    
  $data = json_decode($response, true);
  
  if ($data['error']) {
    $error = $data['error'];
    $error_description = $data['error_description'];
  } else {
    
    $_SESSION['access_token']     = $data['access_token'];
    $_SESSION['token_type']       = $data['token_type'];
    $_SESSION['expires_in']       = $data['expires_in'];
    $_SESSION['refresh_token']    = $data['refresh_token'];
    $_SESSION['route']            = $data['route'];
    $_SESSION['scope']            = $data['scope'];
    
    // Set a session value to indicate the user is logged in
    $_SESSION['loggedin']         = true;
    
    $date = date_create();
    $timestamp                    = date_timestamp_get($date);
    $expires_time                 = $timestamp + $data['expires_in'];
    $_SESSION['expires_time']     = $expires_time;
  }
}

/*
 * Compute the remaining time for the access_token.
 * This will be used to refresh the token when it expires.
 */
$curr_time = date_timestamp_get(date_create());
$exp_time = $_SESSION['expires_time'];
$rem_time = $exp_time - $curr_time;

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
  
  # Exchange the refresh token that we have for an access token
  
  $url = $connectURL . '/oauth/access_token';

  $headers = array(
    'Accept: application/json'
  );
  
  $data = array(
    'grant_type'      => 'refresh_token',
    'refresh_token'   => $_SESSION['refresh_token'],
    'scope'           => $extendedPermissions
  );

  $options = array();
  
  //$response = curl_get($url, $data, $headers, $options);
  $response = curl_post($url, $data, $headers, $options);

  /*
   * Decode the JSON response into a PHP array.
   */ 
  $data = json_decode($response, true);
  $_SESSION['access_token']     = $data['access_token'];
  $_SESSION['token_type']       = $data['token_type'];
  $_SESSION['expires_in']       = $data['expires_in'];
  $_SESSION['refresh_token']    = $data['refresh_token'];
  $_SESSION['scope']            = $data['scope'];
  #$_SESSION['route']            = $data['route'];
  
  $date = date_create();
  $timestamp                    = date_timestamp_get($date);
  $expires_time                 = $timestamp + $data['expires_in'];
  $_SESSION['expires_time']     = $expires_time;
  
  //$url = $callbackURL;
  //header("Location: $url", true, 303);
  //die();  
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
  /*
   * Construct the URL using the parameters received from the form POST
   */
  /*
  $url = $connectURL;
  $url .= '/connect/user/current-user/account/default-account/consumption';
  $url .= '/' . $params['resolution'];
  $url .= ';from=' . $params['fromDate'];
  $url .= ';to=' . $params['toDate'];
  */

  $url = $connectURL;
  $url .= '/connect/meter/read';
  $url .= ';external-account-id=' . $_SESSION['externalAccountId'];
  $url .= ';from=' . $params['fromDate'];
  $url .= ';to=' . $params['toDate'];
  $url .= ';limit-to-latest=' . $params['limitToLatest'];
  $url .= ';source=' . $params['source'];
  
  $headers = array(
    'Accept: application/json',
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

$end_time = microtime(true);
$total_time = round(($end_time - $start_time), 2);
?>
<html>
  <head>
    <title>OAuth Password Login and Meter Reading API</title>
    <style>
      #login {display:block; width: 300px;}
      #login .row {clear: both; margin: 10px auto;}
      #login label {}
      #login input {float: right;}
      #login input[type="image"] {float: none; display: block; margin: 0px auto;}
    </style>
  </head>
  <body>
    <h3>Tendril Connect Sample - OAUTH Password Login and Meter Reading API</h3>
    <?php if($_SESSION['loggedin']) { ?>
    <div>
      <form id="logout" name="logout" action="oauth2-password.php" method="post">
        <input type="submit" name="logout" value="Logout"/>
      </form>
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
        <li>x_route: <?= $x_route ?></li>
        <li>total_time: <?= $total_time ?></li>
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
    <?php } else { ?>
      <form id="login" name="login" action="oauth2-password.php" method="post" accept-charset="UTF-8">
        <fieldset>
          <legend>Login</legend>
          <div class="row">
            <label><?= $errorMessages[$error] ?></label>
          </div>
          <input type="hidden" name="login" value="login"/>
          <div class="row">
            <label for="username" >Username:</label>
            <input type="text" name="username" id="username" value="<?= $params['username'] ?>"/>
          </div>
          <div class="row">
            <label for="password" >Password:</label>
            <input type="password" name="password" id="password" value="<?= $params['password'] ?>"/>
          </div>
          <div class="row">
            <input type="image" src="http://dev.tendrilinc.com/images/connect_green_175x22.png" onsubmit="submit-form();"/>
          </div>
        </fieldset>
      </form>
    <?php } ?>
    <hr>
    <h4 onclick="toggleInfo();" style="cursor: pointer">Info (Click to toggle)</h4>
    <div id="info" style="display: <?= $_SESSION['showInfo'] == 'true' ? 'block' : 'none' ?>;">
      <ul style="list-style: none; display: inline-block; margin: 0px; padding: 5px; border: 1px solid #000000">
        <li>url: <?= $url ?></li>
        <li>response: <?= $response ?></li>
        <li>data: <?= $data ?></li>
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
    <script>
      function toggleInfo() {
        var d = document.getElementById('info');
        d.style.display = d.style.display == 'none' ? 'block' : 'none';
        //var show = document.forms['params'].elements['showInfo'].value;
        //document.forms['params'].elements['showInfo'].value = show === 'true' ? 'false' : 'true';
      }
    </script>
  </body>
</html>