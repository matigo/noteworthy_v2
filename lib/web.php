<?php

/**
 * Class Responds to the Web Data Route and Returns the Appropriate Data
 */
require_once(CONF_DIR . '/versions.php');
require_once(CONF_DIR . '/config.php');
require_once( LIB_DIR . '/functions.php');
require_once( LIB_DIR . '/cookies.php');

class Route extends Midori {
    var $settings;
    var $strings;
    var $custom;

    function __construct( $settings, $strings ) {
        $this->settings = $settings;
        $this->strings = $strings;
        $this->custom = false;

        /* Ensure the Asset Version.id Is Set */
        if ( defined('CSS_VER') === false ) {
            $ver = filemtime(CONF_DIR . '/versions.php');
            if ( nullInt($ver) <= 0 ) { $ver = nullInt(APP_VER); }
            define('CSS_VER', $ver);
        }

        /* Ensure we have the basics in place */
        if ( defined('SITE_DEFAULT') === false ) { define('SITE_DEFAULT', ''); }
        if ( defined('SITE_LAYOUT') === false ) { define('SITE_LAYOUT', ''); }
        if ( defined('SITE_HTTPS') === false ) { define('SITE_HTTPS', 0); }
    }

    /* ************************************************************************************** *
     *  Function determines what needs to be done and returns the appropriate HTML Document.
     * ************************************************************************************** */
    public function getResponseData() {
        $PropUrl = ((YNBool(SITE_HTTPS)) ? 'https' : 'http') . '://' . strtolower($_SERVER['SERVER_NAME']);
        $RedirectURL = NoNull($_SERVER['HTTP_REFERER'], $PropUrl);
        $PgRoot = strtolower(NoNull($this->settings['PgRoot']));
        $ReplStr = array();

        $html = readResource(FLATS_DIR . '/templates/500.html', $ReplStr);
        $ThemeFile = THEME_DIR . '/error.html';

        /* Confirm the Protocol is correct, only if we should be using HTTPS */
        if ( YNBool(SITE_HTTPS) ) {
            $PropProtocol = ((YNBool(SITE_HTTPS)) ? 'https' : 'http');
            $ReqProtocol = getServerProtocol();
            if ( $PropProtocol != $ReqProtocol ) {
                /* Is there anything afterward that needs to be included? */
                $suffix = '/' . NoNull($this->settings['PgRoot']);
                if ( $suffix != '' ) {
                    for ( $i = 1; $i <= 9; $i++ ) {
                        $itm = NoNull($this->settings['PgSub' . $i]);
                        if ( $itm != '' ) { $suffix .= "/$itm"; }
                    }
                }
                if ( mb_strlen($suffix) > 2 ) { $PropUrl .= $suffix; }

                /* Perform the redirect */
                redirectTo($PropUrl, $this->settings);
            }
        }

        /* Is this a Sitemap Request? */
        $this->_handleSitemapRequest();

        /* Are we working with a valid request */
        $this->_checkStaticResourceRequest();

        /* Do we have an authentication operation to perform? */
        switch ( $PgRoot ) {
            case 'validatetoken':
            case 'validate':
                if ( mb_strlen(NoNull($this->settings['token'])) > 20 ) {
                    redirectTo( $RedirectURL, $this->settings );
                }
                break;

            case 'signout':
            case 'logout':
                if ( YNBool($this->settings['_logged_in']) ) {
                    require_once(LIB_DIR . '/auth.php');
                    $auth = new Auth($this->settings);
                    $sOK = $auth->performLogout();
                    unset($auth);
                }
                redirectTo( $RedirectURL, $this->settings );
                break;

            default:
                /* Carry On */
        }

        /* Load the Requested HTML Content */
        $html = $this->_getPageHTML();

        /* Return the HTML and unset the various objects */
        if ( is_array($this->custom) === false ) { unset($this->custom); }
        unset($this->strings);

        return $html;
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

    /** ********************************************************************** *
     *  Private Functions
     ** ********************************************************************** */
    /**
     *  Function Returns an Array With the Appropriate Content
     */
    private function _getPageHTML() {
        $theme = strtolower(NoNull(SITE_DEFAULT, 'error'));
        if ( YNBool($this->settings['_logged_in']) && mb_strlen(NoNull(SITE_LAYOUT)) > 0 ) { $theme = strtolower(NoNull(SITE_LAYOUT)); }

        $location = THEME_DIR . "/$theme";
        if ( file_exists($location . "/base.html") === false ) {
            $location = THEME_DIR . '/error';
            $theme = 'error';
        }

        /* Ensure we have the language strings loaded */
        $local = $this->_getLanguageStrings($theme);
        if ( is_array($local) === false || count($local) > 0 ) {
            foreach ( $local as $kk=>$vv ) {
                $this->strings[$kk] = $vv;
            }
        }

        /* Prep the Replacement string */
        $ReplStr = $this->_getPageMetadataArray();

        if ( is_array($this->strings) && count($this->strings) > 0 ) {
            foreach ( $this->strings as $kk=>$vv ) {
                $Key = "[$kk]";
                if ( array_key_exists($Key, $ReplStr) === false ) {
                    $ReplStr[$Key] = $vv;
                }
            }
        }

        /* If there is a custom theme class, collect the Page HTML from there */
        if ( file_exists("$location/custom.php") ) {
            if ( $this->custom === false ) {
                require_once("$location/custom.php");
                $ClassName = ucfirst(NoNull($theme, 'default'));
                $this->custom = new $ClassName( $this->settings, $this->strings );
            }
            if ( method_exists($this->custom, 'getPageHTML') ) {
                $this->settings['errors'] = $this->custom->getResponseMeta();
                $this->settings['status'] = $this->custom->getResponseCode();
                $ReplStr['[PAGE_HTML]'] = $this->custom->getPageHTML();
            }

        } else {
            $ReqFile = $this->_getContentPage();
            $ReplStr['[PAGE_HTML]'] = readResource($ReqFile, $ReplStr);
        }

        /* Set the Output HTML */
        $html = readResource("$location/base.html", $ReplStr);

        /* Return the Completed HTML Page Content */
        return str_replace('[GenTime]', getRunTime('html'), $html);
    }

    /**
     *  Collect the Language Strings that Will Be Used In the Theme
     *  Note: The Default Theme Language is Loaded First To Reduce the Risk of NULL Descriptors
     */
    private function _getLanguageStrings( $Location ) {
        $ThemeLocation = THEME_DIR . '/' . $Location;

        if ( file_exists("$ThemeLocation/base.html") === false ) { $ThemeLocation = THEME_DIR . '/error'; }
        $locale = strtolower(str_replace('_', '-', DEFAULT_LANG));
        $rVal = array();

        /* Collect the Default Langauge Strings */
        $LangFile = "$ThemeLocation/lang/" . $locale . '.json';
        if ( file_exists( $LangFile ) ) {
            $json = readResource( $LangFile );
            $items = objectToArray(json_decode($json));

            if ( is_array($items) ) {
                foreach ( $items as $Key=>$Value ) {
                    $rVal["$Key"] = NoNull($Value);
                }
            }
        }

        /* Is Multi-Lang Enabled And Required? If So, Load It */
        $LangCode = NoNull($this->settings['DispLang'], $this->settings['_language_code']);
        if ( ENABLE_MULTILANG == 1 && (strtolower($LangCode) != strtolower(DEFAULT_LANG)) ) {
            $locale = strtolower(str_replace('_', '-', $LangCode));
            $LangFile = "$ThemeLocation/lang/" . $locale . '.json';
            if ( file_exists( $LangFile ) ) {
                $json = readResource( $LangFile );
                $items = objectToArray(json_decode($json));

                if ( is_array($items) ) {
                    foreach ( $items as $Key=>$Value ) {
                        $rVal["$Key"] = NoNull($Value);
                    }
                }
            }
        }

        /* Do We Have a Special File for the Page? */
        $locale = strtolower(str_replace('_', '-', DEFAULT_LANG));
        $LangFile = "$ThemeLocation/lang/" . NoNull($this->settings['PgRoot']) . '_' . $locale . '.json';
        if ( file_exists( $LangFile ) ) {
            $json = readResource( $LangFile );
            $items = objectToArray(json_decode($json));

            if ( is_array($items) ) {
                foreach ( $items as $Key=>$Value ) {
                    $rVal["$Key"] = NoNull($Value);
                }
            }
        }

        $LangCode = NoNull($this->settings['DispLang'], $this->settings['_language_code']);
        if ( ENABLE_MULTILANG == 1 && (strtolower($LangCode) != strtolower(DEFAULT_LANG)) ) {
            $locale = strtolower(str_replace('_', '-', $LangCode));
            $LangFile = "$ThemeLocation/lang/" . NoNull($this->settings['PgRoot']) . '_' . $locale . '.json';
            if ( file_exists( $LangFile ) ) {
                $json = readResource( $LangFile );
                $items = objectToArray(json_decode($json));

                if ( is_array($items) ) {
                    foreach ( $items as $Key=>$Value ) {
                        $rVal["$Key"] = NoNull($Value);
                    }
                }
            }
        }

        /* Update the Language Strings for the Class */
        if ( is_array($rVal) ) {
            foreach ( $rVal as $Key=>$Value ) {
                $this->strings["$Key"] = NoNull($Value);
            }
        }
    }

    private function _getPageMetadataArray() {
        $theme = strtolower(NoNull(SITE_DEFAULT, 'error'));
        if ( YNBool($this->settings['_logged_in']) && mb_strlen(NoNull(SITE_LAYOUT)) > 0 ) { $theme = strtolower(NoNull(SITE_LAYOUT)); }

        $HomeUrl = ((YNBool(SITE_HTTPS)) ? 'https' : 'http') . '://' . strtolower($_SERVER['SERVER_NAME']);
        $SiteUrl = NoNull($HomeUrl . '/themes/' . $theme);
        $PgRoot = strtolower(NoNull($this->settings['PgRoot']));
        $ApiUrl = getApiUrl();
        $CdnUrl = getCdnUrl();

        /* Get the Banner (if one exists) */
        $banner_img = NoNull($data['banner_img']);
        if ( NoNull($banner_img) == '' ) { $banner_img = NoNull($HomeUrl . '/shared/images/social_banner.png'); }

        /* Set the Welcome Line */
        $welcomeLine = str_replace('{display_name}', $this->settings['_display_name'], $this->strings['welcomeLine']);

        // Construct the Core Array
        $rVal = array( '[SHARED_FONT]'  => $HomeUrl . '/shared/fonts',
                       '[SHARED_CSS]'   => $HomeUrl . '/shared/css',
                       '[SHARED_IMG]'   => $HomeUrl . '/shared/images',
                       '[SHARED_JS]'    => $HomeUrl . '/shared/js',

                       '[SITE_FONT]'    => $SiteUrl . '/fonts',
                       '[SITE_CSS]'     => $SiteUrl . '/css',
                       '[SITE_IMG]'     => $SiteUrl . '/img',
                       '[SITE_JS]'      => $SiteUrl . '/js',

                       '[FONT_DIR]'     => $SiteUrl . '/fonts',
                       '[CSS_DIR]'      => $SiteUrl . '/css',
                       '[IMG_DIR]'      => $SiteUrl . '/img',
                       '[JS_DIR]'       => $SiteUrl . '/js',
                       '[HOMEURL]'      => NoNull($this->settings['HomeURL']),
                       '[API_URL]'      => getApiUrl(),
                       '[CDN_URL]'      => getCdnUrl(),

                       '[CSS_VER]'      => getMetaVersion(),
                       '[GENERATOR]'    => GENERATOR . " (" . APP_VER . ")",
                       '[APP_NAME]'     => APP_NAME,
                       '[APP_VER]'      => APP_VER,
                       '[LANG_CD]'      => NoNull($this->settings['_language_code'], $this->settings['DispLang']),
                       '[LANGUAGE]'     => str_replace('_', '-', NoNull($this->settings['_language_code'], $data['locale'])),
                       '[ACCOUNT_TYPE]' => NoNull($this->settings['_account_type'], 'account.none'),
                       '[AVATAR_URL]'   => NoNull($this->settings['HomeURL']) . '/avatars/' . $this->settings['_avatar_file'],
                       '[WELCOME_LINE]' => NoNull($welcomeLine),
                       '[DISPLAY_NAME]' => NoNull($this->settings['_display_name'], $this->settings['_first_name']),
                       '[PGSUB_1]'      => NoNull($this->settings['PgSub1']),
                       '[TODAY]'        => date('Y-m-d'),
                       '[YEAR]'         => date('Y'),

                       '[TOKEN]'        => ((YNBool($this->settings['_logged_in']) && mb_strlen(NoNull($this->settings['token'])) > 30 ) ? NoNull($this->settings['token']) : ''),

                       '[SITE_URL]'     => $this->settings['HomeURL'],
                       '[SITE_NAME]'    => $data['name'],
                       '[SITEDESCR]'    => $data['description'],
                       '[SITEKEYWD]'    => $data['keywords'],

                       '[PAGE_URL]'     => $this->_getPageUrl(),

                       '[META_DOMAIN]'  => NoNull($data['HomeURL']),
                       '[META_DESCR]'   => NoNull($data['description']),
                       '[BANNER_IMG]'   => $banner_img,
                      );

        /* Return the Strings */
        return $rVal;
    }

    /**
     *  Function Collects the Necessary Page Contents
     */
    private function _getContentPage() {
        $theme = strtolower(NoNull(SITE_DEFAULT, 'error'));
        if ( YNBool($this->settings['_logged_in']) && mb_strlen(NoNull(SITE_LAYOUT)) > 0 ) { $theme = strtolower(NoNull(SITE_LAYOUT)); }

        $location = THEME_DIR . "/$theme";
        if ( file_exists($theme . "/base.html") === false ) { $location = THEME_DIR . '/error'; }
        if ( file_exists("$location/base.html") === false ) { $theme = 'error'; }
        $pgName = NoNull($this->settings['PgRoot'], 'main');

        $ResDIR = THEME_DIR . "/$theme/resources/";
        $page = 'page-' . NoNull($pgName, '404') . '.html';
        if ( file_exists($ResDIR . $page) === false ) { $page = 'page-404.html'; }
        if ( $page == 'page-404.html' ) { $this->settings['status'] = 404; }
        if ( $page == 'page-403.html' ) { $this->settings['status'] = 403; }

        /* Return the Necessary Page */
        return $ResDIR . $page;
    }

    /**
     *  Function Determines the Current Page URL
     */
    private function _getPageURL() {
        $url = $this->settings['HomeURL'];

        /* Let's check for the Root and Subs */
        if ( mb_strlen(NoNull($this->settings['PgRoot'])) > 0 ) {
            $url .= '/' . NoNull($this->settings['PgRoot']);

            /* Do we have PgSub values to include? */
            for ( $i = 1; $i <= 9; $i++ ) {
                if ( mb_strlen(NoNull($this->settings['PgSub' . $i])) > 0 ) {
                    $url .= '/' . NoNull($this->settings['PgSub' . $i]);
                } else {
                    $i += 100;
                }
            }
        }

        /* Return the Current URL */
        return $url;
    }

    /** ********************************************************************** *
     *  Alternative Return Functions
     ** ********************************************************************** */
    /**
     *  If someone is requesting the sitemap, return it
     */
    private function _handleSitemapRequest() {
        $PgRoot = strtolower(NoNull($this->settings['PgRoot']));
        if ( in_array($PgRoot, array('sitemap.xml')) ) {
            $HomeUrl = ((YNBool(SITE_HTTPS)) ? 'https' : 'http') . '://' . strtolower($_SERVER['SERVER_NAME']);
            $ReplStr = array( '[SITEURL]' => $HomeUrl );
            $xml = readResource(FLATS_DIR . '/templates/sitemap.xml', $ReplStr);
            if ( mb_strlen($xml) > 10 ) {
                formatResult($xml, $this->settings, 'application/xhtml+xml', 200);
            }
        }

        /* If we're here, nothing needed to be done */
    }

    /**
     *  Function attempts to determine if the HTTP request is looking for a static resource
     *      and, if it is, updates $this->settings accordingly
     */
    private function _checkStaticResourceRequest() {
        $exts = array( 'css', 'html', 'xml', 'json', 'pdf',
                       'jpg', 'jpeg', 'svg', 'gif', 'png', 'tiff',
                       'xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx',
                       'mp3', 'mp4', 'm4a',
                       'rar', 'zip', '7z',
                      );
        $uri = NoNull($this->settings['ReqURI']);
        $ext = getFileExtension($uri);

        /* If the request is a resource, treat it as a 404 */
        if ( in_array($ext, $exts) ) {
            /* If we are in a files route, then let's check for permission */
            if ( NoNull($this->settings['Route']) == 'files' ) {
                require_once(LIB_DIR . '/files.php');
                $res = new Files($this->settings, $this->strings);
                $sOK = $res->requestResource();
                unset($res);

                /* If we have successfully sent the file, then exit */
                if ( $sOK ) { exit(); }
            }

            $this->settings['PgRoot'] = '404';
            $this->settings['status'] = 404;

            if ( is_array($this->settings['errors']) === false ) { $this->settings['errors'] = array(); }
            $this->settings['errors'][] = NoNull($this->strings['msg404Detail'], "Cannot Find Requested Resource");
        }
    }
}
?>