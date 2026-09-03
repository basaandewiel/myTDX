/* myTDX API layer - modern fetch-based replacement for jQuery ajax storage */
(function () {
    'use strict';

    var base = window.MTTX.mttUrl;
    var csrf = window.MTTX.options.csrfToken;

    function buildQs(params) {
        var parts = [];
        for (var k in params) {
            if (Object.prototype.hasOwnProperty.call(params, k)) {
                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
            }
        }
        return parts.join('&');
    }

    function handleResponse(resp) {
        if (resp.status === 403) {
            return Promise.reject({ denied: true, error: 'csrf' });
        }
        if (resp.status === 500) {
            return resp.json().then(function (j) {
                throw j;
            }).catch(function (e) {
                if (e && e.error) throw e;
                throw { error: 'Internal server error' };
            });
        }
        return resp.json();
    }

    var API = {
        base: base,

        get: function (action, params) {
            params = params || {};
            params.action = action;
            var url = base + 'ajax.php?' + buildQs(params) + '&rnd=' + Date.now();
            return fetch(url, { credentials: 'same-origin' }).then(handleResponse);
        },

        post: function (action, params) {
            params = params || {};
            params.action = action;
            params.csrf_token = csrf;
            var body = new URLSearchParams();
            for (var k in params) {
                if (Object.prototype.hasOwnProperty.call(params, k)) {
                    body.set(k, params[k]);
                }
            }
            var url = base + 'ajax.php?action=' + encodeURIComponent(action);
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(handleResponse);
        },

        loadLists: function () {
            return this.get('loadLists');
        },

        loadTasks: function (params) {
            return this.get('loadTasks', params);
        },

        newTask: function (params) {
            return this.post('newTask', params);
        },

        fullNewTask: function (params) {
            return this.post('fullNewTask', params);
        },

        editTask: function (params) {
            return this.post('editTask', params);
        },

        deleteTask: function (id) {
            return this.post('deleteTask', { id: id });
        },

        completeTask: function (id, compl) {
            return this.post('completeTask', { id: id, compl: compl ? 1 : 0 });
        },

        editNote: function (id, note) {
            return this.post('editNote', { id: id, note: note });
        },

        setPrio: function (id, prio) {
            return this.post('setPrio', { id: id, prio: prio });
        },

        setDuedate: function (id, duedate) {
            return this.post('setDuedate', { id: id, duedate: duedate || '' });
        },

        changeOrder: function (orderStr) {
            return this.post('changeOrder', { order: orderStr });
        },

        moveTask: function (id, from, to) {
            return this.post('moveTask', { id: id, from: from, to: to });
        },

        parseTaskStr: function (title) {
            return this.post('parseTaskStr', { title: title });
        },

        addList: function (name) {
            return this.post('addList', { name: name });
        },

        renameList: function (id, name) {
            return this.post('renameList', { list: id, name: name });
        },

        deleteList: function (id) {
            return this.post('deleteList', { list: id });
        },

        setSort: function (id, sort) {
            return this.post('setSort', { list: id, sort: sort });
        },

        publishList: function (id, publish) {
            return this.post('publishList', { list: id, publish: publish ? 1 : 0 });
        },

        changeListOrder: function (orderArray) {
            return this.post('changeListOrder', { order: orderArray });
        },

        setShowNotesInList: function (id, shownotes) {
            return this.post('setShowNotesInList', { list: id, shownotes: shownotes ? 1 : 0 });
        },

        setHideList: function (id, hide) {
            return this.post('setHideList', { list: id, hide: hide ? 1 : 0 });
        },

        clearCompletedInList: function (id) {
            return this.post('clearCompletedInList', { list: id });
        },

        tagCloud: function (listId) {
            return this.get('tagCloud', { list: listId });
        },

        suggestTags: function (listId, q) {
            return this.get('suggestTags', { list: listId, q: q, limit: 8 });
        },

        login: function (password) {
            return this.post('login', { login: 1, password: password });
        },

        verify2fa: function (code) {
            return this.post('verify_2fa', { verify_2fa: 1, code: code });
        },

        logout: function () {
            return this.post('logout', { logout: 1 });
        },

        generate2faSecret: function () {
            return this.get('generate_2fa_secret');
        },

        save2fa: function (enabled, secret) {
            return this.post('save_2fa_settings', { totp_enabled: enabled ? 1 : 0, totp_secret: secret });
        },

        saveSettings: function (params) {
            return this.post('_settings', params);
        }
    };

    window.MTTX.api = API;
})();