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
})();
