<?php
/**
 * This page checks whether there has been a callback
 * and session entered into the temp DB
 *
 * @package SimpleSAMLphp
 */

session_start();
$opaqueId = $_SESSION['opaqueId'];

$db = new PDO("sqlite:/var/www/simplesamlphp/modules/readid/db/database.sqlite");
$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$stmt = $db->query("SELECT * FROM sessions where opaqueid='" . $opaqueId . "' LIMIT 1;");
$result = $stmt->fetch();
# 202 Accepted
if (!$result) http_response_code(202);

