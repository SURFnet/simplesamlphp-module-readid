# ReadID simpleSAMLphp auth module

This module adds ReadID Ready App as auth source to simpleSAMLphp. ReadID Ready is an app that can read the data from your passport or other identity documents. Besides collecting your personal data from the document chip it also verifies if your identity document is valid and authentic. By using this module you can use an ID-card, passport or drivers-licence to authenticate.

## Disclaimer
This module was not created by ReadID or InnoValor (the company that created ReadID) and will not be supported by them. Please use [github issues](https://github.com/SURFnet/simplesamlphp-module-readid/issues/new) for any questions about this module.
A valid contract for using ReadID Ready is required for using this module. For more information on ReadID see https://www.readid.com/

## Usage
SimpleSAMLphp makes use of Composer to manage dependencies and third-party modules. Just execute the following command:

```composer.phar require surf/simplesamlphp-module-readid```

Please note that if you don't have console access to your web server, you will need to deploy the module somewhere else and then copy the files to your server.

## Config
Add the authentication source to ```authsource.php```:
```
$config = [
...
    'readid' => [
        'readid:ReadID',
        'apiCreateSession' => '/odata/v1/ODataServlet/createReadySession',
        'requesterApiKey' => <YOUR REQUESTER KEY>
        'iProov' => true,
        'scope' => '<YOUR ORGANISATIONAL SCOPE>',
        'timeout' => 180,
    ],
...
```

- apiCreateSession, you probably won't have to change this
- requesterApiKey, is the ReadID key that can request sessions
- iProov, enable or disable iProov during the ReadID process
- scope, the scope added to scoped attributes. You can adjust the (SAML) attribute mapping in lib/Auth/Source/ReadID.php::getUser()
- timeout, how long does the user have to complete the reading of their document before a timeout message is shown

The authsource also needs a specific readid configuration ```config-readid.conf``` in the config directory:

```
<?php
$config = [
    'dbFile' => '<YOUR SQLITE DB LOCATION>',
    'apiBaseUrl' => 'https://<the readid endpoint base>',
    'apiReadSession' => '/odata/v1/ODataServlet/Sessions',
    'viewerApiKey' => <YOUR VIEWER KEY>,
    'callbackUser' => <ReadID callback username>,
    'callbackPassword' => <ReadID callback password>,
];
```

- dbfile contains the location of the temporary session database. This is an sqlite file and needs to be writeable by the webserver user.
- apiBaseUrl is the ReadID base URL you want to use (without trailing slash), please consult the ReadID documentation
- apiReadSession, you probably won't have to change this
- viewerApiKey is the ReadID key that can view sessions
- callbackUser and callbackPassword are the user/password you configure at ReadID so that they can call our authenticated callback endpoint
