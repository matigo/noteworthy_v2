<?php

/**
 * Class contains the rules and methods called to Authorize Accounts and Verify Tokens
 */
require_once( LIB_DIR . '/functions.php');

class Auth {
    var $settings;
    var $cache;

    function __construct( $Items ) {
        $this->_populateClass( $Items );
    }

    /** ********************************************************************* *
     *  Population
     ** ********************************************************************* */
    /**
     *  Function Populates the Class Using a Token if Supplied
     */
    private function _populateClass( $Items = array() ) {
        $data = ( is_array($Items) ) ? $this->_getBaseArray( $Items ) : array();
        if ( !defined('PASSWORD_LIFE') ) { define('PASSWORD_LIFE', 36525); }
        if ( !defined('ACCOUNT_LOCK') ) { define('ACCOUNT_LOCK', 36525); }
        if ( !defined('TOKEN_PREFIX') ) { define('TOKEN_PREFIX', 'AKGAA_'); }
        if ( !defined('TOKEN_EXPY') ) { define('TOKEN_EXPY', 120); }
        if ( !defined('SHA_SALT') ) { define('SHA_SALT', 'FooBarBeeGnoFoo'); }

        // Set the Class Array Accordingly
        $this->settings = $data;
        $this->cache = false;
        unset($data);
    }

