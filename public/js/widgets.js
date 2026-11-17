// Hive NSP: the floating accessibility panel and AI chat helper (markup in partials/site-widgets.blade.php).
// theme.js already applied saved accessibility settings before the first paint (NSP.a11y / NSP.setA11y);
// this file runs the panel controls, read-aloud, the reading guide and the chat.
(function () {
  'use strict';

  var NSP = (window.NSP = window.NSP || {});
  var root = document.documentElement;
  var launchers = document.querySelector('[data-site-widgets]');
  if (!launchers || typeof NSP.setA11y !== 'function') return;

  function qsa(scope, selector) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }
  function prefs() { var p = NSP.a11y(); return p && typeof p === 'object' ? p : {}; }
  function save(next) {
    Object.keys(next).forEach(function (key) {
      if (next[key] === false || next[key] === '' || next[key] === 0 || next[key] == null) delete next[key];
    });
    NSP.setA11y(next);
    sync();
  }
  function announce(text) {
    var status = document.querySelector('[data-a11y-status]');
    if (!status) return;
    status.textContent = '';
    window.setTimeout(function () { status.textContent = text; }, 30);
  }

  launchers.hidden = false;

  /* ---------------------------------------------------------------- */
  /* Panels: non-modal dialogs, one open at a time, Esc closes.        */
  /* ---------------------------------------------------------------- */
  var lastOpener = null;

  function openPanel(id, opener) {
    qsa(document, '.sw-panel').forEach(function (panel) { if (panel.id !== id) closePanel(panel, false); });
    var panel = document.getElementById(id);
    if (!panel) return;
    panel.hidden = false;
    lastOpener = opener || document.querySelector('[data-sw-open="' + id + '"]');
    qsa(document, '[data-sw-open="' + id + '"]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
    var main = panel.querySelector('[data-chat-main]');
    var focusTarget = main && !main.hidden ? panel.querySelector('[data-chat-input]') : null;
    if (!focusTarget) focusTarget = panel.querySelector('[data-chat-grant]:not([hidden])') || panel.querySelector('.sw-panel-title');
    if (focusTarget) focusTarget.focus();
    if (id === 'sw-chat') scrollLog();
  }

  function closePanel(panel, restoreFocus) {
    if (!panel || panel.hidden) return;
    panel.hidden = true;
    qsa(document, '[data-sw-open="' + panel.id + '"]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    if (restoreFocus !== false && lastOpener) lastOpener.focus();
  }

  function togglePanel(id, opener) {
    var panel = document.getElementById(id);
    if (!panel) return;
    if (panel.hidden) openPanel(id, opener); else closePanel(panel);
  }

  document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-sw-open]');
    if (opener) { togglePanel(opener.getAttribute('data-sw-open'), opener); return; }
    var closer = event.target.closest('[data-sw-close]');
    if (closer) closePanel(closer.closest('.sw-panel'));
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      var open = event.target.closest && event.target.closest('.sw-panel');
      if (open) { event.preventDefault(); closePanel(open); return; }
    }
    if (event.altKey && event.shiftKey && !event.ctrlKey && !event.metaKey) {
      var key = (event.key || '').toLowerCase();
      var code = event.code || '';
      if (key === 'a' || code === 'KeyA') { event.preventDefault(); togglePanel('sw-a11y'); }
      if ((key === 'c' || code === 'KeyC') && document.getElementById('sw-chat')) { event.preventDefault(); togglePanel('sw-chat'); }
    }
  });

  /* ---------------------------------------------------------------- */
  /* Accessibility settings                                            */
  /* ---------------------------------------------------------------- */
  var PROFILES = {
    vision: { text: 2, line: 1, color: 'contrast', links: true, focus: true, cursor: true },
    dyslexia: { font: true, spacing: 1, line: 1, align: true, guide: 'line' },
    focus: { guide: 'mask', motion: true, images: false, headings: true },
    seizure: { motion: true, color: 'low-sat' },
    motor: { focus: true, cursor: true, links: true, text: 1 },
    blind: { clickread: true, focus: true, links: true }
  };
  var STEP_MAX = { text: 4, line: 2, spacing: 2 };
  var panel = document.getElementById('sw-a11y');

  function sync() {
    var p = prefs();
    if (!panel) return;
    qsa(panel, '[data-a11y-toggle]').forEach(function (b) {
      b.setAttribute('aria-pressed', p[b.getAttribute('data-a11y-toggle')] ? 'true' : 'false');
    });
    qsa(panel, '[data-a11y-choice]').forEach(function (b) {
      var current = p[b.getAttribute('data-a11y-choice')] || '';
      b.setAttribute('aria-pressed', current === b.getAttribute('data-value') ? 'true' : 'false');
    });
    qsa(panel, '[data-a11y-step]').forEach(function (row) {
      var name = row.getAttribute('data-a11y-step');
      var values = row.getAttribute('data-values').split('|');
      var value = Math.max(0, Math.min(STEP_MAX[name], parseInt(p[name], 10) || 0));
      row.querySelector('output').textContent = values[value];
      row.querySelector('[data-dir="-1"]').disabled = value === 0;
      row.querySelector('[data-dir="1"]').disabled = value === STEP_MAX[name];
    });
    qsa(panel, '[data-a11y-profile]').forEach(function (b) {
      var profile = PROFILES[b.getAttribute('data-a11y-profile')];
      var on = Object.keys(profile).every(function (key) { return (p[key] || false) === profile[key] || (!p[key] && !profile[key]); });
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    var rate = panel.querySelector('[data-a11y-select="rate"]');
    if (rate) rate.value = String(p.rate || 1);
    var voice = panel.querySelector('[data-a11y-select="voice"]');
    if (voice && p.voice && voice.querySelector('option[value="' + CSS.escape(p.voice) + '"]')) voice.value = p.voice;
    guide.update(p.guide || '');
    clickRead(!!p.clickread);
  }

  if (panel) {
    panel.addEventListener('click', function (event) {
      var b = event.target.closest('button');
      if (!b || !panel.contains(b)) return;
      var p = prefs();
      if (b.hasAttribute('data-a11y-toggle')) {
        var name = b.getAttribute('data-a11y-toggle');
        p[name] = !p[name];
        save(p);
        announce(b.textContent.trim() + (p[name] ? ' on' : ' off'));
      } else if (b.hasAttribute('data-a11y-choice')) {
        var choice = b.getAttribute('data-a11y-choice');
        var value = b.getAttribute('data-value');
        p[choice] = b.hasAttribute('data-allow-none') && p[choice] === value ? '' : value;
        save(p);
        announce(b.textContent.trim() + (p[choice] === value ? ' on' : ' off'));
      } else if (b.closest('[data-a11y-step]') && b.hasAttribute('data-dir')) {
        var row = b.closest('[data-a11y-step]');
        var step = row.getAttribute('data-a11y-step');
        p[step] = Math.max(0, Math.min(STEP_MAX[step], (parseInt(p[step], 10) || 0) + parseInt(b.getAttribute('data-dir'), 10)));
        save(p);
        announce(row.querySelector('.sw-stepper-label').textContent + ' ' + row.querySelector('output').textContent);
        if (b.disabled) (row.querySelector('button:not([disabled])') || row).focus();
      } else if (b.hasAttribute('data-a11y-profile')) {
        var profile = PROFILES[b.getAttribute('data-a11y-profile')];
        var active = b.getAttribute('aria-pressed') === 'true';
        Object.keys(profile).forEach(function (key) {
          if (active) delete p[key]; else p[key] = profile[key];
        });
        save(p);
        announce(b.textContent.trim() + ' profile ' + (active ? 'off' : 'on'));
      } else if (b.hasAttribute('data-a11y-reset')) {
        speech.stop();
        save({});
        announce('All accessibility settings reset');
      }
    });
    panel.addEventListener('change', function (event) {
      var select = event.target.closest('[data-a11y-select]');
      if (!select) return;
      var p = prefs();
      p[select.getAttribute('data-a11y-select')] = select.value === '1' && select.getAttribute('data-a11y-select') === 'rate' ? '' : select.value;
      save(p);
    });
  }

  /* Reading guide (a bar that follows the pointer) or reading mask (dims all but a band). */
  var guide = (function () {
    var mode = '';
    var bar = null, top = null, bottom = null;
    var y = window.innerHeight / 3;
    function place() {
      if (mode === 'line' && bar) bar.style.transform = 'translateY(' + (y - 6) + 'px)';
      if (mode === 'mask' && top) {
        top.style.height = Math.max(0, y - 60) + 'px';
        bottom.style.top = (y + 60) + 'px';
      }
    }
    function move(event) {
      if (event.touches && event.touches[0]) y = event.touches[0].clientY;
      else if (typeof event.clientY === 'number') y = event.clientY;
      place();
    }
    function focusMove(event) {
      var rect = event.target.getBoundingClientRect ? event.target.getBoundingClientRect() : null;
      if (rect && rect.height) { y = rect.top + rect.height / 2; place(); }
    }
    function make(cls) { var d = document.createElement('div'); d.className = cls; d.setAttribute('aria-hidden', 'true'); document.body.appendChild(d); return d; }
    return {
      update: function (next) {
        if (next === mode) return;
        [bar, top, bottom].forEach(function (node) { if (node) node.remove(); });
        bar = top = bottom = null;
        mode = next;
        if (!mode) {
          document.removeEventListener('pointermove', move);
          document.removeEventListener('touchmove', move);
          document.removeEventListener('focusin', focusMove);
          return;
        }
        if (mode === 'line') bar = make('sw-guide-line');
        else { top = make('sw-mask sw-mask-top'); bottom = make('sw-mask sw-mask-bottom'); }
        document.addEventListener('pointermove', move, { passive: true });
        document.addEventListener('touchmove', move, { passive: true });
        document.addEventListener('focusin', focusMove);
        place();
      }
    };
  })();

  /* ---------------------------------------------------------------- */
  /* Read aloud (the browser's own speech; nothing leaves the device). */
  /* ---------------------------------------------------------------- */
  var speech = (function () {
    var synth = window.speechSynthesis;
    var supported = !!(synth && window.SpeechSynthesisUtterance);
    var queue = [];
    var reading = null;

    function chunks(text) {
      var parts = (text.replace(/\s+/g, ' ').trim().match(/[^.!?;:]+[.!?;:]*\s*/g) || []);
      var out = [], current = '';
      parts.forEach(function (part) {
        if ((current + part).length > 220 && current) { out.push(current); current = ''; }
        current += part;
      });
      if (current.trim()) out.push(current);
      return out;
    }
    function voice() {
      var wanted = prefs().voice;
      return wanted ? synth.getVoices().filter(function (v) { return v.voiceURI === wanted; })[0] : null;
    }
    function next() {
      if (!queue.length) { finish(); return; }
      var u = new window.SpeechSynthesisUtterance(queue.shift());
      u.rate = parseFloat(prefs().rate) || 1;
      u.lang = root.lang || 'en';
      var v = voice();
      if (v) u.voice = v;
      u.onend = next;
      u.onerror = function () { finish(); };
      synth.speak(u);
    }
    function finish() {
      queue = [];
      if (reading) reading.classList.remove('sw-reading');
      reading = null;
      var pause = document.querySelector('[data-tts-action="pause"]');
      if (pause) pause.setAttribute('aria-pressed', 'false');
    }
    function readable(node) {
      var clone = node.cloneNode(true);
      qsa(clone, 'script, style, noscript, template, [hidden], [aria-hidden="true"], .visually-hidden.skip-link, .sw-panel, .sw-launchers, .sw-chat-actions').forEach(function (n) { n.remove(); });
      qsa(clone, 'img[alt]').forEach(function (img) { if (img.alt) img.replaceWith(document.createTextNode(' Image: ' + img.alt + '. ')); });
      qsa(clone, 'h1, h2, h3, h4, h5, h6, p, li, td, th, dt, dd, label, button, a, div, section, article').forEach(function (n) { n.append(document.createTextNode('. ')); });
      return clone.textContent.replace(/(\.\s*){2,}/g, '. ');
    }
    return {
      supported: supported,
      speak: function (text, element) {
        if (!supported || !text || !text.trim()) return;
        synth.cancel();
        finish();
        queue = chunks(text);
        if (element) { reading = element; element.classList.add('sw-reading'); }
        next();
      },
      readNode: function (node) { this.speak(readable(node), node); },
      pause: function () {
        if (!supported || (!synth.speaking && !synth.paused)) return false;
        if (synth.paused) { synth.resume(); return false; }
        synth.pause(); return true;
      },
      stop: function () { if (supported) synth.cancel(); finish(); },
      voices: function () { return supported ? synth.getVoices() : []; }
    };
  })();
  NSP.speak = function (text) { speech.speak(text); };

  if (panel) {
    var tts = panel.querySelector('[data-tts]');
    if (!speech.supported) {
      tts.querySelector('[data-tts-unsupported]').hidden = false;
      qsa(tts, '[data-tts-controls]').forEach(function (n) { n.hidden = true; });
      var blind = panel.querySelector('[data-a11y-profile="blind"]');
      if (blind) blind.hidden = true;
    } else {
      var fillVoices = function () {
        var select = tts.querySelector('[data-a11y-select="voice"]');
        var lang = (root.lang || 'en').slice(0, 2).toLowerCase();
        var voices = speech.voices().filter(function (v) { return (v.lang || '').toLowerCase().indexOf(lang) === 0; });
        if (!voices.length) voices = speech.voices();
        while (select.options.length > 1) select.remove(1);
        voices.forEach(function (v) { select.add(new Option(v.name + (v.lang ? ' (' + v.lang + ')' : ''), v.voiceURI)); });
        if (prefs().voice) select.value = prefs().voice;
      };
      fillVoices();
      if (window.speechSynthesis.addEventListener) window.speechSynthesis.addEventListener('voiceschanged', fillVoices);
      tts.addEventListener('click', function (event) {
        var b = event.target.closest('[data-tts-action]');
        if (!b) return;
        var action = b.getAttribute('data-tts-action');
        if (action === 'page') speech.readNode(document.getElementById('main') || document.body);
        if (action === 'selection') {
          var text = String(window.getSelection ? window.getSelection() : '');
          if (text.trim()) speech.speak(text); else announce('Select some text on the page first, then choose Read selected text.');
        }
        if (action === 'pause') b.setAttribute('aria-pressed', speech.pause() ? 'true' : 'false');
        if (action === 'stop') speech.stop();
      });
    }
  }
  window.addEventListener('pagehide', function () { speech.stop(); });

  /* Read text I click: reads a paragraph, heading, list item or table cell when it is clicked or focused with Enter. */
  var clickReadOn = false;
  function clickReadHandler(event) {
    if (event.target.closest('.sw-panel, .sw-launchers, input, textarea, select')) return;
    var node = event.target.closest('p, li, h1, h2, h3, h4, h5, h6, td, th, dt, dd, blockquote, figcaption, label, a, button, .meta');
    if (node) speech.readNode(node);
  }
  function clickRead(on) {
    if (on === clickReadOn || !speech.supported) return;
    clickReadOn = on;
    if (on) document.addEventListener('click', clickReadHandler, true);
    else document.removeEventListener('click', clickReadHandler, true);
  }

  sync();

  /* ---------------------------------------------------------------- */
  /* AI chat helper                                                    */
  /* ---------------------------------------------------------------- */
  var chat = document.querySelector('[data-chat]');
  if (!chat || !chat.hasAttribute('data-endpoint')) return;

  var log = chat.querySelector('[data-chat-log]');
  var form = chat.querySelector('[data-chat-form]');
  var input = chat.querySelector('[data-chat-input]');
  var send = chat.querySelector('[data-chat-send]');
  var status = chat.querySelector('[data-chat-status]');
  var suggestions = chat.querySelector('[data-chat-suggestions]');
  var autoread = chat.querySelector('[data-chat-autoread]');
  var maxHistory = parseInt(chat.getAttribute('data-history'), 10) || 8;
  var storeKey = 'nsp-chat-' + chat.getAttribute('data-user');
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var history = [];
  var busy = false;

  try {
    // A chat from another account in this tab (sign out, sign in as someone else) is never shown.
    Object.keys(window.sessionStorage).forEach(function (key) { if (key.indexOf('nsp-chat-') === 0 && key !== storeKey) window.sessionStorage.removeItem(key); });
    history = JSON.parse(window.sessionStorage.getItem(storeKey) || '[]');
    if (!Array.isArray(history)) history = [];
  } catch (e) { history = []; }

  function persist() {
    try { window.sessionStorage.setItem(storeKey, JSON.stringify(history.slice(-40))); } catch (e) { /* storage blocked */ }
  }
  function scrollLog() { if (log) log.scrollTop = log.scrollHeight; }
  function setStatus(text) { status.textContent = text || ''; }

  function render(message) {
    var item = document.createElement('div');
    item.className = 'sw-chat-msg is-' + message.role + (message.error ? ' is-error' : '');
    var who = document.createElement('span');
    who.className = 'visually-hidden';
    who.textContent = message.role === 'user' ? 'You said: ' : (message.error ? 'Problem: ' : 'Assistant: ');
    var body = document.createElement('div');
    body.className = 'sw-chat-text';
    body.textContent = message.text;
    item.appendChild(who);
    item.appendChild(body);
    if (message.role === 'assistant' && !message.error) {
      var actions = document.createElement('div');
      actions.className = 'sw-chat-actions';
      if (speech.supported) actions.appendChild(actionButton('fa-volume-high', 'Read aloud', function () { speech.speak(message.text, item); }));
      if (navigator.clipboard) actions.appendChild(actionButton('fa-copy', 'Copy', function (btn) {
        navigator.clipboard.writeText(message.text).then(function () { btn.querySelector('span').textContent = 'Copied'; setTimeout(function () { btn.querySelector('span').textContent = 'Copy'; }, 1500); });
      }));
      var note = document.createElement('span');
      note.className = 'sw-chat-note';
      note.textContent = 'AI-generated · check important details';
      actions.appendChild(note);
      item.appendChild(actions);
    }
    log.appendChild(item);
    scrollLog();
    return item;
  }
  function actionButton(icon, label, handler) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'sw-chat-action';
    b.innerHTML = '<i class="fas ' + icon + '" aria-hidden="true"></i> ';
    var span = document.createElement('span');
    span.textContent = label;
    b.appendChild(span);
    b.addEventListener('click', function () { handler(b); });
    return b;
  }
  function refreshSuggestions() { if (suggestions) suggestions.hidden = history.length > 0; }

  history.forEach(render);
  refreshSuggestions();

  if (autoread) {
    if (!speech.supported) autoread.hidden = true;
    autoread.setAttribute('aria-pressed', prefs().chatread ? 'true' : 'false');
    autoread.addEventListener('click', function () {
      var p = prefs();
      p.chatread = !p.chatread;
      save(p);
      autoread.setAttribute('aria-pressed', p.chatread ? 'true' : 'false');
      setStatus(p.chatread ? 'Answers will be read aloud.' : 'Answers will not be read aloud.');
      if (!p.chatread) speech.stop();
    });
  }

  function post(url, payload) {
    return window.fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload)
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) { return { status: response.status, data: data }; });
    });
  }
  function problem(code, data) {
    if (code === 419) return 'Your session has expired. Reload the page and try again.';
    if (code === 429) return 'You are sending messages too quickly. Wait a minute and try again.';
    if (code === 404) return 'AI chat has been switched off. Reload the page.';
    if (code === 401) return 'Sign in again to use AI chat.';
    return (data && data.message) || 'The assistant could not answer just now. Try again in a moment.';
  }

  function ask(text) {
    text = (text || '').trim();
    if (!text || busy) return;
    busy = true;
    send.disabled = true;
    input.setAttribute('aria-busy', 'true');
    var earlier = history.filter(function (m) { return !m.error; }).slice(-maxHistory).map(function (m) { return { role: m.role, text: m.text }; });
    var mine = { role: 'user', text: text };
    history.push(mine);
    persist();
    render(mine);
    refreshSuggestions();
    input.value = '';
    setStatus('The assistant is writing an answer…');
    var typing = document.createElement('div');
    typing.className = 'sw-chat-msg is-assistant is-typing';
    typing.setAttribute('aria-hidden', 'true');
    typing.innerHTML = '<span></span><span></span><span></span>';
    log.appendChild(typing);
    scrollLog();

    post(chat.getAttribute('data-endpoint'), { message: text, history: earlier, page: document.title })
      .then(function (res) {
        var ok = res.status === 200 && res.data && res.data.text;
        var reply = ok
          ? { role: 'assistant', text: res.data.text }
          : { role: 'assistant', error: true, text: res.status === 200 ? (res.data.message || 'The assistant could not answer just now.') : problem(res.status, res.data) };
        if (!ok && res.status === 422 && /turn on ai chat/i.test(reply.text)) showConsent(true);
        return reply;
      }, function () {
        return { role: 'assistant', error: true, text: 'The assistant could not be reached. Check your connection and try again.' };
      })
      .then(function (reply) {
        typing.remove();
        if (!reply.error) { history.push(reply); persist(); }
        render(reply);
        setStatus(reply.error ? '' : 'Answer received.');
        if (!reply.error && prefs().chatread) speech.speak(reply.text);
      })
      .then(function () {
        busy = false;
        send.disabled = false;
        input.removeAttribute('aria-busy');
        input.focus();
      });
  }

  form.addEventListener('submit', function (event) { event.preventDefault(); ask(input.value); });
  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); ask(input.value); }
  });
  chat.addEventListener('click', function (event) {
    var suggestion = event.target.closest('[data-chat-suggest]');
    if (suggestion) ask(suggestion.textContent);
    if (event.target.closest('[data-chat-clear]')) {
      speech.stop();
      history = [];
      persist();
      qsa(log, '.sw-chat-msg:not(.sw-chat-welcome)').forEach(function (n) { n.remove(); });
      refreshSuggestions();
      setStatus('Conversation cleared.');
      input.focus();
    }
    if (event.target.closest('[data-chat-grant]')) consent(true);
    if (event.target.closest('[data-chat-revoke]')) consent(false);
  });

  function showConsent(needed) {
    chat.querySelector('[data-chat-consent]').hidden = !needed;
    chat.querySelector('[data-chat-main]').hidden = needed;
    var clear = chat.querySelector('[data-chat-clear]');
    if (clear) clear.hidden = needed;
    if (autoread) autoread.hidden = needed || !speech.supported;
  }
  function consent(grant) {
    post(chat.getAttribute('data-consent-endpoint'), { grant: grant ? 1 : 0 }).then(function (res) {
      if (res.status !== 200) { setStatus(problem(res.status, res.data)); return; }
      if (!grant) {
        history = [];
        persist();
        qsa(log, '.sw-chat-msg:not(.sw-chat-welcome)').forEach(function (n) { n.remove(); });
        refreshSuggestions();
      }
      showConsent(!grant);
      var target = grant ? input : chat.querySelector('[data-chat-grant]');
      if (target) target.focus();
    }, function () { setStatus('Could not save the setting. Check your connection and try again.'); });
  }
  showConsent(!chat.querySelector('[data-chat-consent]').hidden);
})();
