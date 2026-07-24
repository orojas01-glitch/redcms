(function () {
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    const toggle = document.querySelector('[data-menu-toggle]');
    const nav = document.querySelector('[data-site-nav]');

    if (!toggle || !nav || !nav.classList.contains('is-open')) return;

    toggle.setAttribute('aria-expanded', 'false');
    nav.classList.remove('is-open');
    document.body.classList.remove('is-menu-open');
    toggle.focus();
  });
})();
