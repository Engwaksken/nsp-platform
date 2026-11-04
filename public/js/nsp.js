// Hive NSP: small progressive enhancements. Every page works without this file.
(function () {
  'use strict';

  // Mobile navigation toggles (public header and admin sidebar).
  document.querySelectorAll('[data-toggle-target]').forEach(function (button) {
    var target = document.getElementById(button.getAttribute('data-toggle-target'));
    if (!target) return;
    button.addEventListener('click', function () {
      var open = target.classList.toggle('is-open');
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  // Ask before destructive actions.
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
  });

  // Page builder: add, remove and reorder sections.
  var editor = document.getElementById('blocks');
  var template = document.getElementById('block-template');
  if (editor && template) {
    var renumber = function () {
      editor.querySelectorAll('.block-editor').forEach(function (block, index) {
        block.querySelectorAll('[data-name]').forEach(function (field) {
          field.name = 'blocks[' + index + '][' + field.getAttribute('data-name') + ']';
          field.id = 'block-' + index + '-' + field.getAttribute('data-name');
        });
        block.querySelectorAll('[data-for]').forEach(function (label) {
          label.setAttribute('for', 'block-' + index + '-' + label.getAttribute('data-for'));
        });
        var title = block.querySelector('[data-block-number]');
        if (title) title.textContent = 'Section ' + (index + 1);
      });
    };
    var announce = function (text) {
      var live = document.getElementById('blocks-status');
      if (live) live.textContent = text;
    };
    document.getElementById('add-block').addEventListener('click', function () {
      var type = document.getElementById('new-block-type');
      var clone = template.content.firstElementChild.cloneNode(true);
      clone.querySelector('[data-name="type"]').value = type.value;
      clone.querySelector('[data-block-type-label]').textContent = type.options[type.selectedIndex].text;
      editor.appendChild(clone);
      renumber();
      clone.querySelector('[data-name="heading"]').focus();
      announce('Section added.');
    });
    editor.addEventListener('click', function (event) {
      var button = event.target.closest('[data-block-action]');
      if (!button) return;
      var block = button.closest('.block-editor');
      var action = button.getAttribute('data-block-action');
      if (action === 'remove') { block.remove(); announce('Section removed.'); }
      if (action === 'up' && block.previousElementSibling) { editor.insertBefore(block, block.previousElementSibling); announce('Section moved up.'); }
      if (action === 'down' && block.nextElementSibling) { editor.insertBefore(block.nextElementSibling, block); announce('Section moved down.'); }
      renumber();
      button.focus();
    });
    renumber();
  }

  // Suggest a slug from the title while the slug is still untouched.
  var title = document.querySelector('[data-slug-source]');
  var slug = document.querySelector('[data-slug-target]');
  if (title && slug && slug.value === '') {
    var touched = false;
    slug.addEventListener('input', function () { touched = true; });
    title.addEventListener('input', function () {
      if (touched) return;
      slug.value = title.value.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
    });
  }
})();
