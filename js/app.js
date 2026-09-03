/* myTDX client-side application - vanilla JS (no jQuery) */
(function () {
    'use strict';

    var M = window.MTTX.ui;
    var L = M.L;
    var api = window.MTTX.api;
    var opt = window.MTTX.options;
    var tpl = window.MTTX.templateUrl;

    var state = {
        lists: [],
        curListId: null,
        allSort: 1,
        tasks: {},
        order: [],
        filter: { compl: 0, search: '', tags: '', due: '' },
        flag: {
            needAuth: opt.needAuth,
            isLogged: opt.isLogged,
            readOnly: opt.isReadonly,
            tagsChanged: true,
            editFormChanged: false
        },
        taskCnt: { total: 0, past: 0, today: 0, soon: 0 },
        showCompl: false,
        currentFilter: 'all' // all|past|today|soon
    };

    /* ---------- General helpers ---------- */

    function esc(s) {
        return M.escapeHtml(s);
    }

    function prepareTaskHtml(item, noteExpanded) {
        var cls = [];
        if (item.compl) cls.push('task-completed');
        if (item.dueClass) cls.push(item.dueClass);
        if (item.noteText) cls.push('task-has-note');
        if (noteExpanded) cls.push('task-expanded');
        if (item.tags_ids) {
            var ids = item.tags_ids.split(',');
            ids.forEach(function (id) { cls.push('tag-id-' + id); });
        }

        var h = '<li id="taskrow_' + item.id + '" class="' + cls.join(' ') + '" data-id="' + item.id + '">';

        // Context menu button
        if (!state.flag.readOnly) {
            h += '<a class="taskactionbtn" href="#" data-action="menu" title=""></a>';
        }

        h += '<div class="task-left">';
        h += '<input type="checkbox" class="task-check" ' + (item.compl ? 'checked' : '') + (state.flag.readOnly ? ' disabled' : '') + '>';
        if (item.noteText) {
            h += '<span class="task-toggle"></span>';
        }
        h += '</div>';

        h += '<div class="task-middle">';
        h += '<div class="task-through">';
        h += preparePrio(item.prio, item.id);
        if (item.listId === -1 || opt.taskxrefs) {
            h += '<a class="task-xref" data-tid="' + item.id + '" href="#">#' + item.id + '</a>';
        }
        h += '<span class="task-title"><a href="#">' + item.title + '</a></span>';
        if (state.curListId === -1) {
            var listName = '';
            var l = getListById(item.listId);
            if (l) listName = l.name;
            h += '<span class="task-listname">' + esc(listName) + '</span>';
        }
        h += prepareDuedate(item);
        h += prepareTagsStr(item);
        if (opt.showdate && item.dateInline) {
            h += '<span class="task-date" title="' + esc(item.dateInlineTitle) + '">' + esc(item.dateInline) + '</span>';
        }
        if (item.dateCompletedInline) {
            h += '<span class="task-date-completed" title="' + esc(item.dateCompletedInlineTitle) + '">' + esc(item.dateCompletedInline) + '</span>';
        }
        h += '</div>';
        h += '</div>';

        // Note block
        h += '<div class="task-note-block">';
        h += '<div class="task-note">' + item.note + '</div>';
        h += '<div class="task-note-area">';
        h += '<textarea data-note-' + item.id + '>' + esc(item.noteText) + '</textarea>';
        h += '<div class="task-note-actions">';
        h += '<a href="#" data-action="notesave">' + esc(L('actionNoteSave')) + '</a> / ';
        h += '<a href="#" data-action="notecancel">' + esc(L('actionNoteCancel')) + '</a>';
        h += '</div></div>';
        h += '</div>';

        h += '</li>';
        return h;
    }

    function preparePrio(prio, id) {
        var cls = 'prio-zero';
        if (prio > 0) cls = prio === 2 ? 'prio-pos' : 'prio-pos-1';
        if (prio < 0) cls = 'prio-neg';
        var display = prio > 0 ? '+' + prio : (prio < 0 ? prio : '&plusmn;0');
        return '<span class="task-prio ' + cls + '" data-id="' + id + '" data-prio="' + prio + '">' + display + '</span>';
    }

    function prepareTagsStr(item) {
        if (!item.tags) return '';
        var tags = item.tags.split(',');
        var ids = item.tags_ids ? item.tags_ids.split(',') : [];
        var h = '<span class="task-tags">';
        tags.forEach(function (tag, i) {
            var tid = ids[i] || '';
            var cls = 'tag';
            var prefix = '';
            if (tag.charAt(0) === '@') { cls += ' tag_user'; }
            else if (tag.charAt(0) === '=') { cls += ' tag_time'; }
            else if (tag.charAt(0) === '~') { cls += ' tag_fixme'; }
            else if (tag.charAt(0) === '!') { cls += ' tag_highlight'; }
            h += '<a class="' + cls + '" tag="' + esc(tag) + '" tagid="' + tid + '" href="#">' + esc(tag) + '</a>';
        });
        h += '</span>';
        return h;
    }

    function prepareDuedate(item) {
        if (!item.dueStr) return '';
        return '<span class="duedate task-through-right" title="' + esc(item.dueTitle || '') + '" data-date="' + esc(item.duedate || '') + '">&rarr; ' + esc(item.dueStr) + '</span>';
    }

    function getListById(id) {
        for (var i = 0; i < state.lists.length; i++) {
            if (state.lists[i].id === id) return state.lists[i];
        }
        return null;
    }

    function tagSuggestSource() {
        return function (q) {
            if (!q) return Promise.resolve([]);
            var curListId = state.curListId === -1 ? (state.lists[0] ? state.lists[0].id : 0) : state.curListId;
            return api.suggestTags(curListId, q).then(function (json) {
                return (json && json.suggestions || []).map(function (s) {
                    return { name: s.name, id: s.id };
                });
            });
        };
    }

    function getCurList() {
        if (state.curListId === -1) {
            return { id: -1, name: L('alltasks'), sort: state.allSort, published: 0 };
        }
        return getListById(state.curListId) || { id: 0, name: '', sort: 0 };
    }

    /* ---------- List tabs ---------- */

    function renderTabs() {
        var tabs = M.$('.mtt-tabs');
        tabs.innerHTML = '';

        var visible = state.lists.filter(function (l) { return !l.hidden; });
        if (visible.length === 1) {
            tabs.classList.add('mtt-tabs-only-one');
        } else {
            tabs.classList.remove('mtt-tabs-only-one');
        }

        state.lists.forEach(function (list) {
            var li = document.createElement('li');
            li.className = 'mtt-tab' + (list.id === state.curListId ? ' mtt-tabs-selected' : '') + (list.hidden ? ' mtt-tabs-hidden' : '');
            li.setAttribute('data-list-id', list.id);
            var a = document.createElement('a');
            a.href = '#list/' + list.id;
            var span = document.createElement('span');
            span.textContent = list.name;
            a.appendChild(span);
            var action = document.createElement('div');
            action.className = 'list-action';
            action.setAttribute('data-list-action', list.id);
            action.setAttribute('title', 'List menu (sort, export, publish...)');
            a.appendChild(action);
            li.appendChild(a);
            tabs.appendChild(li);
        });

        // All-tasks tab
        var allTasks = M.$('#list_all');
        if (allTasks) {
            if (state.curListId === -1) {
                allTasks.classList.remove('mtt-tabs-hidden');
                allTasks.classList.add('mtt-tabs-selected');
            } else {
                allTasks.classList.remove('mtt-tabs-selected');
            }
            if (state.lists.length > 1 && !state.flag.readOnly) {
                allTasks.classList.remove('mtt-tabs-hidden');
            } else {
                allTasks.classList.add('mtt-tabs-hidden');
            }
        }

        // Readonly handling
        document.body.classList.toggle('readonly', state.flag.readOnly);
        var body = M.$('#mtt_body');
        if (body) body.classList.toggle('readonly', state.flag.readOnly);
    }

    function selectList(id) {
        if (id === -1) {
            state.curListId = -1;
        } else {
            var l = getListById(id);
            if (!l) id = state.lists.length ? state.lists[0].id : 0;
            state.curListId = id;
        }
        renderTabs();
        loadTasks();
        updateAccessStatus();
        window.history.replaceState(null, '', '#list/' + (state.curListId === -1 ? 'alltasks' : state.curListId));
    }

    /* ---------- Tasks ---------- */

    function loadTasks() {
        var curList = getCurList();
        var params = {
            list: state.curListId === -1 ? -1 : state.curListId,
            sort: curList.sort || 0
        };
        if (state.filter.search) params.s = state.filter.search;
        if (state.filter.tags) params.t = state.filter.tags;

        if (state.showCompl) {
            params.compl = 1;
        } else {
            params.compl = 0;
        }

        return api.loadTasks(params).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            state.tasks = {};
            state.order = [];
            state.taskCnt = { total: 0, past: 0, today: 0, soon: 0 };

            var listArr = json.list || [];
            listArr.forEach(function (t) {
                state.tasks[t.id] = t;
                state.order.push(t.id);
                state.taskCnt.total++;
                if (t.dueClass === 'past') state.taskCnt.past++;
                if (t.dueClass === 'today') state.taskCnt.today++;
                if (t.dueClass === 'soon') state.taskCnt.soon++;
            });

            renderTasks();
            updateCounts();
            updateFilters();
        }).catch(function (err) {
            if (err && err.denied) M.errorDenied();
            else { console.error('[apperr]', err && (err.stack || (err.name + ': ' + err.message))); M.flashError('Some error occurred'); }
        });
    }

    function renderTasks() {
        var container = M.$('#tasklist');
        container.innerHTML = '';

        var showNotesDefault = getCurList().showNotes;
        var visible = state.order.filter(function (id) {
            var t = state.tasks[id];
            if (!t) return false;
            if (state.filter.due) {
                if (t.dueClass !== state.filter.due) return false;
            }
            return true;
        });

        visible.forEach(function (id) {
            var t = state.tasks[id];
            var expand = showNotesDefault || t.compl && false;
            container.insertAdjacentHTML('beforeend', prepareTaskHtml(t, expand));
        });

        // Re-apply due filters
        applyDueFilter();
    }

    function updateCounts() {
        var el = M.$('#total');
        if (el) el.textContent = state.taskCnt.total;
        var c = function (id, v) { var e = M.$(id); if (e) e.textContent = v; };
        c('#cnt_total', state.taskCnt.total);
        c('#cnt_past', state.taskCnt.past);
        c('#cnt_today', state.taskCnt.today);
        c('#cnt_soon', state.taskCnt.soon);
    }

    function applyDueFilter() {
        var tl = M.$('#tasklist');
        if (!tl) return;
        tl.className = 'sortable';
        if (state.filter.due) {
            tl.classList.add('filter-' + state.filter.due);
        }
    }

    function updateFilters() {
        var container = M.$('#mtt_filters');
        if (!container) return;
        container.innerHTML = '';
        if (state.filter.tags) {
            var span = document.createElement('span');
            span.className = 'tag-filter';
            span.innerHTML = '<span class="mtt-filter-header">' + esc(L('tagfilter')) + '</span>';
            state.filter.tags.split(',').forEach(function (tag) {
                var exclude = tag.charAt(0) === '^';
                var a = document.createElement('a');
                a.href = '#';
                a.textContent = exclude ? tag.slice(1) : tag;
                a.className = 'tag-link' + (exclude ? ' tag-filter-exclude' : '');
                a.setAttribute('data-tag', tag);
                a.addEventListener('click', function (e) { e.preventDefault(); removeTagFilter(tag); });
                span.appendChild(a);
                span.appendChild(document.createTextNode(' '));
            });
            var close = document.createElement('a');
            close.href = '#';
            close.className = 'mtt-filter-close';
            close.addEventListener('click', function (e) { e.preventDefault(); clearTagFilter(); });
            span.appendChild(close);
            container.appendChild(span);
        }
    }

    function setDueFilter(due) {
        state.filter.due = state.filter.due === due ? '' : due;
        applyDueFilter();
        updateAccessStatus();
    }

    function clearTagFilter() {
        state.filter.tags = '';
        state.flag.tagsChanged = false;
        loadTasks();
    }

    function removeTagFilter(tag) {
        var tags = state.filter.tags.split(',').filter(function (t) { return t !== tag; });
        state.filter.tags = tags.join(',');
        loadTasks();
    }

    /* ---------- task mutations ---------- */

    function addQuickTask() {
        var input = M.$('#task');
        var title = input.value.trim();
        if (!title) return;

        var params = { list: state.curListId === -1 ? (state.lists[0] ? state.lists[0].id : 0) : state.curListId, title: title };
        if (opt.autotag && state.filter.tags) {
            params.tag = state.filter.tags.split(',').filter(function (t) { return t.charAt(0) !== '^'; }).pop() || '';
        }

        api.newTask(params).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            if (!parseInt(json.total)) return;
            var t = json.list[0];
            input.value = '';
            state.tasks[t.id] = t;
            state.order.unshift(t.id);
            state.taskCnt.total++;
            if (t.dueClass === 'past') state.taskCnt.past++;
            if (t.dueClass === 'today') state.taskCnt.today++;
            if (t.dueClass === 'soon') state.taskCnt.soon++;
            state.flag.tagsChanged = true;
            renderTasks();
            updateCounts();
            updateFilters();
        }).catch(function (e) {
            if (e && e.denied) M.errorDenied();
        });
    }

    function completeTask(id, compl) {
        var curList = getCurList();
        api.completeTask(id, compl ? 1 : 0).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            if (!parseInt(json.total)) return;
            var t = json.list[0];
            var wasInView = !state.showCompl;
            state.tasks[id] = t;
            renderTasks();
            updateCounts();
        }).catch(function () {});
    }

    function deleteTask(id) {
        if (!M.confirm(L('confirmDelete'))) return;
        api.deleteTask(id).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            if (!parseInt(json.total)) return;
            var t = state.tasks[id];
            delete state.tasks[id];
            var idx = state.order.indexOf(id);
            if (idx >= 0) state.order.splice(idx, 1);
            state.taskCnt.total = Math.max(0, state.taskCnt.total - 1);
            if (t) {
                if (t.dueClass === 'past') state.taskCnt.past = Math.max(0, state.taskCnt.past - 1);
                if (t.dueClass === 'today') state.taskCnt.today = Math.max(0, state.taskCnt.today - 1);
                if (t.dueClass === 'soon') state.taskCnt.soon = Math.max(0, state.taskCnt.soon - 1);
            }
            state.flag.tagsChanged = true;
            renderTasks();
            updateCounts();
        }).catch(function () {});
    }

    function setPrio(id, prio) {
        api.setPrio(id, prio).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            if (state.tasks[id]) {
                state.tasks[id].prio = prio;
            }
            renderTasks();
        }).catch(function () {});
    }

    function openDuedatePicker(el, id) {
        window.MTTX.datepicker.open(el, function (dateStr) {
            api.setDuedate(id, dateStr).then(function (json) {
                if (json.denied) { M.errorDenied(); return; }
                if (state.tasks[id] && json.list[0]) {
                    updateDuedateState(id, json.list[0]);
                }
                renderTasks();
            }).catch(function () {});
        }, function () {
            api.setDuedate(id, '').then(function (json) {
                if (json.denied) { M.errorDenied(); return; }
                if (state.tasks[id] && json.list[0]) {
                    updateDuedateState(id, json.list[0]);
                }
                renderTasks();
            }).catch(function () {});
        });
    }

    function updateDuedateState(id, r) {
        state.tasks[id].duedate = r.duedate || '';
        state.tasks[id].dueStr = r.dueStr || '';
        state.tasks[id].dueClass = r.dueClass || '';
        state.tasks[id].dueTitle = r.dueTitle || '';
        state.tasks[id].dueInt = r.dueInt;
    }

    /* ---------- note editing (inline) ---------- */

    function toggleNote(id) {
        var li = M.$('#taskrow_' + id);
        if (!li) return;
        li.classList.toggle('task-expanded');
    }

    function saveInlineNote(id) {
        var ta = M.$('textarea[data-note-' + id + ']');
        if (!ta) return;
        var note = ta.value;
        api.editNote(id, note).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            if (!parseInt(json.total)) return;
            var r = json.list[0];
            if (state.tasks[id]) {
                state.tasks[id].noteText = r.noteText;
                state.tasks[id].note = r.note;
            }
            var li = M.$('#taskrow_' + id);
            if (li) {
                li.classList.add('task-has-note');
                li.querySelector('.task-note').innerHTML = r.note;
                li.querySelector('.task-note-area').style.display = 'none';
                li.classList.add('task-expanded');
            }
        }).catch(function () {});
    }

    function cancelInlineNote(id) {
        var li = M.$('#taskrow_' + id);
        if (!li) return;
        li.querySelector('.task-note-area').style.display = 'none';
    }

    /* ---------- task edit page ---------- */

    function showTaskEdit(id) {
        var page = M.$('#page_taskedit');
        page.style.display = 'block';
        M.$('#page_tasks').style.display = 'none';
        page.classList.remove('mtt-inadd');
        page.classList.add('mtt-inedit');

        var form = M.$('#taskedit_form');
        form.elements.id.value = id;
        form.elements.isadd.value = 0;

        var t = state.tasks[id];
        if (t) {
            form.elements.task.value = t.title.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#39;/g, "'");
            form.elements.note.value = t.noteText;
            form.elements.prio.value = String(t.prio);
            form.elements.duedate.value = t.duedate || '';
            form.elements.tags.value = t.tags ? t.tags.split(',').join('; ') : '';
            var existing = M.$('#taskedit-tid');
            if (existing) existing.textContent = '#' + id;
            var dc = page.querySelector('.date-created span');
            if (dc) dc.textContent = t.date || '';
            var dcm = page.querySelector('.date-completed span');
            if (dcm) dcm.textContent = t.dateCompleted || '';
        }

        state.flag.editFormChanged = false;
        loadAllTags();
    }

    function showTaskAdd() {
        var page = M.$('#page_taskedit');
        page.style.display = 'block';
        M.$('#page_tasks').style.display = 'none';
        page.classList.add('mtt-inadd');
        page.classList.remove('mtt-inedit');

        var form = M.$('#taskedit_form');
        form.elements.id.value = '';
        form.elements.isadd.value = 1;
        form.elements.task.value = '';
        form.elements.note.value = '';
        form.elements.prio.value = '0';
        form.elements.duedate.value = '';
        form.elements.tags.value = '';

        var e = M.$('#taskedit-tid');
        if (e) e.textContent = '';
        var dc = page.querySelector('.date-created span');
        if (dc) dc.textContent = '';
        var dcm = page.querySelector('.date-completed span');
        if (dcm) dcm.textContent = '';

        state.flag.editFormChanged = false;
        loadAllTags();
    }

    function pageBack() {
        M.$('#page_taskedit').style.display = 'none';
        M.$('#page_ajax').style.display = 'none';
        M.$('#page_tasks').style.display = 'block';
    }

    function submitTaskEdit() {
        var form = M.$('#taskedit_form');
        var isadd = parseInt(form.elements.isadd.value);
        var id = parseInt(form.elements.id.value || '0');
        var title = form.elements.task.value.trim();
        if (!title) return;

        var params = {
            title: title,
            note: form.elements.note.value,
            prio: form.elements.prio.value,
            duedate: form.elements.duedate.value,
            tags: form.elements.tags.value
        };

        var curListId = state.curListId === -1 ? (state.lists[0] ? state.lists[0].id : 0) : state.curListId;
        if (isadd) params.list = curListId;

        var p = isadd ? api.fullNewTask(params) : api.editTask(Object.assign({ id: id }, params));

        p.then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            if (!parseInt(json.total)) return;
            var t = json.list[0];
            state.flag.tagsChanged = true;
            state.showCompl = false; // since we saved, filter incomplete
            state.tasks[t.id] = t;
            if (state.order.indexOf(t.id) < 0) state.order.unshift(t.id);
            state.flag.editFormChanged = false;
            pageBack();
            loadTasks();
            renderTabs();
        }).catch(function () {});
    }

    /* ---------- tag cloud ---------- */

    function toggleTagCloud() {
        var cloud = M.$('#tagcloud');
        if (cloud.style.display === 'none' || !cloud.style.display) {
            loadTagCloudShow();
        } else {
            cloud.style.display = 'none';
        }
    }

    function loadTagCloudShow() {
        var cloud = M.$('#tagcloud');
        cloud.style.display = 'block';
        var content = M.$('#tagcloudcontent');
        var load = M.$('#tagcloudload');
        load.style.display = 'block';
        content.innerHTML = '';

        api.tagCloud(state.curListId === -1 ? 0 : state.curListId).then(function (json) {
            load.style.display = 'none';
            if (!parseInt(json.total)) {
                content.textContent = '';
                return;
            }
            var h = '';
            json.cloud.forEach(function (tag) {
                if (tag.alien && !opt.alientags) return;
                var cls = 'tag w' + tag.w;
                if (tag.alien) cls += ' tag_alien';
                h += '<a class="' + cls + '" data-tag="' + esc(tag.tag) + '" tagid="' + tag.id + '" href="#">' + esc(tag.tag) + ' ' + tag.w + '</a><br>';
            });
            content.innerHTML = h;
            state.flag.tagsChanged = false;
        }).catch(function () { load.style.display = 'none'; });
    }

    function tagFromCloudClick(e) {
        var a = e.target.closest('.tag');
        if (!a) return;
        e.preventDefault();
        addTagFilter(a.getAttribute('data-tag'));
        M.hideAllMenus();
        M.$('#tagcloud').style.display = 'none';
    }

    function addTagFilter(text) {
        var tags = state.filter.tags ? state.filter.tags.split(',') : [];
        if (tags.indexOf(text) === -1) {
            tags.push(text);
        }
        state.filter.tags = tags.join(',');
        loadTasks();
    }

    /* ---------- all tags in edit page ---------- */

    function loadAllTags() {
        var curListId = state.curListId === -1 ? (state.lists[0] ? state.lists[0].id : 0) : state.curListId;
        api.tagCloud(curListId).then(function (json) {
            var container = M.$('#alltags .tags-list');
            if (!container) return;
            var h = '';
            json.cloud.forEach(function (tag) {
                if (tag.alien && !opt.alientags) return;
                h += '<a class="tag' + (tag.alien ? ' tag_alien' : '') + '" data-tag="' + esc(tag.tag) + '" href="#">' + esc(tag.tag) + '</a> ';
            });
            container.innerHTML = h;
        }).catch(function () {});
    }

    /* ---------- lists CRUD ---------- */

    function addList() {
        var name = prompt(L('addList'), L('addListDefault'));
        if (name === null) return;
        name = name.trim();
        if (!name) return;
        api.addList(name).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            if (!parseInt(json.total)) return;
            state.lists.push(json.list[0]);
            renderTabs();
            selectList(json.list[0].id);
        }).catch(function () {});
    }

    function renameList() {
        var cur = getCurList();
        if (cur.id === -1 || cur.id === 0) return;
        var name = prompt(L('renameList'), cur.name);
        if (name === null) return;
        name = name.trim();
        if (!name) return;
        api.renameList(cur.id, name).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            if (!parseInt(json.total)) return;
            var l = getListById(cur.id);
            if (l && json.list[0]) {
                l.name = json.list[0].name;
            }
            renderTabs();
        }).catch(function () {});
    }

    function deleteList() {
        var cur = getCurList();
        if (cur.id === -1 || cur.id === 0) return;
        if (!M.confirm(L('deleteList').replace(/\\n/g, '\n'))) return;
        api.deleteList(cur.id).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            state.lists = state.lists.filter(function (l) { return l.id !== cur.id; });
            var nextId = state.lists.length ? state.lists[0].id : 0;
            if (state.curListId === cur.id) {
                state.curListId = nextId;
            }
            state.flag.tagsChanged = true;
            renderTabs();
            loadTasks();
        }).catch(function () {});
    }

    function togglePublish() {
        var cur = getCurList();
        if (cur.id === -1 || cur.id === 0) return;
        var publish = cur.published ? 0 : 1;
        api.publishList(cur.id, publish).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            var l = getListById(cur.id);
            if (l) l.published = publish;
            renderTabs();
            updateAccessStatus();
        }).catch(function () {});
    }

    function toggleHide() {
        var cur = getCurList();
        if (cur.id === -1 || cur.id === 0) return;
        var hide = cur.hidden ? 0 : 1;
        api.setHideList(cur.id, hide).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            var l = getListById(cur.id);
            if (l) l.hidden = hide;
            renderTabs();
        }).catch(function () {});
    }

    function toggleShowCompleted() {
        state.showCompl = !state.showCompl;
        var cur = getCurList();
        if (cur.id > 0) {
            api.setSort(cur.id, cur.sort || 0).then(function () {});
        }
        loadTasks();
    }

    function setSortMode(mode) {
        var cur = getCurList();
        if (!cur) return;
        if (mode === cur.sort) return;
        if (cur.id > 0) {
            api.setSort(cur.id, mode).then(function (json) {
                if (json.denied) { M.errorDenied(); return; }
                if (getListById(cur.id)) getListById(cur.id).sort = mode;
                loadTasks();
            }).catch(function () {});
        } else {
            // All-tasks view: store the sort for this view then reload
            state.allSort = mode;
            loadTasks();
        }
        renderTabs();
    }

    function exportCurList(csv) {
        var cur = getCurList();
        if (cur.id === -1 || cur.id === 0) return;
        var format = csv ? 'csv' : 'ical';
        window.location.href = 'export.php?list=' + cur.id + '&format=' + format;
    }

    function feedCurList() {
        var cur = getCurList();
        if (cur.id === -1 || cur.id === 0) return;
        window.location.href = 'feed.php?list=' + cur.id;
    }

    function clearCompleted() {
        var cur = getCurList();
        if (cur.id === -1 || cur.id === 0) return;
        if (!M.confirm(L('clearCompleted').replace(/\\n/g, '\n'))) return;
        api.clearCompletedInList(cur.id).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            state.flag.tagsChanged = true;
            loadTasks();
        }).catch(function () {});
    }

    /* ---------- move task to list ---------- */

    function moveTaskTo(id, toListId) {
        var curId = state.curListId;
        api.moveTask(id, curId, toListId).then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            if (!parseInt(json.total)) return;

            delete state.tasks[id];
            var idx = state.order.indexOf(id);
            if (idx >= 0) state.order.splice(idx, 1);
            state.taskCnt.total = Math.max(0, state.taskCnt.total - 1);
            state.flag.tagsChanged = true;
            renderTasks();
            updateCounts();
        }).catch(function () {});
    }

    /* ---------- drag & drop sorting ---------- */

    function initSortable() {
        var container = M.$('#tasklist');
        if (!container) return;

        container.addEventListener('dragstart', function (e) {
            var li = e.target.closest('li[data-id]');
            if (li) {
                e.dataTransfer.setData('text/plain', li.getAttribute('data-id'));
                li.classList.add('mtt-dragging');
            }
        });

        container.addEventListener('dragover', function (e) {
            e.preventDefault();
            var li = e.target.closest('li[data-id]');
            if (li) {
                var dragging = container.querySelector('.mtt-dragging');
                if (dragging && dragging !== li) {
                    var rect = li.getBoundingClientRect();
                    var after = (e.clientY - rect.top) > (rect.height / 2);
                    container.insertBefore(dragging, after ? li.nextSibling : li);
                }
            }
        });

        container.addEventListener('dragend', function (e) {
            container.querySelectorAll('.mtt-dragging').forEach(function (el) {
                el.classList.remove('mtt-dragging');
            });
            saveOrder();
        });
    }

    function saveOrder() {
        var container = M.$('#tasklist');
        var items = container.querySelectorAll('li[data-id]');
        var orderArray = [];
        items.forEach(function (li) { orderArray.push(parseInt(li.getAttribute('data-id'))); });

        // Compute diff relative to previous order
        var diffs = {};
        orderArray.forEach(function (id, idx) {
            var prevIdx = state.order.indexOf(id);
            diffs[id] = idx - prevIdx;
        });

        // Rebuild state.order
        state.order = orderArray;

        if (Object.keys(diffs).some(function (id) { return diffs[id] !== 0; })) {
            var qs = orderArray.map(function (id, idx) { return id + '=' + diffs[id]; }).join('&');
            api.changeOrder(qs).then(function (json) {
                if (json.denied) { M.errorDenied(); return; }
            }).catch(function () {});
        }
    }

    function canSort() {
        var cur = getCurList();
        return cur && (cur.sort === 0 || cur.sort === undefined);
    }

    /* ---------- menus ---------- */

    function showListMenu(anchor) {
        var cur = getCurList();
        var menu = M.$('#listmenucontainer');

        // Toggle states
        var realItems = menu.querySelectorAll('.mtt-need-real-list');
        realItems.forEach(function (item) {
            item.style.display = (cur.id === -1 || cur.id === 0) ? 'none' : '';
        });
        var publishItem = M.$('#btnPublish');
        if (publishItem) {
            var pubIcon = publishItem.querySelector('.menu-icon');
            if (pubIcon) pubIcon.textContent = cur.published ? '\u2713' : '';
        }

        // Indicate the active sort method and direction
        var sortMap = { 0: 'sortByHand', 1: 'sortByPrio', 2: 'sortByDueDate', 3: 'sortByDateCreated', 4: 'sortByDateModified' };
        var curSort = cur.sort || 0;
        var baseSort = curSort;
        var reversed = curSort >= 100;
        if (reversed) baseSort = curSort - 100;
        var sortIds = ['sortByHand', 'sortByPrio', 'sortByDueDate', 'sortByDateCreated', 'sortByDateModified'];
        sortIds.forEach(function (id) {
            var el = M.$('#' + id);
            if (!el) return;
            var dir = el.querySelector('.mtt-sort-direction');
            var isActive = (sortMap[baseSort] === id) && id !== 'sortByHand';
            var icon = (isActive ? (reversed ? '\u25b2' : '\u25bc') : '\u00a0');
            if (dir) dir.textContent = icon;
        });

        M.showMenu(menu, menu, anchor);
    }

    function showTaskMenu(anchor, taskId) {
        var menu = M.$('#taskcontextcontainer');
        menu._taskId = taskId;
        M.showMenu(menu, menu, anchor);
    }

    function refreshMoveSubmenu() {
        var container = M.$('#cmenulistscontainer ul');
        container.innerHTML = '';
        state.lists.forEach(function (list) {
            if (list.id === state.curListId) return;
            var li = document.createElement('li');
            li.setAttribute('data-move-to', list.id);
            li.innerHTML = '<div class="menu-icon"></div>' + esc(list.name);
            container.appendChild(li);
        });
    }

    function showPrioSubmenu(anchor) {
        var menu = M.$('#cmenupriocontainer');
        var pid = state.curListId;
        M.showMenu(menu, menu, anchor);
    }

    /* ---------- auth ---------- */

    function updateAccessStatus() {
        var needAuth = opt.needAuth;
        var isLogged = state.flag.isLogged;
        var readOnly = state.flag.readOnly;

        var ownerEls = document.querySelectorAll('.menu-owner');
        var barAuth = M.$('#bar_auth');
        var barLogout = M.$('#bar_logout');
        var barLogin = M.$('#bar_login');
        var barPublic = M.$('#bar_public');

        if (needAuth && !isLogged) {
            state.flag.readOnly = true;
            readOnly = true;
            if (ownerEls) ownerEls.forEach(function (e) { e.style.display = 'none'; });
            if (barAuth) barAuth.style.display = '';
            if (barLogin) barLogin.style.display = '';
        } else {
            state.flag.readOnly = false;
            readOnly = false;
            if (ownerEls) ownerEls.forEach(function (e) { e.style.display = ''; });
            if (barAuth) barAuth.style.display = isLogged ? 'none' : '';
            if (barLogin) barLogin.style.display = isLogged ? 'none' : '';
        }

        var body = M.$('#mtt_body');
        if (body) body.classList.toggle('readonly', readOnly);
        renderTabs();

        if (needAuth && !isLogged) {
            showLoginForm();
        }
    }

    function showLoginForm() {
        M.$('#authform').style.display = 'block';
        M.$('#password').focus();
    }

    function doLogin() {
        var p = M.$('#password');
        if (!p || !p.value) return;
        var password = p.value;
        api.login(password).then(function (json) {
            if (json.disabled) {
                M.flashError('Login disabled');
                return;
            }
            if (json.requires_2fa) {
                // Show 2FA form inside the same form element (keep form for submit)
                var form = M.$('#login_form');
                form.innerHTML = '<div class="h">2FA</div><div><input type="text" id="2fa_code" maxlength="8" autocomplete="off" /></div><div><input type="submit" value="Verify" /></div>';
                var codeInput = M.$('#2fa_code');
                codeInput.focus();
            } else if (json.logged) {
                M.$('#authform').style.display = 'none';
                window.location.reload();
            } else {
                M.flashError(L('invalidpass'));
                var p = M.$('#password');
                if (p) { p.value = ''; p.focus(); }
            }
        }).catch(function (err) { console.error('[apperr]', err && (err.stack || (err.name + ': ' + err.message))); M.flashError('Some error occurred'); });
    }

    function verify2fa() {
        var ci = M.$('#2fa_code');
        if (!ci) return;
        var code = ci.value;
        api.verify2fa(code).then(function (json) {
            if (json.verified && json.logged) {
                M.$('#authform').style.display = 'none';
                window.location.reload();
            } else {
                M.flashError('Invalid 2FA code');
                var c = M.$('#2fa_code');
                if (c) { c.value = ''; c.focus(); }
            }
        }).catch(function () {});
    }

    function doLogout() {
        api.logout().then(function () {
            window.location.reload();
        }).catch(function () {});
    }

    /* ---------- settings ---------- */

    function loadSettingsPage() {
        var page = M.$('#page_ajax');
        page.style.display = 'block';
        M.$('#page_tasks').style.display = 'none';
        page.className = 'mtt-page-settings';
        page.classList.remove('mtt-page-back');

        var h = '<div style="padding:10px">';
        h += '<h2>' + esc(L('set_header')) + '</h2>';
        h += '<form id="settings_form" method="post">';
        h += '<input type="hidden" name="save" value="1">';

        h += '<table class="mtt-settings-table">';

        h += '<tr><th>' + esc(L('set_title')) + '<div class="descr">' + esc(L('set_title_descr')) + '</div></th>';
        h += '<td><input type="text" name="title" class="in350" value="' + esc(opt.title || '') + '"></td></tr>';

        h += '<tr><th>' + esc(L('set_language')) + '</th><td>';
        h += '<select name="lang">';
        ['en', 'de', 'ru'].forEach(function (l) {
            h += '<option value="' + l + '">' + l + '</option>';
        });
        h += '</select></td></tr>';

        h += '<tr><th>' + esc(L('set_newpass')) + '<div class="descr">' + esc(L('set_newpass_descr')) + '</div></th>';
        h += '<td><input type="password" name="password" class="in350" autocomplete="new-password"></td></tr>';

        h += '<tr><th>' + esc(L('set_smartsyntax')) + ' <div class="descr">' + esc(L('set_smartsyntax_descr')) + '</div></th>';
        h += '<td><input type="checkbox" name="smartsyntax" ' + (opt.smartsyntax ? 'checked' : '') + '></td></tr>';

        h += '<tr><th>' + esc(L('set_markdown')) + '</th>';
        h += '<td><input type="checkbox" name="markdown" ' + (opt.markdown ? 'checked' : '') + '></td></tr>';

        h += '<tr><th>' + esc(L('set_autotag')) + ' <div class="descr">' + esc(L('set_autotag_descr')) + '</div></th>';
        h += '<td><input type="checkbox" name="autotag" ' + (opt.autotag ? 'checked' : '') + '></td></tr>';

        h += '<tr><th>' + esc(L('set_timezone')) + '</th>';
        h += '<td><select name="timezone">';
        var tz = new Date().toString().match(/\(([^)]+)\)$/);
        h += '<option value="UTC">UTC</option>';
        h += '</select></td></tr>';

        h += '<tr><th>' + esc(L('set_clock')) + '</th>';
        h += '<td><select name="clock"><option value="24">' + esc(L('set_24hour')) + '</option><option value="12">' + esc(L('set_12hour')) + '</option></select></td></tr>';

        h += '<tr><th>' + esc(L('set_date2')) + '</th>';
        h += '<td><input type="text" name="dateformat2" class="in350" value="' + esc(opt.dateformat2 || 'Y-m-d') + '"></td></tr>';

        h += '<tr><th>' + esc(L('set_showdate')) + '</th>';
        h += '<td><input type="checkbox" name="showdate" ' + (opt.showdate ? 'checked' : '') + '></td></tr>';

        h += '<tr><th>' + esc(L('set_alientags')) + '</th>';
        h += '<td><input type="checkbox" name="alientags" ' + (opt.alientags ? 'checked' : '') + '></td></tr>';

        h += '<tr><th>' + esc(L('set_taskxrefs')) + '</th>';
        h += '<td><input type="checkbox" name="taskxrefs" ' + (opt.taskxrefs ? 'checked' : '') + '></td></tr>';

        h += '<tr><th>' + esc(L('set_dbbackup')) + '</th>';
        h += '<td><select name="dbbackup">';
        h += '<option value="-1">' + esc(L('set_nobackup')) + '</option>';
        h += '<option value="0">' + esc(L('set_always')) + '</option>';
        h += '<option value="1">1</option>';
        h += '<option value="7">7</option>';
        h += '<option value="30">30</option>';
        h += '<option value="365">365</option>';
        h += '</select></td></tr>';

        h += '</table>';

        // 2FA section
        h += '<h3>2FA</h3>';
        h += '<table class="mtt-settings-table">';
        h += '<tr><th>' + esc(L('set_enabled')) + '</th>';
        h += '<td><input type="checkbox" id="totp_enabled" name="totp_enabled2" ' + (opt.totp_enabled ? 'checked' : '') + '></td></tr>';
        h += '<tr><th id="totp_secret_row" style="display:none">Secret</th>';
        h += '<td id="totp_secret_row_td" style="display:none"><input type="text" id="totp_secret_display" readonly class="in350">';
        h += ' <button type="button" id="generate_totp">Generate</button>';
        h += ' <button type="button" id="save_totp">Enable</button>';
        h += ' <div id="totp_qr" style="margin-top:10px"></div></td></tr>';
        h += '</table>';

        h += '<div class="form-buttons" style="text-align:center;margin-top:15px">';
        h += '<input type="submit" value="' + esc(L('set_submit')) + '"> <input type="button" class="mtt-back-button" value="' + esc(L('set_cancel')) + '" id="settings_cancel">';
        h += '</div>';

        h += '</form>';
        h += '</div>';

        page.innerHTML = h;

        // 2FA interactions
        var totpCheck = M.$('#totp_enabled');
        var secretRow = M.$('#totp_secret_row');
        var secretRowTd = M.$('#totp_secret_row_td');
        totpCheck.addEventListener('change', function () {
            var show = totpCheck.checked;
            secretRow.style.display = show ? '' : 'none';
            secretRowTd.style.display = show ? '' : 'none';
            if (show && !M.$('#totp_secret_display').value) {
                api.generate2faSecret().then(function (j) {
                    if (j.secret) {
                        M.$('#totp_secret_display').value = j.secret;
                        // Show QR in a pragmatic way: link to external QR api
                        M.$('#totp_qr').innerHTML = '<a href="' + esc(j.uri) + '" target="_blank">Scan QR / OTP URI</a>';
                    }
                }).catch(function () {});
            }
        });

        M.$('#generate_totp').addEventListener('click', function () {
            api.generate2faSecret().then(function (j) {
                if (j.secret) {
                    M.$('#totp_secret_display').value = j.secret;
                    M.$('#totp_qr').innerHTML = '<a href="' + esc(j.uri) + '" target="_blank">Scan QR / OTP URI</a>';
                }
            }).catch(function () {});
        });

        M.$('#save_totp').addEventListener('click', function () {
            var secret = M.$('#totp_secret_display').value;
            if (!secret) return;
            api.save2fa(true, secret).then(function (j) {
                if (j.saved) M.flashInfo('2FA enabled');
            }).catch(function () {});
        });

        M.$('#settings_cancel').addEventListener('click', function () {
            page.style.display = 'none';
            M.$('#page_tasks').style.display = 'block';
        });

        M.$('#settings_form').addEventListener('submit', function (e) {
            e.preventDefault();
            saveSettings();
        });
    }

    function saveSettings() {
        var form = M.$('#settings_form');
        var params = {};
        var inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(function (field) {
            var name = field.name;
            if (!name) return;
            if (field.type === 'checkbox') {
                var v = field.checked ? '1' : '0';
                // For toggles only send present fields
                params[name] = v;
            } else {
                params[name] = field.value;
            }
        });
        // Rename 2FA fields
        if (params.totp_enabled2 !== undefined) params.totp_enabled = params.totp_enabled2;
        delete params.totp_enabled2;

        fetch(window.MTTX.mttUrl + 'settings.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(Object.assign({ csrf_token: opt.csrfToken, save: 'ajax' }, params, { save: 'ajax' }))
        }).then(function (resp) {
            return resp.json();
        }).then(function (json) {
            if (json.saved) {
                M.flashInfo(L('settingsSaved') || 'Settings saved. Reloading...');
                setTimeout(function () { window.location.reload(); }, 1200);
            } else {
                M.flashError('Save failed');
            }
        }).catch(function (err) { console.error('[apperr]', err && (err.stack || (err.name + ': ' + err.message))); M.flashError('Some error occurred'); });
    }

    /* ---------- search ---------- */

    function doSearch(q) {
        q = q.trim();
        state.filter.search = q;
        loadTasks();
    }

    /* ---------- event wiring ---------- */

    function initEvents() {
        var body = document.body;

        // Tab clicks
        body.addEventListener('click', function (e) {
            var tab = e.target.closest('.mtt-tab a[href^="#list/"]');
            if (tab) {
                e.preventDefault();
                var id = parseInt(e.target.closest('.mtt-tab').getAttribute('data-list-id'));
                selectList(id);
                return;
            }
            var allTab = e.target.closest('#list_all');
            if (allTab) {
                e.preventDefault();
                selectList(-1);
                return;
            }
        });

        // List action button
        body.addEventListener('click', function (e) {
            var btn = e.target.closest('.list-action');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                var id = parseInt(btn.getAttribute('data-list-action'));
                selectList(id);
                showListMenu(M.$('.mtt-tab[data-list-id="' + id + '"] a'));
                return;
            }
        });

        // Add list button
        var addBtn = M.$('.mtt-tabs-add-button');
        if (addBtn) addBtn.addEventListener('click', addList);

        // Select list (submenu)
        var selectBtn = M.$('.mtt-tabs-select-button');
        if (selectBtn) selectBtn.addEventListener('click', function () {
            if (selectBtn.classList.contains('mtt-menu-button-active')) {
                M.hideAllMenus();
                return;
            }
            var menu = M.$('#slmenucontainer');
            var ul = menu.querySelector('ul');
            // rebuild: drop stale dynamic entries, re-insert current lists after the delimiter
            ul.querySelectorAll('li[data-list]').forEach(function (n) { n.remove(); });
            var begin = ul.querySelector('.slmenu-lists-begin');
            state.lists.forEach(function (list) {
                var li = document.createElement('li');
                li.setAttribute('data-list', list.id);
                li.innerHTML = '<div class="menu-icon"></div>' + esc(list.name);
                li.addEventListener('click', function () {
                    selectList(list.id);
                    M.hideAllMenus();
                });
                ul.insertBefore(li, begin.nextSibling);
            });
            M.showMenu(menu, menu, selectBtn);
        });

        // All-tasks tab action
        var allAction = M.$('#list_all .list-action');
        if (allAction) allAction.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            showListMenu(M.$('#list_all a'));
        });

        // Task clicks: toggle, checkbox, title, priority, tags, actions, note
        body.addEventListener('click', function (e) {
            var taskEl = e.target.closest('li[data-id]');
            if (!taskEl) return;
            var id = parseInt(taskEl.getAttribute('data-id'));

            var chk = e.target.closest('.task-check');
            if (chk) {
                completeTask(id, chk.checked);
                return;
            }

            var toggle = e.target.closest('.task-toggle');
            if (toggle) {
                toggleNote(id);
                return;
            }

            var title = e.target.closest('.task-title a');
            if (title) {
                e.preventDefault();
                if (!state.flag.readOnly) showTaskEdit(id);
                return;
            }

            var prio = e.target.closest('.task-prio');
            if (prio && !state.flag.readOnly) {
                e.preventDefault();
                showPrioPopup(prio, id);
                return;
            }

            var duedateEl = e.target.closest('.duedate');
            if (duedateEl && !state.flag.readOnly) {
                e.preventDefault();
                openDuedatePicker(duedateEl, id);
                return;
            }

            var tag = e.target.closest('.tag[tag]');
            if (tag) {
                e.preventDefault();
                addTagFilter(tag.getAttribute('tag'));
                return;
            }

            var actBtn = e.target.closest('.taskactionbtn');
            if (actBtn) {
                e.preventDefault();
                showTaskMenu(actBtn, id);
                return;
            }

            var xref = e.target.closest('.task-xref');
            if (xref) {
                e.preventDefault();
                selectTaskById(parseInt(xref.getAttribute('data-tid')));
                return;
            }

            var noteBtn = e.target.closest('.task-note-area textarea');
            if (noteBtn) {
                var li = e.target.closest('li[data-id]');
                if (li) {
                    li.classList.remove('task-expanded');
                    li.querySelector('.task-note-area').style.display = 'block';
                }
                return;
            }
        });

        // Note area action buttons
        body.addEventListener('click', function (e) {
            var save = e.target.closest('[data-action="notesave"]');
            if (save) {
                e.preventDefault();
                var li = e.target.closest('li[data-id]');
                if (li) saveInlineNote(parseInt(li.getAttribute('data-id')));
                return;
            }
            var cancel = e.target.closest('[data-action="notecancel"]');
            if (cancel) {
                e.preventDefault();
                var li2 = e.target.closest('li[data-id]');
                if (li2) cancelInlineNote(parseInt(li2.getAttribute('data-id')));
            }
        });

        // Quick task form
        var nf = M.$('#newtask_form');
        if (nf) nf.addEventListener('submit', function (e) { e.preventDefault(); addQuickTask(); });
        var submitBtn = M.$('#newtask_submit');
        if (submitBtn) submitBtn.addEventListener('click', addQuickTask);

        // Advanced add
        var advBtn = M.$('#newtask_adv');
        if (advBtn) advBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var quickVal = M.$('#task').value.trim();
            if (quickVal) {
                api.parseTaskStr(quickVal).then(function (p) {
                    showTaskAdd();
                    var form = M.$('#taskedit_form');
                    form.elements.task.value = p.title;
                    form.elements.tags.value = p.tags || '';
                    form.elements.prio.value = String(p.prio);
                    M.$('#task').value = '';
                }).catch(function () { showTaskAdd(); });
            } else {
                showTaskAdd();
            }
        });

        // Task edit form
        var ef = M.$('#taskedit_form');
        if (ef) {
            ef.addEventListener('submit', function (e) {
                e.preventDefault();
                submitTaskEdit();
            });
            // Track changes
            ef.addEventListener('input', function () { state.flag.editFormChanged = true; });
        }
        var cancelBtn = M.$('#mtt_edit_cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', pageBack);
        var backBtn = M.$('.mtt-back-button');
        if (backBtn) backBtn.addEventListener('click', function (e) {
            if (e.target.closest('.mtt-back-button') === backBtn && !e.target.closest('#settings_form')) {
                pageBack();
            }
        });

        // Due date picker trigger
        var duedateInput = M.$('#duedate');
        if (duedateInput) {
            duedateInput.addEventListener('click', function (e) {
                e.preventDefault();
                window.MTTX.datepicker.open(duedateInput, null, null, true);
            });
        }

        // All tags show/hide
        var showAll = M.$('#alltags_show');
        if (showAll) showAll.addEventListener('click', function (e) {
            e.preventDefault();
            M.$('#alltags').style.display = 'block';
            showAll.style.display = 'none';
            var hideAll = M.$('#alltags_hide');
            if (hideAll) hideAll.style.display = '';
            loadAllTags();
        });
        var hideAll = M.$('#alltags_hide');
        if (hideAll) hideAll.addEventListener('click', function (e) {
            e.preventDefault();
            M.$('#alltags').style.display = 'none';
            hideAll.style.display = 'none';
            if (showAll) showAll.style.display = '';
        });

        // Autocomplete on tags
        var editTags = M.$('#edittags');
        if (editTags) {
            M.initAutocomplete(editTags, tagSuggestSource());
        }

        // Autocomplete on quick add task box (smart syntax /tags/)
        var quickTask = M.$('#task');
        if (quickTask) {
            M.initAutocomplete(quickTask, tagSuggestSource(), null, {
                getQuery: function (val) {
                    var m = val.match(/\/([^\s/]*)$/);
                    return m ? m[1] : null;
                },
                apply: function (inputEl, name) {
                    inputEl.value = inputEl.value.replace(/\/[^\s/]*$/, '/' + name + '/');
                }
            });
        }

        // Notes show/hide
        var showNotes = M.$('#mtt-notes-show');
        if (showNotes) showNotes.addEventListener('click', function (e) {
            e.preventDefault();
            M.$$('#tasklist li').forEach(function (li) { li.classList.add('task-expanded'); });
        });
        var hideNotes = M.$('#mtt-notes-hide');
        if (hideNotes) hideNotes.addEventListener('click', function (e) {
            e.preventDefault();
            M.$$('#tasklist li').forEach(function (li) { li.classList.remove('task-expanded'); });
        });

        // Search
        var searchInput = M.$('#search');
        if (searchInput) {
            var timer = null;
            searchInput.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { doSearch(searchInput.value); }, 300);
            });
        }

        // Login
        var barLogin = M.$('#bar_login');
        if (barLogin) barLogin.addEventListener('click', function (e) {
            e.preventDefault();
            showLoginForm();
        });
        var loginForm = M.$('#login_form');
        if (loginForm) loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (M.$('#2fa_code')) {
                verify2fa();
            } else {
                doLogin();
            }
        });

        var barLogout = M.$('#bar_logout');
        if (barLogout) barLogout.addEventListener('click', function (e) {
            e.preventDefault();
            doLogout();
        });

        // Settings link
        var settingsLink = M.$('#settings');
        if (settingsLink) settingsLink.addEventListener('click', function (e) {
            e.preventDefault();
            loadSettingsPage();
        });

        // Tag cloud
        var cloudBtn = M.$('#tagcloudbtn');
        if (cloudBtn) cloudBtn.addEventListener('click', function () {
            toggleTagCloud();
        });
        var cloudClose = M.$('#tagcloudcancel');
        if (cloudClose) cloudClose.addEventListener('click', function () {
            M.$('#tagcloud').style.display = 'none';
        });
        M.$('#tagcloud').addEventListener('click', tagFromCloudClick);

        // Taskview menu (filter)
        var taskviewBtn = M.$('#taskview');
        if (taskviewBtn) taskviewBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (taskviewBtn.classList.contains('mtt-menu-button-active')) {
                M.hideAllMenus();
                return;
            }
            M.$('#cnt_total').textContent = state.taskCnt.total;
            M.$('#cnt_past').textContent = state.taskCnt.past;
            M.$('#cnt_today').textContent = state.taskCnt.today;
            M.$('#cnt_soon').textContent = state.taskCnt.soon;
            M.showMenu(M.$('#taskviewcontainer'), M.$('#taskviewcontainer'), taskviewBtn);
        });

        // Taskview menu items
        ['#view_tasks', '#view_past', '#view_today', '#view_soon'].forEach(function (sel) {
            var item = M.$(sel);
            if (item) item.addEventListener('click', function () {
                var due = '';
                if (sel === '#view_past') due = 'past';
                if (sel === '#view_today') due = 'today';
                if (sel === '#view_soon') due = 'soon';
                setDueFilter(due);
                M.hideAllMenus();
            });
        });

        // List menu items
        M.$('#btnRenameList').addEventListener('click', function () { renameList(); M.hideAllMenus(); });
        M.$('#btnDeleteList').addEventListener('click', function () { deleteList(); M.hideAllMenus(); });
        M.$('#btnHideList').addEventListener('click', function () { toggleHide(); M.hideAllMenus(); });
        M.$('#btnClearCompleted').addEventListener('click', function () { clearCompleted(); M.hideAllMenus(); });
        M.$('#btnPublish').addEventListener('click', function () { togglePublish(); M.hideAllMenus(); });
        M.$('#btnRssFeed').addEventListener('click', function () { feedCurList(); M.hideAllMenus(); });
        M.$('#btnShowCompleted').addEventListener('click', function () { toggleShowCompleted(); M.hideAllMenus(); });
        M.$('#btnExport').addEventListener('click', function () {
            // export submenu: show two options inline
            var menu = M.$('#listexportmenucontainer');
            M.showMenu(menu, menu, M.$('#btnExport'));
        });
        M.$('#btnExportCSV').addEventListener('click', function () { exportCurList(true); });
        M.$('#btnExportICAL').addEventListener('click', function () { exportCurList(false); });

        // Sort menu items
        var sortDefs = [
            ['#sortByHand', 0],
            ['#sortByPrio', 1],
            ['#sortByDueDate', 2],
            ['#sortByDateCreated', 3],
            ['#sortByDateModified', 4]
        ];
        sortDefs.forEach(function (def) {
            var node = M.$(def[0]);
            if (node) node.addEventListener('click', function () {
                var cur = getCurList();
                var mode = def[1];
                // toggle direction
                if (cur.sort === mode) {
                    mode = 100 + mode; // reverse
                } else if (cur.sort === 100 + mode) {
                    mode = mode === 0 ? 0 : mode;
                } else {
                    mode = mode;
                }
                setSortMode(mode);
                M.hideAllMenus();
            });
        });

        // Task context menu items
        M.$('#cmenu_edit').addEventListener('click', function () {
            var mid = M.$('#taskcontextcontainer')._taskId;
            showTaskEdit(mid);
            M.hideAllMenus();
        });
        M.$('#cmenu_note').addEventListener('click', function () {
            var mid = M.$('#taskcontextcontainer')._taskId;
            toggleNote(mid);
            M.hideAllMenus();
        });
        M.$('#cmenu_delete').addEventListener('click', function () {
            var mid = M.$('#taskcontextcontainer')._taskId;
            deleteTask(mid);
            M.hideAllMenus();
        });
        M.$('#cmenu_prio').addEventListener('mouseenter', function () {
            var menu = M.$('#cmenupriocontainer');
            M.showSubmenu(menu, M.$('#cmenu_prio'), M.$('#taskcontextcontainer'));
        });
        M.$('#cmenu_prio').addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var menu = M.$('#cmenupriocontainer');
            M.showSubmenu(menu, M.$('#cmenu_prio'), M.$('#taskcontextcontainer'));
        });
        M.$('#cmenu_move').addEventListener('mouseenter', function () {
            refreshMoveSubmenu();
            var menu = M.$('#cmenulistscontainer');
            M.showSubmenu(menu, M.$('#cmenu_move'), M.$('#taskcontextcontainer'));
        });
        M.$('#cmenu_move').addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            refreshMoveSubmenu();
            var menu = M.$('#cmenulistscontainer');
            M.showSubmenu(menu, M.$('#cmenu_move'), M.$('#taskcontextcontainer'));
        });

        // Priority submenu
        [2, 1, 0, -1].forEach(function (p) {
            var item = document.getElementById('cmenu_prio:' + p);
            if (item) item.addEventListener('click', function () {
                var mid = M.$('#taskcontextcontainer')._taskId;
                setPrio(mid, p);
                M.hideAllMenus();
            });
        });

        // Move submenu (delegated)
        M.$('#cmenulistscontainer ul').addEventListener('click', function (e) {
            var li = e.target.closest('[data-move-to]');
            if (li) {
                var mid = M.$('#taskcontextcontainer')._taskId;
                moveTaskTo(mid, parseInt(li.getAttribute('data-move-to')));
                M.hideAllMenus();
            }
        });

        // Select list submenu (delegated on container)
        M.$('#slmenucontainer ul').addEventListener('click', function (e) {
            var li = e.target.closest('li[data-list]');
            if (li) {
                selectList(parseInt(li.getAttribute('data-list')));
                M.hideAllMenus();
            }
            var all = e.target.closest('.list-id--1, [data-list="-1"]');
            if (all) {
                selectList(-1);
                M.hideAllMenus();
            }
        });

        // Priority popup
        M.$('#priopopup').addEventListener('click', function (e) {
            var span = e.target.closest('span[data-prio]');
            if (!span) return;
            var prio = parseInt(span.getAttribute('data-prio'));
            var id = parseInt(M.$('#priopopup').getAttribute('data-task'));
            setPrio(id, prio);
            M.$('#priopopup').style.display = 'none';
        });
    }

    function showPrioPopup(el, id) {
        var popup = M.$('#priopopup');
        popup.setAttribute('data-task', id);
        var rect = el.getBoundingClientRect();
        popup.style.display = 'block';
        popup.style.position = 'fixed';
        popup.style.left = rect.left + 'px';
        popup.style.top = rect.bottom + 'px';
        popup.style.zIndex = '100';
    }

    function selectTaskById(id) {
        selectList(state.curListId === -1 ? -1 : state.curListId);
        // Filter to single task
        api.loadTasks({ list: -1, i: id }).then(function (json) {
            if (!parseInt(json.total)) return;
            var t = json.list[0];
            if (t) showTaskEdit(t.id);
        }).catch(function () {});
    }

    /* ---------- routing ---------- */

    function parseAnchor() {
        var hash = window.location.hash;
        if (!hash) return null;
        if (hash === '#alltasks') return { list: -1 };
        var m = hash.match(/^#list\/(\d+)$/);
        if (m) return { list: parseInt(m[1]) };
        if (hash === '#settings') return { settings: true };
        return null;
    }

    /* ---------- init ---------- */

    function init() {
        // Show body
        M.$('#page_tasks').style.display = 'block';
        M.$('#loading').style.display = 'none';

        initSortable();
        initEvents();

        var route = parseAnchor();
        var external = window.MTTX.externalSearch;
        var openList = window.MTTX.openList;

        if (external) {
            state.filter.search = external.replace(/^#/, '');
        }

        // Load lists
        api.loadLists().then(function (json) {
            if (json.denied) { M.errorDenied(); return; }
            state.lists = json.list || [];

            var target = route && route.list !== undefined ? route.list
                : (openList !== null ? openList : (state.lists.length ? state.lists[0].id : 0));

            // If auth required and not logged, pick first published or first list
            if (state.flag.needAuth && !state.flag.isLogged) {
                var pub = state.lists.find(function (l) { return l.published; });
                target = pub ? pub.id : (state.lists.length ? state.lists[0].id : 0);
                if (state.lists.length) {
                    var hidden = state.lists.filter(function (l) { return !l.hidden; });
                    if (hidden.length) target = hidden[0].id;
                }
            }

            if (route && route.settings) {
                loadSettingsPage();
                return;
            }

            if (state.lists.length === 0) {
                // no lists
                M.$('#mtt_body').classList.add('no-lists');
            }

            state.curListId = target !== null && target !== undefined ? target : 0;
            renderTabs();
            if (state.lists.length > 0) {
                loadTasks();
            }
            updateAccessStatus();
        }).catch(function (err) {
            if (err && err.denied) M.errorDenied();
            else { console.error('[apperr]', err && (err.stack || (err.name + ': ' + err.message))); M.flashError('Some error occurred'); }
        });

        // beforeunload warn on unsaved edits
        window.addEventListener('beforeunload', function (e) {
            if (M.$('#page_taskedit').style.display === 'block' && state.flag.editFormChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();