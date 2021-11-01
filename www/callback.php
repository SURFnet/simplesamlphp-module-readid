<?php

use SimpleSAML\Logger;

/*
*
*The ReadID ready server will post a json to this callback, containing a sessionid
*
*/

$callback = new \SimpleSAML\Module\readid\Auth\Callback();

// The ReadID callback used basic auth
$callback->requireAuth();

Logger::notice('Callback sessionId: ' . $callback->sessionId);

// Request the info from ReadID
$callback->getInfo();

error_log('Callback done');
