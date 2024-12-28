<?php

/**
 * Class contains the rules and methods called for Accounts
 */
require_once( LIB_DIR . '/functions.php');

class Account {
    var $settings;
    var $strings;
    var $cache;

    function __construct( $settings, $strings = false ) {
        if ( !defined('SHA_SALT') ) { define('SHA_SALT', 'FooBarBeeGnoFoo'); }

        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $this->cache = array();
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        $ReqType = NoNull(strtolower($this->settings['ReqType']));

        /* Do not allow unauthenticated requests */
        if ( in_array($Activity, array('forgot', 'checknick')) === false && !$this->settings['_logged_in'] ) {
            return $this->_setMetaMessage("You need to sign in to use this API endpoint", 403);
        }

        /* Perform the Action */
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
                // Do Nothing
        }

        /* If we're here, then there's nothing to return */
        return false;
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'profile'; }

        switch ( $Activity ) {
            case 'checknick':
            case 'nickcheck':
            case 'nickname':
            case 'nick':
                return $this->_checkNickAvailable();
                break;

            case 'preferences':
            case 'preference':
            case 'prefs':
                return $this->_getPreference();
                break;

            case 'profile':
            case 'me':
                return $this->_getProfile();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, then there's nothing to return */
        return false;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'set'; }

        switch ( $Activity ) {
            case 'create':
                return $this->_createAccount();
                break;

            case 'forgot':
                return $this->_forgotPassword();
                break;

            case 'password':
                return $this->_setAccountPassword();
                break;

            case 'preference':
            case 'welcome':
                return $this->_setMetaRecord();
                break;

            case 'set':
            case 'me':
            case '':
                return $this->_setAccountData();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, then there's nothing to return */
        return $rVal;
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'unset'; }

        switch ( $Activity ) {
            case '':
                /* Do Nothing */
                break;

            default:
                // Do Nothing
        }

        /* If we're here, then there's nothing to return */
        return false;
    }

    /**
     *  Function Returns the Response Type (HTML / XML / JSON / etc.)
     */
    public function getResponseType() {
        return NoNull($this->settings['type'], 'application/json');
    }

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
     *  Public Functions
     ** ********************************************************************* */
    public function getPreference($Key) {
        $data = $this->_getPreference($Key);
        return $data['value'];
    }
    public function getAccountData( $account_id = 0, $version = 0 ) { return $this->_getAccountData( $account_id, $version ); }

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    /**
     *  Function collects as much Account-specific values from the input variables as is possible
     *
     *  Note: this is used for Account creation and updates
     */
    private function _collectValues() {
        $CleanNick = strtolower(NoNull($this->settings['login'], NoNull($this->settings['nickname'], $this->settings['nick'])));
        $CleanGuid = '';

        /* If we are using a "me", ensure the Account.guid is properly identified */
        if ( NoNull($this->settings['PgSub2'], $this->settings['PgSub1']) == 'me' ) {
            $CleanGuid = NoNull($this->settings['_account_guid']);
        }

        /* Return an Array of possible values */
        return array( 'acct_guid'  => NoNull($CleanGuid, NoNull($this->settings['account_guid'], $this->settings['guid'])),
                      'acct_type'  => NoNull($this->settings['account_type'], $this->settings['type']),

                      'nickname'   => preg_replace("/[^a-zA-Z0-9]+/", '', $CleanNick),
                      'password'   => NoNull($this->settings['password'], NoNull($this->settings['account_password'], $this->settings['account_pass'])),
                      'mail'       => NoNull($this->settings['email'], NoNull($this->settings['mail'], $this->settings['mail_addr'])),
                      'avatar'     => NoNull($this->settings['avatar_file'], NoNull($this->settings['avatar_url'], $this->settings['avatar'])),
                      'display_as' => NoNull($this->settings['display_name'], NoNull($this->settings['display_as'], $this->settings['displayas'])),

                      'last_name'  => NoNull($this->settings['last_name'], NoNull($this->settings['last_ro'], $this->settings['lastname'])),
                      'first_name' => NoNull($this->settings['first_name'], NoNull($this->settings['first_ro'], $this->settings['firstname'])),

                      'locale'     => validateLanguage(NoNull($this->settings['locale_code'], $this->settings['locale'])),
                      'timezone'   => NoNull($this->settings['time_zone'], $this->settings['timezone']),
                     );
    }

    /** ********************************************************************* *
     *  Account Management
     ** ********************************************************************* */
    /**
     *  Function creates an account and returns an Object or an unhappy boolean
     */
    private function _createAccount() {
        if ( !defined('DEFAULT_LANG') ) { define('DEFAULT_LANG', 'en_US'); }
        if ( !defined('TIMEZONE') ) { define('TIMEZONE', 'UTC'); }
        if ( !defined('SHA_SALT') ) { return $this->_setMetaMessage("This system has not been configured. Cannot proceed.", 400); }

        /* Accounts can only be created by administrators, so ensure the Account.type is valid */
        if ( in_array($this->settings['_account_type'], array('account.global', 'account.admin')) === false ) {
            return $this->_setMetaMessage("You must be an Administrator to create accounts", 401);
        }

        /* Collect a standardized array of values */
        $data = $this->_collectValues();

        /* Now let's do some basic validation */
        if ( mb_strlen(NoNull($data['password'])) <= 6 ) {
            return $this->_setMetaMessage( "Password is too weak. Please choose a better one.", 400 );
        }

        if ( mb_strlen(NoNull($data['nickname'])) < 4 ) {
            return $this->_setMetaMessage( "Nickname is too short. Please choose a longer one.", 400 );
        }

        if ( mb_strlen(NoNull($data['mail'])) <= 5 ) {
            return $this->_setMetaMessage( "Email address is too short. Please enter a correct address.", 400 );
        }

        if ( validateEmail(NoNull($data['mail'])) === false ) {
            return $this->_setMetaMessage( "Email address does not appear correct. Please enter a correct address.", 400 );
        }

        /* Ensure some sensible defaults exist */
        if ( mb_strlen(NoNull($data['timezone'])) < 5 ) { $data['timezone'] = TIMEZONE; }
        if ( mb_strlen(NoNull($data['locale'])) < 5 ) { $data['locale'] = DEFAULT_LANG; }

        /* If we're here, we *should* be good. Create the account. */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[NICKNAME]'   => sqlScrub($data['nickname']),
                          '[EMAIL]'      => sqlScrub($data['mail']),
                          '[DISPLAY_AS]' => sqlScrub(NoNull($data['display_as'], $data['first_name'])),
                          '[PASSWORD]'   => sqlScrub($data['password']),
                          '[SHA_SALT]'   => sqlScrub(SHA_SALT),

                          '[GENDER]'     => sqlScrub($data['gender']),
                          '[LAST_NAME]'  => sqlScrub($data['last_name']),
                          '[FIRST_NAME]' => sqlScrub($data['first_name']),
                          '[PRINT_NAME]' => sqlScrub($data['print_name']),
                          '[LAST_ALT]'   => sqlScrub($data['last_alt']),
                          '[FIRST_ALT]'  => sqlScrub($data['first_alt']),
                          '[PRINT_ALT]'  => sqlScrub($data['print_alt']),

                          '[LCMS_NO]'    => sqlScrub($data['lcms_no']),
                          '[COSMOS_ID]'  => sqlScrub($data['cosmos_id']),
                          '[LEGACY_ID]'  => sqlScrub($data['legacy_id']),
                          '[LOCALE]'     => sqlScrub($data['locale']),
                          '[TIMEZONE]'   => sqlScrub($data['timezone']),
                          '[TYPE]'       => sqlScrub($data['account_type']),
                         );
        $sqlStr = readResource(SQL_DIR . '/account/setAccountCreate.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                if ( mb_strlen(NoNull($Row['account_guid'])) == 36 ) {
                    $data = $this->_getAccountData($Row['account_id'], $Row['person_version']);
                }
            }
        }

        /* If we're here, we could not create an account for some reason */
        return $this->_setMetaMessage("Could not create account", 400);
    }

    /**
     *  Function Updates the Account fields available to an account-holder
     */
    private function _setAccountData() {
        if ( !defined('DEFAULT_LANG') ) { define('DEFAULT_LANG', 'en_US'); }
        if ( !defined('TIMEZONE') ) { define('TIMEZONE', 'UTC'); }
        if ( !defined('SHA_SALT') ) {
            return $this->_setMetaMessage("This system has not been configured. Cannot proceed.", 400);
        }

        /* Collect a standardized array of values */
        $data = $this->_collectValues();

        /* Only administrators can update records belonging to others */
        if ( NoNull($data['account_guid']) != NoNull($this->settings['_account_guid']) ) {
            if ( in_array($this->settings['_account_type'], array('account.global', 'account.admin')) === false ) {
                return $this->_setMetaMessage("You must be an Administrator to create accounts", 401);
            }
        }

        /* Now let's do some basic validation */
        if ( mb_strlen(NoNull($data['password'])) > 0 && mb_strlen(NoNull($data['password'])) <= 6 ) {
            return $this->_setMetaMessage( "Password is too weak. Please choose a better one.", 400 );
        }

        if ( mb_strlen(NoNull($data['mail'])) <= 5 ) {
            return $this->_setMetaMessage( "Email address is too short. Please enter a correct address.", 400 );
        }

        if ( validateEmail(NoNull($data['mail'])) === false ) {
            return $this->_setMetaMessage( "Email address does not appear correct. Please enter a correct address.", 400 );
        }

        /* Ensure some sensible defaults exist */
        if ( mb_strlen(NoNull($data['timezone'])) < 5 ) { $data['timezone'] = TIMEZONE; }
        if ( mb_strlen(NoNull($data['locale'])) < 5 ) { $data['locale'] = DEFAULT_LANG; }

        /* If we're here, we *should* be good. Create the account. */
        $ReplStr = array( '[ACCOUNT_ID]'   => nullInt($this->settings['_account_id']),
                          '[ACCOUNT_GUID]' => sqlScrub($data['account_guid']),

                          '[EMAIL]'      => sqlScrub($data['mail']),
                          '[DISPLAY_AS]' => sqlScrub(NoNull($data['display_as'], $data['first_name'])),
                          '[PASSWORD]'   => sqlScrub($data['password']),
                          '[SHA_SALT]'   => sqlScrub(SHA_SALT),

                          '[GENDER]'     => sqlScrub($data['gender']),
                          '[LAST_NAME]'  => sqlScrub($data['last_name']),
                          '[FIRST_NAME]' => sqlScrub($data['first_name']),
                          '[PRINT_NAME]' => sqlScrub($data['print_name']),
                          '[LAST_ALT]'   => sqlScrub($data['last_alt']),
                          '[FIRST_ALT]'  => sqlScrub($data['first_alt']),
                          '[PRINT_ALT]'  => sqlScrub($data['print_alt']),

                          '[LCMS_NO]'    => sqlScrub($data['lcms_no']),
                          '[COSMOS_ID]'  => sqlScrub($data['cosmos_id']),
                          '[LEGACY_ID]'  => sqlScrub($data['legacy_id']),
                          '[LOCALE]'     => sqlScrub($data['locale']),
                          '[TIMEZONE]'   => sqlScrub($data['timezone']),
                          '[TYPE]'       => sqlScrub($data['account_type']),
                         );


        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                if ( mb_strlen(NoNull($Row['account_guid'])) == 36 ) {
                    $data = $this->_getAccountData($Row['account_id'], $Row['person_version']);
                }
            }
        }

        /* If we're here, then we could not update the database */
        return $this->_setMetaMessage("Could not update the account record.", 400);
    }

    /**
     *  This function is similar to getProfile, but uses an Account.id and will return *generic* account data that can be cached for faster access
     */
    private function _getAccountData( $account_id, $ver = 0 ) {
        if ( !defined('DEFAULT_LANG') ) { define('DEFAULT_LANG', 'en_US'); }
        if ( !defined('TIMEZONE') ) { define('TIMEZONE', 'UTC'); }

        $account_id = nullInt($account_id);
        $version = nullInt($ver);

        /* Perform some basic validation */
        if ( $account_id <= 0 ) { return false; }

        /* Determine the Correct Cache Key (if the version is zero, check to see if we've already done a lookup in this HTTP request) */
        $CacheKey = 'account-' . substr('00000000' . $account_id, -8) . '-' . $version;
        if ( $version <= 0 ) {
            $propKey = getGlobalObject('prop-' . $CacheKey);
            if ( is_bool($propKey) === false && NoNull($propKey) != '' ) { $CacheKey = $propKey; }
        }

        /* Do we already have data for this Account? */
        $data = getCacheObject($CacheKey);

        /* If we do not have this record in the cache, query it from the database */
        if ( is_array($data) === false ) {
            $ReplStr = array( '[ACCOUNT_ID]' => nullInt($account_id) );
            $sqlStr = readResource(SQL_DIR . '/account/getAccountData.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                $propKey = '';
                $pos = false;

                /* Yep, $data is wiped out with each for, and $pos continues to grow. Ideally this will not happen more than twice. */
                foreach ( $rslt as $Row ) {
                    $propKey = 'account-' . substr('00000000' . nullInt($Row['account_id']), -8) . '-' . nullInt($Row['version']);

                    /* If we have either Account or Person meta records, collect them */
                    $meta = false;
                    if ( YNBool($Row['has_meta']) ) { $meta = $this->_getAccountMeta($Row['account_id']); }

                    $data = array( 'id'          => nullInt($Row['id']),
                                   'guid'        => NoNull($Row['guid']),
                                   'type'        => NoNull($Row['type']),
                                   'avatar_url'  => NoNull($this->settings['HomeURL']) . '/avatars/' . NoNull($meta['profile']['avatar'], 'default.png'),

                                   'display_name' => NoNull($Row['display_name']),
                                   'last_name'   => NoNull($Row['last_name']),
                                   'first_name'  => NoNull($Row['first_name']),
                                   'email'        => NoNull($Row['email']),

                                   'locale'      => NoNull($Row['locale_code'], DEFAULT_LANG),
                                   'timezone'    => NoNull($Row['timezone'], TIMEZONE),

                                   'is_you'      => false,

                                   'created_at'   => apiDate($Row['created_unix'], 'Z'),
                                   'created_unix' => apiDate($Row['created_unix'], 'U'),
                                   'updated_at'   => apiDate($Row['updated_unix'], 'Z'),
                                   'updated_unix' => apiDate($Row['updated_unix'], 'U'),
                                  );
                }

                /* Is the cache key different from the "Proper Key"? */
                if ( $CacheKey != $propKey ) { setGlobalObject('prop-' . $CacheKey, $propKey); }

                /* Cache the Data (if it's valid) */
                if ( mb_strlen($data['guid']) == 36 ) { setCacheObject($CacheKey, $data); }
            }
        }

        /* Set some individualized values */
        if ( nullInt($data['id']) == nullInt($this->settings['_account_id']) ) { $data['is_you'] = true; }

        /* Remove Internal Index */
        unset($data['id']);

        /* If we have something that appears valid, return it. Otherwise, unhappy boolean. */
        if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
        return false;
    }

    /**
     *  Function returns a Meta object for an Account or an unhappy boolean
     *
     *  Note: caching is not used here because it's part of the Account object
     */
    private function _getAccountMeta( $account_id ) {
        if ( nullInt($account_id) <= 0 ) { return false; }

        /* Collect the data */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($account_id) );
        $sqlStr = readResource(SQL_DIR . '/account/getAccountMeta.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = buildMetaArray($rslt);

            /* If the data looks valid, let's return it */
            if ( is_array($data) && count($data) > 0  ) { return $data; }
        }

        /* If we're here, there's nothing */
        return false;
    }

    /** ********************************************************************* *
     *  Password Management Functions
     ** ********************************************************************* */
    /**
     *  Function checks an email address is valid and sends an email to that address
     *      containing some links that allow them to sign into the system.
     */
    private function _forgotPassword() {
        $CleanMail = strtolower(NoNull($this->settings['email'], $this->settings['mail_addr']));

        if ( validateEmail($CleanMail) ) {
            $ReplStr = array( '[MAIL_ADDR]' => sqlScrub($CleanMail) );
            $sqlStr = readResource(SQL_DIR . '/account/chkPasswordReset.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                if ( count($rslt) <= 0 ) { $this->_setMetaMessage("Could not find supplied email address.", 404); }

                foreach ( $rslt as $Row ) {
                    $sOK = false;

                    /* If we are permitted to email, let's do so */
                    if ( YNBool($Row['can_email']) ) {
                        if ( defined('DEFAULT_LANG') === false ) { define('DEFAULT_LANG', 'en-us'); }
                        $locale = strtolower(str_replace('_', '-', NoNull($Row['locale_code'], DEFAULT_LANG)));
                        $template = 'email.forgot_' . NoNull($locale, 'en-us');

                        /* Obtain a properly-structured authentication token */
                        require_once(LIB_DIR . '/auth.php');
                        $auth = new Auth($this->settings);
                        $Token = $auth->buildAuthToken($Row['token_id'], $Row['token_guid']);
                        unset($auth);

                        /* Do not allow a bad authentication token to be returned */
                        if ( mb_strlen($Token) <= 36 ) {
                            return $this->_setMetaMessage("Invalid Authentication Token generated.", 500);
                        }

                        $ReplStr = array( '[FIRST_NAME]' => NoNull($Row['display_name'], $Row['first_name']),
                                          '[AUTH_TOKEN]' => $Token,
                                          '[APP_NAME]'   => NoNull($this->strings['friendly_name'], APP_NAME),
                                          '[HOMEURL]'    => NoNull($this->settings['HomeURL']),
                                         );

                        /* Construct the Message array */
                        $msg = array( 'from_addr' => NoNull(MAIL_ADDRESS),
                                      'from_name' => NoNull($this->strings['friendly_name'], APP_NAME),
                                      'send_from' => NoNull(MAIL_ADDRESS),
                                      'send_to' => NoNull($Row['email']),
                                      'html'    => '',
                                      'text'    => '',
                                     );

                        /* Read the HTML template (if exists) */
                        if ( file_exists(FLATS_DIR . '/templates/' . $template . '.html') ) {
                            $msg['html'] = readResource(FLATS_DIR . '/templates/' . $template . '.html', $ReplStr);
                        } else {
                            $msg['html'] = readResource(FLATS_DIR . '/templates/email.forgot_en-us.html', $ReplStr);
                        }

                        /* Read the TXT template (if exists) */
                        if ( file_exists(FLATS_DIR . '/templates/' . $template . '.txt') ) {
                            $msg['text'] = readResource(FLATS_DIR . '/templates/' . $template . '.txt', $ReplStr);
                        } else {
                            $msg['text'] = readResource(FLATS_DIR . '/templates/email.forgot_en-us.txt', $ReplStr);
                        }

                        /* Send the Message */
                        require_once(LIB_DIR . '/email.php');
                        $mail = new Email($this->settings);
                        $sOK = $mail->sendMail($msg);
                        unset($mail);

                        /* If there's an error, report it */
                        if ( $sOK === false ) { $this->_setMetaMessage("Could not send email", 400); }
                    }

                    /* Return an array */
                    return array( 'is_valid' => ((nullInt($Row['id']) > 0) ? true : false) );
                }
            } else {
                $this->_setMetaMessage("Could not find supplied email address.", 404);
            }

        } else {
            $this->_setMetaMessage("Invalid Email Address provided", 400);
        }

        /* Return an Empty Array, Regardless of whether the data is good or not (to prevent email cycling) */
        return array( 'is_valid' => false );
    }

    /**
     *  Function sets a person's password and returns a simplified confirmation array
     */
    private function _setAccountPassword() {
        $password = NoNull($this->settings['account_password'], $this->settings['password']);
        if ( mb_strlen($password) < 8 ) { return $this->_setMetaMessage("Supplied password is too short.", 400); }

        /* Disallow passwords that consist of fewer than 4 distinct characters */
        if ( count(array_unique(str_split($password))) < 4 ) { return $this->_setMetaMessage("Supplied password is not complex enough.", 400); }

        /* If we're here, let's update the password */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[PASSWORD]' => sqlScrub($password),
                          '[SHA_SALT]' => SHA_SALT
                         );
        $sqlStr = readResource(SQL_DIR . '/account/setAccountPassword.sql', $ReplStr);
        $sOK = doSQLExecute($sqlStr);

        /* Now let's check that the password has been updated and return a simplified array */
        $sqlStr = readResource(SQL_DIR . '/account/chkAccountPasswordSet.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                return array( 'is_set' => YNBool($Row['pass_set']),
                              'tokens' => nullInt($Row['active_tokens']),
                             );
            }
        }

        /* If we're here, then we could not set the Account Password */
        return $this->_setMetaMessage("Could not update account password.", 403);
    }

    /** ********************************************************************* *
     *  Preferences
     ** ********************************************************************* */
    /**
     *  Function Sets a Person's Preference and Returns a list of preferences
     */
    private function _setMetaRecord() {
        $MetaPrefix = NoNull($this->settings['PgSub2'], $this->settings['PgSub1']);
        $CleanValue = NoNull($this->settings['value']);
        $CleanKey = NoNull($this->settings['type'], $this->settings['key']);
        if ( $MetaPrefix != '' && strpos($CleanKey, $MetaPrefix) === false ) { $CleanKey = $MetaPrefix . '.' . $CleanKey; }

        /* Ensure the Key is long enough */
        if ( strlen($CleanKey) < 3 ) { return $this->_setMetaMessage("Invalid Meta Key Passed [$CleanKey]", 400); }

        /* Ensure the Key follows protocol */
        if ( substr_count($CleanKey, '.') < 1 ) { return $this->_setMetaMessage("Meta Key is in the wrong format [$CleanKey]", 400); }

        /* Prep the SQL Statement */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[VALUE]'      => sqlScrub($CleanValue),
                          '[KEY]'        => sqlScrub($CleanKey),
                         );
        $sqlStr = readResource(SQL_DIR . '/account/setAccountMeta.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                return array( 'key'          => NoNull($Row['key']),
                              'value'        => NoNull($Row['value']),

                              'created_at'   => apiDate($Row['created_at'], 'Z'),
                              'created_unix' => apiDate($Row['created_at'], 'U'),
                              'updated_at'   => apiDate($Row['updated_at'], 'Z'),
                              'updated_unix' => apiDate($Row['updated_at'], 'U'),
                             );
            }
        }

        /* If we're here, something failed */
        return $this->_setMetaMessage("Could not save Account Meta record", 400);
    }

    private function _getPreference( $key = '' ) {
        $CleanType = NoNull($key, NoNull($this->settings['type'], $this->settings['key']));
        if ( $CleanType == '' ) {
            return $this->_setMetaMessage("Invalid Type Key Passed", 400);
        }

        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[TYPE_KEY]'   => strtolower(sqlScrub($CleanType)),
                         );
        $sqlStr = readResource(SQL_DIR . '/account/getPreference.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();
            foreach ( $rslt as $Row ) {
                $data[] = array( 'type'         => NoNull($Row['type']),
                                 'value'        => NoNull($Row['value']),

                                 'created_at'   => apiDate($Row['created_at'], 'Z'),
                                 'created_unix' => apiDate($Row['created_at'], 'U'),
                                 'updated_at'   => apiDate($Row['updated_at'], 'Z'),
                                 'updated_unix' => apiDate($Row['updated_at'], 'U'),
                                );
            }

            /* If We Have Data, Return it */
            if ( count($data) > 0 ) { return (count($data) == 1) ? $data[0] : $data; }
        }

        /* Return the Preference Object or an empty array */
        return array();
    }

    /** ********************************************************************* *
     *  Profile Management Functions
     ** ********************************************************************* */
    /**
     *  Function returns an Account profile
     *
     *  Note: the current Accout's data is returned if no Account.guid is supplied
     */
    private function _getProfile( $guid = '' ) {
        if ( NoNull($this->settings['PgSub2'], $this->settings['PgSub1']) == 'me' ) {
            $guid = NoNull($this->settings['_persona_guid']);
        }
        $CleanGuid = NoNull($guid);

        /* Do we have a specific Persona to check? */
        $opts = array('persona_guid', 'profile_guid', 'persona', 'profile', 'guid');
        foreach ( $opts as $opt ) {
            if ( mb_strlen($CleanGuid) != 36 ) { $CleanGuid = NoNull($this->settings[$opt]); }
        }

        /* Perform some basic validation */
        if ( mb_strlen($CleanGuid) != 36 ) {
            return $this->_setMetaMessage("Invalid persona identifier provided", 400);
        }

        /* Query the Database */
        $ReplStr = array( '[ACCOUNT_ID]'   => nullInt($this->settings['_account_id']),
                          '[PERSONA_GUID]' => sqlScrub($CleanGuid),
                         );
        $sqlStr = readResource(SQL_DIR . '/account/getAccountPersona.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                $data = $this->_buildPersonaRecord($Row);
                if ( is_array($data) ) { return $data; }
            }
        }

        /* If we're here, no profile could be found */
        return $this->_setMetaMessage("No persona profile found", 404);
    }

    /**
     *  Function constructs and returns a Profile for a given Account record
     */
    private function _buildPersonaRecord( $Row ) {
        if ( is_array($Row) === false ) { return false; }

        /* Perform some basic validation */
        if ( mb_strlen(NoNull($Row['persona_guid'])) != 36 ) { return false; }

        /* Build the Return Array */
        $defaultAvatarUrl = getCdnUrl() . '/avatars/default.png';

        $data = array( 'guid'    => NoNull($Row['persona_guid']),
                       'email'   => NoNull($Row['persona_email']),

                       'nickname'     => NoNull($Row['nickname']),
                       'display_name' => NoNull($Row['persona_display_name']),
                       'last_name'    => CleanName($Row['persona_last_name']),
                       'first_name'   => CleanName($Row['persona_first_name']),

                       'avatar_url'   => NoNull($Row['avatar_url'], $defaultAvatarUrl),
                       'site_url'     => NoNull($Row['my_site']),
                       'bio'          => NoNull($Row['persona_bio']),

                       'is_you'       => YNBool($Row['is_you']),
                       'is_active'    => YNBool($Row['is_active']),

                       'created_at'   => apiDate($Row['persona_created_at'], 'Z'),
                       'created_unix' => apiDate($Row['persona_created_at'], 'U'),
                       'updated_at'   => apiDate($Row['persona_updated_at'], 'Z'),
                       'updated_unix' => apiDate($Row['persona_updated_at'], 'U'),
                      );

        /* If this isn't our persona, hide some fields */
        if ( YNBool($Row['is_you']) === false ) {
            $excludes = array( 'email', 'is_you', 'updated_at', 'updated_unix' );
            foreach ( $excludes as $ee ) {
                if ( array_key_exists($ee, $data) ) { unset($data[$ee]); }
            }
        }

        /* Return the Array */
        if ( is_array($data) ) { return $data; }
        return false;
    }

    /** ********************************************************************* *
     *  Language Management Functions
     ** ********************************************************************* */
    /**
     *  Function Updates an Account's preferred language so long as the requested language is valid.
     */
    private function _setLanguage() {
        $ReqLang = NoNull($this->settings['language_code'], NoNull($this->settings['lang_code'], $this->settings['value']));
        $CleanLang = validateLanguage($ReqLang);

        /* Prep the SQL Statement */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[LOCALE]'     => sqlScrub($CleanLang)
                         );
        $sqlStr = readResource(SQL_DIR . '/account/setLocaleData.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                return array( 'locale_code'  => NoNull($Row['locale_code']),
                              'updated_at'   => apiDate($Row['updated_at'], 'Z'),
                              'updated_unix' => apiDate($Row['updated_at'], 'U'),
                             );
            }
        }

        /* If we're here, we couldn't update the locale */
        return $this->_setMetaMessage("Could not update account locale", 400);
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