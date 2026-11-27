/*
 * Nebrix Panel – progressive enhancements. No inline scripts are used
 * anywhere (strict Content Security Policy); behaviour is attached through
 * data attributes.
 */
(function () {
  'use strict';

  // Confirmation dialogs for destructive actions: <form data-confirm="…">
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) { return; }
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
      return;
    }
    // Prevent double submissions.
    if (form.dataset.submitted === '1') {
      event.preventDefault();
      return;
    }
    form.dataset.submitted = '1';
    window.setTimeout(function () { form.dataset.submitted = ''; }, 4000);
  });

  // Theme toggle: remembers the choice in a cookie read by the server.
  document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var root = document.documentElement;
      var current = root.getAttribute('data-theme');
      if (!current || current === 'system') {
        current = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      }
      var next = current === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      document.cookie = 'nx_theme=' + next + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    });
  });

  // Mobile sidebar.
  document.querySelectorAll('[data-sidebar-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) { sidebar.classList.toggle('is-open'); }
    });
  });

  // Copy to clipboard: <button data-copy="#element-id">
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = document.querySelector(button.getAttribute('data-copy'));
      if (!target || !navigator.clipboard) { return; }
      navigator.clipboard.writeText(target.textContent.trim()).then(function () {
        var label = button.textContent;
        button.textContent = button.getAttribute('data-copied') || 'Copied';
        window.setTimeout(function () { button.textContent = label; }, 1500);
      });
    });
  });

  // Reveal secret values: <button data-reveal="#element-id">
  document.querySelectorAll('[data-reveal]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = document.querySelector(button.getAttribute('data-reveal'));
      if (!target) { return; }
      target.classList.toggle('hidden');
    });
  });

  // Auto-submit selects in filter bars: <select data-autosubmit>
  document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () {
      if (select.form) { select.form.submit(); }
    });
  });

  // Hide success flash messages after a few seconds.
  document.querySelectorAll('.alert-success[data-autohide]').forEach(function (alert) {
    window.setTimeout(function () { alert.classList.add('hidden'); }, 6000);
  });
})();
