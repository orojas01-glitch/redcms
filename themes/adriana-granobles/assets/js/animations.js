(function () {
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const revealItems = document.querySelectorAll('[data-reveal]');

  function addHeroMusicOverlay() {
    const notes = [
      { symbol: '♪', x: 238, y: 130, delay: '1.2s', duration: '9.4s', size: 76, rotate: '-10deg' },
      { symbol: '♩', x: 430, y: 154, delay: '2.8s', duration: '8.8s', size: 88, rotate: '7deg' },
      { symbol: '♫', x: 690, y: 184, delay: '1.9s', duration: '10.2s', size: 104, rotate: '-6deg' },
      { symbol: '♪', x: 870, y: 126, delay: '3.7s', duration: '9.1s', size: 86, rotate: '9deg' },
      { symbol: '♬', x: 1112, y: 86, delay: '2.3s', duration: '10.8s', size: 116, rotate: '-3deg' },
      { symbol: '♫', x: 1300, y: 116, delay: '4.5s', duration: '9.8s', size: 100, rotate: '8deg' },
      { symbol: '♪', x: 1480, y: 152, delay: '5.4s', duration: '8.9s', size: 80, rotate: '-8deg' }
    ];

    const staffPath = (offset) => {
      const y = (value) => value + offset;
      return `M -90 ${y(136)} C 90 ${y(70)}, 220 ${y(96)}, 348 ${y(126)} C 470 ${y(155)}, 540 ${y(218)}, 676 ${y(184)} C 820 ${y(148)}, 878 ${y(58)}, 1048 ${y(62)} C 1224 ${y(68)}, 1292 ${y(150)}, 1450 ${y(148)} C 1544 ${y(146)}, 1602 ${y(112)}, 1690 ${y(122)}`;
    };

    const lines = [-28, -14, 0, 14, 28].map((offset, index) => (
      `<path class="hero-music-overlay__line" pathLength="1" d="${staffPath(offset)}" style="--line-delay: ${index * 0.09}s"></path>`
    )).join('');

    const bars = [
      { x: 336, y1: 100, y2: 164, delay: '1.65s' },
      { x: 610, y1: 142, y2: 210, delay: '2.2s' },
      { x: 1016, y1: 34, y2: 96, delay: '2.75s' },
      { x: 1370, y1: 110, y2: 178, delay: '3.4s' }
    ].map((bar) => (
      `<line class="hero-music-bar" x1="${bar.x}" y1="${bar.y1}" x2="${bar.x}" y2="${bar.y2}" style="--bar-delay: ${bar.delay}"></line>`
    )).join('');

    document.querySelectorAll('.hero').forEach((hero, heroIndex) => {
      if (hero.querySelector('.hero-music-overlay')) return;

      const overlay = document.createElement('div');
      overlay.className = 'hero-music-overlay';
      overlay.setAttribute('aria-hidden', 'true');
      overlay.style.setProperty('--hero-music-delay', `${heroIndex * 0.18}s`);

      const noteMarkup = notes.map((note) => (
        `<text class="hero-music-note" x="${note.x}" y="${note.y}" style="--note-delay: ${note.delay}; --note-duration: ${note.duration}; --note-size: ${note.size}px; --note-rotate: ${note.rotate};">${note.symbol}</text>`
      )).join('');

      overlay.innerHTML = `
        <svg class="hero-music-overlay__staff" viewBox="0 0 1600 260" preserveAspectRatio="xMidYMid meet" focusable="false">
          <g class="hero-music-overlay__lines">${lines}</g>
          <path class="hero-music-overlay__glow" pathLength="1" d="${staffPath(0)}"></path>
          <text class="hero-music-clef" x="112" y="162">𝄞</text>
          <g class="hero-music-bars">${bars}</g>
          <g class="hero-music-notes">${noteMarkup}</g>
        </svg>
      `;

      hero.appendChild(overlay);
    });
  }

  if (!prefersReduced) {
    addHeroMusicOverlay();
  }

  if (prefersReduced || !('IntersectionObserver' in window)) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
    return;
  }

  const groupCounts = new WeakMap();

  revealItems.forEach((item) => {
    const parent = item.parentElement || document.body;
    const index = groupCounts.get(parent) || 0;
    const revealIndex = Math.min(index, 3);
    item.style.setProperty('--reveal-index', String(revealIndex));
    item.style.setProperty('--reveal-delay', `${revealIndex * 70}ms`);
    item.style.setProperty('--route-reveal-delay', `${revealIndex * 180}ms`);
    groupCounts.set(parent, index + 1);
  });

  document.documentElement.classList.add('motion-ready');

  function revealItem(item) {
    item.classList.add('is-visible');
    observer.unobserve(item);
  }

  function revealPassedItems() {
    revealItems.forEach((item) => {
      if (item.classList.contains('is-visible')) return;
      const rect = item.getBoundingClientRect();
      if (rect.top < window.innerHeight * 0.92) {
        revealItem(item);
      }
    });
  }

  const observer = new IntersectionObserver((entries) => {
    let hasVisibleEntry = false;

    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      hasVisibleEntry = true;
      revealItem(entry.target);
    });

    if (hasVisibleEntry) {
      window.requestAnimationFrame(revealPassedItems);
    }
  }, { rootMargin: '0px 0px 8% 0px', threshold: 0.08 });

  revealItems.forEach((item) => observer.observe(item));
  window.requestAnimationFrame(revealPassedItems);
})();
