<?php

namespace SimpleSAML\Module\readid\Auth;

/**
 * The Callback class holds helper functions.
 *
 */

class Callback {

    private $dbFile = '';
    private $apiBaseUrl = '';
    private $viewerApiKey = '';
    private $callbackUser = '';
    private $callbackPassword = '';
    public $sessionId = '';

    /**
     * Constructor
     *
     */
    public function __construct() {
        error_log('Callback constructor');
        $config = \SimpleSAML\Configuration::getOptionalConfig('config-readid.php');

        assert($config->hasValue('dbFile'));
        $this->dbFile = $config->getValue('dbFile');

        assert($config->hasValue('apiBaseUrl'));
        $this->apiBaseUrl = $config->getValue('apiBaseUrl');

        assert($config->hasValue('viewerApiKey'));
        $this->viewerApiKey = $config->getValue('viewerApiKey');

        assert($config->hasValue('callbackUser'));
        $this->callbackUser = $config->getValue('callbackUser');

        assert($config->hasValue('callbackPassword'));
        $this->callbackPassword = $config->getValue('callbackPassword');
    }

    /**
     * Our callback endpoint requires Authentication
     *
     */
    public function requireAuth() {
        header('Cache-Control: no-cache, must-revalidate, max-age=0');

        error_log('Callback requireAuth');
        $AUTH_USER = $this->callbackUser;
        $AUTH_PASS = $this->callbackPassword;
        $has_credentials = (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']));
        $is_authenticated = (
                $has_credentials &&
                $_SERVER['PHP_AUTH_USER'] == $AUTH_USER &&
                $_SERVER['PHP_AUTH_PW']   == $AUTH_PASS
	);

        if (!$is_authenticated) {
                header('HTTP/1.1 401 Authorization Required');
                header('WWW-Authenticate: Basic realm="Access denied"');
                exit;
        }

        // Extract the info from the post
        $info = json_decode(file_get_contents('php://input'), true);
        $this->sessionId = $info['sessionId'];
        error_log('Callback sessionId: ' . $this->sessionId);
    }

    /**
     * Retrieve info from ReadID Sessions endpoint
     *
     */
    public function getInfo() {
        error_log('Callback getInfo');

        $baseUrl = $this->apiBaseUrl;
        $url = $baseUrl . "/odata/v1/ODataServlet/Sessions('" . $this->sessionId . "')?\$select=";

        //request only the data we use
        $url .= "consolidatedIdentityData/documentType,";
        $url .= "consolidatedIdentityData/documentNumber,";
        $url .= "consolidatedIdentityData/issuingCountry,";
        $url .= "consolidatedIdentityData/dateOfBirth,";
        $url .= "consolidatedIdentityData/nameOfHolder,";
        $url .= "consolidatedIdentityData/primaryIdentifier,";
        $url .= "consolidatedIdentityData/secondaryIdentifier,";
        $url .= "consolidatedIdentityData/selfieVerificationStatus,";
        $url .= "readySession/opaqueId,sessionId";
        //echo $url;

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array(
                'Content-Type:application/json',
                'Accept: application/json',
                'X-Innovalor-Authorization: ' . $this->viewerApiKey,
                'OData-MaxVersion: 4.0',
                'OData-Version: 4.0')
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        # Send request.
        $result = curl_exec($ch);
        curl_close($ch);

        $info = json_decode($result, true);
        $this->insertSession($info);

        $url = $baseUrl . "/odata/v1/ODataServlet/Sessions('" . $this->sessionId . "')";
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array(
                'Content-Type:application/json',
                'Accept: application/json',
                'X-Innovalor-Authorization: ' . $this->viewerApiKey,
                'OData-MaxVersion: 4.0',
                'OData-Version: 4.0')
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        # DELETE request
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");

        # Send request.
        $result = curl_exec($ch);
        $statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        error_log("Callback delete: $statuscode $result");
        curl_close($ch);
    }

    /**
     * Insert session in our temporary DB so that the frontend
     * can send the attributes
     *
     */
    private function insertSession($info) {
        // Get the needed data
        $opaqueId = $info['readySession']['opaqueId'];
        $uid  = $info['consolidatedIdentityData']['issuingCountry'];
        $uid .= $info['consolidatedIdentityData']['documentType'];
        $uid .= $info['consolidatedIdentityData']['documentNumber'];
        $cn = $info['consolidatedIdentityData']['nameOfHolder'];
        $sn = $info['consolidatedIdentityData']['primaryIdentifier'];
        $givenname = $info['consolidatedIdentityData']['secondaryIdentifier'];
        error_log('Callback insertSession: ' . $opaqueId);

        $selfieVerificationStatus = $info['consolidatedIdentityData']['selfieVerificationStatus'];
        $dob = $info['consolidatedIdentityData']['dateOfBirth'];

        // Save the info to a temp database
        try {
            $db = new \PDO("sqlite:" . $this->dbFile);
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $sql  = 'INSERT INTO sessions(opaqueid,uid,cn,givenname,sn,dob,proof) ';
            $sql .= 'VALUES(:opaqueid,:uid,:cn,:givenname,:sn,:dob,:proof)';
            $stmt = $db->prepare($sql);
            $stmt->execute([
             ':opaqueid' => $opaqueId,
             ':uid' => $uid,
             ':cn' => $cn,
             ':givenname' => $givenname,
             ':sn' => $sn,
             ':dob' => $dob,
             ':proof' => $selfieVerificationStatus
            ]);
        } catch(\PDOException $e) {
            echo $e->getMessage();
            die();
        }
    }
}
