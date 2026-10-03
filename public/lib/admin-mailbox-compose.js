(function () {
    'use strict';

    var toggle = document.getElementById('toggle-cc-bcc');
    var ccRow = document.getElementById('compose-cc-row');
    var bccRow = document.getElementById('compose-bcc-row');
    if (!toggle || !ccRow || !bccRow) {
        return;
    }

    var forceOpen = (document.getElementById('compose-cc') || {}).value
        || (document.getElementById('compose-bcc') || {}).value;

    function setOpen(open) {
        ccRow.hidden = !open;
        bccRow.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.hidden = open;
    }

    if (forceOpen) {
        setOpen(true);
    }

    toggle.addEventListener('click', function () {
        setOpen(true);
        var cc = document.getElementById('compose-cc');
        if (cc) {
            cc.focus();
        }
    });
})();
