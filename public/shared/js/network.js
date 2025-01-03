window.network_active = false;
window.online = true;

function doJSONQuery( endpoint, type, parameters, afterwards ) {
    if ( window.online === false ) { afterwards(false); }
    var access_token = getMetaValue('authorization');
    var api_url = getMetaValue('api_url');
    if ( NoNull(api_url).length <= 10 ) {
        api_url = location.protocol + '//' + location.hostname + '/api';
    }
    var xhr = new XMLHttpRequest();

    xhr.onreadystatechange = function() {
        window.network_active = true;
        if ( xhr.readyState == 4 ) {
            window.network_active = false;
            var rsp = false;
            if ( xhr.responseText != '' ) { rsp = JSON.parse(xhr.responseText); }
            if ( afterwards !== false ) { afterwards(rsp); }
        }
    };
    xhr.onerror = function() {
        window.network_active = false;
        var rsp = false;
        if ( xhr.responseText != '' ) { rsp = JSON.parse(xhr.responseText); }
        if ( afterwards !== false ) { afterwards(rsp); }
    }
    xhr.ontimeout = function() {
        window.network_active = false;
        if ( afterwards !== false ) { afterwards(false); }
    }
    var suffix = '';
    if ( type == 'GET' ) { suffix = jsonToQueryString(parameters); }

    /* Open the XHR Connection and Send the Request */
    xhr.open(type, api_url + '/' + endpoint + suffix, true);
    xhr.timeout = (endpoint == 'auth/login') ? 5000 : 600000;
    if ( access_token != '' ) { xhr.setRequestHeader("Authorization", access_token); }
    xhr.setRequestHeader("Content-Type", "Application/json; charset=utf-8");
    xhr.send(JSON.stringify(parameters));
}
function jsonToQueryString(json) {
    var data = Object.keys(json).map(function(key) { return encodeURIComponent(key) + '=' + encodeURIComponent(json[key]); }).join('&');
    return (data !== undefined && data !== null && data != '' ) ? '?' + data : '';
}
function getMetaValue( _name ) {
    if ( _name === undefined || _name === false || _name === null || NoNull(_name) == '' ) { return ''; }
    var metas = document.getElementsByTagName('meta');
    for (var i = 0; i < metas.length; i++) {
        if ( metas[i].getAttribute("name") == _name ) {
            return metas[i].getAttribute("content");
        }
    }
    return '';
}
function setMetaValue( _name, _content ) {
    if ( _name === undefined || _name === false || _name === null || NoNull(_name) == '' ) { return ''; }
    var metas = document.getElementsByTagName('meta');
    for (var i = 0; i < metas.length; i++) {
        if ( metas[i].getAttribute("name") == _name ) {
            metas[i].setAttribute("content", _content);
            return true;
        }
    }
    return false;
}

function showNetworkStatus( isOnline ) {
    if ( isOnline === undefined || isOnline === null || isOnline !== true ) { isOnline = false; }
    var _clsList = ['system-message', 'offline'];
    for ( let e = 0; e < _clsList.length; e++ ) {
        if ( isOnline ) {
            hideByClass(_clsList[e]);
        } else {
            showByClass(_clsList[e]);
        }
    }
    window.online = isOnline;
}
function isValidJsonRsp( data ) {
    if ( data !== undefined && data.meta !== undefined && data.meta.code == 200 ) { return true; }
    return false;
}