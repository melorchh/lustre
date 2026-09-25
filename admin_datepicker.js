/* LustreMDC Admin — designed calendar date picker */
(function () {
  'use strict';

  var pad2 = function (n) { return String(n).padStart(2, '0'); };
  var WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

  function todayStr() {
    var d = new Date();
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  }

  function toDate(str) {
    if (!str) return null;
    var d = new Date(str + 'T00:00:00');
    return isNaN(d.getTime()) ? null : d;
  }

  function fmtDate(str) {
    var d = toDate(str);
    if (!d) return '';
    return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  }

  var icons = {
    chevL: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>',
    chevR: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>',
    cal: '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="3"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    chevD: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>'
  };

  function upgrade(input) {
    input.style.display = 'none';

    var wrap = document.createElement('div');
    wrap.className = 'admin-datepicker';

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'admin-dp-trigger';
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML =
      '<span class="admin-dp-cal">' + icons.cal + '</span>' +
      '<span class="admin-dp-text">' + (input.value ? fmtDate(input.value) : 'Select a date') + '</span>' +
      '<span class="admin-dp-chev">' + icons.chevD + '</span>';

    var popup = document.createElement('div');
    popup.className = 'admin-dp-popup';
    popup.setAttribute('role', 'dialog');

    wrap.appendChild(trigger);
    input.parentNode.insertBefore(wrap, input.nextSibling);
    document.body.appendChild(popup);

    var value = input.value || '';
    var view = { y: 0, m: 0 };
    var open = false;

    function minDate(){ return toDate(input.min); }
    function maxDate(){ return toDate(input.max); }
    function isAllowed(dateStr){
      if (typeof input.__dpAllowed !== 'function') return true;
      return input.__dpAllowed(dateStr);
    }

    function setValue(v) {
      value = v;
      input.value = v;
      var t = trigger.querySelector('.admin-dp-text');
      t.textContent = v ? fmtDate(v) : 'Select a date';
      trigger.classList.toggle('has-value', !!v);
      trigger.classList.toggle('placeholder', !v);
      input.dispatchEvent(new Event('change', { bubbles: true }));
      input.dispatchEvent(new Event('input', { bubbles: true }));
      if (v && input.closest('form')) {
        try { input.closest('form').dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
      }
    }

    function openAt() {
      var base = toDate(value) || new Date();
      view.y = base.getFullYear();
      view.m = base.getMonth();
      render();
      open = true;
      trigger.classList.add('open');
      trigger.setAttribute('aria-expanded', 'true');
      popup.classList.add('show');
      position();
      requestAnimationFrame(position);
      document.addEventListener('mousedown', onDocDown);
      document.addEventListener('touchstart', onDocDown, { passive: true });
      document.addEventListener('keydown', onDocKey);
      window.addEventListener('resize', position);
      window.addEventListener('scroll', position, true);
    }

    function close() {
      if (!open) return;
      open = false;
      trigger.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
      popup.classList.remove('show');
      document.removeEventListener('mousedown', onDocDown);
      document.removeEventListener('touchstart', onDocDown);
      document.removeEventListener('keydown', onDocKey);
      window.removeEventListener('resize', position);
      window.removeEventListener('scroll', position, true);
    }

    function onDocDown(e) {
      if (!wrap.contains(e.target) && !popup.contains(e.target)) close();
    }
    function onDocKey(e) {
      if (e.key === 'Escape') close();
    }

    function position() {
      if (!open) return;
      var r = wrap.getBoundingClientRect();
      var vh = window.innerHeight || document.documentElement.clientHeight;
      var vw = window.innerWidth || document.documentElement.clientWidth;
      var calW = Math.max(295, Math.min(330, r.width));
      var need = popup.querySelector('.calendar').getBoundingClientRect().height || 380;

      var downRoom = vh - r.bottom;
      var up = downRoom < need + 16 && r.top > need + 16;

      popup.classList.toggle('up', up);
      popup.style.setProperty('--dp-w', calW + 'px');

      var top = up ? r.top - need - 10 : r.bottom + 10;
      top = Math.max(8, Math.min(top, vh - need - 8));
      popup.style.top = top + 'px';

      var left = r.left;
      if (left + calW > vw - 8) left = vw - 8 - calW;
      if (left < 8) left = 8;
      popup.style.left = left + 'px';
    }

    function canPrev() {
      if (!minDate()) return true;
      return view.y > minDate().getFullYear() || (view.y === minDate().getFullYear() && view.m > minDate().getMonth());
    }
    function canNext() {
      if (!maxDate()) return true;
      return view.y < maxDate().getFullYear() || (view.y === maxDate().getFullYear() && view.m < maxDate().getMonth());
    }
    function shift(dir) {
      var m = view.m + dir;
      if (m < 0) { view.m = 11; view.y--; }
      else if (m > 11) { view.m = 0; view.y++; }
      else { view.m = m; }
      render();
      position();
    }

    function render() {
      var label = new Date(view.y, view.m, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      var firstDow = new Date(view.y, view.m, 1).getDay();
      var daysInMonth = new Date(view.y, view.m + 1, 0).getDate();
      var html = '';

      html += '<div class="calendar admin-cal">';
      html += '<div class="cal-head">';
      html += '<button type="button" class="cal-nav"' + (canPrev() ? '' : ' disabled') + ' data-nav="-1" aria-label="Previous month">' + icons.chevL + '</button>';
      html += '<span class="cal-month">' + label + '</span>';
      html += '<button type="button" class="cal-nav"' + (canNext() ? '' : ' disabled') + ' data-nav="1" aria-label="Next month">' + icons.chevR + '</button>';
      html += '</div>';

      html += '<div class="cal-weekdays">';
      WEEKDAYS.forEach(function (w) { html += '<span>' + w + '</span>'; });
      html += '</div>';

      html += '<div class="cal-days">';
      for (var i = 0; i < firstDow; i++) html += '<span class="cal-day cal-day--blank"></span>';
      var tday = todayStr();
      for (var d = 1; d <= daysInMonth; d++) {
        var dateStr = view.y + '-' + pad2(view.m + 1) + '-' + pad2(d);
        var dt = toDate(dateStr);
        var disabled = (minDate() && dt < minDate()) || (maxDate() && dt > maxDate()) || !isAllowed(dateStr);
        if (disabled) {
          html += '<span class="cal-day cal-day--disabled"><span class="cal-day-num">' + d + '</span></span>';
        } else {
          var cls = 'cal-btn';
          if (dateStr === value) cls += ' active';
          if (dateStr === tday) cls += ' cal-today';
          html += '<span class="cal-day"><button type="button" class="' + cls + '" data-date="' + dateStr + '"><span class="cal-day-num">' + d + '</span></button></span>';
        }
      }
      html += '</div>';

      html += '<div class="cal-legend">';
      html += '<span class="cal-legend-dot"></span>';
      html += '<span>' + (value ? 'Selected: ' + fmtDate(value) : 'Pick a date from the calendar') + '</span>';
      if (value) html += '<button type="button" class="cal-clear" data-clear>Clear</button>';
      html += '</div></div>';

      popup.innerHTML = html;
      popup.querySelectorAll('[data-nav]').forEach(function (b) {
        b.addEventListener('click', function () { shift(parseInt(b.getAttribute('data-nav'), 10)); });
      });
      popup.querySelectorAll('[data-date]').forEach(function (b) {
        b.addEventListener('click', function () {
          setValue(b.getAttribute('data-date'));
          close();
        });
      });
      var clearBtn = popup.querySelector('[data-clear]');
      if (clearBtn) clearBtn.addEventListener('click', function () {
        setValue('');
        close();
      });
    }

    trigger.addEventListener('click', function () {
      if (open) close(); else openAt();
    });

    input.__fdSetValue = function (v) {
      setValue(v || '');
      if (open) render();
    };

    input.__fdRefresh = function () {
      var t = trigger.querySelector('.admin-dp-text');
      t.textContent = value ? fmtDate(value) : 'Select a date';
      trigger.classList.toggle('has-value', !!value);
      trigger.classList.toggle('placeholder', !value);
      if (open) render();
      if (input.closest('form')) {
        try { input.closest('form').dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
      }
    };

    setValue(value);
  }

  function init() {
    document.querySelectorAll('input[type="date"][data-datepicker]:not([data-dp-ready])').forEach(function (inp) {
      inp.setAttribute('data-dp-ready', '1');
      upgrade(inp);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
