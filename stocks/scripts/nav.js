(function () {
  const css = `
    .hamburger {
      position: fixed; top: 16px; left: 16px;
      background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px;
      padding: 10px 11px; cursor: pointer; z-index: 100;
      display: flex; flex-direction: column; gap: 4px;
    }
    .hamburger span { display: block; width: 20px; height: 2px; background: #888; border-radius: 2px; transition: background 0.2s; }
    .hamburger:hover span { background: #e8e8e8; }
    .nav-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 150; }
    .nav-backdrop.show { display: block; }
    .nav-overlay {
      position: fixed; top: 0; left: -280px; width: 260px; height: 100vh;
      background: #111; border-right: 1px solid #222; z-index: 200;
      padding: 56px 20px 32px; transition: left 0.25s ease;
    }
    .nav-overlay.open { left: 0; }
    .nav-close { position: absolute; top: 16px; right: 16px; background: none; border: none; color: #555; font-size: 1.2rem; cursor: pointer; line-height: 1; }
    .nav-close:hover { color: #e8e8e8; }
    .nav-section-label { font-size: 0.72rem; color: #444; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 12px; }
    .nav-overlay ul { list-style: none; }
    .nav-overlay li { margin-bottom: 2px; }
    .nav-overlay a { display: block; color: #aaa; text-decoration: none; font-size: 0.92rem; padding: 9px 12px; border-radius: 10px; transition: background 0.15s, color 0.15s; }
    .nav-overlay a:hover { background: #1a1a1a; color: #e8e8e8; }
    .nav-overlay a.active { background: #1a1a1a; color: #e8e8e8; font-weight: 600; }
  `;

  const style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  document.addEventListener('DOMContentLoaded', function () {
    const isEtf = /\/etf/.test(window.location.pathname);

    const wrap = document.createElement('div');
    wrap.innerHTML = `
      <button class="hamburger" id="menuBtn" aria-label="메뉴 열기">
        <span></span><span></span><span></span>
      </button>
      <div class="nav-backdrop" id="navBackdrop"></div>
      <nav class="nav-overlay" id="navOverlay">
        <button class="nav-close" id="navClose" aria-label="메뉴 닫기">✕</button>
        <div class="nav-section-label">계산기 목록</div>
        <ul>
          <li><a href="/stocks"${!isEtf ? ' class="active"' : ''}>🦜 껄무새 계산기</a></li>
          <li><a href="/stocks/etf/"${isEtf ? ' class="active"' : ''}>📈 ETF 적립식 계산기</a></li>
        </ul>
      </nav>
    `;
    document.body.prepend(wrap);

    const menuBtn = document.getElementById('menuBtn');
    const navClose = document.getElementById('navClose');
    const navOverlay = document.getElementById('navOverlay');
    const navBackdrop = document.getElementById('navBackdrop');

    function openNav()  { navOverlay.classList.add('open');    navBackdrop.classList.add('show'); }
    function closeNav() { navOverlay.classList.remove('open'); navBackdrop.classList.remove('show'); }

    menuBtn.addEventListener('click', openNav);
    navClose.addEventListener('click', closeNav);
    navBackdrop.addEventListener('click', closeNav);
  });
})();
