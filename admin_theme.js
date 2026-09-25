/* LustreMDC Admin — day/night theme toggle */
(function () {
  'use strict';
  if (window.__meThemeInit) return;
  window.__meThemeInit = true;

  var STORAGE = 'meTheme';

  function current() {
    return (document.documentElement.getAttribute('data-theme') || 'light');
  }
  function apply(theme) {
    document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'dark' : 'light');
    document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: current() } }));
  }
  function toggle() {
    apply(current() === 'dark' ? 'light' : 'dark');
  }

  // Persist without forcing a reload; save on change.
  document.addEventListener('themechange', function (e) {
    try {
      var t = e.detail && e.detail.theme ? e.detail.theme : current();
      localStorage.setItem(STORAGE, t);
    } catch (err) {}
  });

  // Bind any theme-toggle button(s) present on the page.
  function bind() {
    Array.prototype.forEach.call(document.querySelectorAll('.theme-toggle'), function (btn) {
      if (btn.dataset.meBound) return;
      btn.dataset.meBound = '1';
      btn.addEventListener('click', toggle);
    });
  }
  bind();

  // The no-FOUC inline script in <head> applies the stored theme before paint;
  // this catches the case where that script was blocked or the element changed.
  if ((document.documentElement.getAttribute('data-theme') || 'light') !== 'light') {
    // already applied
  } else {
    try {
      if (localStorage.getItem(STORAGE) === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: 'dark' } }));
      }
    } catch (err) {}
  }
})();
