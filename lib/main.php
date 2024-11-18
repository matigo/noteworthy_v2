<?php

/**
 * Class Responds to the Data Route and Returns the Appropriate Data
 */
require_once(CONF_DIR . '/versions.php');
require_once(LIB_DIR . '/functions.php');
require_once(LIB_DIR . '/cookies.php');

class Midori {
    var $settings;
    var $strings;

    function __construct() {
        $GLOBALS['Perf']['app_s'] = getMicroTime();

        /* Check to ensure that config.php exists */
        if ( $this->_chkRequirements() ) {
            require_once(CONF_DIR . '/config.php');

            $sets = new cookies;
            $this->settings = $sets->cookies;
            $this->strings = getLangDefaults($this->settings['_language_code']);
            unset( $sets );
        }
    }

    /* ********************************************************************* *
     *  Function determines what needs to be done and returns the
     *      appropriate JSON Content
     * ********************************************************************* */
    function buildResult() {
        $ReplStr = $this->_getReplStrArray();
        $rslt = readResource(FLATS_DIR . '/templates/500.html', $ReplStr);
        $type = 'text/html';
        $meta = false;
        $code = 500;

        /* Check to ensure the visitor meets the validation criteria and respond accordingly */
        if ( $this->_isValidRequest() && $this->_isValidAgent() ) {
            switch ( strtolower($this->settings['Route']) ) {
                case 'api':
                    require_once(LIB_DIR . '/api.php');
                    break;

                case 'hooks':
                    require_once(LIB_DIR . '/hooks.php');
                    break;

                default:
                    require_once(LIB_DIR . '/web.php');
                    break;
            }

            /* Ensure the Timezone is properly set */
            $useTZ = NoNull($this->settings['_timezone'], TIMEZONE);
            date_default_timezone_set($useTZ);

            /* Based on the Route, Perform the Necessary Operations */
            $data = new Route($this->settings, $this->strings);
            $rslt = $data->getResponseData();
            $type = $data->getResponseType();
            $code = $data->getResponseCode();
            $meta = $data->getResponseMeta();
            $more = ((method_exists($data, 'getHasMore')) ? $data->getHasMore() : false);
            unset($data);

        } else {
            if ( $this->_isValidAgent() ) {
                $code = $this->_isValidRequest() ? 420 : 422;
            } else {
                $code = 403;
            }
            $rslt = readResource( FLATS_DIR . "/templates/$code.html", $ReplStr);
        }

        /* Is this an uncaught POST request? */
        $isMissing = $this->_isUncaughtRequest(nullInt($code));

        /* Return the Data in the Correct Format */
        formatResult($rslt, $this->settings, $type, $code, $meta, $more);
    }

