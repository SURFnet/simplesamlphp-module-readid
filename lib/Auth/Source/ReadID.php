<?php

declare(strict_types=1);

namespace SimpleSAML\Module\readid\Auth\Source;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Error;
use SimpleSAML\Module;
use SimpleSAML\Utils;
use SimpleSAML\Logger;

/**
 * ReadID authentication source.
 *
 * This class is a ReadID authentication source which is designed to
 * hook into an external authentication system.
 *
 * @package SimpleSAMLphp
 */
class ReadID extends \SimpleSAML\Auth\Source
{
    /**
     * The key of the AuthID field in the state.
     */
    public const AUTHID = 'readid:AuthID';

    public $dbFile = '';
    public $apiBaseUrl = '';
    public $requesterApiKey = '';
    public $apiCreateSession = '';
    public $iProov = false;
    public $scope = '';
    public $timeout = 0;

    /**
     * Constructor for this authentication source.
     *
     * @param array $info  Information about this authentication source.
     * @param array $config  Configuration.
     */
    public function __construct($info, $config)
    {
        assert(is_array($info));
        assert(is_array($config));

        $readid_config = \SimpleSAML\Configuration::getOptionalConfig('config-readid.php');
        assert(is_array($readid_config));

        // Call the parent constructor first, as required by the interface
        parent::__construct($info, $config);

        // Check configuration
        assert($readid_config->hasValue('dbFile'));
        $this->dbFile = $readid_config->getValue('dbFile');

        assert($readid_config->hasValue('apiBaseUrl'));
        $this->apiBaseUrl = $readid_config->getValue('apiBaseUrl');

        Assert::keyExists($config, 'requesterApiKey');
        $this->requesterApiKey = $config['requesterApiKey'];

        Assert::keyExists($config, 'apiCreateSession');
        $this->apiCreateSession = $config['apiCreateSession'];

        Assert::keyExists($config, 'iProov');
        $this->iProov = $config['iProov'];

        Assert::keyExists($config, 'scope');
        $this->scope = $config['scope'];

        Assert::keyExists($config, 'timeout');
        $this->timeout = $config['timeout'];

        // Do any other configuration we need here
        $db = new \PDO("sqlite:" . $this->dbFile);
        $db->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

        $sql_create_table_sessions = '
                CREATE TABLE IF NOT EXISTS sessions (
                   opaqueid TEXT,
                   uid TEXT,
                   cn TEXT,
                   givenname TEXT,
                   sn TEXT,
                   dob TEXT,
                   proof TEXT
                 )';
        $db->exec($sql_create_table_sessions);
    }

    /**
     * Retrieve attributes for the user.
     *
     * @return array|null  The user's attributes, or NULL if the user isn't authenticated.
     */
    private function getUser(): ?array
    {
        /*
         * In this example we assume that the attributes are
         * stored in the users PHP session, but this could be replaced
         * with anything.
         */

        if (!session_id()) {
            // session_start not called before. Do it here
            session_start();
        }

        if (!isset($_SESSION['uid'])) {
            // The user isn't authenticated
            return null;
        }

        /*
         * Find the attributes for the user.
         * Note that all attributes in SimpleSAMLphp are multivalued, so we need
         * to store them as arrays.
         */

        $attributes = [
            'urn:mace:dir:attribute-def:uid'                          => [$_SESSION['uid']],
            'urn:mace:dir:attribute-def:cn'                           => [$_SESSION['cn']],
            'urn:mace:dir:attribute-def:displayName'                  => [$_SESSION['cn']],
            'urn:mace:dir:attribute-def:givenName'                    => [$_SESSION['givename']],
            'urn:mace:dir:attribute-def:sn'                           => [$_SESSION['sn']],
            'urn:mace:terena.org:attribute-def:schacDateOfBirth'      => [$_SESSION['dob']],
            'urn:mace:dir:attribute-def:eduPersonPrincipalName'       => [$_SESSION['uid'] . '@' . $this->scope],
            'urn:mace:terena.org:attribute-def:schacHomeOrganization' => [$this->scope]
        ];

        return $attributes;
    }


