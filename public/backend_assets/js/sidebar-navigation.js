/* Submenu headers are controls, not destinations. The theme Menu handles toggling. */
(function () {
  'use strict';
  const sidebar = document.getElementById('layout-menu');
  if (!sidebar) return;
  function syncExpanded() {
    sidebar.querySelectorAll('button.menu-toggle').forEach(function (button) {
      button.setAttribute('aria-expanded', String(button.parentElement.classList.contains('open')));
    });
  }
  sidebar.addEventListener('click', function (event) {
    if (event.target.closest('button.menu-toggle')) event.preventDefault();
  });
  new MutationObserver(syncExpanded).observe(sidebar, {
    subtree: true, attributes: true, attributeFilter: ['class']
  });
  syncExpanded();
}());
