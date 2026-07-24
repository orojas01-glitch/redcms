(function () {
  const header = document.querySelector('[data-site-header]');
  const hasAdminOverlay = document.body.classList.contains('red-standard-theme--with-admin');
  const toggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('[data-site-nav]');
  const navGroups = Array.from(document.querySelectorAll('[data-nav-group]'));

  function closeNavGroups(exceptGroup) {
    navGroups.forEach((group) => {
      if (group === exceptGroup) return;
      group.classList.remove('is-open');
      const button = group.querySelector('[data-dropdown-toggle]');
      if (button) button.setAttribute('aria-expanded', 'false');
    });
  }

  function setHeaderState() {
    if (!header) return;
    header.classList.toggle('is-scrolled', !hasAdminOverlay && window.scrollY > 12);
  }

  setHeaderState();
  window.addEventListener('scroll', setHeaderState, { passive: true });

  function initScrollToTopButton() {
    if (document.querySelector('[data-scroll-top]')) return;

    const button = document.createElement('button');
    button.className = 'scroll-to-top';
    button.type = 'button';
    button.setAttribute('aria-label', 'Ir arriba');
    button.setAttribute('data-scroll-top', '');
    button.innerHTML = `
      <span class="scroll-to-top__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false">
          <path d="M7 14l5-5 5 5"></path>
          <path d="M7 19l5-5 5 5"></path>
        </svg>
      </span>
      <span>Ir arriba</span>
    `;

    function setScrollButtonState() {
      button.classList.toggle('is-visible', window.scrollY > 420);
    }

    button.addEventListener('click', () => {
      const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({
        top: 0,
        behavior: prefersReducedMotion ? 'auto' : 'smooth'
      });
    });

    document.body.appendChild(button);
    setScrollButtonState();
    window.addEventListener('scroll', setScrollButtonState, { passive: true });
  }

  initScrollToTopButton();

  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!open));
      nav.classList.toggle('is-open', !open);
      document.body.classList.toggle('is-menu-open', !open);
      if (open) closeNavGroups();
    });

    nav.addEventListener('click', (event) => {
      if (!event.target.closest('a')) return;
      toggle.setAttribute('aria-expanded', 'false');
      nav.classList.remove('is-open');
      document.body.classList.remove('is-menu-open');
      closeNavGroups();
    });
  }

  navGroups.forEach((group) => {
    const button = group.querySelector('[data-dropdown-toggle]');
    if (!button) return;

    button.addEventListener('click', (event) => {
      event.preventDefault();
      const open = button.getAttribute('aria-expanded') === 'true';
      closeNavGroups(group);
      button.setAttribute('aria-expanded', String(!open));
      group.classList.toggle('is-open', !open);
    });
  });

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-nav-group]')) return;
    closeNavGroups();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    closeNavGroups();
  });

  document.querySelectorAll('[data-tabs]').forEach((tabs) => {
    const buttons = Array.from(tabs.querySelectorAll('[role="tab"]'));
    const panels = Array.from(tabs.querySelectorAll('[role="tabpanel"]'));

    function activateTab(controlId, scrollToPanel) {
      const button = buttons.find((item) => item.getAttribute('aria-controls') === controlId);
      const targetPanel = panels.find((panel) => panel.id === controlId);

      if (!button || !targetPanel) return false;

      buttons.forEach((item) => item.setAttribute('aria-selected', String(item === button)));
      panels.forEach((panel) => {
        panel.hidden = panel !== targetPanel;
      });

      if (scrollToPanel) {
        window.requestAnimationFrame(() => {
          targetPanel.scrollIntoView({ block: 'start' });
        });
      }

      return true;
    }

    function activateTabFromHash() {
      const controlId = window.location.hash.slice(1);
      if (!controlId) return;
      activateTab(controlId, true);
    }

    buttons.forEach((button) => {
      button.addEventListener('click', () => {
        activateTab(button.getAttribute('aria-controls'), false);
      });
    });

    activateTabFromHash();
    window.addEventListener('hashchange', activateTabFromHash);
  });

})();
