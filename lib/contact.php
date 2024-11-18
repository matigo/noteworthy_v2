<?php

/**
 * Class contains the rules and functions around Contact messages from websites,
 *      whether they are anonymous or authenticated.
 */
require_once( LIB_DIR . '/functions.php');

class Contact {
    var $settings;
    var $strings;
    var $parser;
    var $cache;

    function __construct( $settings, $strings = false ) {
        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $this->cache = array();
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $ReqType = NoNull(strtolower($this->settings['ReqType']));

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

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Could not recognize [" . strtoupper($ReqType) . "] Activity");
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        /* Check the Account Token is Valid */
        if ( !$this->settings['_logged_in']) {
            return $this->_setMetaMessage("You need to sign in before using this API endpoint", 403);
        }

        switch ( $Activity ) {
            case 'list':
                return $this->_getCommentList();
                break;

            case 'read':
                return $this->_getComment();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Nothing to do: [GET] $Activity", 404);
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case 'write':
            case 'send':
            case 'post':
            case 'set':
                return $this->_setComment();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Nothing to do: [POST] $Activity", 404);
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        /* Check the Account Token is Valid */
        if ( !$this->settings['_logged_in']) {
            return $this->_setMetaMessage("You need to sign in before using this API endpoint", 403);
        }

        switch ( $Activity ) {
            case '':
                return false;
                break;

            default:
                // Do Nothing
        }

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Nothing to do: [DELETE] $Activity", 404);
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
     *  Public Properties & Functions
     ** ********************************************************************* */

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    /**
     *  Function collects as many Contact-specific values from the input variables as is possible
     */
    private function _collectValues() {
        $CleanMessage = '';
        $CleanName = '';
        $CleanMail = '';

        $valids = array('message', 'message_text', 'content', 'content_text', 'comment', 'comment_text', 'text')
        foreach ( $valids as $key ) {

        }


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
     *  Send Message
     ** ********************************************************************* */
    /**
     *  Function records a message against a website and notifies the site owner
     */
    private function _setComment() {


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