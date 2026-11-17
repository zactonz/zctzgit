(function () {
    'use strict';

    var root = document.getElementById('zctz');
    if (!root) { return; }

    function post(payload) {
        var body = new URLSearchParams(payload);
        return fetch('actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (response) {
            return response.json().catch(function () {
                return { ok: false, message: 'The server returned an unreadable response.' };
            });
        }).catch(function () {
            return { ok: false, message: 'The request could not be sent.' };
        });
    }

    function notify(message, kind) {
        var box = document.getElementById('zctz-notice');
        box.textContent = message;
        box.className = 'zctz-note zctz-note--' + (kind || 'ok');
        box.classList.remove('zctz-hide');
        box.scrollIntoView({ block: 'nearest' });
    }

    function busy(on) {
        root.classList.toggle('zctz-busy', !!on);
    }

    function showLog(id) {
        var panel = document.getElementById('zctz-log');
        var output = document.getElementById('zctz-log-output');
        busy(true);
        post({ action: 'log', id: id }).then(function (data) {
            busy(false);
            output.textContent = data.log && data.log.length ? data.log : 'Nothing logged yet.';
            panel.classList.remove('zctz-hide');
            panel.scrollIntoView({ block: 'nearest' });
        });
    }

    function showKey(id) {
        busy(true);
        post({ action: 'public_key', id: id }).then(function (data) {
            busy(false);
            if (!data.public_key) {
                notify('No deploy key exists for this repository.', 'warn');
                return;
            }
            var panel = document.getElementById('zctz-key');
            document.getElementById('zctz-key-output').textContent = data.public_key;
            panel.classList.remove('zctz-hide');
            panel.scrollIntoView({ block: 'nearest' });
        });
    }

    function act(action, id, confirmText) {
        if (confirmText && !window.confirm(confirmText)) { return; }
        busy(true);
        post({ action: action, id: id }).then(function (data) {
            busy(false);
            notify(data.message, data.ok ? 'ok' : 'error');
            if (data.ok && action !== 'deploy') { window.location.reload(); return; }
            if (data.ok) { window.setTimeout(function () { window.location.reload(); }, 1200); }
        });
    }

    root.addEventListener('click', function (event) {
        var button = event.target.closest('[data-zctz-action]');
        if (!button) { return; }
        event.preventDefault();

        var id = button.getAttribute('data-zctz-id');
        var action = button.getAttribute('data-zctz-action');

        if (action === 'log') { showLog(id); return; }
        if (action === 'key') { showKey(id); return; }
        if (action === 'close') {
            button.closest('.zctz-panel').classList.add('zctz-hide');
            return;
        }
        if (action === 'deploy') { act('deploy', id, 'Deploy this repository now?'); return; }
        if (action === 'toggle') { act('toggle', id, null); return; }
        if (action === 'delete') {
            act('delete', id, 'Remove this repository from the list? The files on the server are left in place.');
        }
    });

    var form = document.getElementById('zctz-add');
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            busy(true);
            post(new FormData(form)).then(function (data) {
                busy(false);
                notify(data.message, data.ok ? 'ok' : 'error');
                if (data.ok && data.public_key) {
                    document.getElementById('zctz-key-output').textContent = data.public_key;
                    document.getElementById('zctz-key').classList.remove('zctz-hide');
                    return;
                }
                if (data.ok) { window.location.reload(); }
            });
        });

        form.addEventListener('change', function (event) {
            if (event.target.name !== 'credential') { return; }
            var token = document.getElementById('zctz-token-field');
            token.classList.toggle('zctz-hide', event.target.value !== 'token');
        });
    }
}());
