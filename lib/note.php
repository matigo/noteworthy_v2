<?php

/**
 * Class contains the rules and methods called for Note handling
 */
require_once( LIB_DIR . '/functions.php');
require_once(LIB_DIR . '/markdown/MarkdownExtra.inc.php');
use Michelf\MarkdownExtra;

class Note {
    var $settings;
    var $strings;
    var $parser;
    var $acct;

    function __construct( $settings, $strings = false ) {
        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $this->parser = false;
        $this->acct = false;
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $ReqType = strtolower(NoNull($this->settings['ReqType'], 'GET'));

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
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Unrecognized Request Type: " . strtoupper($ReqType), 404);
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'read'; }

        switch ( $Activity ) {
            case 'read':
                return $this->_getNoteByGuid();
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Nothing to do: [GET] $Activity", 404);
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'set'; }

        /* Check the Account Token is Valid */
        if ( !$this->settings['_logged_in']) { return $this->_setMetaMessage("You need to sign in before using this API endpoint", 403); }

        switch ( $Activity ) {
            case 'write':
            case 'set':
                return $this->_setNoteData();
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Nothing to do: [POST] $Activity", 404);
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'scrub'; }

        /* Check the Account Token is Valid */
        if ( !$this->settings['_logged_in']) { return $this->_setMetaMessage("You need to sign in before using this API endpoint", 403); }

        switch ( $Activity ) {
            case '':
                return array( 'activity' => "[DELETE] /session/$Activity" );
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Nothing to do: [DELETE] $Activity", 404);
    }