    /**
     *  Function Returns the Basic Array Used by the Authorization Class
     */
    private function _getBaseArray( $Items ) {
        $this->settings = array( 'HomeURL' => str_replace(array('https://', 'http://'), '', $Items['HomeURL']) );
        $Pass = NoNull($Items['account_pass'], NoNull($Items['acctpass'], $Items['password']));
        $Name = NoNull($Items['account_name'], NoNull($Items['acctname'], $Items['lookup']));
        $isHTTPS = ( strpos($Items['HomeURL'], 'https://') !== false ? true : false);
        $data = $this->_getTokenData($Items['token']);

        return array( 'is_valid'     => ((is_array($data)) ? $data['_logged_in'] : false),

                      'token'        => NoNull($Items['token']),
                      'account_name' => NoNull($Name),
                      'account_pass' => NoNull($Pass),

                      'HomeURL'      => str_replace(array('https://', 'http://'), '', $Items['HomeURL']),
                      'ReqType'      => $Items['ReqType'],
                      'PgRoot'       => $Items['PgRoot'],
                      'PgSub1'       => $Items['PgSub1'],
                      'https'        => $isHTTPS,
                     );
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $ReqType = NoNull(strtolower($this->settings['ReqType']));

        /* Perform the Appropriate Action */
        switch ( $ReqType ) {
            case 'get':
                return $this->_performGetAction();
                break;

            case 'post':
            case 'put':
                return $this->_performPostAction();
                break;

            case 'delete':
                return $this->_performDeleteAction();
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, nothing was done */
        return false;
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case 'status':
                return $this->_checkTokenStatus();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, nothing was done */
        return false;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case 'login':
            case '':
                return $this->_performLogin();
                break;

            case 'reset':
                return $this->_performCompleteLogout();
                break;

            case 'signout':
            case 'logout':
                return $this->_performLogout();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, nothing was done */
        return false;
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        /* Do not allow unauthenticated requests */
        if ( !$this->settings['is_valid'] ) {
            $this->_setMetaMessage("You need to sign in to use this API endpoint", 403);
            return false;
        }

        switch ( $Activity ) {
            case '':
                return $this->_performLogout();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, nothing was done */
        return false;
    }

    /** ********************************************************************* *
     *  Public Properties & Functions
     ** ********************************************************************* */
    public function isLoggedIn() { return BoolYN($this->settings['is_valid']); }
    public function performLogout() { return $this->_performLogout(); }
    public function getTokenData( $Token ) { return $this->_getTokenData($Token); }
    public function buildAuthToken( $id = 0, $guid = '' ) { return $this->_buildAuthToken($id, $guid); }

    /**
     *  Function Returns the Reponse Code (200 / 201 / 400 / 401 / etc.)
     */
    public function getResponseCode() {
        return nullInt($this->settings['status'], 200);
    }

    /**
     *  Function Returns any Error Messages that might have been raised
     */
    public function getResponseMeta() {
        return is_array($this->settings['errors']) ? $this->settings['errors'] : false;
    }

    /** ********************************************************************* *
     *  Class Functions
     ** ********************************************************************* */
    /**
     *  Function Returns Any Data That Might Be Associated With a Token
     */
    private function _getTokenData( $Token = '' ) {
        if ( defined('TOKEN_PREFIX') === false || defined('TOKEN_EXPY') === false ) {
            $this->_setMetaMessage("System has not been properly configured.", 500);
            return false;
        }

        /* If We Have the Data, Return It */
        $data = getGlobalObject('token_data');
        if ( is_array($data) ) { return $data; }

        /* Verifiy We Have a Token Value and Split It Accordingly */
        if ( NoNull($Token) == '' ) { return false; }
        $data = explode('_', $Token);
        if ( count($data) != 3 ) { return false; }

        /* Get the Maximum Age of an Account's Password (28.25 years by default) */
        $PassAgeLimit = 10000;
        if ( defined('PASSWORD_LIFE') ) { $PassAgeLimit = nullInt(PASSWORD_LIFE, 10000); }

        /* Confirm that we have the minimum expectations for the Token */
        $tokenGuid = NoNull($data[2]);
        $tokenId = alphaToInt($data[1]);

        if ( mb_strlen($tokenGuid) <= 20 ) { return false; }
        if ( $tokenId <= 0 ) { return false;}

        /* If the Prefix Matches, Validate the Token Data */
        if ( NoNull($data[0]) == str_replace('_', '', TOKEN_PREFIX) ) {
            $ReplStr = array( '[TOKEN_ID]'     => nullInt($tokenId),
                              '[TOKEN_GUID]'   => sqlScrub($tokenGuid),
                              '[LIFESPAN]'     => nullInt(TOKEN_EXPY),
                             );
            $sqlStr = readResource(SQL_DIR . '/auth/getTokenData.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                foreach ( $rslt as $Row ) {
                    $defaultAvatarUrl = getCdnUrl() . '/avatars/default.png';

                    /* Construct the Output */
                    $data = array( '_account_id'    => nullInt($Row['account_id']),
                                   '_account_guid'  => NoNull($Row['account_guid']),
                                   '_account_type'  => NoNull($Row['type'], 'account.unknown'),
                                   '_avatar_file'   => NoNull($Row['avatar_url'], 'default.png'),
                                   '_version'       => nullInt($Row['version']),

                                   '_persona_guid'  => NoNull($Row['persona_guid']),
                                   '_display_name'  => NoNull($Row['display_name'], $Row['first_name']),
                                   '_first_name'    => NoNull($Row['first_name']),
                                   '_last_name'     => NoNull($Row['last_name']),

                                   '_email'         => NoNull($Row['email']),

                                   '_language_code' => NoNull($Row['locale_code']),
                                   '_timezone'      => NoNull($Row['timezone'], 'UTC'),

                                   '_fontfamily'    => NoNull($Row['pref_fontfamily'], 'auto'),
                                   '_fontsize'      => NoNull($Row['pref_fontsize'], 'auto'),
                                   '_colour'        => NoNull($Row['pref_colour'], 'auto'),
                                   '_canemail'      => YNBool($Row['pref_canemail']),

                                   '_is_admin'      => YNBool($Row['is_admin']),
                                   '_token_id'      => nullInt($Row['token_id']),
                                   '_token_guid'    => NoNull($Row['token_guid']),

                                   '_login_at'      => apiDate($Row['login_at'], 'Z'),
                                   '_login_unix'    => apiDate($Row['login_at'], 'U'),
                                   '_logged_in'     => true,
                                  );

                    /* Set the Global Object and Return the Data */
                    if ( is_array($data) && $data['_account_id'] > 0 ) {
                        setGlobalObject('token_data', $data);
                        return $data;
                    }
                }
            }
        }

        /* If we're here, the Token is Invalid */
        return false;
    }

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    /**
     *  Function Attempts to Log a User In with X-Auth (Username/Password Combination)
     *      and returns a Token or Unhappy Boolean
     */
    private function _performLogin() {
        $AcctName = NoNull($this->settings['account_name']);
        $AcctPass = NoNull($this->settings['account_pass']);
        $Token = false;

        // Ensure We Have the Data, and Check the Database
        if ( mb_strlen($AcctName) > 0 && mb_strlen($AcctPass) > 0 && $AcctName != $AcctPass ) {
            $ReplStr = array( '[USERADDR]'   => sqlScrub($AcctName),
                              '[USERPASS]'   => sqlScrub($AcctPass),
                              '[SHA_SALT]'   => sqlScrub(SHA_SALT),
                             );
            $sqlStr = prepSQLQuery( "SELECT token_id, token_guid, account_id FROM auth_local_login('[USERADDR]', '[USERPASS]', '[SHA_SALT]');", $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                foreach ( $rslt as $Row ) {
                    $guid = NoNull($Row['token_guid']);
                    $id = nullInt($Row['token_id']);

                    if ( $id > 0 && mb_strlen($guid) >= 20 ) {
                        $Token = $this->_buildAuthToken($id, $guid);
                        if ( is_string($Token) && mb_strlen($Token) > 30 ) {
                            return array( 'token' => $Token );
                        }
                    }
                }
            }
        }

        /* If we're here, the credentials do not match */
        return $this->_setMetaMessage("Unrecognised Credentials", 401);
    }

    /**
     *  Function Marks a Token Record as isDeleted = 'Y'
     */
    private function _performLogout() {
        $Token = NoNull($this->settings['token']);
        if ( mb_strlen($Token) > 30 ) {
            $data = explode('_', $Token);
            if ( $data[0] == str_replace('_', '', TOKEN_PREFIX) ) {
                $ReplStr = array( '[TOKEN_ID]'   => alphaToInt($data[1]),
                                  '[TOKEN_GUID]' => sqlScrub($data[2]),
                                 );
                $sqlStr = readResource(SQL_DIR . '/auth/setTokenDeleted.sql', $ReplStr);
                $rslt = doSQLQuery($sqlStr);
                if ( is_array($rslt) ) {
                    foreach ( $rslt as $Row ) {
                        return array( 'is_complete'  => YNBool($Row['is_deleted']),
                                      'updated_at'   => apiDate($Row['updated_at'], 'Z'),
                                      'updated_unix' => apiDate($Row['updated_at'], 'U'),
                                     );
                    }
                }
            }
        }

        /* If we're here, the Token is either already expired or unrecognized */
        return $this->_setMetaMessage("Unrecognised Token Reference", 400);
    }

    /**
     *  Function checks the status of the current authentication token and returns an array
     */
    private function _checkTokenStatus() {
        /* If we have data, the token is valid, so let's return it */
        $data = getGlobalObject('token_data');
        if ( is_array($data) ) {
            return array( 'account_id'    => nullInt($data['_account_id']),
                          'type'          => NoNull($data['_account_type']),
                          'display_name'  => NoNull($data['_display_name']),
                          'language_code' => NoNull($data['_language_code']),

                          'logged_in'     => YNBool($data['_logged_in']),
                          'login_at'      => NoNull($data['_login_at']),
                          'login_unix'    => nullInt($data['_login_unix']),
                         );
        }

        /* If We're Here, the Token is Invalid (or Expired) */
        return $this->_setMetaMessage("Invalid or Expired Token Supplied", 400);
    }

    /**
     *  Function recieves a Token.id and Token.guid value and returns a properly-structured Authentication Token
     */
    private function _buildAuthToken( $id = 0, $guid = '' ) {
        if ( mb_strlen(NoNull($guid)) < 36 ) { return ''; }
        if ( nullInt($id) <= 0 ) { return ''; }

        /* Return the String */
        return strtoupper(TOKEN_PREFIX) . ((stripos(TOKEN_PREFIX, '_') === false) ? '_' : '' ) . intToAlpha($id) . '_' . NoNull($guid);
    }

    /** ********************************************************************* *
     *  Class Functions
     ** ********************************************************************* */
    /**
     *  Function Sets a Message in the Meta Field
     */
    private function _setMetaMessage( $msg, $code = 0 ) {
        if ( is_array($this->settings['errors']) === false ) { $this->settings['errors'] = array(); }
        if ( NoNull($msg) != '' ) { $this->settings['errors'][] = NoNull($msg); }
        if ( $code > 0 && nullInt($this->settings['status']) == 0 ) { $this->settings['status'] = nullInt($code); }
        return false;
    }
}
?>