    /**
     * Log in using an external authentication helper.
     *
     * @param array &$state  Information about the current authentication.
     * @return void
     */
    public function authenticate(&$state)
    {
        assert(is_array($state));
        $requester = "Unknown";
        if (isset($state['saml:RequesterID'])) {
            $requester = implode(",", $state['saml:RequesterID']);
        }
        Logger::notice("ReadID Requester: $requester");

        $attributes = $this->getUser();
        if ($attributes !== null) {
            /*
             * The user is already authenticated.
             *
             * Add the users attributes to the $state-array, and return control
             * to the authentication process.
             */
            $state['Attributes'] = $attributes;
            return;
        }

        $state[self::AUTHID] = $this->authId;
        $stateId = Auth\State::saveState($state, 'readid:ReadID');

        $returnTo = Module::getModuleURL('readid/resume.php', [
            'State' => $stateId,
        ]);

        $authPage = Module::getModuleURL('readid/authpage.php');
        Utils\HTTP::redirectTrustedURL($authPage, [
            'ReturnTo' => $returnTo,
        ]);

        /*
         * The redirect function never returns, so we never get this far.
         */
        assert(false);
    }


    /**
     * Resume authentication process.
     *
     * This function resumes the authentication process after the user has
     * entered his or her credentials.
     *
     * @return void
     * @throws \SimpleSAML\Error\BadRequest
     * @throws \SimpleSAML\Error\Exception
     */
    public static function resume()
    {
        /*
         * First we need to restore the $state-array. We should have the identifier for
         * it in the 'State' request parameter.
         */
        if (!isset($_REQUEST['State'])) {
            throw new Error\BadRequest('Missing "State" parameter.');
        }

        /** @var array $state */
        $state = Auth\State::loadState($_REQUEST['State'], 'readid:ReadID');

        $source = Auth\Source::getById($state[self::AUTHID]);
        if ($source === null) {
            /*
             * The only way this should fail is if we remove or rename the authentication source
             * while the user is at the login page.
             */
            throw new Error\Exception('Could not find authentication source with id ' . $state[self::AUTHID]);
        }

        if (!($source instanceof self)) {
            throw new Error\Exception('Authentication source type changed.');
        }

        $attributes = $source->getUser();
        if ($attributes === null) {
            throw new Error\Exception('User not authenticated after login page.');
        }

        $state['Attributes'] = $attributes;
        Auth\Source::completeAuth($state);

        /*
         * The completeAuth-function never returns, so we never get this far.
         */
        assert(false);
    }

    /**
     * Retrieve QR Code form ReadID API
     *
     * This function requests a new ReadID session and returns
     * the needed info to render the QR code screen
     *
     * @param string $stateId
     * @param string $opaqueId
     * @return ReadID info
     * @throws \SimpleSAML\Error\NoState
     * @throws \SimpleSAML\Error\Exception
     */
    public static function getQRcode($stateId, $opaqueId) {
        assert(is_string($stateId));

        /* Retrieve the authentication state. */
        $state = \SimpleSAML\Auth\State::loadState($stateId, 'readid:ReadID');
        if (is_null($state)) {
            throw new \SimpleSAML\Error\NoState();
        }

        /* Find authentication source. */
        assert(array_key_exists(self::AUTHID, $state));

        $source = \SimpleSAML\Auth\Source::getById($state[self::AUTHID]);
        if ($source === null) {
            throw new \Exception('Could not find authentication source with id '.$state[self::AUTHID]);
        }

        if (!($source instanceof self)) {
            throw new Error\Exception('Authentication source type changed.');
        }

       $readidbaseurl = $source->apiBaseUrl;
       $requesterApiKey = $source->requesterApiKey;
       $apiCreateSession = $source->apiCreateSession;
       $url = $readidbaseurl . $apiCreateSession;

       $ch = curl_init( $url );
       # Setup request to send json via POST.
       $payload = json_encode( array( "opaqueID"=>$opaqueId, "TTL"=>300 ) );
       curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );

       curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Innovalor-Authorization: ' . $requesterApiKey,
                'OData-MaxVersion: 4.0',
                'OData-Version: 4.0'));

       # Return response instead of printing.
       curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
       # Send request.
       $result = curl_exec($ch);
       curl_close($ch);

       $info = json_decode($result, true);
       $info['timeout'] = $source->timeout;

       return $info;
    }

    /**
     * Handle login
     *
     * This function handles the login after succesfully
     * scanning the ID document
     *
     * @param string $stateId
     * @param string $opaqueId
     * @param string $returnTo
     * @return void
     * @throws \SimpleSAML\Error\Exception
     */
    public static function handleLogin($stateId, $opaqueId, $returnTo) {
        assert(is_string($stateId));

        /* Retrieve the authentication state. */
        $state = \SimpleSAML\Auth\State::loadState($stateId, 'readid:ReadID');
        if (is_null($state)) {
            throw new \SimpleSAML\Error\NoState();
        }

        /* Find authentication source. */
        assert(array_key_exists(self::AUTHID, $state));

        $source = \SimpleSAML\Auth\Source::getById($state[self::AUTHID]);
        if ($source === null) {
            throw new \Exception('Could not find authentication source with id '.$state[self::AUTHID]);
        }

        if (!($source instanceof self)) {
            throw new Error\Exception('Authentication source type changed.');
        }

        try {
           //Make your connection handler to your database
            $db = new \PDO("sqlite:" . $source->dbFile);
            $db->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

            $stmt = $db->query("SELECT * FROM sessions where opaqueid='" . $opaqueId . "' LIMIT 1;");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            //Logger::notice("iProov config: " . $source->iProov);
            //Logger::notice("iProov: " . $result['proof']);
            $success = (!$source->iProov or ($result['proof'] == "SUCCEEDED"));

            if ($result and $success) {
                if (!session_id()) {
                // session_start not called before. Do it here.
                 session_start();
                }

                $_SESSION['uid'] = $result['uid'];
                $_SESSION['cn'] = $result['cn'];
                $_SESSION['givename'] = $result['givenname'];
                $_SESSION['sn'] = $result['sn'];
                $_SESSION['dob'] = $result['dob'];

                \SimpleSAML\Utils\HTTP::redirectTrustedURL($returnTo);
            } else {
                Logger::error("iProov failed");
                $failed = Module::getModuleURL('readid/failed.php', [
                    'State' => $stateId,
                ]);
                \SimpleSAML\Utils\HTTP::redirectTrustedURL($failed);
            }

        } catch(\PDOException $e) {
            echo $e->getMessage();
            die();
        }
    }

    /**
     * Handle stop
     *
     * This function handles cancellation of the
     * process by returning a SAML Error Response
     *
     * @param string $stateId
     * @return void
     * @throws \SimpleSAML\Error\NoState
     * @throws \SimpleSAML\Error\Error
     */
    public static function handleStop($stateId) {
        assert(is_string($stateId));

        Logger::notice("Authentication stopped");

        /* Retrieve the authentication state. */
        $state = \SimpleSAML\Auth\State::loadState($stateId, 'readid:ReadID');
        if (is_null($state)) {
            throw new \SimpleSAML\Error\NoState();
        }

        Auth\State::throwException($state,
            new \SimpleSAML\Module\saml\Error(
                \SAML2\Constants::STATUS_RESPONDER,
                \SAML2\Constants::STATUS_AUTHN_FAILED,
                'Authentication failed'
            )
        );
    }

    /**
     * This function is called when the user start a logout operation, for example
     * by logging out of a SP that supports single logout.
     *
     * @param array &$state The logout state array.
     * @return void
     */
    public function logout(&$state)
    {
        assert(is_array($state));

        if (!session_id()) {
            // session_start not called before. Do it here
            session_start();
        }

        /*
         * In this example we simply remove the 'uid' from the session.
         */
        unset($_SESSION['uid']);

        /*
         * If we need to do a redirect to a different page, we could do this
         * here, but in this example we don't need to do this.
         */
    }
}