    /**
     *  Function Constructs and Returns the Language String Replacement Array
     */
    private function _getReplStrArray() {
        $httpHost = NoNull($_SERVER['REQUEST_SCHEME'], 'http') . '://' . NoNull($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']);
        $rVal = array( '[SITEURL]' => NoNull($this->settings['HomeURL'], $httpHost),
                       '[RUNTIME]' => getRunTime('html'),
                      );
        foreach ( $this->strings as $Key=>$Val ) {
            $rVal["[$Key]"] = NoNull($Val);
        }

        $strs = getLangDefaults();
        if ( is_array($strs) ) {
            foreach ( $strs as $Key=>$Val ) {
                $rVal["[$Key]"] = NoNull($Val);
            }
        }

        // Return the Array
        return $rVal;
    }

    /** ********************************************************************** *
     *  Uncaught Request Functions
     ** ********************************************************************** */
    private function _isUncaughtRequest( $code = 200 ) {
        if ( $code > 0 && $code <= 290 ) { return false; }
        $ReqType = NoNull(strtolower($this->settings['ReqType']));
        if ( NoNull($ReqType, 'get') == 'get' ) { return false; }

        /* If we're here, we have something unaccounted for */
        date_default_timezone_set(TIMEZONE);
        $data = json_encode($this->settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

        if ( mb_strlen(NoNull($data)) > 10 ) {
            $ima = time();
            $log_file = LOG_DIR . "/uncaught-$ima.log";

            $fh = fopen($log_file, 'a');
            $stringData = NoNull($data);
            fwrite($fh, $stringData);
            fclose($fh);

            return true;
        }

        /* If we're here, the request was incomplete */
        return false;
    }

    /** ********************************************************************** *
     *  Bad Behaviour Functions
     ** ********************************************************************** */
    /**
     *  Function determines if the request is looking for a WordPress, phpMyAdmin, or other
     *      open-source package-based attack vector and returns an abrupt message if so.
     */
    private function _isValidRequest() {
        $roots = array( 'phpmyadmin', 'phpmyadm1n', 'phpmy', 'pass',
                        'tools', 'typo3', 'xampp', 'www', 'web',
                        'wp-admin', 'wp-content', 'wp-includes', 'vendor',
                        'wlwmanifest.xml',
                       );
        if ( in_array(strtolower(NoNull($this->settings['PgSub1'], $this->settings['PgRoot'])), $roots) ) { return false; }

        /* Check for any protected file extensions */
        $exts = array('conf', 'env', 'ini', 'php', 'sql', 'txt', 'md');
        foreach ( $exts as $ext ) {
            if ( strpos(strtolower(NoNull($this->settings['ReqURI'])), '.' . $ext) !== false ) { return false; }
        }

        /* Check the Path for stupid stuff */
        $flags = array('../..', '0x');
        foreach ( $flags as $flag ) {
            if ( strpos(strtolower(NoNull($this->settings['ReqURI'])), $flag) !== false ) { return false; }
        }

        /* If we're here, we're probably okay */
        return true;
    }

    /**
     *  Function determines if the reported agent is valid for use or not. This is not meant to be a comprehensive list of
     *      unacceptable agents, as agent strings are easily spoofed.
     */
    private function _isValidAgent() {
        $excludes = array( 'ahrefsbot', 'mj12bot', 'mb2345browser', 'semrushbot', 'mmb29p', 'mbcrawler', 'blexbot', 'sogou web spider',
                           'serpstatbot', 'semanticscholarbot', 'yandexbot', 'yandeximages', 'gwene', 'barkrowler', 'yeti', 'ccbot',
                           'seznambot', 'domainstatsbot', 'sottopop', 'megaindex.ru', '9537.53', 'seekport crawler', 'iccrawler',
                           'magpie-crawler', 'crawler4j', 'facebookexternalhit', 'turnitinbot', 'netestate', 'googlebot',
                           'thither.direct', 'liebaofast', 'micromessenger', 'youdaobot', 'theworld', 'qqbrowser',
                           'dotbot', 'exabot', 'gigabot', 'slurp', 'keybot translation', 'searchatlas.com', 'googlebot',
                           'bingbot/2.0', 'aspiegelbot', 'baiduspider', 'ruby', 'webprosbot', 'censysinspect',
                           'zh-cn;oppo a33 build/lmy47v', 'oppo a33 build/lmy47v;wv' );
        $agent = strtolower(NoNull($_SERVER['HTTP_USER_AGENT']));
        if ( $agent != '' ) {
            foreach ( $excludes as $chk ) {
                if ( mb_strpos($agent, $chk) !== false ) { return false; }
            }
        }
        return true;
    }

    /**
     *  Function Looks for Basics before allowing anything to continue
     */
    private function _chkRequirements() {
        /* Confirm the Existence of a config.php file */
        $cfgFile = CONF_DIR . '/config.php';
        if ( file_exists($cfgFile) === false ) {
            $ReplStr = $this->_getReplStrArray();
            $ReplStr['[msg500Title]'] = 'Missing Config.php';
            $ReplStr['[msg500Line1]'] = 'No <code>config.php</code> file found!';
            $ReplStr['[msg500Line2]'] = 'This should not happen unless the system is in the midst of being built for the first time ...';
            $rslt = readResource(FLATS_DIR . '/templates/500.html', $ReplStr);

            formatResult($rslt, $this->settings, 'text/html', 500, false);
            return false;
        }

        /* If we're here, it's all good */
        return true;
    }
}
?>