<?php

use SimpleSAML\Auth;

/**
 * This page serves as a ReadID login page.
 *
 * @package SimpleSAMLphp
 */

session_start();

if (!isset($_REQUEST['ReturnTo'])) {
    die('Missing ReturnTo parameter.');
}

$returnTo = \SimpleSAML\Utils\HTTP::checkURLAllowed($_REQUEST['ReturnTo']);

/**
 * What we do here is to extract the $state-array identifier, and check that it belongs to
 * the readid:ReadID process.
 */
if (!preg_match('@State=(.*)@', $returnTo, $matches)) {
    die('Invalid ReturnTo URL for this example.');
}

/**
 * The loadState-function will not return if the second parameter does not
 * match the parameter passed to saveState, so by now we know that we arrived here
 * through the readid:ReadID authentication page.
 */
$stateId = urldecode($matches[1]);

// time to handle login response
$error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'STOP') {
        \SimpleSAML\Module\readid\Auth\Source\ReadID::handleStop($stateId);
    }

    $opaqueId = $_SESSION['opaqueId'];
	\SimpleSAML\Module\readid\Auth\Source\ReadID::handleLogin($stateId, $opaqueId, $returnTo);

} else {
    // if we get this far, we need to show the login page to the user
    $opaqueId = uniqid('', true);
    $_SESSION['opaqueId'] = $opaqueId;

    $info = \SimpleSAML\Module\readid\Auth\Source\ReadID::getQRcode($stateId, $opaqueId);
    $config = \SimpleSAML\Configuration::getInstance();
    $t = new \SimpleSAML\XHTML\Template($config, 'readid:authenticate.tpl.php');
    $translator = $t->getTranslator();
    $t->data['header'] = $translator->t('{readid:readid:title}');
    $t->data['scan_code'] = $translator->t('{readid:readid:scan_code}');
    $t->data['appstore'] = $translator->t('{readid:readid:appstore}');
    $t->data['continue'] = $translator->t('{readid:readid:continue}');
    $t->data['noqr'] = $translator->t('{readid:readid:noqr}');
    $t->data['stop'] = $translator->t('{readid:readid:stop}');
    $t->data['close'] = $translator->t('{readid:readid:close}');
    $t->data['timeout_msg'] = $translator->t('{readid:readid:timeout}');
    $t->data['qrimg'] = $info['base64QR'];
    $t->data['token'] = $info['jwtToken'];
    $t->data['callbackUrl'] = 'http' . ($_SERVER['HTTPS']?'s':'') . '://' . $_SERVER['HTTP_HOST'] . '/simplesaml/module.php/readid/callback.php';
    $t->data['timeout'] = $info['timeout'];
    $t->data['error'] = $error;
    $t->data['returnTo'] = $returnTo;

    $t->show();
}
