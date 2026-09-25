/* Custom styled dropdowns replacing native <select> popups.
   The popup is appended to <body> and positioned as fixed so it never gets
   clipped by scrolling containers (tables, modals with overflow). */
(function(){
  if (window.__fdInit) return;
  window.__fdInit = true;

  var CHEV = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';

  function build(sel){
    if (sel.__fd) return;
    sel.__fd = true;

    var isSearchable = sel.hasAttribute('data-searchable');

    var wrap = document.createElement('div');
    var kind = 'cd-default';
    if (sel.classList.contains('form-dropdown')) kind = 'cd-form';
    else if (sel.classList.contains('action-select')) kind = 'cd-action';
    wrap.className = 'cust-dropdown ' + kind;
    sel.parentNode.insertBefore(wrap, sel.nextSibling);

    function currentOptions(){
      return Array.prototype.slice.call(sel.options);
    }

    wrap.innerHTML =
      '<button type="button" class="cd-btn' + (isSearchable ? ' cd-searchable' : '') + '" role="combobox" aria-haspopup="listbox" aria-expanded="false">' +
        '<span class="cd-label"></span>' +
        '<span class="cd-arrow">' + CHEV + '</span>' +
      '</button>';

    var btn     = wrap.querySelector('.cd-btn');
    var labelEl = wrap.querySelector('.cd-label');

    var menu = document.createElement('div');
    var menuCls = 'cd-menu';
    if (kind === 'cd-form') menuCls += ' fd-form';
    else if (kind === 'cd-action') menuCls += ' fd-action';
    menu.className = menuCls;
    menu.setAttribute('role', 'listbox');
    document.body.appendChild(menu);

    var open = false;
    var searchInput = null;

    function setLabel(){
      labelEl.textContent = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent : '';
    }

    sel.__fdResync = function(){
      setLabel();
      if (open) renderOptions();
    };

    (function(){
      var proto = sel;
      var desc = null;
      while (proto && !desc) {
        try { desc = Object.getOwnPropertyDescriptor(proto, 'value'); } catch (e) {}
        proto = Object.getPrototypeOf(proto);
      }
      if (!desc || !desc.set) return;
      try {
        Object.defineProperty(sel, 'value', {
          get: function(){ return desc.get.call(sel); },
          set: function(v){ desc.set.call(sel, v); sel.__fdResync(); },
          configurable: true
        });
      } catch (e) {}
    })();

    var optionsContainer = null;

    function renderOptions(){
      if (!optionsContainer) return;
      optionsContainer.innerHTML = '';
      var filter = searchInput ? searchInput.value.toLowerCase() : '';
      currentOptions().forEach(function(option, idx){
        var text = option.textContent;
        if (filter && text.toLowerCase().indexOf(filter) === -1) return;

        var o = document.createElement('div');
        var optCls = 'cd-opt';
        if (kind === 'cd-form') optCls += ' fd-opt';
        else if (kind === 'cd-action') optCls += ' fd-act';
        o.className = optCls + (idx === sel.selectedIndex ? ' sel' : '');
        o.textContent = text;
        o.setAttribute('role', 'option');
        o.setAttribute('aria-selected', idx === sel.selectedIndex);
        o.setAttribute('tabindex', '-1');
        o.addEventListener('click', function(ev){
          ev.stopPropagation();
          sel.selectedIndex = idx;
          setLabel();
          close();
          sel.dispatchEvent(new Event('change'));
        });
        o.addEventListener('keydown', function(ev){
          if (ev.key === 'ArrowDown') {
            ev.preventDefault();
            var next = o.nextElementSibling;
            if (next && next.classList.contains('cd-opt')) next.focus();
          } else if (ev.key === 'ArrowUp') {
            ev.preventDefault();
            var prev = o.previousElementSibling;
            if (prev && prev.classList.contains('cd-opt')) prev.focus();
            else if (searchInput) searchInput.focus();
          } else if (ev.key === 'Enter') {
            ev.preventDefault();
            o.click();
          } else if (ev.key === 'Escape') {
            close();
          }
        });
        optionsContainer.appendChild(o);
      });
    }

    function render(){
      menu.innerHTML = '';

      if (isSearchable) {
        var searchWrap = document.createElement('div');
        searchWrap.className = 'cd-search-wrap';
        searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'cd-search-input';
        searchInput.placeholder = 'Type to search...';
        searchInput.autocomplete = 'off';
        searchWrap.appendChild(searchInput);
        menu.appendChild(searchWrap);

        searchInput.addEventListener('input', function(){
          renderOptions();
        });
        searchInput.addEventListener('keydown', function(e){
          if (e.key === 'Escape') {
            close();
          } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            var firstOpt = optionsContainer.querySelector('.cd-opt');
            if (firstOpt) firstOpt.focus();
          }
        });
        searchInput.addEventListener('click', function(e){
          e.stopPropagation();
        });
      }

      optionsContainer = document.createElement('div');
      menu.appendChild(optionsContainer);
      renderOptions();

      if (isSearchable && searchInput) {
        searchInput.focus();
      }
    }

    function position(){
      var r = wrap.getBoundingClientRect();
      var menuH = menu.offsetHeight || Math.min(options.length * 38, 280);
      var vh = window.innerHeight || document.documentElement.clientHeight;
      var vw = window.innerWidth || document.documentElement.clientWidth;

      var downRoom = vh - r.bottom;
      var up = downRoom < menuH + 12 && r.top > menuH + 12;

      menu.classList.toggle('up', up);
      menu.classList.toggle('down', !up);

      var top = up ? r.top - menuH - 8 : r.bottom + 8;
      top = Math.max(8, Math.min(top, vh - menuH - 8));
      menu.style.top = top + 'px';

      var left = Math.max(8, Math.min(r.left, vw - menu.offsetWidth - 8));
      menu.style.left = left + 'px';
    }

    function openMenu(){
      render();
      menu.classList.add('show');
      open = true;
      btn.setAttribute('aria-expanded', 'true');
      wrap.classList.add('open');
      position();
      requestAnimationFrame(position);
      if (isSearchable && searchInput) {
        searchInput.focus();
      }
      document.addEventListener('mousedown', onDocDown);
      document.addEventListener('keydown', onDocKey);
      window.addEventListener('resize', position);
      window.addEventListener('scroll', position, true);
      document.querySelectorAll('.cust-dropdown').forEach(function(w){
        if (w !== wrap) w.classList.remove('open');
      });
      document.querySelectorAll('.cd-menu.show').forEach(function(m){
        if (m !== menu) m.classList.remove('show');
      });
    }

    function close(){
      if (!open) return;
      open = false;
      menu.classList.remove('show');
      wrap.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
      document.removeEventListener('mousedown', onDocDown);
      document.removeEventListener('keydown', onDocKey);
      window.removeEventListener('resize', position);
      window.removeEventListener('scroll', position, true);
    }

    function onDocDown(e){
      if (wrap.contains(e.target) || menu.contains(e.target)) return;
      close();
    }

    function onDocKey(e){
      if (e.key === 'Escape') close();
    }

    btn.addEventListener('click', function(ev){
      ev.stopPropagation();
      if (open) close();
      else openMenu();
    });

    setLabel();
  }

  function init(){
    document.querySelectorAll('select.filter-select:not([data-fd]), select.form-dropdown:not([data-fd]), select.action-select:not([data-fd])').forEach(function(sel){
      sel.setAttribute('data-fd', '1');
      build(sel);
    });
    document.querySelectorAll('form').forEach(function(f){
      f.addEventListener('reset', function(){
        setTimeout(function(){
          f.querySelectorAll('select[data-fd]').forEach(function(sel){
            if (sel.__fdResync) sel.__fdResync();
          });
        }, 0);
      });
    });
  }

  var reschedTips = document.querySelectorAll('.resched-tip');
  reschedTips.forEach(function(tip){
    var pop = tip.querySelector('.resched-pop');
    if (!pop) return;
    // Move the popup to <body> so `position:fixed` is not trapped by any
    // transformed ancestor (e.g. the content-card entry animation), which makes
    // the viewport-relative coordinates computed below land in the right place.
    document.body.appendChild(pop);
    tip.addEventListener('mouseenter', function(){
      var rc = tip.getBoundingClientRect();
      var popW = pop.offsetWidth || 260;
      var popH = pop.offsetHeight || 160;
      var vw = window.innerWidth, vh = window.innerHeight;
      var spaceBelow = vh - rc.bottom;
      var spaceAbove = rc.top;
      var showAbove = (spaceAbove > spaceBelow && spaceAbove >= popH + 14) || spaceBelow < popH + 14;
      pop.classList.toggle('resched-pop--above', showAbove);
      var left = Math.min(Math.max(8, rc.left + rc.width / 2 - popW / 2), vw - popW - 8);
      if (left < 8) left = 8;
      var top = showAbove ? rc.top - popH - 10 : rc.bottom + 10;
      if (top < 8) top = 8;
      if (top + popH > vh - 8) top = vh - popH - 8;
      pop.style.left = left + 'px';
      pop.style.top = top + 'px';
      pop.classList.add('resched-pop--show');
    });
    tip.addEventListener('mouseleave', function(){
      pop.classList.remove('resched-pop--show', 'resched-pop--above');
    });
  });

  // Public helper: re-sync a custom dropdown after its <option> list changes
  // (e.g. cascading selects populated dynamically via innerHTML/appendChild).
  window.__fdResync = function(sel){
    if (sel && sel.__fdResync) sel.__fdResync();
  };

  // ---- Change Password link in the sidebar user block ----
  (function(){
    var body = document.querySelector('.sidebar-user-body');
    if (!body || body.querySelector('.sidebar-pw')) return;
    var page = (location.pathname.split('/').pop() || '');
    var isDoctor = page.indexOf('doctor_') === 0;
    var a = document.createElement('a');
    a.className = 'sidebar-pw';
    a.href = isDoctor ? 'doctor_change_password.php' : 'admin_change_password.php';
    a.title = 'Change password';
    a.innerHTML =
      '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>' +
      '<span>Change Password</span>';
    body.appendChild(a);
  })();

  // ---- Admin: force password change when required ----
  (function(){
    var page = (location.pathname.split('/').pop() || '');
    if (page.indexOf('doctor_') === 0) return;
    if (page === 'admin_change_password.php' || page === 'admin_login.php' ||
        page === 'admin_login_process.php' || page === 'admin_logout.php' ||
        page === 'admin_auth_status.php') return;
    fetch('admin_auth_status.php')
      .then(function(r){ return r.text(); })
      .then(function(t){
        if (t.trim() === 'change') location.href = 'admin_change_password.php';
      })
      .catch(function(){});
  })();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