    /**
     *  Function Returns the Response Type (HTML / XML / JSON / etc.)
     */
    public function getResponseType() {
        return NoNull($this->settings['type'], 'text/html');
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
    public function getNoteDetails( $note_id, $version = 0 ) { return $this->_getNoteById($note_id, $version); }

    /** ********************************************************************* *
     *  Data Reading Functions
     ** ********************************************************************* */
    /**
     *  Function reads the input values and returns a standardized array
     */
    private function _getInputValues() {
        $TextKeys = array( 'content_text', 'content', 'note_text', 'note', 'text', 'value' );
        $CleanText = '';
        foreach ( $TextKeys as $Key ) {
            if ( mb_strlen($CleanText) <= 0 && array_key_exists($Key, $this->settings) ) { $CleanText = NoNull($this->settings[$Key]); }
        }

        $CleanParent = NoNull($this->settings['parent_guid'], $this->settings['parent']);
        $CleanTitle = NoNull($this->settings['note_title'], $this->settings['title']);
        $CleanGuid = NoNull($this->settings['note_guid'], $this->settings['guid']);
        $CleanType = NoNull($this->settings['note_type'], $this->settings['type']);

        $CleanDate = NoNull($this->settings['note_date'], $this->settings['date']);
        if ( mb_strlen($CleanDate) > 10 ) { if ( validateDate($CleanDate, 'Y-m-d H:i:s') !== true ) { $CleanDate = ''; } }
        if ( mb_strlen($CleanDate) == 10 ) { if ( validateDate($CleanDate) !== true ) { $CleanDate = ''; } }

        /* Is there a sort order? */
        $sort = nullInt($this->settings['sort_order'], $this->settings['order']);
        if ( $sort > 9999 ) { $sort = 9999; }
        if ( $sort < 0 ) { $sort = 0; }

        /* Do we have tags to parse? */
        $CleanTags = '';
        $TagList = NoNull($this->settings['note_tags'], $this->settings['tags']);
        if ( mb_strlen($TagList) > 0 ) {
            $TagList = str_replace(array(','), '|', "$TagList|");
            $tags = explode('|');
            $vv = array();

            foreach ( $tags as $tag ) {
                $tag = NoNull($tag);
                $tt = strtolower($tag);
                if ( mb_strlen($tt) > 0 && in_array($tt, $vv) === false ) { $vv[] = $tag; }
            }

            /* If we have tags, let's set the value */
            if ( is_array($vv) && count($vv) > 0 ) { $CleanTags = implode('|', $vv); }
        }

        /* Get the Boolean Options */
        $mdown = NoNull($this->settings['as_markdown'], $this->settings['markdown']);

        /* Build the output array */
        return array( 'title'   => ((mb_strlen($CleanTitle) > 0) ? $CleanTitle : ''),
                      'content' => NoNull($CleanText),

                      'parent'  => ((mb_strlen($CleanParent) == 36) ? $CleanParent : ''),
                      'guid'    => ((mb_strlen($CleanGuid) == 36) ? $CleanGuid : ''),
                      'type'    => ((mb_strlen($CleanType) > 0) ? $CleanType : 'general'),
                      'date'    => ((mb_strlen($CleanDate) >= 10) ? $CleanDate : ''),

                      'tags'    => NoNull($CleanTags),

                      'sort_order' => nullInt($sort, 5000),
                      'publish_at' => '',
                      'expires_at' => '',

                      'markdown' => YNBool($mdown),
                     );
    }

    /** ********************************************************************* *
     *  Reading Functions
     ** ********************************************************************* */
    /**
     *  Function returns a Note based on the Guid provided
     */
    private function _getNoteByGuid() {
        $inputs = $this->_getInputValues();

        /* Perform some basic validation */
        $CleanGuid = NoNull($inputs['guid']);
        if ( mb_strlen($CleanGuid) != 36 ) { return $this->_setMetaMessage("Invalid Note Identifier Provided", 400); }

        /* Collect the Library */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[NOTE_GUID]'  => sqlScrub($CleanGuid),
                         );
        $sqlStr = readResource(SQL_DIR . '/note/getNoteByGuid.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                if ( YNBool($Row['can_access']) ) {
                    $data = $this->_getNoteById($Row['note_id'], $Row['note_version']);

                    /* If we have data, let's return it */
                    if ( is_array($data) && count($data) ) { return $data; }

                } else {
                    return $this->_setMetaMessage("You do not have permission to access this note", 403);
                }
            }
        }

        /* If we're here, then no note could be found */
        return $this->_setMetaMessage("Could not find requested Note", 404);
    }

    /** ********************************************************************* *
     *  Writing Functions
     ** ********************************************************************* */
    /**
     *  Function creates or updates a Note record and returns an updated Note object
     */
    private function _setNoteData() {
        $inputs = $this->_getInputValues();

        /* Ensure we have the bare minimum */
        if ( mb_strlen(NoNull($inputs['title']) . NoNull($inputs['content'])) <= 0 ) {
            return $this->_setMetaMessage("A title or some content is required for a Note", 400);
        }

        /* Construct the SQL statement and execute */
        $ReplStr = array( '[ACCOUNT_ID]'  => nullInt($this->settings['_account_id']),
                          '[TITLE]'       => sqlScrub($inputs['title']),
                          '[CONTENT]'     => sqlScrub($inputs['content']),
                          '[TYPE]'        => sqlScrub($inputs['type']),
                          '[TAG_LIST]'    => sqlScrub($inputs['tags']),

                          '[NOTE_GUID]'   => sqlScrub($inputs['guid']),
                          '[PARENT_GUID]' => sqlScrub($inputs['parent']),

                          '[PUBLISH_AT]'  => sqlScrub($inputs['publish_at']),
                          '[EXPIRES_AT]'  => sqlScrub($inputs['expires_at']),
                          '[SORT_ORDER]'  => nullInt($inputs['sort_order']),
                         );
        $sqlStr = readResource(SQL_DIR . '/note/setNoteData.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                $note_id = nullInt($Row['note_id']);

                /* So long as we have a Note.id, let's continue */
                if ( $note_id > 0 ) {
                    /* Save any meta data we might have */
                    $meta = false;


                    $mOK = $this->_setMetaRecord($note_id, $meta);

                    /* Return a completed Note object */
                    $data = $this->_getNoteById($note_id, $Row['version']);
                }
            }
        }

        /* If we're here, the data could not be saved */
        return $this->_setMetaMessage("Could not save Note data", 400);
    }

    /**
     *  Function writes the meta data in $meta to the database and returns either a version or an unhappy boolean response
     *
     *  Note: Meta keys that are not included in the $meta object WILL NOT be removed from the database
     */
    private function _setMetaRecords( $note_id, $meta ) {
        if ( is_array($meta) === false ) { return false; }
        $note_id = nullInt($note_id);
        $version = 0;

        /* Perform some basic validation */
        if ( $note_id <= 0 ) { return false; }

        /* Run through the meta */
        foreach ( $meta as $Key=>$Value ) {
            $isOK = true;

            /* Perform some validation on the key */
            if ( mb_strlen($Key) <= 0 ) { $isOK = false; }
            if ( mb_strpos($Key, '.') === false ) { $isOK = false; }

            /* If things appear okay, record it to the database */
            if ( $isOK ) {
                $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                                  '[NOTE_ID]'    => nullInt($note_id),
                                  '[VALUE]'      => sqlScrub($Value),
                                  '[KEY]'        => sqlScrub($Key),
                                 );
                $sqlStr = readResource(SQL_DIR . '/note/setNoteMeta.sql', $ReplStr);
                $rslt = doSQLQuery($sqlStr);
                if ( is_array($rslt) ) {
                    foreach ( $rslt as $Row ) {
                        if ( nullInt($Row['version']) > $version ) { $version = nullInt($Row['version']); }
                    }
                }
            }
        }

        /* If we've updated some meta, let's return the version number */
        if ( $version > 0 ) { return $version; }

        /* If we're here, something failed */
        return false;
    }

    /** ********************************************************************* *
     *  Delete Functions
     ** ********************************************************************* */
    /**
     *  Function marks a Note record as deleted and returns a basic confirmation array
     */
    private function _deleteNote() {

    }

    /**
     *  Function marks a Meta key on a Note as deleted
     */
    private function _deleteNoteMeta() {

    }

    /**
     *  Function marks a Tag on a Note as deleted
     */
    private function _deleteNoteTag() {

    }

    /** ********************************************************************* *
     *  Interntal Functions
     ** ********************************************************************* */
    /**
     *  Function returns a Note by Id if it exists and is visible, otherwise an unhappy boolean
     */
    private function _getNoteById( $note_id, $version = 0 ) {
        $note_id = nullInt($note_id);
        $version = nullInt($version);

        /* Perform a basic error check */
        if ( $note_id <= 0 ) { return false; }

        $CachePrefix = 'note-' . paddNumber($note_id, 8) . '.' . paddNumber($this->settings['_account_id'], 8);
        $data = false;

        /* Is there a "proper" version number for this Post? */
        $propVer = false;
        if ( $version <= 0 ) { $propVer = getGlobalObject($CachePrefix); }
        if ( is_bool($propVer) === false && nullInt($propVer) > 1000 ) {
            $version = nullInt($propVer);
        }

        /* Append the version to the prefix */
        $CacheKey = $CachePrefix . '-' . nullInt($version);

        /* If we have a valid version, check to see if we have the requested data in the cache */
        if ( $version > 1000 ) { $data = getCacheObject($CacheKey); }

        /* If we do not have data, collect it from the DB */
        if ( is_array($data) === false ) {
            $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                              '[NOTE_ID]'    => nullInt($note_id)
                             );
            $sqlStr = readResource(SQL_DIR . '/note/getNoteById.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                if ( is_bool($this->acct) ) {
                    require_once(LIB_DIR . '/account.php');
                    $this->acct = new Account($this->settings, $this->strings);
                }

                foreach ( $rslt as $Row ) {
                    if ( YNBool($Row['can_access']) ) {
                        $parent = false;
                        $thread = false;
                        $meta = false;
                        $tags = false;

                        if ( nullInt($Row['version']) > $version ) { $version = nullInt($Row['version']); }
                        if ( YNBool($Row['has_meta']) ) { $meta = $this->_getNoteMetaData($Row['note_id']); }
                        if ( YNBool($Row['has_tags']) ) { $tags = $this->_getNoteTags($Row['note_id']); }

                        if ( nullInt($Row['thread_id']) > 0 ) {
                            $thread = array( 'guid'    => NoNull($Row['thread_guid']),
                                             'hash'    => NoNull($Row['thread_hash']),
                                             'version' => nullInt($Row['thread_version']),
                                            );
                        }

                        if ( nullInt($Row['parent_id']) > 0 ) {
                            $parent = array( 'guid'    => NoNull($Row['parent_guid']),
                                             'hash'    => NoNull($Row['parent_hash']),
                                             'version' => nullInt($Row['parent_version']),
                                            );
                        }

                        /* Construct the output array */
                        $data = array( 'guid'    => NoNull($Row['guid']),
                                       'type'    => NoNull($Row['type']),

                                       'title'   => NoNull($Row['title']),
                                       'content' => array( 'text' => $this->_getPlainText($Row['content']),
                                                           'html' => $this->_getMarkdownHTML($Row['content']),
                                                          ),

                                       'hash'    => NoNull($Row['hash']),
                                       'meta'    => $meta,
                                       'tags'    => $tags,

                                       'owner'          => $this->acct->getAccountData($Row['account_id'], $Row['account_version']),
                                       'thread'         => $thread,
                                       'parent'         => $parent,
                                       'has_history'    => YNBool($Row['has_history']),

                                       'created_at'     => apiDate($Row['created_at'], 'Z'),
                                       'created_unix'   => apiDate($Row['created_at'], 'U'),
                                       'updated_at'     => apiDate($Row['updated_at'], 'Z'),
                                       'updated_unix'   => apiDate($Row['updated_at'], 'U'),
                                       'updated_by'     => $this->acct->getAccountData($Row['updated_by'], $Row['updated_by_version']),
                                      );
                    }
                }
            }

            /* Ensure the Cache Key is set to the proper Post version */
            if ( $version > 1000 ) { $CacheKey = $CachePrefix . '-' . $version; }

            /* If we have valid data, let's save it */
            if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { setCacheObject($CacheKey, $data); }

            /* Set the Post version for future queries of the same object (if required) */
            setGlobalObject($CachePrefix, nullInt($version));
        }

        /* If we have valid data, return it. Otherwise, unhappy boolean. */
        if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
        return false;
    }

    /**
     *  Function collects and returns the meta data for a given Note in an array or an unhappy boolean
     *
     *  Note: caching is not used here becuase the Note object itself is cached
     */
    private function _getNoteMetaData( $note_id ) {
        $note_id = nullInt($note_id);
        if ( $note_id <= 0 ) { return false; }

        /* Let's collect the data */
        $ReplStr = array( '[NOTE_ID]' => nullInt($note_id) );
        $sqlStr = readResource(SQL_DIR . '/note/getNoteMetaData.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = buildMetaArray($rslt);

            /* If we have data, let's return it */
            if ( is_array($data) && count($data) > 0 ) { return $data; }
        }

        /* If we're here, there is nothing */
        return false;
    }

    /**
     *  Function collects the tags that are associated with a Note and returns an array or an unhappy boolean
     *
     *  Note: caching is not used here becuase the Note object itself is cached
     */
    private function _getNoteTags( $note_id ) {
        $note_id = nullInt($note_id);
        if ( $note_id <= 0 ) { return false; }

        /* Let's collect the data */
        $ReplStr = array( '[NOTE_ID]' => nullInt($note_id) );
        $sqlStr = readResource(SQL_DIR . '/note/getNoteTags.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();

            foreach ( $rslt as $Row ) {
                $data[] = array( 'key'     => NoNull($Row['key']),
                                 'name'    => NoNull($Row['name']),
                                 'summary' => ((mb_strlen(NoNull($Row['summary'])) > 0) ? NoNull($Row['summary']) : false),
                                 'guid'    => NoNull($Row['guid']),
                                );
            }

            /* If we have data, let's return it */
            if ( is_array($data) && count($data) > 0 ) { return $data; }
        }

        /* If we're here, there is nothing */
        return false;
    }

    /** ********************************************************************* *
     *  Text Cleanup Functions
     ** ********************************************************************* */
    /**
     *  INTERNAL function takes the text it receives and cleans it up for consistency and clarity
     */
    private function _scrubContent( $text ) {
        /* Correct any tag and spacing issues that may exist */
        $splitOn = array( '<div>', '</div>', '<p>', '</p>', '<br>', '<br/>', "\r", "\n" );
        $text = str_ireplace($splitOn, "\n\n", $text);

        /* Handle <ul> and <ol> transformations */
        preg_match_all('/<ul>(.*?)<\/ul>/s', $text, $matches);
        if ( is_array($matches) ) {
            foreach ( $matches[0] as $idx=>$html ) {
                $ulli = str_replace(array("\n", "\r", '<li>', '</li>'), array('', '', '* ', "\n"), $matches[1][$idx]);
                $text = str_replace($matches[0][$idx], $ulli, $text);
            }
        }

        preg_match_all('/<ol>(.*?)<\/ol>/s', $text, $matches);
        if ( is_array($matches) ) {
            foreach ( $matches[0] as $idx=>$html ) {
                $olli = str_replace(array("\n", "\r", '<li>', '</li>'), array('', '', '1. ', "\n"), $matches[1][$idx]);
                $text = str_replace($matches[0][$idx], $olli, $text);
            }
        }

        /* Remove Inline Styling */
        $text = preg_replace('/(<[^>]*) style=("[^"]+"|\'[^\']+\')([^>]*>)/i', '$1$3', $text);

        /* Replace some of the HTML tags with Markdown formatting where possible */
        $mdSwap = array( '<strong>' => '**', '</strong>' => '**', '<b>' => '**', '</b>' => '**',
                         '<em>' => '*', '</em>' => '*', '<i>' => '*', '</i>' => '*',
                         '<u>' => '++', '</u>' => '++', '<del>' => '~~', '</del>' => '~~',
                         '<h1>' => '#', '</h1>' => "\n", '<h2>' => '##', '</h2>' => "\n", '<h3>' => '###', '</h3>' => "\n",
                         '<h4>' => '####', '</h4>' => "\n", '<h5>' => '#####', '</h5>' => "\n", '<h6>' => '######', '</h6>' => "\n",
                         '<span>' => '', '</span>' => '', '<code>' => '`', '</code>' => '`',
                        );
        $text = str_replace(array_keys($mdSwap), array_values($mdSwap), $text);

        /* Try to Handle Inline HTML */
        $text = NoNull(str_replace('<', '&lt;', $text));
        $ReplStr = array( '&lt;section' => '<section', '&lt;iframe' => '<iframe',
                          '&lt;str' => '<str', '&lt;del' => '<del', '&lt;pre' => '<pre',
                          '&lt;h1' => '<h1', '&lt;h2' => '<h2', '&lt;h3' => '<h3',
                          '&lt;h4' => '<h4', '&lt;h5' => '<h5', '&lt;h6' => '<h6',
                          '&lt;ol' => '<ol', '&lt;ul' => '<ul', '&lt;li' => '<li',
                          '&lt;b' => '<b', '&lt;i' => '<i', '&lt;u' => '<u',
                          '&lt;kbd' => '<kbd', '&lt;/kbd' => '</kbd', '<kbd> ' => '&lt;kbd> ',
                          '`<kbd>`' => '`&lt;kbd>`', '`<kbd></kbd>`' => '`&lt;kbd>&lt;/kbd>`',
                          '&amp;nbsp;' => '&nbsp;',
                         );
        $text = NoNull(str_replace(array_keys($ReplStr), array_values($ReplStr), $text));

        /* Let's work on some lines */
        $source = explode("\n", $text);
        $lines = array();
        $rslt = '';

        /* First we'll identify *proper* lines */
        $inCodeBlock = false;
        $lnCnt = 0;

        foreach ( $source as $line ) {
            /* Are we in a code block? */
            if ( mb_substr($line, 0, 3) == '```' ) {
                $inCodeBlock = !$inCodeBlock;
                $lnCnt = 0;
            }
            if ( $inCodeBlock ) {
                if ( NoNull($line) != '' || $lnCnt == 1 ) {
                    if ( NoNull($line) != '' ) { $lnCnt = 0; }
                    $lines[] = rtrim($line);
                }
                if ( NoNull($line) == '' ) { $lnCnt++; }

            } else {
                if ( NoNull($line) != '' ) {
                    $lines[] = NoNull($line);
                }
            }
        }

        /* Now we'll parse them for lists and other details */
        $inCodeBlock = false;
        $inTable = false;
        $idx = 0;

        foreach ( $lines as $line ) {
            $eol = "\n\n";

            /* Are we working with a Blockquote prefix? */
            if ( mb_substr($line, 0, 4) == '&gt;' ) {
                $line = '> ' . NoNull(mb_substr($line, 5));
            }

            /* If the current line *and* the next line are part of a list, only one newline is required */
            if ( $this->_checkLineIsList($line) ) {
                if ( $this->_checkLineIsList($lines[$idx + 1]) ) { $eol = "\n"; }
            }

            /* Are we working with a table? */
            if ( mb_strpos(NoNull($lines[$idx + 1]), '--') !== false && mb_strpos(NoNull($lines[$idx + 1]), '|') !== false ) { $inTable = true; }
            if ( NoNull($line) == '' || ($inTable && mb_strpos(NoNull($line), '|') === false ) ) {
                $rslt .= "\n";
                $inTable = false;
            }

            /* Are we in a code block? */
            if ( mb_substr($line, 0, 3) == '```' ) { $inCodeBlock = !$inCodeBlock; }
            if ( $inCodeBlock || $inTable ) { $eol = "\n"; }

            /* Add the Line to the Return */
            $rslt .= $line . $eol;
            $idx++;
        }

        /* Return the scrubbed text */
        return NoNull($rslt);
    }

    /**
     *  Function cleans up some weirdness with passed text items to ensure a plain text response
     */
    private function _getPlainText( $text = '' ) {
        if ( mb_strlen(NoNull($text)) <= 0 ) { return ''; }

        $splitOn = array( '<div>', '</div>', '<p>', '</p>', '<br>', '<br/>', "\r", "\n" );
        $text = str_ireplace($splitOn, "\n\n", $text);

        /* Ensure there are not long expanses of hard returns */
        for ( $i = 50; $i >= 3; $i-- ) {
            $text = str_ireplace(str_repeat("\n", $i), "\n\n", $text);
        }

        /* Return a Cleaned up Text string */
        return NoNull($text);
    }

    /**
     *  Function Converts a Text String to HTML Via Markdown
     */
    private function _getMarkdownHTML( $text, $showLinkURL = false ) {
        $illegals = array( '<' => '&lt;', '>' => '&gt;' );
        $Excludes = array("\r", "\n", "\t");
        $ValidateUrls = false;
        if ( defined('VALIDATE_URLS') ) { $ValidateUrls = YNBool(VALIDATE_URLS); }

        /* Confirm the string is semi-valid */
        $text = NoNull($text);
        if ( mb_strlen($text) <= 2 ) { return $text; }

        /* If the Markdown Parser hasn't been loaded yet, do so */
        if ( $this->parser === false ) { $this->parser = new MarkdownExtra; }

        /* Fix the Lines with Breaks Where Appropriate */
        $text = str_replace("\r", "\n", $text);
        $lines = explode("\n", $text);
        $inCodeBlock = false;
        $inTable = false;
        $fixed = '';
        $last = '';

        foreach ( $lines as $line ) {
            $thisLine = NoNull($line);
            if ( mb_strpos($thisLine, '```') !== false ) { $inCodeBlock = !$inCodeBlock; }
            if ( $inCodeBlock ) { $thisLine = $line; }
            $doBR = ( $fixed != '' && $last != '' && $thisLine != '' ) ? true : false;

            // Are we working with a table?
            if ( mb_strpos($thisLine, '--') !== false && mb_strpos($thisLine, '|') !== false ) { $inTable = true; }
            if ( NoNull($thisLine) == '' ) { $inTable = false; }

            // If We Have What Looks Like a List, Prep It Accordingly
            if ( nullInt(mb_substr($thisLine, 0, 2)) > 0 && nullInt(mb_substr($last, 0, 2)) > 0 ) { $doBR = false; }
            if ( mb_substr($thisLine, 0, 2) == '* ' && mb_substr($last, 0, 2) == '* ' ) { $doBR = false; }
            if ( mb_substr($thisLine, 0, 2) == '- ' && mb_substr($last, 0, 2) == '- ' ) { $doBR = false; }

            if ( mb_substr($thisLine, 0, 2) == '* ' && mb_substr($last, 0, 2) != '* ' && strlen($last) > 0 ) {
                $fixed .= "\n";
                $doBR = false;
            }
            if ( mb_substr($thisLine, 0, 2) == '- ' && mb_substr($last, 0, 2) != '- ' && strlen($last) > 0 ) {
                $fixed .= "\n";
                $doBR = false;
            }

            if ( nullInt(mb_substr($thisLine, 0, 2)) > 0 && $last == '' ) { $fixed .= "\n"; }
            if ( mb_substr($thisLine, 0, 2) == '* ' && $last == '' ) { $fixed .= "\n"; }
            if ( mb_substr($thisLine, 0, 2) == '- ' && $last == '' ) { $fixed .= "\n"; }
            if ( $inCodeBlock || mb_strpos($thisLine, '```') !== false ) { $doBR = false; }
            if ( $inTable ) { $doBR = false; }

            $fixed .= ( $doBR ) ? '<br>' : "\n";
            $fixed .= ( $inCodeBlock ) ? str_replace(array_keys($illegals), array_values($illegals), $line) : $thisLine;
            $last = NoNull($thisLine);
        }

        $postFix = array('<br><br>' => "\n\n" );
        $text = NoNull( str_replace(array_keys($postFix), array_values($postFix), $fixed) );

        /* Handle the Footnotes */
        $fnotes = '';
        if ( strpos($text, '[') > 0 ) {
            $notes = array();
            $pass = 0;

            while ( $pass < 100 ) {
                $inBracket = false;
                $btxt = '';
                $bidx = '';
                $bid = 0;
                for ( $i = 0; $i < strlen($text); $i++ ) {
                    if ( substr($text, $i, 1) == "[" ) {
                        $bracketValid = false;
                        if ( strpos(substr($text, $i, 6), '. ') > 0 ) { $bracketValid = true; }
                        if ( $bracketValid || $inBracket ) {
                            $inBracket = true;
                            $bid++;
                        }
                    }
                    if ( $inBracket ) { $btxt .= substr($text, $i, 1); }
                    if ( $inBracket && substr($text, $i, 1) == "]" ) {
                        $bid--;
                        if ( $bid <= 0 ) {
                            $n = count($notes) + 1;
                            $ntxt = substr($btxt, strpos($btxt, '. ') + 2);
                            $ntxt = substr($ntxt, 0, strlen($ntxt) - 1);
                            if ( NoNull($ntxt) != '' ) {
                                $text = str_replace($btxt, "<sup class=\"footnote\" data-idx=\"$n\">$n</sup>", $text);
                                $notes[] = NoNull($ntxt);
                                $btxt = '';
                                break;
                            }
                        }
                    }
                }
                $pass++;
            }

            if ( count($notes) > 0 ) {
                $idx = 1;

                foreach ( $notes as $note ) {
                    $note = NoNull( str_replace(array_keys($postFix), array_values($postFix), $note) );
                    $fnotes .= "<li class=\"footnote\" data-idx=\"$idx\">" . $this->parser->defaultTransform($note) . "</li>";
                    $idx++;
                }
            }
        }

        /* Handle Code Blocks */
        if (preg_match_all('/\```(.+?)\```/s', $text, $matches)) {
            foreach($matches[0] as $fn) {
                $cbRepl = array( '```' => '', '<code><br>' => "<code>", '<br></code>' => '</code>', "\n" => '<br>', ' ' => "&nbsp;" );
                $code = "<pre><code>" . str_replace(array_keys($cbRepl), array_values($cbRepl), $fn) . "</code></pre>";
                $code = str_replace(array_keys($cbRepl), array_values($cbRepl), $code);
                $text = str_replace($fn, $code, $text);
            }
        }

        /* Are there any underlines or strike-throughs? */
        $text = preg_replace('/\+\+(.+)\+\+/sU',"<u>$1</u>", $text);
        $text = preg_replace('/~~(.+)~~/sU',"<del>$1</del>", $text);

        /* Get the Markdown Formatted */
        $text = str_replace('\\', '&#92;', $text);
        $rVal = $this->parser->defaultTransform($text);
        for ( $i = 0; $i <= 5; $i++ ) {
            foreach ( $Excludes as $Item ) {
                $rVal = str_replace($Item, '', $rVal);
            }
        }

        /* Replace any Hashtags if they exist */
        $rVal = str_replace('</p>', '</p> ', $rVal);
        $words = explode(' ', " $rVal ");
        $out_str = '';
        foreach ( $words as $word ) {
            $clean_word = NoNull(strip_tags($word));
            $hash = '';

            if ( NoNull(substr($clean_word, 0, 1)) == '#' ) {
                $hash_scrub = array('#', '?', '.', ',', '!');
                $hash = NoNull(str_replace($hash_scrub, '', $clean_word));

                if ($hash != '' && mb_stripos($hash_list, $hash) === false ) {
                    if ( $hash_list != '' ) { $hash_list .= ','; }
                    $hash_list .= strtolower($hash);
                }
            }
            $out_str .= ($hash != '') ? str_ireplace($clean_word, '<span class="hash" data-hash="' . strtolower($hash) . '">' . NoNull($clean_word) . '</span> ', $word)
                                      : "$word ";
        }
        $rVal = NoNull($out_str);

        /* Format the URLs as Required */
        $url_pattern = '#(www\.|https?://)?[a-z0-9]+\.[a-z0-9]\S*#i';
        $fixes = array( 'http//'  => "http://",         'http://http://'   => 'http://',
                        'https//' => "https://",        'https://https://' => 'https://',
                        ','       => '',                'http://https://'  => 'https://',
                       );
        $splits = array( '</p><p>' => '</p> <p>', '<br>' => '<br> ' );
        $scrub = array('#', '?', '.', ':', ';');
        $words = explode(' ', ' ' . str_replace(array_keys($splits), array_values($splits), $rVal) . ' ');

        $out_str = '';
        foreach ( $words as $word ) {
            /* Do We Have an Unparsed URL? */
            if ( mb_strpos($word, '.') !== false && mb_strpos($word, '.') <= (mb_strlen($word) - 1) && NoNull(str_ireplace('.', '', $word)) != '' &&
                 mb_strpos($word, '[') === false && mb_strpos($word, ']') === false ) {
                $clean_word = str_replace("\n", '', strip_tags($word));
                if ( in_array(substr($clean_word, -1), $scrub) ) { $clean_word = substr($clean_word, 0, -1); }

                $url = ((stripos($clean_word, 'http') === false ) ? "http://" : '') . $clean_word;
                $url = str_ireplace(array_keys($fixes), array_values($fixes), $url);
                $headers = false;

                /* Ensure We Have a Valid URL Here */
                $hdParts = explode('.', $url);
                $hdCount = 0;

                /* Count How Many Parts We Have */
                if ( is_array($hdParts) ) {
                    foreach( $hdParts as $item ) {
                        if ( NoNull($item) != '' ) { $hdCount++; }
                    }
                }

                /* No URL Has Just One Element */
                if ( $hdCount > 0 ) {
                    if ( $ValidateUrls ) {
                        if ( $hdCount > 1 ) { $headers = get_headers($url); }
                        if ( is_array($headers) ) {
                            $okHead = array('HTTPS/1.0 200 OK', 'HTTPS/1.1 200 OK', 'HTTPS/2.0 200 OK',
                                            'HTTP/1.0 200 OK', 'HTTP/1.1 200 OK', 'HTTP/2.0 200 OK');
                            $suffix = '';
                            $rURL = $url;

                            // Do We Have a Redirect?
                            if ( count($headers) > 0 ) {
                                foreach ($headers as $Row) {
                                    if ( mb_strpos(strtolower($Row), 'location') !== false ) {
                                        $rURL = NoNull(str_ireplace('location:', '', strtolower($Row)));
                                        break;
                                    }
                                    if ( in_array(NoNull(strtoupper($Row)), $okHead) ) { break; }
                                }
                            }

                            $host = parse_url($rURL, PHP_URL_HOST);
                            if ( $host != '' && $showLinkURL ) {
                                if ( mb_strpos(strtolower($clean_word), strtolower($host)) === false ) {
                                    $suffix = " [" . strtolower(str_ireplace('www.', '', $host)) . "]";
                                }
                            }

                            $clean_text = $clean_word;
                            if ( mb_stripos($clean_text, '?') ) {
                                $clean_text = substr($clean_text, 0, mb_stripos($clean_text, '?'));
                            }

                            $word = str_ireplace($clean_word, '<a target="_blank" href="' . $rURL . '">' . $clean_text . '</a>' . $suffix, $word);
                        }

                    } else {
                        $hparts = explode('.', parse_url($url, PHP_URL_HOST));
                        $domain = '';
                        $parts = 0;
                        $nulls = 0;

                        for ( $dd = 0; $dd < count($hparts); $dd++ ) {
                            if ( NoNull($hparts[$dd]) != '' ) {
                                $domain = NoNull($hparts[$dd]);
                                $parts++;
                            } else {
                                $nulls++;
                            }
                        }

                        if ( $nulls == 0 && $parts > 1 && isValidTLD($domain) ) {
                            $host = parse_url($url, PHP_URL_HOST);
                            if ( $host != '' && $showLinkURL ) {
                                if ( mb_strpos(strtolower($clean_word), strtolower($host)) === false ) {
                                    $suffix = " [" . strtolower(str_ireplace('www.', '', $host)) . "]";
                                }
                            }

                            $clean_text = $clean_word;
                            if ( mb_stripos($clean_text, '?') ) {
                                $clean_text = substr($clean_text, 0, mb_stripos($clean_text, '?'));
                            }

                            $word = str_ireplace($clean_word, '<a target="_blank" href="' . $url . '">' . $clean_text . '</a>' . $suffix, $word);
                        }
                    }
                }
            }

            /* Output Something Here */
            $out_str .= " $word";
        }

        /* If We Have Footnotes, Add them */
        if ( $fnotes != '' ) { $out_str .= '<ol class="footnote-list">' . $fnotes . '</ol>'; }

        /* Fix any Links that Don't Have Targets */
        $rVal = str_ireplace('<a href="', '<a target="_blank" href="', $out_str);
        $rVal = str_ireplace('<a target="_blank" href="http://mailto:', '<a href="mailto:', $rVal);

        /* Do Not Permit Any Forbidden Characters to Go Back */
        $forbid = array( '<script'      => "&lt;script",    '</script'           => "&lt;/script",   '< script'     => "&lt;script",
                         '<br></p>'     => '</p>',          '<br></li>'          => '</li>',         '<br> '        => '<br>',
                         '&#95;'        => '_',             '&amp;#92;'          => '&#92;',         ' </p>'        => '</p>',
                         '&lt;iframe '  => '<iframe ',      '&gt;&lt;/iframe&gt' => '></iframe>',    '&lt;/iframe>' => '</iframe>',
                         '</p></p>'     => '</p>',          '<p><p>'             => '<p>',           '</p> <p>'     => '</p><p>',
                         '...'          => '…',

                         '<p><blockquote>' => '<blockquote>',
                         '<pre><code><br>' => '<pre><code>',
                        );
        for ( $i = 0; $i < 10; $i++ ) {
            $rVal = str_replace(array_keys($forbid), array_values($forbid), $rVal);
        }

        /* Return the Markdown-formatted HTML */
        return NoNull($rVal);
    }

    /**
     *  INTERNAL function determines if a line provided is a list item or not, returning a boolean value
     */
    private function _checkLineIsList( $line ) {
        /* First check to see if the line is an unsorted list */
        if ( mb_substr($line, 0, 2) == '* ' ) { return true; }

        /* If we're here, let's check to see if line is a numbered list */
        $listStartPos = mb_strpos($line, '. ');
        if ( $listStartPos >= 0 ) {
            $sub = mb_substr($line, 0, $listStartPos);
            if ( is_numeric($sub) && mb_strlen($sub) <= 5 ) {
                $prefix = nullInt($sub) . '.';
                if ( mb_strpos($line, $prefix) == 0 ) { return true; }
            }
        }

        /* If we're here, then the line is probably not a list */
        return false;
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