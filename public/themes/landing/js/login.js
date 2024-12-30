window.KEY_DOWNARROW = 40;
window.KEY_ESCAPE = 27;
window.KEY_ENTER = 13;

document.onreadystatechange = function () {
    if (document.readyState == "interactive") {
        if ( isBrowserCompatible() ) {
            window.addEventListener('offline', function(e) { showNetworkStatus(); });
            window.addEventListener('online', function(e) { showNetworkStatus(true); });
            window.onbeforeunload = function (e) {
                if ( window.hasChanges ) { return "Make sure you save all changes before leaving"; }
            };

            /* Set the Action Buttons */
            var els = document.getElementsByClassName('btn-action');
            for ( let i = 0; i < els.length; i++ ) {
                els[i].addEventListener('touchend', function(e) { handleButtonAction(e); });
                els[i].addEventListener('click', function(e) { handleButtonAction(e); });
            }

            /* Ensure the Inputs are properly set */
            var els = document.getElementsByName('fdata');
            if ( els.length !== undefined && els.length > 0 ) {
                for ( let e = 0; e < els.length; e++ ) {
                    var _tags = ['input', 'select', 'textarea'];
                    if ( _tags.indexOf(NoNull(els[e].tagName).toLowerCase()) >= 0 ) {
                        if ( els[e].classList.contains('form-control') === false ) { els[e].classList.add('form-control'); }
                        if ( els[e].classList.contains('text-center') === false ) { els[e].classList.add('text-center'); }
                        if ( NoNull(els[e].getAttribute('data-required')).toUpperCase() == 'Y' ) {
                            if ( els[e].classList.contains('required') === false ) { els[e].classList.add('required'); }
                        }

                        /* Ensure the annoying autocompletions and squigglies are removed */
                        els[e].setAttribute('autocomplete', 'off');
                        els[e].setAttribute('spellcheck', 'false');
                    }
                }
            }

            /* Populate the Page */
            prepScreen();

        } else {
            hideByClass('content');
            showByClass('compat');
        }
    }
}

/** ************************************************************************ **
 *      Button Functions
 ** ************************************************************************ */
function handleButtonAction(el) {
    if ( el === undefined || el === null || el === false ) { return; }
    if ( el.currentTarget !== undefined && el.currentTarget !== null ) { el = el.currentTarget; }
    if ( NoNull(el.tagName).toLowerCase() !== 'button' ) { return; }
    if ( splitSecondCheck(el) ) {
        var _action = NoNull(el.getAttribute('data-action')).toLowerCase();

        switch ( _action ) {
            case 'login':
                getAuthToken();
                break;

            case 'minmax':
                toggleBoxContent(el);
                break;

            case 'toggle':
                toggleButton(el);
                break;

            default:
                console.log("Not sure how to handle: " + _action);
        }
    }
}

/** ************************************************************************* *
 *  Watcher Functions
 ** ************************************************************************* */
function watchLoginForm() {
    var els = document.getElementsByName('fdata');
    if ( els.length !== undefined && els.length > 0 ) {
        var _isOK = validateData();

        /* Set the status of the Login button */
        disableButtons('btn-login', ((_isOK) ? false : true));

        /* Run the function again */
        setTimeout(function () { watchLoginForm(); }, 333);
    }
}

/** ************************************************************************* *
 *  Token Validation Functions
 ** ************************************************************************* */
function prepScreen() {
    var _token = getMetaValue('authorization');

    /* Check the Auth Token (if exists) */
    if ( _token.length >= 30 ) {
        /* Validate the Access Token */

    } else {
        setTimeout(function () { watchLoginForm(); }, 500);
    }
}
function parseTokenValidation(data) {
    if ( data.meta !== undefined && data.meta.code == 200 ) {
        var ds = data.data;

        /* If everything is good, structure the UI accordingly */
        showByClass('req-auth');
        hideByClass('no-auth');
    }
}

/** ************************************************************************* *
 *  Authentication Operations
 ** ************************************************************************* */
function validateData() {
    var _cnt = 0;

    var els = document.getElementsByName('fdata');
    for ( let e = 0; e < els.length; e++ ) {
        var _name = NoNull(els[e].getAttribute('data-name')).toLowerCase();
        if ( _name.length > 0 ) {
            var _req = NoNull(els[e].getAttribute('data-required')).toUpperCase();
            var _min = parseInt(NoNull(els[e].getAttribute('data-minlength')));
            if ( _min === undefined || _min === null || isNaN(_min) ) { _min = 1; }
            if ( _min < 1 ) { _min = 1; }

            var _val = getElementValue(els[e]);
            if ( _req == 'Y' && _val.length < _min ) { _cnt++; }

            /* Is there a secondary check to consider? */
            var _chk = NoNull(els[e].getAttribute('data-valid'), '-').toUpperCase();
            if ( _chk == 'N' ) { _cnt++; }

            /* If this is an email, ensure it fits the basic structure */
            if ( _name == 'email' ) {
                if ( validateEmail(_val) === false ) { _cnt++; }
            }
        }
    }

    /* Return a Boolean */
    return ((_cnt <= 0) ? true : false);
}
function getAuthToken() {
    if ( validateData() ) {
        var _params = { 'target': getMetaValue('target') };

        var els = document.getElementsByName('fdata');
        for ( let e = 0; e < els.length; e++ ) {
            var _name = NoNull(els[e].getAttribute('data-name')).toLowerCase();
            if ( NoNull(_name).length > 0 ) { _params[_name] = getElementValue(els[e]); }
        }

        /* Attempt to authenticate */
        setTimeout(function () { doJSONQuery('auth/login', 'POST', _params, parseAuthToken); }, 25);
        spinButtons('btn-login');
    }
}
function parseAuthToken( data ) {
    spinButtons('btn-login', true);

    if ( data.meta !== undefined && data.meta.code == 200 ) {
        var ds = data.data;

        console.log(ds);

        if ( ds.token !== undefined && ds.token !== null && NoNull(ds.token).length > 40 ) {
            window.location = location.protocol + '//' + location.hostname + '?validatetoken=' + NoNull(ds.token);
        }
    }
}