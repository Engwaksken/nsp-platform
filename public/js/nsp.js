// Hive NSP: progressive enhancements. Every page still works without this file:
// - links marked data-modal open their page normally; with JS the page's form opens in a popup;
// - [data-inline-modal] panels stay in the page; with JS they move into a popup behind a button;
// - [data-tabs] sections stack normally; with JS they become tabs;
// - form[data-confirm] falls back to the browser's confirm(); with JS a styled popup asks instead.
(function () {
  'use strict';

  var NSP = (window.NSP = window.NSP || {});
  var hasDialog = typeof window.HTMLDialogElement === 'function';
  var hasFetch = typeof window.fetch === 'function' && typeof window.FormData === 'function' && typeof window.DOMParser === 'function';
  var uid = 0;

  function qsa(root, selector) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function nextId(prefix) { uid += 1; return prefix + '-' + uid; }
  function el(tag, attrs, text) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (key) { node.setAttribute(key, attrs[key]); });
    if (text != null) node.textContent = text;
    return node;
  }
  function storage(key, value) {
    try {
      if (value === undefined) return window.sessionStorage.getItem(key);
      window.sessionStorage.setItem(key, value);
    } catch (e) { /* private mode or blocked storage: tabs simply start on the first one */ }
    return null;
  }
  // Visible text of a button, without screen-reader-only extras such as " Example page".
  function visibleText(node) {
    if (!node) return '';
    var clone = node.cloneNode(true);
    qsa(clone, '.visually-hidden').forEach(function (hidden) { hidden.remove(); });
    return clone.textContent.replace(/\s+/g, ' ').trim();
  }
  function focusFirst(container) {
    var target = container.querySelector('[autofocus]') ||
      container.querySelector('.is-invalid, [aria-invalid="true"]') ||
      container.querySelector('input:not([type=hidden]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled])');
    if (target && target.offsetParent !== null) { target.focus(); return; }
    var fallback = container.querySelector('button, a[href]');
    if (fallback) fallback.focus();
  }

  /* ------------------------------------------------------------------ */
  /* Mobile navigation toggles (public header and admin sidebar)         */
  /* ------------------------------------------------------------------ */
  // Several buttons may control one panel (the header Menu and the phone tab bar's Menu both open #site-nav).
  function setPanel(target, open) {
    target.classList.toggle('is-open', open);
    qsa(document, '[data-toggle-target="' + target.id + '"]').forEach(function (other) {
      other.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    // On phones the site menu is a full sheet: stop the page behind it from scrolling.
    if (target.id === 'site-nav') document.documentElement.classList.toggle('nav-sheet-open', open);
    // App shells (admin and member): on small screens the sidebar slides in over a backdrop.
    if (target.hasAttribute('data-offcanvas')) {
      document.documentElement.classList.toggle('nav-sheet-open', open);
      qsa(document, '.shell-backdrop').forEach(function (backdrop) { backdrop.hidden = !open; });
      if (open && window.matchMedia('(max-width: 960px)').matches) {
        var first = target.querySelector('[data-offcanvas-close], a[href]');
        if (first) first.focus();
      }
    }
  }
  function closeOffcanvas(returnFocus) {
    qsa(document, '[data-offcanvas].is-open').forEach(function (panel) {
      setPanel(panel, false);
      var toggle = document.querySelector('[data-toggle-target="' + panel.id + '"]');
      if (returnFocus && toggle) toggle.focus();
    });
  }
  qsa(document, '[data-offcanvas-close]').forEach(function (closer) {
    closer.addEventListener('click', function () { closeOffcanvas(true); });
  });
  // Back on a wide screen the sidebar is always shown: drop the phone state.
  if (window.matchMedia) {
    var wide = window.matchMedia('(min-width: 961px)');
    var onWide = function () { if (wide.matches) closeOffcanvas(false); };
    if (wide.addEventListener) wide.addEventListener('change', onWide); else if (wide.addListener) wide.addListener(onWide);
  }
  qsa(document, '[data-toggle-target]').forEach(function (button) {
    var target = document.getElementById(button.getAttribute('data-toggle-target'));
    if (!target) return;
    button.addEventListener('click', function () { setPanel(target, !target.classList.contains('is-open')); });
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && document.querySelector('[data-offcanvas].is-open')) { closeOffcanvas(true); return; }
    var siteNav = document.getElementById('site-nav');
    if (event.key === 'Escape' && siteNav && siteNav.classList.contains('is-open') && window.matchMedia('(max-width: 768px)').matches) {
      setPanel(siteNav, false);
      var tab = document.querySelector('.app-tab-menu');
      if (tab) tab.focus();
    }
  });

  // Colour theme switch (partials/theme-switch). public/js/theme.js applies and stores the choice.
  var themeButtons = qsa(document, '[data-theme-choice]');
  if (themeButtons.length && NSP.setTheme) {
    var syncTheme = function () {
      var current = NSP.theme();
      themeButtons.forEach(function (button) {
        button.setAttribute('aria-pressed', button.getAttribute('data-theme-choice') === current ? 'true' : 'false');
      });
    };
    themeButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        NSP.setTheme(button.getAttribute('data-theme-choice'));
        themeButtons.forEach(function (other) {
          other.setAttribute('aria-pressed', other.getAttribute('data-theme-choice') === button.getAttribute('data-theme-choice') ? 'true' : 'false');
        });
      });
    });
    window.addEventListener('storage', syncTheme);
    syncTheme();
  }

  // Installable app: the service worker only adds an offline page and caches the
  // versioned CSS/JS/fonts/icons. Pages themselves are never cached.
  var swMeta = document.querySelector('meta[name="nsp-sw"]');
  if (swMeta && 'serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(swMeta.getAttribute('content')).catch(function () { /* the site works without it */ });
    });
  }

  // Public header dropdowns (Opportunities, Ecosystem, Account, CMS sub-menus).
  // Desktop: one open at a time, closed by outside click, Escape or tabbing away.
  // Phone: the same buttons open their links in place inside the Menu panel.
  var navToggles = qsa(document, '.nav-group-toggle');
  if (navToggles.length) {
    var menuOf = function (button) { return document.getElementById(button.getAttribute('aria-controls')); };
    var setGroup = function (button, open) {
      var menu = menuOf(button);
      if (!menu) return;
      menu.classList.toggle('is-open', open);
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      // Right-align a dropdown that would run past the edge of the screen.
      if (open && !menu.hasAttribute('data-fixed-align')) {
        menu.classList.remove('nav-dropdown-end');
        if (menu.getBoundingClientRect().right > document.documentElement.clientWidth - 8) menu.classList.add('nav-dropdown-end');
      }
    };
    var closeAll = function (except) {
      navToggles.forEach(function (button) { if (button !== except) setGroup(button, false); });
    };
    navToggles.forEach(function (button) {
      button.addEventListener('click', function () {
        var open = button.getAttribute('aria-expanded') !== 'true';
        closeAll(button);
        setGroup(button, open);
      });
      var group = button.parentNode;
      group.addEventListener('focusout', function (event) {
        if (event.relatedTarget && !group.contains(event.relatedTarget)) setGroup(button, false);
      });
    });
    document.addEventListener('click', function (event) {
      if (!event.target.closest || !event.target.closest('.nav-group')) closeAll(null);
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      var open = navToggles.filter(function (button) { return button.getAttribute('aria-expanded') === 'true'; })[0];
      if (open) { setGroup(open, false); open.focus(); }
    });
  }

  /* ------------------------------------------------------------------ */
  /* Confirm popup                                                       */
  /* ------------------------------------------------------------------ */
  NSP.confirm = function (message, options) {
    options = options || {};
    if (!hasDialog) return Promise.resolve(window.confirm(message));
    return new Promise(function (resolve) {
      var titleId = nextId('confirm-title');
      var textId = nextId('confirm-text');
      var dialog = el('dialog', { 'class': 'confirm-dialog' + (options.danger ? ' is-danger' : ''), 'aria-labelledby': titleId, 'aria-describedby': textId });
      var icon = el('div', { 'class': 'confirm-icon', 'aria-hidden': 'true' }, options.danger ? '!' : '?');
      var title = el('h2', { id: titleId, 'class': 'confirm-title' }, options.title || (options.danger ? 'Are you sure?' : 'Please confirm'));
      var text = el('p', { id: textId, 'class': 'confirm-text' }, message);
      var form = el('form', { method: 'dialog', 'class': 'confirm-actions' });
      var cancel = el('button', { type: 'submit', value: 'no', 'class': 'btn btn-secondary' }, options.cancelLabel || 'Cancel');
      var ok = el('button', { type: 'submit', value: 'yes', 'class': 'btn ' + (options.danger ? 'btn-danger' : 'btn-primary') }, options.confirmLabel || 'Confirm');
      form.appendChild(cancel);
      form.appendChild(ok);
      [icon, title, text, form].forEach(function (node) { dialog.appendChild(node); });
      dialog.addEventListener('close', function () {
        var answer = dialog.returnValue === 'yes';
        dialog.remove();
        resolve(answer);
      });
      dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close('no'); });
      document.body.appendChild(dialog);
      dialog.showModal();
      // Safer default for destructive actions: focus starts on Cancel.
      (options.danger ? cancel : ok).focus();
    });
  };

  function isDanger(form, submitter) {
    var method = form.querySelector('input[name="_method"]');
    return !!((submitter && submitter.classList.contains('btn-danger')) ||
      (method && method.value.toUpperCase() === 'DELETE') ||
      form.querySelector('.btn-danger'));
  }

  /* ------------------------------------------------------------------ */
  /* Modals                                                              */
  /* ------------------------------------------------------------------ */
  function buildModal(title, size) {
    var titleId = nextId('modal-title');
    var dialog = el('dialog', { 'class': 'modal' + (size ? ' modal-' + size : ''), 'aria-labelledby': titleId });
    var header = el('div', { 'class': 'modal-header' });
    var heading = el('h2', { id: titleId, 'class': 'modal-title' }, title || '');
    var close = el('button', { type: 'button', 'class': 'modal-close', 'aria-label': 'Close' });
    close.innerHTML = '<svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    var body = el('div', { 'class': 'modal-body' });
    header.appendChild(heading);
    header.appendChild(close);
    dialog.appendChild(header);
    dialog.appendChild(body);
    dialog.__nsp = { heading: heading, body: body, dirty: false };

    close.addEventListener('click', function () { requestClose(dialog); });
    dialog.addEventListener('cancel', function (event) { event.preventDefault(); requestClose(dialog); });
    // Close on a click on the backdrop (the dialog box itself, outside its content).
    dialog.addEventListener('mousedown', function (event) { dialog.__nsp.downOnBackdrop = event.target === dialog; });
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog && dialog.__nsp.downOnBackdrop) requestClose(dialog);
    });
    dialog.addEventListener('input', function () { dialog.__nsp.dirty = true; });
    dialog.addEventListener('change', function () { dialog.__nsp.dirty = true; });
    // "Cancel" or "back" links that point at the page underneath simply close the popup.
    dialog.addEventListener('click', function (event) {
      var link = event.target.closest('a[href]');
      if (!link || link.target === '_blank') return;
      var url = new URL(link.href, window.location.href);
      if (url.origin === window.location.origin && url.pathname === window.location.pathname) {
        event.preventDefault();
        requestClose(dialog);
      }
    });
    document.body.appendChild(dialog);
    return dialog;
  }

  function openModal(dialog, trigger) {
    dialog.__nsp.trigger = trigger || document.activeElement;
    document.documentElement.classList.add('has-modal');
    dialog.showModal();
  }

  function requestClose(dialog) {
    if (dialog.__nsp.busy) return;
    if (!dialog.__nsp.dirty) { closeModal(dialog); return; }
    NSP.confirm('You have unsaved changes in this form. Close it and discard them?', {
      title: 'Discard changes?', confirmLabel: 'Discard changes', cancelLabel: 'Keep editing', danger: true
    }).then(function (discard) { if (discard) closeModal(dialog); });
  }

  function closeModal(dialog) {
    var state = dialog.__nsp;
    if (state.onClose) state.onClose();
    dialog.close();
    if (!document.querySelector('dialog.modal[open]')) document.documentElement.classList.remove('has-modal');
    if (state.remote) dialog.remove();
    if (state.trigger && document.contains(state.trigger)) state.trigger.focus();
  }
  NSP.closeModal = closeModal;

  // A link with data-modal: load that page and show its main content in a popup.
  function openRemote(link) {
    var dialog = buildModal(visibleText(link), link.getAttribute('data-modal') || '');
    dialog.__nsp.remote = true;
    dialog.__nsp.body.innerHTML = '<div class="modal-loading" role="status"><span class="spinner" aria-hidden="true"></span> Loading…</div>';
    openModal(dialog, link);

    fetch(link.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
      .then(function (response) {
        // Signed out, forbidden or an error page: fall back to opening the page itself.
        if (!response.ok || (response.redirected && new URL(response.url).pathname !== new URL(link.href).pathname)) throw new Error('fallback');
        return response.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var main = doc.querySelector('#main');
        if (!main) throw new Error('fallback');
        qsa(main, '.breadcrumbs, .skip-link, script').forEach(function (node) { node.remove(); });
        var h1 = main.querySelector('.page-head h1, h1');
        if (h1) {
          dialog.__nsp.heading.textContent = h1.textContent.trim();
          var head = h1.closest('.page-head');
          h1.remove();
          if (head && !head.textContent.trim() && !head.querySelector('a, button, form')) head.remove();
          else if (head) head.classList.add('modal-toolbar');
        }
        var body = dialog.__nsp.body;
        body.innerHTML = '';
        while (main.firstChild) body.appendChild(document.adoptNode(main.firstChild));
        NSP.enhance(body);
        dialog.__nsp.dirty = false;
        focusFirst(body);
      })
      .catch(function () { window.location.href = link.href; });
  }

  // A [data-inline-modal] panel: move it into a popup and put it back on close.
  function openInline(panel, trigger) {
    if (!panel || !hasDialog) return;
    if (panel.__nspDialog && panel.__nspDialog.open) return;
    var heading = panel.querySelector(':scope > h2, :scope > h3, :scope > form > h2, :scope > form > h3');
    var title = panel.getAttribute('data-modal-title') || (heading ? heading.textContent.trim() : '');
    var dialog = buildModal(title, panel.getAttribute('data-modal-size') || '');
    var placeholder = document.createComment('modal-panel');
    panel.parentNode.insertBefore(placeholder, panel);
    if (heading) heading.classList.add('is-modal-heading');
    dialog.__nsp.body.appendChild(panel);
    panel.hidden = false;
    panel.__nspDialog = dialog;
    dialog.__nsp.onClose = function () {
      // Discarded edits go back to what the page had; the popup can be reopened cleanly.
      qsa(panel, 'form').forEach(function (form) { if (!dialog.__nsp.saved) form.reset(); });
      clearErrors(panel, dialog);
      if (heading) heading.classList.remove('is-modal-heading');
      placeholder.parentNode.insertBefore(panel, placeholder);
      placeholder.remove();
      panel.hidden = true;
      window.setTimeout(function () { dialog.remove(); }, 0);
    };
    openModal(dialog, trigger);
    focusFirst(panel);
  }

  function initInlineModals(root) {
    if (!hasDialog) return;
    qsa(root, '[data-inline-modal]').forEach(function (panel) {
      if (panel.__nspInline) return;
      panel.__nspInline = true;
      if (!panel.id) panel.id = nextId('panel');
      var label = panel.getAttribute('data-modal-trigger');
      if (label) {
        var button = el('button', { type: 'button', 'class': panel.getAttribute('data-modal-trigger-class') || 'btn btn-primary', 'data-modal-open': panel.id }, label);
        var into = panel.getAttribute('data-modal-trigger-into');
        var host = into ? document.querySelector(into) : null;
        if (host) host.appendChild(button); else panel.parentNode.insertBefore(button, panel);
      }
      // Server-side errors from a submit without JavaScript: reopen the popup to show them.
      var hasErrors = panel.querySelector('.error, [aria-invalid="true"]');
      panel.hidden = true;
      if (hasErrors) openInline(panel, null);
    });
  }

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    var opener = event.target.closest('[data-modal-open]');
    if (opener && hasDialog) {
      var panel = document.getElementById(opener.getAttribute('data-modal-open').replace(/^#/, ''));
      if (panel) { event.preventDefault(); openInline(panel, opener); }
      return;
    }
    var link = event.target.closest('a[data-modal]');
    if (link && hasDialog && hasFetch && !link.closest('dialog')) {
      event.preventDefault();
      openRemote(link);
    }
  });

  /* ------------------------------------------------------------------ */
  /* Form submission: confirm first, then send forms in popups in place  */
  /* ------------------------------------------------------------------ */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    var submitter = event.submitter || null;

    if (form.hasAttribute('data-confirm') && !form.__nspConfirmed) {
      event.preventDefault();
      NSP.confirm(form.getAttribute('data-confirm'), {
        danger: isDanger(form, submitter),
        confirmLabel: visibleText(submitter) || undefined
      }).then(function (ok) {
        if (!ok) return;
        form.__nspConfirmed = true;
        if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
        else form.submit();
      });
      return;
    }
    form.__nspConfirmed = false;

    var dialog = form.closest('dialog.modal');
    var method = (form.getAttribute('method') || 'get').toLowerCase();
    if (dialog && hasFetch && method === 'post' && !form.hasAttribute('data-native-submit') && !form.target) {
      event.preventDefault();
      ajaxSubmit(form, submitter, dialog);
    }
  });

  function ajaxSubmit(form, submitter, dialog) {
    if (dialog.__nsp.busy) return;
    var data = new FormData(form);
    if (submitter && submitter.name) data.append(submitter.name, submitter.value);
    setBusy(form, dialog, submitter, true);
    clearErrors(form, dialog);

    fetch(form.action, {
      method: 'POST', body: data, credentials: 'same-origin', redirect: 'manual',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
      // Laravel answers a successful save with a redirect. Not following it here keeps the
      // "Saved" message in the session, so the reloaded page shows it.
      if (response.type === 'opaqueredirect' || (response.status >= 200 && response.status < 300)) {
        dialog.__nsp.saved = true;
        dialog.__nsp.dirty = false;
        window.location.href = window.location.href.split('#')[0];
        return null;
      }
      if (response.status === 422) {
        return response.json().then(function (body) {
          setBusy(form, dialog, submitter, false);
          showErrors(form, dialog, body && body.errors ? body.errors : {}, body && body.message);
        });
      }
      var messages = {
        401: 'You have been signed out. Reload the page and sign in again.',
        403: 'You do not have permission to do that.',
        404: 'This item no longer exists. Reload the page.',
        413: 'The file is too large to upload.',
        419: 'Your session has expired. Reload the page and try again.',
        429: 'Too many attempts. Wait a minute and try again.'
      };
      throw new Error(messages[response.status] || 'Something went wrong (error ' + response.status + '). Please try again.');
    }).catch(function (error) {
      setBusy(form, dialog, submitter, false);
      showAlert(dialog, error && error.message ? error.message : 'Could not reach the server. Check your connection and try again.');
    });
  }

  function setBusy(form, dialog, submitter, busy) {
    dialog.__nsp.busy = busy;
    form.setAttribute('aria-busy', busy ? 'true' : 'false');
    qsa(form, 'button[type=submit], button:not([type])').concat(submitter && !form.contains(submitter) ? [submitter] : []).forEach(function (button) {
      button.disabled = busy;
    });
    if (submitter) {
      if (busy) { submitter.__label = submitter.innerHTML; submitter.classList.add('is-loading'); submitter.innerHTML = '<span class="spinner" aria-hidden="true"></span> ' + (visibleText(submitter).match(/^(delete|remove)/i) ? 'Working…' : 'Saving…'); }
      else if (submitter.__label != null) { submitter.innerHTML = submitter.__label; submitter.classList.remove('is-loading'); }
    }
  }

  function clearErrors(scope, dialog) {
    qsa(scope, '[data-nsp-error]').forEach(function (node) { node.remove(); });
    qsa(scope, '[data-nsp-invalid]').forEach(function (field) {
      field.classList.remove('is-invalid');
      field.removeAttribute('aria-invalid');
      field.removeAttribute('data-nsp-invalid');
      var describedBy = (field.getAttribute('aria-describedby') || '').split(' ').filter(function (id) { return id.indexOf('nsp-err-') !== 0; }).join(' ');
      if (describedBy) field.setAttribute('aria-describedby', describedBy); else field.removeAttribute('aria-describedby');
    });
    if (dialog) qsa(dialog, '[data-nsp-alert]').forEach(function (node) { node.remove(); });
  }

  function fieldFor(form, key) {
    var bracket = key.split('.').map(function (part, index) { return index === 0 ? part : '[' + part + ']'; }).join('');
    var base = key.replace(/\.\d+$/, '');
    var baseBracket = base.split('.').map(function (part, index) { return index === 0 ? part : '[' + part + ']'; }).join('');
    var names = [key, bracket, key + '[]', bracket + '[]', baseBracket + '[]', base];
    for (var i = 0; i < names.length; i++) {
      var match = form.querySelector('[name="' + names[i].replace(/"/g, '\\"') + '"]');
      if (match && match.type !== 'hidden') return match;
    }
    return null;
  }

  function showErrors(form, dialog, errors, message) {
    var all = [];
    var first = null;
    Object.keys(errors).forEach(function (key) {
      var list = [].concat(errors[key]);
      all = all.concat(list);
      var field = fieldFor(form, key);
      if (!field) return;
      var group = field.closest('.form-group, fieldset') || field.parentNode;
      var target = (field.type === 'checkbox' || field.type === 'radio') ? (field.closest('fieldset') || group) : field;
      if (field.type !== 'checkbox' && field.type !== 'radio') {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('data-nsp-invalid', '');
      }
      var errorId = nextId('nsp-err');
      var note = el('p', { 'class': 'error', id: errorId, 'data-nsp-error': '' }, list[0]);
      group.appendChild(note);
      if (field.type !== 'checkbox' && field.type !== 'radio') {
        field.setAttribute('aria-describedby', ((field.getAttribute('aria-describedby') || '') + ' ' + errorId).trim());
      }
      if (!first) first = target;
    });
    showAlert(dialog, all.length ? null : (message || 'Please check the form.'), all);
    if (first) {
      NSP.showTabFor(first);
      if (first.focus) first.focus();
    }
  }

  function showAlert(dialog, text, list) {
    qsa(dialog, '[data-nsp-alert]').forEach(function (node) { node.remove(); });
    var alert = el('div', { 'class': 'alert alert-danger', role: 'alert', 'data-nsp-alert': '' });
    if (list && list.length) {
      alert.appendChild(el('strong', {}, 'Please fix the following:'));
      var ul = el('ul');
      list.forEach(function (item) { ul.appendChild(el('li', {}, item)); });
      alert.appendChild(ul);
    } else {
      alert.textContent = text;
    }
    var body = dialog.__nsp ? dialog.__nsp.body : dialog;
    body.insertBefore(alert, body.firstChild);
    body.scrollTop = 0;
  }

  /* ------------------------------------------------------------------ */
  /* Tabs                                                                */
  /* ------------------------------------------------------------------ */
  function initTabs(root) {
    qsa(root, '[data-tabs]').forEach(function (box, boxIndex) {
      if (box.__nspTabs) return;
      var panels = Array.prototype.filter.call(box.children, function (child) { return child.hasAttribute('data-tab'); });
      if (panels.length < 2) return;
      box.__nspTabs = true;
      var inModal = !!box.closest('dialog');
      var key = 'nsp-tab:' + window.location.pathname + ':' + (box.getAttribute('data-tabs-key') || boxIndex);
      var list = el('div', { 'class': 'tabs-list', role: 'tablist', 'aria-label': box.getAttribute('data-tabs') || 'Sections' });
      var tabs = panels.map(function (panel) {
        if (!panel.id) panel.id = nextId('tabpanel');
        var tab = el('button', { type: 'button', role: 'tab', id: panel.id + '-tab', 'aria-controls': panel.id, 'class': 'tab' });
        // Optional Font Awesome icon: data-tab-icon="fa-coins" (letters, digits and dashes only).
        var icon = panel.getAttribute('data-tab-icon');
        if (icon && /^fa-[a-z0-9-]+$/.test(icon)) tab.appendChild(el('i', { 'class': 'fas ' + icon, 'aria-hidden': 'true' }));
        tab.appendChild(el('span', {}, panel.getAttribute('data-tab')));
        var count = panel.getAttribute('data-tab-count');
        if (count) {
          var badge = el('span', { 'class': 'tab-count' });
          badge.appendChild(el('span', { 'class': 'visually-hidden' }, ' ('));
          badge.appendChild(document.createTextNode(count));
          badge.appendChild(el('span', { 'class': 'visually-hidden' }, ')'));
          tab.appendChild(badge);
        }
        if (panel.querySelector('.error, .is-invalid, [aria-invalid="true"]')) tab.classList.add('has-error');
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', tab.id);
        panel.setAttribute('tabindex', '0');
        list.appendChild(tab);
        return tab;
      });
      box.insertBefore(list, panels[0]);
      box.classList.add('is-tabbed');
      // Fade the edge of a tab row that scrolls sideways (narrow screens), so it is clear there is more.
      var edge = function () {
        list.classList.toggle('has-overflow', list.scrollWidth > list.clientWidth + 2);
        list.classList.toggle('at-end', list.scrollLeft + list.clientWidth >= list.scrollWidth - 2);
      };
      list.addEventListener('scroll', edge, { passive: true });
      window.addEventListener('resize', edge);
      window.setTimeout(edge, 0);

      function select(index, focus) {
        tabs.forEach(function (tab, i) {
          var active = i === index;
          tab.setAttribute('aria-selected', active ? 'true' : 'false');
          tab.tabIndex = active ? 0 : -1;
          panels[i].hidden = !active;
        });
        if (focus) tabs[index].focus();
        if (!inModal) storage(key, String(index));
      }
      box.__nspSelect = function (panel) { var i = panels.indexOf(panel); if (i >= 0) select(i, false); };

      tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { select(i, false); });
        tab.addEventListener('keydown', function (event) {
          var next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[event.key];
          if (next === undefined) return;
          event.preventDefault();
          select((next + tabs.length) % tabs.length, true);
        });
      });
      // A required field on a hidden tab: show that tab so the browser can point at the field.
      box.addEventListener('invalid', function (event) { NSP.showTabFor(event.target); }, true);

      var start = panels.findIndex(function (panel) { return panel.querySelector('.error, .is-invalid, [aria-invalid="true"]'); });
      if (start < 0 && window.location.hash) start = panels.findIndex(function (panel) { return '#' + panel.id === window.location.hash; });
      if (start < 0 && !inModal) { var saved = parseInt(storage(key) || '', 10); if (saved >= 0 && saved < panels.length) start = saved; }
      select(start < 0 ? 0 : start, false);
    });
    // An in-page link to something inside a hidden tab (e.g. "#invite") opens that tab.
    if (!NSP.tabHashBound) {
      NSP.tabHashBound = true;
      window.addEventListener('hashchange', function () {
        var target = window.location.hash ? document.getElementById(decodeURIComponent(window.location.hash.slice(1))) : null;
        if (target && NSP.showTabFor) NSP.showTabFor(target);
      });
    }
  }

  NSP.showTabFor = function (node) {
    var panel = node && node.closest ? node.closest('[role="tabpanel"]') : null;
    while (panel) {
      var box = panel.parentElement;
      if (box && box.__nspSelect) box.__nspSelect(panel);
      panel = box ? box.closest('[role="tabpanel"]') : null;
    }
  };

  /* ------------------------------------------------------------------ */
  /* Small helpers: copy, download, print, dismissible alerts            */
  /* ------------------------------------------------------------------ */
  function textOf(selector) {
    var node = document.querySelector(selector);
    if (!node) return '';
    return node.matches('ul, ol') ? qsa(node, 'li').map(function (li) { return li.textContent.trim(); }).join('\n') : node.textContent.trim();
  }
  function flash(button, text) {
    var original = button.getAttribute('data-label') || button.textContent;
    button.setAttribute('data-label', original);
    button.textContent = text;
    window.setTimeout(function () { button.textContent = original; }, 1800);
  }
  document.addEventListener('click', function (event) {
    var copy = event.target.closest('[data-copy]');
    if (copy) {
      var value = textOf(copy.getAttribute('data-copy'));
      var done = function () { flash(copy, 'Copied'); };
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(value).then(done, function () { flash(copy, 'Copy failed'); });
      else {
        var area = el('textarea', { 'aria-hidden': 'true', 'class': 'visually-hidden' });
        area.value = value;
        document.body.appendChild(area);
        area.select();
        try { document.execCommand('copy'); done(); } catch (e) { flash(copy, 'Copy failed'); }
        area.remove();
      }
      return;
    }
    var download = event.target.closest('[data-download]');
    if (download) {
      var blob = new Blob([textOf(download.getAttribute('data-download')) + '\n'], { type: 'text/plain' });
      var link = el('a', { href: URL.createObjectURL(blob), download: download.getAttribute('data-filename') || 'download.txt' });
      document.body.appendChild(link);
      link.click();
      window.setTimeout(function () { URL.revokeObjectURL(link.href); link.remove(); }, 0);
      return;
    }
    if (event.target.closest('[data-print]')) window.print();
  });

  function initAlerts(root) {
    qsa(root, '.admin-content > .alert-success[role="status"], [data-dismissible]').forEach(function (alert) {
      if (alert.__nspDismiss) return;
      alert.__nspDismiss = true;
      alert.classList.add('is-dismissible');
      var button = el('button', { type: 'button', 'class': 'alert-close', 'aria-label': 'Dismiss message' }, '×');
      button.addEventListener('click', function () { alert.remove(); });
      alert.appendChild(button);
    });
  }

  /* ------------------------------------------------------------------ */
  /* Page builder: add, remove and reorder sections                     */
  /* ------------------------------------------------------------------ */
  function initBuilder(root) {
    var editor = root.querySelector('#blocks');
    var template = root.querySelector('#block-template');
    var addButton = root.querySelector('#add-block');
    if (!editor || !template || !addButton || editor.__nspBuilder) return;
    editor.__nspBuilder = true;
    var renumber = function () {
      qsa(editor, '.block-editor').forEach(function (block, index) {
        qsa(block, '[data-name]').forEach(function (field) {
          field.name = 'blocks[' + index + '][' + field.getAttribute('data-name') + ']';
          field.id = 'block-' + index + '-' + field.getAttribute('data-name');
        });
        qsa(block, '[data-for]').forEach(function (label) {
          label.setAttribute('for', 'block-' + index + '-' + label.getAttribute('data-for'));
        });
        var title = block.querySelector('[data-block-number]');
        if (title) title.textContent = 'Section ' + (index + 1);
      });
    };
    var announce = function (text) {
      var live = root.querySelector('#blocks-status');
      if (live) live.textContent = text;
    };
    addButton.addEventListener('click', function () {
      var type = root.querySelector('#new-block-type');
      var clone = template.content.firstElementChild.cloneNode(true);
      clone.querySelector('[data-name="type"]').value = type.value;
      clone.querySelector('[data-block-type-label]').textContent = type.options[type.selectedIndex].text;
      editor.appendChild(clone);
      renumber();
      editor.dispatchEvent(new Event('change', { bubbles: true }));
      clone.querySelector('[data-name="heading"]').focus();
      announce('Section added.');
    });
    editor.addEventListener('click', function (event) {
      var button = event.target.closest('[data-block-action]');
      if (!button) return;
      var block = button.closest('.block-editor');
      var action = button.getAttribute('data-block-action');
      var go = function () {
        if (action === 'remove') { block.remove(); announce('Section removed.'); }
        if (action === 'up' && block.previousElementSibling) { editor.insertBefore(block, block.previousElementSibling); announce('Section moved up.'); }
        if (action === 'down' && block.nextElementSibling) { editor.insertBefore(block.nextElementSibling, block); announce('Section moved down.'); }
        renumber();
        editor.dispatchEvent(new Event('change', { bubbles: true }));
        if (document.contains(button)) button.focus();
      };
      if (action === 'remove') {
        NSP.confirm('Remove this section from the page? It is only deleted when you save.', { danger: true, confirmLabel: 'Remove section' })
          .then(function (ok) { if (ok) go(); });
      } else go();
    });
    renumber();
  }

  /* ------------------------------------------------------------------ */
  /* Suggest a slug from the title while the slug is still untouched    */
  /* ------------------------------------------------------------------ */
  function initSlug(root) {
    var title = root.querySelector('[data-slug-source]');
    var slug = root.querySelector('[data-slug-target]');
    if (!title || !slug || slug.value !== '' || slug.__nspSlug) return;
    slug.__nspSlug = true;
    var touched = false;
    slug.addEventListener('input', function () { touched = true; });
    title.addEventListener('input', function () {
      if (touched) return;
      slug.value = title.value.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
    });
  }

  /* ------------------------------------------------------------------ */
  /* One-time code inputs: digits only, submit when complete            */
  /* ------------------------------------------------------------------ */
  function initOtp(root) {
    qsa(root, 'input.otp-input').forEach(function (input) {
      if (input.__nspOtp) return;
      input.__nspOtp = true;
      input.addEventListener('input', function () {
        if (input.getAttribute('data-otp') === 'digits') input.value = input.value.replace(/\D/g, '').slice(0, 6);
        if (input.getAttribute('data-otp-autosubmit') !== null && /^\d{6}$/.test(input.value) && input.form) {
          if (typeof input.form.requestSubmit === 'function') input.form.requestSubmit(); else input.form.submit();
        }
      });
    });
  }

  /* ------------------------------------------------------------------ */
  /* Tables: label every cell with its column heading so phones can show */
  /* each row as a card (see .is-stacked in nsp.css).                    */
  /* ------------------------------------------------------------------ */
  function initTables(root) {
    qsa(root, '.table-wrap').forEach(function (wrap) {
      var table = wrap.querySelector('table');
      if (!table || wrap.__nspStacked || wrap.hasAttribute('data-no-stack')) return;
      var head = table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
      if (!head) return;
      var labels = [];
      Array.prototype.forEach.call(head.cells, function (cell) {
        var text = visibleText(cell);
        for (var i = 0; i < (cell.colSpan || 1); i++) labels.push(text);
      });
      Array.prototype.forEach.call(table.tBodies, function (body) {
        Array.prototype.forEach.call(body.rows, function (row) {
          var column = 0;
          Array.prototype.forEach.call(row.cells, function (cell) {
            var span = cell.colSpan || 1;
            if (span === 1 && labels[column] && !cell.hasAttribute('data-label')) cell.setAttribute('data-label', labels[column]);
            column += span;
          });
        });
      });
      wrap.__nspStacked = true;
      wrap.classList.add('is-stacked');
    });
  }

  function initAiProviderForm(root) {
    qsa(root, '[data-ai-provider-form]').forEach(function (form) {
      if (form.__nspAiProvider) return;
      var provider = form.querySelector('#field-provider');
      var customFields = form.querySelector('[data-custom-provider-field]');
      var curatedFields = form.querySelector('[data-curated-model-field]');
      var model = form.querySelector('#field-model');
      var customModel = form.querySelector('#field-custom_model');
      var baseUrl = form.querySelector('#field-base_url');
      if (!provider || !customFields || !curatedFields || !model || !customModel || !baseUrl) return;
      form.__nspAiProvider = true;
      var catalog = {};
      try { catalog = JSON.parse(form.getAttribute('data-model-catalog') || '{}'); } catch (e) { return; }
      var defaultModel = form.getAttribute('data-default-model') || '';
      function sync() {
        var isCustom = provider.value === 'custom';
        customFields.hidden = !isCustom;
        curatedFields.hidden = isCustom;
        model.disabled = isCustom;
        model.required = !isCustom;
        customModel.disabled = !isCustom;
        customModel.required = isCustom;
        baseUrl.required = isCustom;
        if (!isCustom) {
          var choices = catalog[provider.value] || {};
          var selected = choices[model.value] ? model.value : (choices[defaultModel] ? defaultModel : Object.keys(choices)[0]);
          model.replaceChildren(new Option('Choose a model', ''));
          Object.keys(choices).forEach(function (id) { model.add(new Option(choices[id], id)); });
          model.value = selected || '';
        }
      }
      provider.addEventListener('change', sync);
      sync();
    });
  }

  function initPasswordToggles(root) {
    qsa(root, 'input[type="password"]:not([data-no-password-toggle])').forEach(function (input) {
      if (input.__nspPasswordToggle) return;
      input.__nspPasswordToggle = true;
      if (!input.id) input.id = nextId('password');
      var wrapper = el('span', { 'class': 'password-toggle-wrap' });
      input.parentNode.insertBefore(wrapper, input);
      wrapper.appendChild(input);
      var button = el('button', {
        type: 'button',
        'class': 'password-toggle',
        'aria-label': 'Show password',
        'aria-controls': input.id,
        'aria-pressed': 'false',
        title: 'Show password'
      });
      var icon = el('i', { 'class': 'fas fa-eye', 'aria-hidden': 'true' });
      button.appendChild(icon);
      button.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
        button.title = show ? 'Hide password' : 'Show password';
        icon.className = 'fas ' + (show ? 'fa-eye-slash' : 'fa-eye');
        input.focus();
      });
      wrapper.appendChild(button);
    });
  }

  function initTableActionIcons(root) {
    qsa(root, 'table').forEach(function (table) {
      var headers = table.tHead ? Array.prototype.slice.call(table.tHead.rows[table.tHead.rows.length - 1].cells) : [];
      var actionIndex = headers.findIndex(function (header) { return visibleText(header).toLowerCase() === 'actions'; });
      if (actionIndex < 0) return;
      var iconFor = function (label) {
        var text = label.toLowerCase();
        if (/review|view|preview|inspect/.test(text)) return 'fa-eye';
        if (/edit|change/.test(text)) return 'fa-pen-to-square';
        if (/delete|remove|take down/.test(text)) return 'fa-trash';
        if (/restore|undo/.test(text)) return 'fa-arrow-rotate-left';
        if (/send|replay/.test(text)) return 'fa-paper-plane';
        if (/merge/.test(text)) return 'fa-code-merge';
        if (/approve|publish/.test(text)) return 'fa-circle-check';
        if (/reject|suspend|do not verify/.test(text)) return 'fa-ban';
        return 'fa-arrow-up-right-from-square';
      };
      Array.prototype.forEach.call(table.tBodies, function (body) {
        Array.prototype.forEach.call(body.rows, function (row) {
          var cell = row.cells[actionIndex];
          if (!cell) return;
          qsa(cell, 'a, button').forEach(function (control) {
            if (control.__nspActionIcon) return;
            control.__nspActionIcon = true;
            var label = visibleText(control);
            if (!control.hasAttribute('title') && label) control.title = label;
            if (!control.querySelector('i.fas')) {
              var icon = el('i', { 'class': 'fas ' + iconFor(label), 'aria-hidden': 'true' });
              control.insertBefore(icon, control.firstChild);
            }
          });
        });
      });
    });
  }

  NSP.enhance = function (root) {
    root = root || document;
    initTables(root);
    initTabs(root);
    initAiProviderForm(root);
    initPasswordToggles(root);
    initTableActionIcons(root);
    initInlineModals(root);
    initAlerts(root);
    initBuilder(root);
    initSlug(root);
    initOtp(root);
  };

  NSP.enhance(document);
})();
