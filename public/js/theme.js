// Hive NSP: runs in <head> before the page is drawn (a blocking file, not an inline script,
// because the Content-Security-Policy allows scripts from this site only).
// - swaps the "no-js" class on <html> for "js", so CSS can give script-free fallbacks;
// - applies the visitor's saved colour theme (light or dark; nothing saved = follow the device)
//   as data-theme on <html> before the first paint, so there is no flash of the wrong theme.
// nsp.js wires the theme switch buttons to NSP.setTheme.
(function () {
  'use strict';
  var root = document.documentElement;
  var KEY = 'nsp-theme';
  root.classList.remove('no-js');
  root.classList.add('js');

  function saved() {
    try {
      var value = window.localStorage.getItem(KEY);
      return value === 'light' || value === 'dark' ? value : 'system';
    } catch (e) { return 'system'; }
  }

  function apply(choice) {
    if (choice === 'light' || choice === 'dark') root.setAttribute('data-theme', choice);
    else root.removeAttribute('data-theme');
    // Browser toolbar colour: each <meta name="theme-color"> covers one device setting; a chosen theme overrides both.
    var metas = document.querySelectorAll('meta[name="theme-color"][data-light]');
    for (var i = 0; i < metas.length; i++) {
      var meta = metas[i];
      var own = /dark/.test(meta.getAttribute('media') || '') ? 'dark' : 'light';
      meta.setAttribute('content', meta.getAttribute('data-' + (choice === 'system' ? own : choice)));
    }
  }

  var NSP = (window.NSP = window.NSP || {});
  NSP.theme = saved;
  NSP.setTheme = function (choice) {
    try {
      if (choice === 'light' || choice === 'dark') window.localStorage.setItem(KEY, choice);
      else window.localStorage.removeItem(KEY);
    } catch (e) { /* storage blocked: the choice lasts for this page only */ }
    apply(choice);
  };
  // Another tab changed the theme.
  window.addEventListener('storage', function (event) { if (event.key === KEY) apply(saved()); });

  apply(saved());

  // Accessibility settings (the universal-access panel, js/widgets.js): saved per device, applied here before
  // the first paint as data-a11y-* attributes on <html> that css/widgets.css responds to.
  var A11Y_KEY = 'nsp-a11y';
  var A11Y_ATTRS = ['text', 'line', 'spacing', 'font', 'color', 'links', 'headings', 'cursor', 'motion', 'images', 'focus', 'align'];

  function a11ySaved() {
    try {
      var value = JSON.parse(window.localStorage.getItem(A11Y_KEY) || '{}');
      return value && typeof value === 'object' ? value : {};
    } catch (e) { return {}; }
  }

  function a11yApply(prefs) {
    for (var i = 0; i < A11Y_ATTRS.length; i++) {
      var name = A11Y_ATTRS[i];
      var value = prefs[name];
      if (value === undefined || value === null || value === false || value === '' || value === 0) root.removeAttribute('data-a11y-' + name);
      else root.setAttribute('data-a11y-' + name, value === true ? 'on' : String(value).replace(/[^a-z0-9-]/gi, ''));
    }
  }

  NSP.a11y = a11ySaved;
  NSP.setA11y = function (prefs) {
    try {
      if (prefs && Object.keys(prefs).length) window.localStorage.setItem(A11Y_KEY, JSON.stringify(prefs));
      else window.localStorage.removeItem(A11Y_KEY);
    } catch (e) { /* storage blocked: settings last for this page only */ }
    a11yApply(prefs || {});
  };
  window.addEventListener('storage', function (event) { if (event.key === A11Y_KEY) a11yApply(a11ySaved()); });

  a11yApply(a11ySaved());
})();
