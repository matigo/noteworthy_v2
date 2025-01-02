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

            /* Begin the Page Population */
            setTimeout(function () { getLibrary(); }, 25);

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
 *  Population Functions
 ** ************************************************************************* */
function getLibrary() {
    parseLibrary(false);
}
function parseLibrary(data) {
    var els = document.getElementsByClassName('section-list');
    for ( let e = 0; e < els.length; e++ ) {
        var _libs = [{'icons': ['fas', 'fa-book'], 'key': 'all', 'label': NoNull(window.strings['lib.all'], 'Everything') },
                     {'icons': ['fas', 'fa-clock'], 'key': 'recent', 'label': NoNull(window.strings['lib.recent'], 'Past Week') },
                     {'icons': ['fas', 'fa-file-lines'], 'key': 'notes', 'label': NoNull(window.strings['lib.notes'], 'Notes') },
                     {'icons': ['fas', 'fa-photo-film'], 'key': 'media', 'label': NoNull(window.strings['lib.media'], 'Media') },
                     {'icons': ['fas', 'fa-map-location-dot'], 'key': 'location', 'label': NoNull(window.strings['lib.location'], 'Locations') },
                     {'icons': ['fas', 'fa-map-pin'], 'key': 'pins', 'label': NoNull(window.strings['lib.pins'], 'Pinned') },
                     {'icons': ['fas', 'fa-trash'], 'key': 'deleted', 'label': NoNull(window.strings['lib.deleted'], 'Deleted') },
                    ];
        els[e].innerHTML = '';

        /* Build the Library */
        els[e].appendChild(buildElement({ 'tag': 'h4', 'text': NoNull(window.strings['lbl.library'], 'Library') }));

        var _lib = buildElement({ 'tag': 'ul', 'classes': ['library-list'] });
        for ( let i = 0; i < _libs.length; i++ ) {
            var _obj = buildElement({ 'tag': 'li',
                                      'classes': ['library-item'],
                                      'attribs': [{'key':'data-key','value':_libs[i].key},
                                                  {'key':'ondragover','value':'libDragOver(event)'},
                                                  {'key':'ondragstart','value':'libDragStart(event)'},
                                                  {'key':'draggable','value':'true'}
                                                  ]
                                     });
                _obj.appendChild(buildElement({ 'tag': 'i', 'classes': _libs[i].icons }));
                _obj.appendChild(buildElement({ 'tag': 'span', 'text': _libs[i].label }));
                _obj.addEventListener('touchend', function(e) { handleSidebarItem(e); });
                _obj.addEventListener('click', function(e) { handleSidebarItem(e); });

            /* Add the Item to the List */
            _lib.appendChild(_obj);
        }
        els[e].appendChild(_lib);



        /* Build the Collections */
        els[e].appendChild(buildElement({ 'tag': 'h4', 'text': NoNull(window.strings['lbl.collections'], 'Collections') }));


        /* Build the Tags */
        els[e].appendChild(buildElement({ 'tag': 'h4', 'text': NoNull(window.strings['lbl.tags'], 'Tags') }));

    }
}

/** ************************************************************************* *
 *  Sidebar Functions
 ** ************************************************************************* */
function handleSidebarItem(el) {
    if ( el === undefined || el === null || el === false ) { return; }
    if ( el.currentTarget !== undefined && el.currentTarget !== null ) { el = el.currentTarget; }
    if ( el.tagName === undefined || el.tagName === null || NoNull(el.tagName).length <= 0 ) { return; }
    for ( let e = 0; e <= 9; e++ ) {
        if ( NoNull(el.tagName).toLowerCase() != 'li' ) {
            el = el.parentElement;
        } else {
            e += 10;
        }
    }

    /* Do the action */
    if ( NoNull(el.tagName).toLowerCase() == 'li' ) {
        if ( splitSecondCheck(el) ) {
            var _key = NoNull(el.getAttribute('data-key')).toLowerCase();
            console.log(_key);
        }
    }
}

/** ************************************************************************* *
 *  Drag and Drop Functions
 ** ************************************************************************* */
var _libEl;

function libDragStart(e) {
    if ( e === undefined || e === null || e === false ) { return; }
    if ( e.dataTransfer === undefined || e.dataTransfer === null || e.dataTransfer === false ) { return; }
    if ( e.target === undefined || e.target === null || e.target === false ) { return; }

    var el = e.target;
    for ( let i = 0; i <= 9; i++ ) {
        if ( NoNull(el.tagName).toLowerCase() != 'li' ) {
            el = el.parentElement;
        } else {
            i += 10;
        }
    }

    if ( NoNull(el.tagName).toLowerCase() == 'li' ) {
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setData("text/plain", null);
        _libEl = el;
    }
}
function libDragOver(e) {
    if ( e === undefined || e === null || e === false ) { return; }
    if ( e.target === undefined || e.target === null || e.target === false ) { return; }
    e.preventDefault();

    /* Ensure we are working with a proper list item */
    var _tg = e.target;
    for ( let i = 0; i <= 9; i++ ) {
        if ( NoNull(_tg.tagName).toLowerCase() != 'li' ) {
            _tg = _tg.parentElement;
        } else {
            i += 10;
        }
    }

    /* Set the Parent */
    var _pp = _tg.parentNode;

    /* Try to arrange the items */
    try {
        if ( libIsBefore(_libEl, _tg) ) {
            _pp.insertBefore(_libEl, _tg);
        } else {
            _pp.insertBefore(_libEl, _tg.nextSibling);
        }

    } catch (error) {
        console.log(error);
    }

    /* If we have a new sort order, let's mark the list as needing a save */
    if ( _pp.classList.contains('has-changes') === false ) { _pp.classList.add('has-changes'); }
}
function libIsBefore(el1, el2) {
    if ( el1 === undefined || el1 === null || el1 === false ) { return; }
    if ( el2 === undefined || el2 === null || el2 === false ) { return; }
    if ( el1.parentNode === undefined || el1.parentNode === null || el1.parentNode === false ) { return; }
    if ( el2.parentNode === undefined || el2.parentNode === null || el2.parentNode === false ) { return; }

    if (el2.parentNode === el1.parentNode) {
        for (var cur = el1.previousSibling; cur && cur.nodeType !== 9; cur = cur.previousSibling) {
            if (cur === el2) { return true; }
        }
    }
    return false;
}
function libSetOrder() {
    var _keys = [];

    var els = document.getElementsByClassName('library-item');
    if ( els.length > 0 ) {
        for ( let e = 0; e < els.length; e++ ) {
            var _kk = NoNull(els[e].getAttribute('data-key')).toLowerCase();
            if ( _keys.indexOf(_kk) < 0 ) { _keys.push(_kk); }
        }
    }

    console.log(_keys);
}
