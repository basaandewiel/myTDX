/* myTDX minimal date picker - replaces jQuery UI datepicker */
(function () {
    'use strict';

    var DAYS = window.MTTX.lang.days_min || ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    var MONTHS = window.MTTX.lang.months_long || [];

    var activePicker = null;
    var openInput = null;
    var pendingSelect = null;
    var pendingClear = null;
    var currentMonth, currentYear;
    var highlightDay = -1;

    function el(tag, attrs, html) {
        var node = document.createElement(tag);
        if (attrs) {
            for (var k in attrs) {
                if (attrs.hasOwnProperty(k)) {
                    if (k === 'class') node.className = attrs[k];
                    else node.setAttribute(k, attrs[k]);
                }
            }
        }
        if (html !== undefined) node.innerHTML = html;
        return node;
    }

    function closePicker() {
        if (activePicker) {
            activePicker.remove();
            activePicker = null;
            openInput = null;
            pendingSelect = null;
            pendingClear = null;
            highlightDay = -1;
        }
    }

    function parseDate(s) {
        if (!s) return new Date();
        var m = s.match(/^(\d{4})-(\d\d)-(\d\d)$/);
        if (m) return new Date(+m[1], +m[2] - 1, +m[3]);
        return new Date();
    }

    function openPicker(input, onSelect, onClear, anchorToday) {
        closePicker();
        var parsed;
        if (anchorToday) {
            parsed = new Date();
        } else {
            parsed = parseDate(input.value || (input.getAttribute ? input.getAttribute('data-date') : ''));
        }
        currentYear = parsed.getFullYear();
        currentMonth = parsed.getMonth();
        highlightDay = parsed.getDate();
        openInput = input;
        pendingSelect = onSelect || null;
        pendingClear = onClear || null;

        var picker = el('div', { class: 'ui-datepicker' });
        picker.style.position = 'fixed';
        picker.style.display = 'block';
        document.body.appendChild(picker);
        activePicker = picker;

        var rect = input.getBoundingClientRect();
        picker.style.top = rect.bottom + 'px';
        picker.style.left = rect.left + 'px';
        picker.style.zIndex = '202';

        render();
    }

    function render() {
        if (!activePicker) return;
        var picker = activePicker;
        // Header
        var header = el('div', { class: 'ui-datepicker-header' });
        var prev = el('a', { class: 'ui-datepicker-prev' });
        prev.innerHTML = '&laquo;';
        prev.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            currentMonth--;
            if (currentMonth < 0) { currentMonth = 11; currentYear--; }
            render();
        });
        var next = el('a', { class: 'ui-datepicker-next' });
        next.innerHTML = '&raquo;';
        next.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            currentMonth++;
            if (currentMonth > 11) { currentMonth = 0; currentYear++; }
            render();
        });
        var title = el('div', { class: 'ui-datepicker-title' });
        title.textContent = MONTHS[currentMonth] + ' ' + currentYear;
        header.appendChild(prev);
        header.appendChild(title);
        header.appendChild(next);

        // Optional "remove date" control (used from task rows)
        if (pendingClear) {
            var clearLink = el('a', { class: 'ui-datepicker-clear', href: '#' });
            clearLink.innerHTML = '&times;';
            clearLink.title = window.MTTX.lang.duedate_remove || 'Remove';
            clearLink.addEventListener('click', function (e) {
                e.preventDefault();
                pendingClear();
                closePicker();
            });
            header.appendChild(clearLink);
        }

        // Calendar
        var table = el('table', { class: 'ui-datepicker-calendar' });
        var thead = el('thead');
        var tr = el('tr');
        DAYS.forEach(function (d) {
            tr.appendChild(el('th', null, escapeHtml(d)));
        });
        thead.appendChild(tr);
        table.appendChild(thead);

        var tbody = el('tbody');
        var firstDay = new Date(currentYear, currentMonth, 1);
        var startWeekday = firstDay.getDay();
        var dim = new Date(currentYear, currentMonth + 1, 0).getDate();
        var today = new Date();
        var todayStr = today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate());

        var cells = [];
        for (var i = 0; i < startWeekday; i++) cells.push(null);
        for (var d = 1; d <= dim; d++) cells.push(d);

        while (cells.length % 7 !== 0) cells.push(null);

        for (var r = 0; r < cells.length; r += 7) {
            var row = el('tr');
for (var c = 0; c < 7; c++) {
                    var td = el('td');
                    var dayNum = cells[r + c];
                    if (dayNum) {
                        var a = el('a', { href: '#' });
                        a.textContent = dayNum;
                        var dateStr = currentYear + '-' + pad(currentMonth + 1) + '-' + pad(dayNum);
                        if (dateStr === todayStr) td.className = 'ui-datepicker-today';
                        else if (dayNum === highlightDay) td.className = 'ui-datepicker-current-day';
                        a.addEventListener('click', (function (ds) {
                            return function (e) {
                                e.preventDefault();
                                if (pendingSelect) {
                                    pendingSelect(ds);
                                } else if (openInput) {
                                    openInput.value = ds;
                                    openInput.dispatchEvent(new Event('change'));
                                }
                                closePicker();
                            };
                        })(dateStr));
                        td.appendChild(a);
                    }
                    row.appendChild(td);
                }
            tbody.appendChild(row);
        }
        table.appendChild(tbody);

        picker.innerHTML = '';
        picker.appendChild(header);
        picker.appendChild(table);
    }

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c];
        });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('.ui-datepicker-trigger')) {
            e.preventDefault();
            var input = document.querySelector(e.target.dataset.for || '#duedate');
            if (input) openPicker(input);
        }
    });

    // Close the picker when clicking anywhere outside it (persistent listener,
    // so it survives the click that opened the picker)
    document.addEventListener('click', function (e) {
        if (!activePicker) return;
        var inPicker = activePicker.contains(e.target || document);
        var onTrigger = e.target.closest && e.target.closest('.ui-datepicker-trigger');
        if (!inPicker && !onTrigger && (!openInput || e.target !== openInput)) {
            closePicker();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && activePicker) {
            closePicker();
        }
    });

    window.MTTX.datepicker = { open: openPicker, close: closePicker, parse: parseDate };
})();