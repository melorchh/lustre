/* LustreMDC — progressive page transitions engine.
   Loaded first inside <body> on portal pages (admin/doctor/landing/auth).
   Provides:
   1. PJAX navigation — fetch + swap for same-portal .php links & GET forms,
      with history (pushState/popstate) support. Falls back to a faded full
      navigation whenever either side lacks the portal container.
   2. Transitions — crossfade on every navigation (replaces the per-page
      "Smooth Transitions" IIFE that was copy-pasted into each page).
   3. Loading feedback — instant top progress bar on click, delayed reuse of
      the existing #loadingScreen overlay for slow fetches.
   Opt out: add data-no-pjax to any <a> or <form>. */
(function () {
  'use strict';

  if (window.LustrePjax) return;

  var RM = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var SELECTOR = 'main.admin-content';

  function hasContainer() {
    return !!document.querySelector(SELECTOR);
  }

  /* ---------------------------------------------------------------- styles */
  var style = document.createElement('style');
  style.setAttribute('data-pjax', 'keep');
  style.textContent = [
    '@keyframes __meIn{from{opacity:0;transform:translateY(10px) scale(.99)}to{opacity:1;transform:none}}',
    '@keyframes __meOut{from{opacity:1;transform:translateY(0)}to{opacity:0;transform:translateY(-6px)}}',
    '@keyframes __meFade{from{opacity:0}to{opacity:1}}',
    '@keyframes __meFadeOut{from{opacity:1}to{opacity:0}}',
    'body{animation:__meFade .4s ease both}',
    'body.__leaving{animation:__meFadeOut .22s ease forwards;pointer-events:none}',
    'body.__entering{animation:__meFade .3s ease both}',
    'button:not(:disabled):active{transform:scale(.965) !important}',
    '.content-card{animation:__meIn .42s cubic-bezier(.22,1,.36,1) both;animation-delay:.12s;opacity:0}',
    '.stat-card{opacity:0;animation:__meIn .38s cubic-bezier(.22,1,.36,1) both}',
    '.stats-grid .stat-card:nth-child(1){animation-delay:.04s}',
    '.stats-grid .stat-card:nth-child(2){animation-delay:.09s}',
    '.stats-grid .stat-card:nth-child(3){animation-delay:.14s}',
    '.stats-grid .stat-card:nth-child(4){animation-delay:.19s}',
    '.stats-grid .stat-card:nth-child(5){animation-delay:.24s}',
    '.stats-grid .stat-card:nth-child(6){animation-delay:.29s}',
    'tr{transition:background .12s}',
    '.action-select{transition:border-color .15s}',
    '.modal-box,.result-modal-box{animation:__meIn .3s cubic-bezier(.34,1.56,.64,1)}',
    '.login-card{animation:__meIn .5s cubic-bezier(.34,1.2,.64,1) both}',
    '.form-group input{transition:border-color .2s,box-shadow .2s}',
    '.form-group input:focus{box-shadow:0 0 0 4px rgba(22,163,74,.15)}',
    '.nav-item{transition:all .18s !important}',
    '.pjax-progress{position:fixed;top:0;left:0;height:3px;width:0;opacity:0;',
    'background:#16a34a;z-index:100000;pointer-events:none;border-radius:0 2px 2px 0;',
    'transition:width .35s cubic-bezier(.2,.7,.3,1),opacity .3s ease}',
    '.pjax-progress.on{opacity:1;width:65%}',
    '.pjax-progress.done{width:100%;opacity:0}',
    '.pjax-progress.fail{background:#dc2626;opacity:1;width:100%}',
    '@media (prefers-reduced-motion:reduce){',
    'body{animation:none}',
    'body.__leaving{animation:__meFadeOut .01s linear forwards;pointer-events:none}',
    'body.__entering{animation:none}',
    '.content-card,.stat-card,.modal-box,.result-modal-box,.login-card{animation:none;opacity:1}',
    '.pjax-progress{transition:width .1s linear,opacity .15s linear}}'
  ].join('');
  document.head.appendChild(style);

  /* ------------------------------------------------------- progress feedback */
  var bar = document.createElement('div');
  bar.className = 'pjax-progress';
  bar.setAttribute('data-pjax', 'keep');
  bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);

  var barTimer = null;
  function progressStart() {
    clearTimeout(barTimer);
    bar.className = 'pjax-progress';
    void bar.offsetWidth; /* reflow so the next transition restarts from 0 */
    bar.className = 'pjax-progress on';
  }
  function progressDone() {
    bar.className = 'pjax-progress done';
    barTimer = setTimeout(function () { bar.className = 'pjax-progress'; }, 450);
  }
  function progressFail() {
    bar.className = 'pjax-progress fail';
    barTimer = setTimeout(function () { bar.className = 'pjax-progress'; }, 1000);
  }

  /* ----------------------------------------------- page-scoped timers cleanup */
  var nativeSetInterval = window.setInterval;
  var nativeClearInterval = window.clearInterval;
  var liveIntervals = [];
  window.setInterval = function () {
    var id = nativeSetInterval.apply(window, arguments);
    liveIntervals.push(id);
    return id;
  };
  window.clearInterval = function (id) {
    for (var i = liveIntervals.length - 1; i >= 0; i--) {
      if (liveIntervals[i] === id) liveIntervals.splice(i, 1);
    }
    return nativeClearInterval.call(window, id);
  };
  function clearPageIntervals() {
    for (var i = liveIntervals.length - 1; i >= 0; i--) {
      nativeClearInterval.call(window, liveIntervals[i]);
    }
    liveIntervals.length = 0;
  }

  /* ------------------------------------------------------------- navigation */
  var seq = 0;
  var saveY = 0;

  function sleep(ms) {
    return new Promise(function (r) { setTimeout(r, ms); });
  }

  function hardVisit(url) {
    document.body.classList.add('__leaving');
    setTimeout(function () { window.location.href = url; }, RM ? 0 : 215);
  }

  function sameOrigin(url) {
    try {
      return new URL(url, location.href).origin === location.origin;
    } catch (e) { return false; }
  }

  function isSkippablePath(pathname) {
    return /\.pdf$/i.test(pathname) || /_pdf\.php$/i.test(pathname);
  }

  function progressOverlay(show, ms) {
    /* returns timer id or 0 */
    if (!show) return 0;
    return setTimeout(function () {
      var el = document.getElementById('loadingScreen');
      if (el) el.classList.add('active');
    }, ms);
  }
  function hideProgressOverlay() {
    var el = document.getElementById('loadingScreen');
    if (el) el.classList.remove('active');
  }

  function visit(url, opts) {
    opts = opts || {};
    var my = ++seq;
    var t0 = performance.now();
    var target = new URL(url, location.href);

    if (!hasContainer()) {
      if (!opts.pop) hardVisit(target.href);
      return Promise.resolve();
    }

    saveY = window.scrollY || 0;
    progressStart();
    document.body.classList.remove('__entering');
    document.body.classList.add('__leaving');
    var overlayTimer = progressOverlay(!RM, 160);

    return fetch(target.href, {
      credentials: 'same-origin',
      redirect: 'follow',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'LustrePjax' }
    })
      .then(function (r) {
        return r.text().then(function (html) {
          return { ok: r.ok, html: html, finalUrl: r.url || target.href };
        });
      })
      .then(function (res) {
        if (my !== seq) return;
        var doc = null;
        try { doc = new DOMParser().parseFromString(res.html, 'text/html'); } catch (e) {}
        var container = doc && doc.body && doc.querySelector(SELECTOR);

        if (!res.ok || !container) {
          /* Server error, or a redirect out of the portal (e.g. session
             expired -> login). Finish the fade with a real navigation. */
          clearTimeout(overlayTimer);
          hideProgressOverlay();
          progressFail();
          window.location.href = res.finalUrl;
          return;
        }

        var wait = RM ? 0 : Math.max(0, 150 - (performance.now() - t0));
        return sleep(wait).then(function () {
          if (my !== seq) return;
          clearTimeout(overlayTimer);
          applySwap(doc, new URL(res.finalUrl, location.href), opts);
          progressDone();
        });
      })
      .catch(function () {
        if (my !== seq) return;
        clearTimeout(overlayTimer);
        hideProgressOverlay();
        progressFail();
        setTimeout(function () {
          document.body.classList.remove('__leaving');
        }, 250);
        if (typeof window.showToast === 'function') {
          window.showToast('Connection problem — please try again', 'error');
        }
      });
  }

  function keepBodyChild(el) {
    if (!el || !el.getAttribute) return false;
    if (el.hasAttribute('data-pjax')) return true;
    if (el.id === 'loadingScreen' || el.id === 'toast' || el.id === 'sidebarOverlay') return true;
    return !!(el.classList && el.classList.contains('sidebar-overlay'));
  }

  function syncHead(doc, url) {
    document.title = doc.title || document.title;

    /* same-origin stylesheets: mirror the target page's set (doctor.css etc.) */
    var want = {};
    var i, l, u;
    var wantLinks = doc.head ? doc.head.querySelectorAll('link[rel="stylesheet"]') : [];
    for (i = 0; i < wantLinks.length; i++) {
      l = wantLinks[i];
      try {
        u = new URL(l.getAttribute('href'), url.href);
        if (u.origin === location.origin) want[u.pathname] = l;
      } catch (e) {}
    }
    var haveLinks = document.head.querySelectorAll('link[rel="stylesheet"]');
    for (i = 0; i < haveLinks.length; i++) {
      l = haveLinks[i];
      try {
        u = new URL(l.getAttribute('href'), location.href);
        if (u.origin === location.origin && !want[u.pathname]) {
          l.parentNode.removeChild(l);
        }
      } catch (e) {}
    }
    Object.keys(want).forEach(function (path) {
      var exists = false;
      var cur = document.head.querySelectorAll('link[rel="stylesheet"]');
      for (var j = 0; j < cur.length; j++) {
        try {
          if (new URL(cur[j].getAttribute('href'), location.href).pathname === path) {
            exists = true;
            break;
          }
        } catch (e) {}
      }
      if (!exists) document.head.appendChild(document.importNode(want[path], true));
    });

    /* theme-color meta */
    var newTc = doc.querySelector('meta[name="theme-color"]');
    var curTc = document.querySelector('meta[name="theme-color"]');
    if (newTc && curTc) curTc.setAttribute('content', newTc.getAttribute('content'));
  }

  function runScripts(nodes, done) {
    var i = 0;
    (function next() {
      if (i >= nodes.length) {
        if (done) done();
        return;
      }
      var proto = nodes[i++];
      /* fresh element: scripts cloned/imported from another document are not
         guaranteed to execute on insertion (jsdom never does; behavior varies
         across browsers) — build a new one and copy everything over. */
      var s = document.createElement('script');
      for (var a = 0; a < proto.attributes.length; a++) {
        s.setAttribute(proto.attributes[a].name, proto.attributes[a].value);
      }
      s.text = proto.text;
      if (!s.src) {
        /* inline script: executes synchronously on insertion */
        document.body.appendChild(s);
        next();
        return;
      }
      var settled = false;
      var fin = function () {
        if (settled) return;
        settled = true;
        next();
      };
      s.addEventListener('load', fin);
      s.addEventListener('error', fin);
      s.async = false;
      document.body.appendChild(s);
    })();
  }

  function applySwap(doc, url, opts) {
    /* 1. stop page-scoped polling from the outgoing page */
    clearPageIntervals();

    /* 2. head sync (title, stylesheets, theme-color) */
    syncHead(doc, url);

    /* 3. body attribute sync (preserving transition state classes) */
    var wasLeaving = document.body.classList.contains('__leaving');
    document.body.className = doc.body.className;
    if (wasLeaving) document.body.classList.add('__leaving');

    /* 4a. extract every script from the fetched doc in document order
          (including ones nested inside modals/<main>), minus keep-list, so we
          can re-run them ourselves in exact order — subtree insertion does not
          reliably execute scripts. */
    var newScripts = [];
    Array.prototype.slice.call(doc.body.querySelectorAll('script')).forEach(function (s) {
      if (keepBodyChild(s)) return; /* skip the fetched engine tag */
      newScripts.push(s);
      if (s.parentNode) s.parentNode.removeChild(s);
    });

    /* 4b. drop old body children (keep-list and the engine tag survive) */
    Array.prototype.slice.call(document.body.children).forEach(function (el) {
      if (keepBodyChild(el)) return;
      if (el.parentNode) el.parentNode.removeChild(el);
    });

    /* 4c. append the fetched body children (now script-free) in order */
    Array.prototype.slice.call(doc.body.children).forEach(function (el) {
      if (keepBodyChild(el)) return; /* skip fetched loading/toast/overlay/engine/bar */
      document.body.appendChild(document.importNode(el, true));
    });

    /* 5. reset shared-library init guards so re-executed scripts re-enhance
          the fresh DOM (custom dropdowns, theme toggle, change-pw link...) */
    window.__fdInit = false;
    window.__meThemeInit = false;
    try { delete window.__noFlash; } catch (e) { window.__noFlash = undefined; }

    /* 6. scroll + history before user-visible scripts run */
    var y = opts && typeof opts.y === 'number' ? opts.y : 0;
    window.scrollTo(0, y);

    if (!opts.pop) {
      try {
        history.replaceState(
          Object.assign({}, history.state || {}, { pjax: true, y: saveY }),
          '',
          location.href
        );
        var state = { pjax: true, url: url.pathname + url.search + url.hash, y: y };
        if (opts.replace) history.replaceState(state, '', url.pathname + url.search + url.hash);
        else history.pushState(state, '', url.pathname + url.search + url.hash);
      } catch (e) {}
    }

    /* 7. run the target page's scripts in document order (src awaited), then
          finish the transition */
    runScripts(newScripts, function () {
      try { delete window.__noFlash; } catch (e) { window.__noFlash = undefined; }
      hideProgressOverlay();
      document.body.classList.remove('__leaving');
      document.body.classList.add('__entering');
      setTimeout(function () {
        document.body.classList.remove('__entering');
      }, 380);
    });
  }

  /* ------------------------------------------------------------ link clicks */
  document.addEventListener(
    'click',
    function (e) {
      if (e.defaultPrevented) return;
      if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
      if (!link) return;
      if (link.hasAttribute('data-no-pjax')) return; /* explicit opt-out: native */
      if (link.hasAttribute('download')) return;
      if (link.target && link.target !== '_self') return;
      var href = link.getAttribute('href');
      if (
        !href ||
        href.charAt(0) === '#' ||
        /^javascript:/i.test(href) ||
        /^mailto:/i.test(href) ||
        /^tel:/i.test(href)
      )
        return;
      var u;
      try { u = new URL(href, location.href); } catch (err) { return; }
      if (u.origin !== location.origin) return;
      if (isSkippablePath(u.pathname)) return; /* print/PDF views: full load */

      e.preventDefault();
      e.stopImmediatePropagation(); /* beat any legacy page-level click nav */

      if (!hasContainer()) {
        hardVisit(u.href);
        return;
      }
      visit(u.href, {});
    },
    true
  );

  /* ------------------------------------------------------------- GET forms */
  function formUrl(form) {
    var u = new URL(form.getAttribute('action') || location.href, location.href);
    var data = new FormData(form);
    u.search = '';
    data.forEach(function (v, k) {
      u.searchParams.append(k, v);
    });
    return u;
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (e.defaultPrevented) return;
    if (!form || form.tagName !== 'FORM') return;
    if (form.hasAttribute('data-no-pjax')) return;
    var method = (form.getAttribute('method') || 'get').toUpperCase();
    if (method !== 'GET') return;
    if (!hasContainer()) return;
    if (!sameOrigin(form.getAttribute('action') || location.href)) return;
    e.preventDefault();
    var u = formUrl(form);
    visit(u.pathname + u.search, {});
  });

  /* onchange="this.form.submit()" bypasses the submit event — patch through. */
  var nativeFormSubmit = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function () {
    var method = (this.getAttribute('method') || 'get').toUpperCase();
    if (
      method === 'GET' &&
      hasContainer() &&
      !this.hasAttribute('data-no-pjax') &&
      sameOrigin(this.getAttribute('action') || location.href)
    ) {
      var u = formUrl(this);
      visit(u.pathname + u.search, {});
      return;
    }
    return nativeFormSubmit.apply(this, arguments);
  };

  /* ------------------------------------------------------ history traversal */
  window.addEventListener('popstate', function () {
    if (!hasContainer()) return;
    var st = {};
    try { st = history.state || {}; } catch (e) {}
    visit(location.href, { pop: true, y: typeof st.y === 'number' ? st.y : 0 });
  });

  try {
    history.scrollRestoration = 'manual';
    history.replaceState(
      Object.assign({}, history.state || {}, { pjax: true, y: window.scrollY || 0 }),
      '',
      location.href
    );
  } catch (e) {}

  /* --------------------------------------------------------------- exports */
  window.__pjaxReload = function () {
    return visit(location.href, { replace: true });
  };

  window.LustrePjax = {
    visit: visit,
    refresh: function () { return visit(location.href, { replace: true }); },
    enabled: hasContainer
  };
})();
