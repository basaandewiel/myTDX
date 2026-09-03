/* myTDX UI helpers - DOM utilities, menus, messages, autocomplete (no jQuery) */
(function () {
    'use strict';

    var lang = window.MTTX.lang || {};

    function L(key) {
        if (lang[key]) return lang[key];
        return key;
    }

    function el(tag, attrs, html) {
        var node = document.createElement(tag);
        if (attrs) {
            for (var k in attrs) {
                if (Object.prototype.hasOwnProperty.call(attrs, k)) {
                    if (k === 'class') node.className = attrs[k];
                    else if (k === 'html') node.innerHTML = attrs[k];
                    else node.setAttribute(k, attrs[k]);
                }
            }
        }
        if (html !== undefined) node.innerHTML = html;
        return node;
    }

    function $(sel, ctx) {
        try {
            if (!ctx && sel.charAt(0) === '#' && sel.indexOf(' ') === -1) {
                return document.getElementById(sel.slice(1));
            }
            return (ctx || document).querySelector(sel);
        } catch (e) {
            return null;
        }
    }

    function $$(sel, ctx) {
        try {
            return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
        } catch (e) {
            return [];
        }
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function prepareHtml(s, enableMarkdown) {
        // Convert URLs to links, then #NNN cross-references
        s = escapeHtml(s);
        s = s.replace(/(^|[\s>])((?:https?:\/\/|www\.)[^\s<]+)/g, function (m, pre, url) {
            var href = url.indexOf('http') === 0 ? url : 'http://' + url;
            return pre + '<a href="' + href + '" rel="nofollow" target="_blank">' + url + '</a>';
        });
        s = s.replace(/#(\d+)\b/g, '<a class="task-xref" data-tid="$1">#$1</a>');
        return s;
    }

    var M = {
        L: L,
        el: el,
        $: $,
        $$: $$,
        escapeHtml: escapeHtml,
        prepareHtml: prepareHtml,

        // Flash message system
        flashError: function (header, details) {
            console.log('[flash] ', header, details || '');
            var msg = $('#msg');
            if (!msg) return;
            msg.className = 'mtt-error';
            msg.querySelector('.msg-text').textContent = header;
            msg.querySelector('.msg-details').textContent = details || '';
            msg.style.display = 'block';
            setTimeout(function () { msg.style.display = 'none'; }, 5000);
        },

        flashInfo: function (header, details) {
            console.log('[flash-info] ', header, details || '');
            var msg = $('#msg');
            if (!msg) return;
            msg.className = 'mtt-info';
            msg.querySelector('.msg-text').textContent = header;
            msg.querySelector('.msg-details').textContent = details || '';
            msg.style.display = 'block';
            setTimeout(function () { msg.style.display = 'none'; }, 5000);
        },

        errorDenied: function () {
            M.flashError('Access denied');
        }
    };

    function hideAllMenus() {
        $$('.mtt-menu-container').forEach(function (c) {
            c.style.display = 'none';
        });
        $$('.mtt-menu-button-active').forEach(function (b) {
            b.classList.remove('mtt-menu-button-active');
        });
    }

    var activeMenu = null;
    var activeAnchor = null;

    function showMenu(el, container, anchorEl) {
        hideAllMenus();
        container.style.display = 'block';
        var body = document.body;
        container.style.position = 'fixed';
        var rect = anchorEl.getBoundingClientRect();
        var cw = container.offsetWidth;
        var ch = container.offsetHeight;
        var left = rect.left + rect.width - (cw + 2);
        if (left < 2) left = 2;
        var top = rect.bottom + 2;
        if (top + ch > window.innerHeight - 8) top = rect.top - ch - 2;
        container.style.left = left + 'px';
        container.style.top = top + 'px';
        activeMenu = container;
        activeAnchor = anchorEl;
        if (anchorEl) anchorEl.classList.add('mtt-menu-button-active');
    }

    function showSubmenu(submenu, anchorEl, parentMenu) {
        var rect = anchorEl.getBoundingClientRect();
        // Hide other menus but keep parent visible
        $$('.mtt-menu-container').forEach(function (c) {
            if (c !== submenu && c !== parentMenu) {
                c.style.display = 'none';
            }
        });
        submenu.style.display = 'block';
        submenu.style.position = 'fixed';
        var cw = submenu.offsetWidth;
        var ch = submenu.offsetHeight;
        var left = rect.right + 2;
        if (left + cw > window.innerWidth - 8) left = rect.left - cw - 2;
        var top = rect.top;
        if (top + ch > window.innerHeight - 8) top = window.innerHeight - ch - 8;
        submenu.style.left = left + 'px';
        submenu.style.top = top + 'px';
        activeMenu = submenu;
    }

    document.addEventListener('click', function (e) {
        if (activeMenu && !activeMenu.contains(e.target) && !(activeAnchor && activeAnchor.contains(e.target))) {
            hideAllMenus();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') hideAllMenus();
    });

    M.showMenu = showMenu;
    M.showSubmenu = showSubmenu;
    M.hideAllMenus = hideAllMenus;

    // Simple autocomplete for tag input
    function initAutocomplete(inputEl, sourceFn, onSelect, opts) {
        opts = opts || {};
        var getQuery = opts.getQuery || function (val) {
            return val.split(/[;,]\s*/).pop();
        };
        var apply = opts.apply || replaceLastTag;
        inputEl.addEventListener('input', function () {
            var q = getQuery(inputEl.value);
            if (!q || q.length < 1) { removeList(); return; }
            sourceFn(q).then(function (items) {
                renderList(inputEl, items, onSelect, apply);
            }).catch(function () {});
        });
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter') {
                var list = inputEl._acList;
                if (list) {
                    e.preventDefault();
                    if (e.key === 'Enter') {
                        var sel = list.querySelector('.ac_over');
                        if (sel) sel.click();
                    } else {
                        moveSel(list, e.key === 'ArrowDown' ? 1 : -1);
                    }
                }
            }
        });
    }

    function moveSel(list, dir) {
        var items = $$('li', list);
        var idx = items.indexOf(list.querySelector('.ac_over'));
        var ni = Math.max(0, Math.min(items.length - 1, (idx < 0 ? -1 : idx) + dir));
        items.forEach(function (li, i) { li.classList.toggle('ac_over', i === ni); });
    }

    function renderList(inputEl, items, onSelect, apply) {
        removeList();
        if (!items.length) return;
        apply = apply || replaceLastTag;
        var list = el('div', { class: 'ac_results' });
        list.style.position = 'fixed';
        var ul = el('ul');
        items.forEach(function (item) {
            var li = el('li', { html: escapeHtml(item.name) });
            li.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                apply(inputEl, item.name);
                if (onSelect) onSelect(item);
                removeList();
            });
            ul.appendChild(li);
        });
        list.appendChild(ul);
        document.body.appendChild(list);
        var rect = inputEl.getBoundingClientRect();
        list.style.top = rect.bottom + 'px';
        list.style.left = rect.left + 'px';
        list.style.zIndex = '99999';
        inputEl._acList = list;
    }

    function replaceLastTag(inputEl, name) {
        var val = inputEl.value;
        if (!val) return;
        var parts = val.split(/[;,]\s*/);
        parts[parts.length - 1] = name;
        inputEl.value = parts.join('; ');
    }

    function removeList() {
        document.querySelectorAll('.ac_results').forEach(function (l) { l.remove(); });
    }

    M.initAutocomplete = initAutocomplete;

    // Simple confirm wrapper
    M.confirm = function (msg) {
        return window.confirm(msg);
    };

    window.MTTX.ui = M;
})();