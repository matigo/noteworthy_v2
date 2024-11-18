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
            case 'contact-next':
                setContactData();
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
function watchContactForm() {
    var els = document.getElementsByName('fdata');
    if ( els.length !== undefined && els.length > 0 ) {
        var _cnt = 0;

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

        /* Set the status of the Join button */
        disableButtons('btn-contact-next', ((_cnt == 0) ? false : true));

        /* Run the function again */
        setTimeout(function () { watchContactForm(); }, 333);
    }
}

/** ************************************************************************* *
 *  Token Validation Functions
 ** ************************************************************************* */
function prepScreen() {
    /* Ensure the form is cleared */
    var els = document.getElementsByName('fdata');
    for ( let e = 0; e < els.length; e++ ) {
        setElementValue(els[e], '');
    }

    /* Start the watcher */
    setTimeout(function () { watchContactForm(); }, 500);
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
 *  Token Validation Functions
 ** ************************************************************************* */
function validateRequestedNick() {
    var els = document.getElementsByName('fdata');
    for ( let e = 0; e < els.length; e++ ) {
        var _name = NoNull(els[e].getAttribute('data-name')).toLowerCase();
        if ( _name == 'login' ) {
            var _nick = NoNull(els[e].getAttribute('data-nick')).toLowerCase();
            var _val = getElementValue(els[e]);
            if ( _val.length > 0 && _val != _nick ) {
                var _params = { 'name': _val };
                setTimeout(function () { doJSONQuery('account/check', 'GET', _params, parseRequestedNick); }, 25);
            }
        }
    }
}
function parseRequestedNick( data ) {
    var _isOK = false;
    var _nick = '';

    if ( data.meta !== undefined && data.meta.code == 200 ) {
        var ds = data.data;

        if ( ds.is_valid !== undefined && ds.is_valid !== null ) { _isOK = ds.is_valid; }
        if ( ds.name !== undefined && ds.name !== null ) { _nick = ds.name; }
    }

    var els = document.getElementsByName('fdata');
    for ( let e = 0; e < els.length; e++ ) {
        var _name = NoNull(els[e].getAttribute('data-name')).toLowerCase();
        if ( _name == 'login' ) {
            els[e].setAttribute('data-valid', ((_isOK) ? 'Y' : 'N'));
            els[e].setAttribute('data-nick', _nick);
        }
    }
}



function setJoinData() {

}