# About

This sample application is provided to kickstart app development with the [Tendril Connect HTTP APIs](https://dev.tendrilinc.com/docs).  It provides a barebones [PHP](http://www.php.net/) application with working OAuth2 client support baked-in to make you productive writing apps with our APIs as quickly as possible.

The application authenticates and authorizes the end user, then makes a call to the [/connect/meter/read](https://dev.tendrilinc.com/docs/meter_readings) API after gathering the user's external account id from [/connect/user/{user-id}/account/{account-id}](https://dev.tendrilinc.com/docs/user_external_account_id).

This app is developed using PHP and its entire functionality is contained in a single file, index.php.

# Installation

First clone the repo:

	git clone git@github.com:timbreeden/meter_read <your project name>

Then, place the project contents in your location of choice on your server.