# About

These three sample applications are provided to kickstart app development with the [Tendril Connect HTTP APIs](https://dev.tendrilinc.com/docs).  They provides barebones [PHP](http://www.php.net/) functionaly with working OAuth2 client support baked-in to make you productive writing apps with our APIs as quickly as possible.

Each application is a self-contained PHP file.

##Meter Read
Meter Read (meter-read.php) authenticates the user with a 3-legged OAuth mechanism, redirecting the client to Tendril's Authentication and Authorization dialogs. It then makes a call to the [/connect/meter/read](https://dev.tendrilinc.com/docs/meter_readings) API after gathering the user's external account id from [/connect/user/{user-id}/account/{account-id}](https://dev.tendrilinc.com/docs/user_external_account_id).

##Flicker
Flicker (flicker.php) also authenticates the user with a 3-legged OAuth mechanism. It then makes a call to the [/connect/device-action](http://dev.tendrilinc.com/docs/create_device_action) to initiate a device action such as a smart plug power toggle. The application uses a request-action pattern, with calls to [/connect/device-action/{request-id}](http://dev.tendrilinc.com/docs/query_device_action) using the request token generated from the initial call. More information about the request-action pattern can be found in the [Devices](http://dev.tendrilinc.com/docs/devices) section of the Tendril API Primer.

##OAuth 2 Password
OAuth 2 Password (oauth2-password.php) authenticates the user with its own login dialog, using the OAuth 2 "0-legged" mechanism. It then makes the same API calls as the Meter Read application.

# Installation

First clone the repo:

	git clone git@github.com:TendrilDevProgram/tendril-php-oauth2-samples <your project name>

Then, place the project contents in your location of choice on your server.