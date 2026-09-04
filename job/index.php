<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>프리랜서 모아 - 핫링크</title>
<style>
  :root {
    --bg: #f4f4f5;
    --surface: #ffffff;
    --surface2: #f0f0f0;
    --border: #e4e4e7;
    --text: #18181b;
    --text2: #71717a;
    --text3: #a1a1aa;
    --accent: #4f9cf9;
    --green: #3faf42;
    --red: #e84040;
    --orange: #f59e0b;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: -apple-system, 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif; font-size: 14px; min-height: 100vh; }

  /* header */
  .header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 20px; display: flex; align-items: center; gap: 10px; position: sticky; top: 0; z-index: 10; }
  .header-back { color: var(--text2); text-decoration: none; font-size: 13px; }
  .header-back:hover { color: var(--text); }
  .header h1 { font-size: 16px; font-weight: 700; }
  .header-sub { font-size: 12px; color: var(--text2); margin-left: auto; }

  /* tabs */
  .tabs { display: flex; gap: 6px; padding: 14px 16px 0; overflow-x: auto; }
  .tab { padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border); background: transparent; color: var(--text2); font-size: 13px; cursor: pointer; white-space: nowrap; transition: all .15s; }
  .tab:hover { border-color: var(--accent); color: var(--accent); }
  .tab.active { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }

  /* filter */
  .filters { display: flex; gap: 6px; padding: 10px 16px; align-items: center; flex-wrap: wrap; }
  .filter-label { font-size: 12px; color: var(--text3); margin-right: 2px; }
  .filter-btn { padding: 4px 10px; border-radius: 12px; border: 1px solid var(--border); background: transparent; color: var(--text2); font-size: 12px; cursor: pointer; transition: all .15s; }
  .filter-btn:hover { border-color: #666; color: var(--text); }
  .filter-btn.active { background: var(--surface2); border-color: #555; color: var(--text); font-weight: 600; }
  .fav-header-btn { margin-left: auto; padding: 5px 12px; border-radius: 14px; border: 1px solid var(--border); background: transparent; color: var(--text2); font-size: 13px; cursor: pointer; transition: all .15s; white-space: nowrap; }
  .fav-header-btn:hover { border-color: var(--orange); color: var(--orange); }
  .fav-header-btn.active { background: rgba(245,158,11,.15); border-color: var(--orange); color: var(--orange); font-weight: 600; }

  /* content */
  .content { padding: 8px 16px 40px; }

  /* section */
  .section { margin-bottom: 28px; }
  .section-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
  .section-icon { font-size: 14px; }
  .section-title { font-size: 15px; font-weight: 700; }
  .section-count { font-size: 12px; color: var(--text3); }
  .section-link { margin-left: auto; font-size: 12px; color: var(--text3); text-decoration: none; }
  .section-link:hover { color: var(--accent); }

  /* card */
  .card-wrap { position: relative; margin-bottom: 8px; }
  .card-wrap.visited { opacity: .55; transition: opacity .15s; }
  .card-wrap.visited:hover { opacity: .85; }
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px 42px 14px 14px; cursor: pointer; transition: border-color .15s, background .15s; text-decoration: none; display: block; }
  .card:hover { border-color: var(--accent); background: var(--surface2); }
  .card-badges { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
  .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; flex-shrink: 0; }
  .badge-active { background: rgba(63,175,66,.18); color: var(--green); border: 1px solid rgba(63,175,66,.3); }
  .badge-closed { background: rgba(136,136,136,.15); color: var(--text3); border: 1px solid var(--border); }
  .badge-cat { background: rgba(79,156,249,.12); color: var(--accent); border: 1px solid rgba(79,156,249,.25); }
  .card-title { font-size: 14px; font-weight: 600; line-height: 1.45; color: var(--text); }
  .card-newtab { display: inline-flex; align-items: center; justify-content: center; vertical-align: -2px; margin-left: 5px; color: var(--text3); transition: color .15s; }
  .card-newtab:hover { color: var(--accent); }
  .card-meta { display: flex; flex-wrap: wrap; gap: 10px; font-size: 12px; color: var(--text2); margin-top: 8px; }
  .meta-item { display: flex; align-items: center; gap: 3px; }
  .meta-icon { font-size: 11px; }

  /* favorite button */
  .fav-btn { position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text3); padding: 4px 6px; line-height: 1; z-index: 1; transition: color .15s, transform .1s; }
  .fav-btn:hover { color: var(--orange); transform: scale(1.2); }
  .fav-btn.active { color: var(--orange); }

  /* empty / loading */
  .empty { text-align: center; color: var(--text3); padding: 40px 0; font-size: 14px; }
  .loading { text-align: center; color: var(--text3); padding: 60px 0; }
  .spinner { display: inline-block; width: 24px; height: 24px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin .7s linear infinite; margin-bottom: 10px; }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* updated at */
  .updated-at { text-align: center; font-size: 11px; color: var(--text3); padding: 6px 0 0; }
</style>
</head>
<body>

<div class="header">
  <h1><a href="/job" style="text-decoration:none;color:inherit;">💼 프리랜서 모아</a></h1>
  <button class="fav-header-btn" id="favFilterBtn">☆ 즐겨찾기</button>
</div>

<div class="tabs" id="tabsEl">
  <button class="tab active" data-site="all">전체</button>
</div>

<div class="filters">
  <span class="filter-label">상태</span>
  <button class="filter-btn" data-status="all">전체</button>
  <button class="filter-btn active" data-status="접수중">접수중</button>
  <button class="filter-btn" data-status="마감">마감</button>
</div>

<div class="content" id="contentEl">
  <div class="loading"><div class="spinner"></div><br>불러오는 중...</div>
</div>

<script>
(function () {
  var allData = {};
  var allSites = [];
  var currentSite = 'all';
  var currentStatus = '접수중';
  var favOnly = false;
  var favorites = JSON.parse(localStorage.getItem('jobFavorites') || '[]');
  var visited = JSON.parse(localStorage.getItem('jobVisited') || '[]');
  var NEWTAB_ICON = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>';

  function fetchJobs() {
    fetch('/api/jobs.php')
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) throw new Error(res.error);
        allData = res.data;
        allSites = res.sites;
        buildTabs();
        render();
      })
      .catch(function (e) {
        document.getElementById('contentEl').innerHTML =
          '<div class="empty">데이터를 불러오지 못했습니다.<br><small>' + e.message + '</small></div>';
      });
  }

  function buildTabs() {
    var tabsEl = document.getElementById('tabsEl');
    allSites.forEach(function (s) {
      var btn = document.createElement('button');
      btn.className = 'tab';
      btn.dataset.site = s.code;
      btn.textContent = s.name;
      btn.addEventListener('click', function () { switchSite(s.code); });
      tabsEl.appendChild(btn);
    });
    tabsEl.querySelector('[data-site="all"]').addEventListener('click', function () { switchSite('all'); });
  }

  function switchSite(code) {
    currentSite = code;
    document.querySelectorAll('.tab').forEach(function (t) {
      t.classList.toggle('active', t.dataset.site === code);
    });
    render();
  }

  function switchStatus(status) {
    currentStatus = status;
    document.querySelectorAll('.filter-btn[data-status]').forEach(function (b) {
      b.classList.toggle('active', b.dataset.status === status);
    });
    render();
  }

  function isFav(url) {
    return favorites.indexOf(url) !== -1;
  }

  function isVisited(url) {
    return visited.indexOf(url) !== -1;
  }

  function markVisited(url) {
    if (!url || isVisited(url)) return;
    visited.push(url);
    localStorage.setItem('jobVisited', JSON.stringify(visited));
  }

  function buildCard(job, code) {
    var displayStatus = normalizeStatus(job, code);
    var isActive = displayStatus.indexOf('접수') !== -1;
    var fav = isFav(job.url);
    var seen = isVisited(job.url);
    var h = '';
    h += '<div class="card-wrap' + (seen ? ' visited' : '') + '">';
    h += '<div class="card" data-url="' + esc(job.url) + '">';
    h += '<div class="card-badges">';
    h += '<span class="badge ' + (isActive ? 'badge-active' : 'badge-closed') + '">' + esc(displayStatus || '-') + '</span>';
    var siteName = allData[code] && allData[code].site_name;
    if (siteName) h += '<span class="badge badge-cat">' + esc(siteName) + '</span>';
    h += '</div>';
    h += '<div class="card-title">' + esc(job.title);
    h += '<a class="card-newtab" href="' + esc(job.url) + '" target="_blank" rel="noopener" title="새 창으로 열기" aria-label="새 창으로 열기">' + NEWTAB_ICON + '</a>';
    h += '</div>';
    var metas = [];
    if (job.period)     metas.push({ icon: '📅', text: formatPeriod(job.period) });
    if (job.deadline)   metas.push({ icon: '⏰', text: job.deadline });
    if (job.budget)     metas.push({ icon: '💰', text: job.budget });
    if (job.experience) metas.push({ icon: '👤', text: job.experience });
    if (job.location)   metas.push({ icon: '📍', text: job.location });
    if (metas.length) {
      h += '<div class="card-meta">';
      metas.forEach(function (m) {
        h += '<span class="meta-item"><span class="meta-icon">' + m.icon + '</span>' + esc(m.text) + '</span>';
      });
      h += '</div>';
    }
    h += '</div>';
    h += '<button class="fav-btn' + (fav ? ' active' : '') + '" data-url="' + esc(job.url) + '" title="즐겨찾기">' + (fav ? '★' : '☆') + '</button>';
    h += '</div>';
    return h;
  }

  function render() {
    var contentEl = document.getElementById('contentEl');
    var html = '';

    if (favOnly || currentSite === 'all') {
      // 전체/즐겨찾기: 플랫 리스트, crawledAt 최신순 정렬
      var flatJobs = [];
      Object.keys(allData).forEach(function (code) {
        allData[code].jobs.forEach(function (j) {
          if (favOnly && !isFav(j.url)) return;
          if (currentStatus !== 'all' && normalizeStatus(j, code).indexOf(currentStatus) === -1) return;
          flatJobs.push({ job: j, code: code });
        });
      });
      flatJobs.sort(function (a, b) {
        return (b.job.crawledAt || '').localeCompare(a.job.crawledAt || '');
      });
      flatJobs.forEach(function (item) { html += buildCard(item.job, item.code); });

    } else {
      // 특정 사이트: 섹션 헤더 + 목록
      var group = allData[currentSite];
      if (group) {
        var jobs = group.jobs.filter(function (j) {
          if (currentStatus === 'all') return true;
          return normalizeStatus(j, currentSite).indexOf(currentStatus) !== -1;
        });
        if (jobs.length > 0) {
          html += '<div class="section">';
          html += '<div class="section-header">';
          html += '<span class="section-icon">📌</span>';
          html += '<span class="section-title">' + esc(group.site_name) + '</span>';
          html += '<span class="section-count">' + jobs.length + '개</span>';
          if (group.site_url) html += '<a class="section-link" href="' + esc(group.site_url) + '">사이트 →</a>';
          html += '</div>';
          jobs.forEach(function (job) { html += buildCard(job, currentSite); });
          html += '</div>';
        }
      }
    }

    contentEl.innerHTML = html || '<div class="empty">표시할 공고가 없습니다.</div>';
  }

  // 기간 포맷 변환
  // "YYYY-MM-DD ~ YYYY-MM-DD" → "YYYY.MM ~ YYYY.MM (N개월)"
  // "MM-DD~MM-DD"             → "YYYY.MM ~ YYYY.MM (N개월)"
  function formatPeriod(period) {
    if (!period) return '';
    period = period.trim();

    var m1 = period.match(/^(\d{4})-(\d{2})-\d{2}\s*~\s*(\d{4})-(\d{2})-\d{2}/);
    if (m1) {
      var sy = +m1[1], sm = +m1[2], ey = +m1[3], em = +m1[4];
      var months = (ey - sy) * 12 + (em - sm) + 1;
      return sy + '.' + p2(sm) + ' ~ ' + ey + '.' + p2(em) + ' (' + months + '개월)';
    }

    var m2 = period.match(/^(\d{2})-(\d{2})\s*~\s*(\d{2})-(\d{2})/);
    if (m2) {
      var sy = new Date().getFullYear(), sm = +m2[1], em = +m2[3];
      if (em === 0) return sy + '.' + p2(sm) + ' ~';
      var ey = em < sm ? sy + 1 : sy;
      var months = (ey - sy) * 12 + (em - sm) + 1;
      return sy + '.' + p2(sm) + ' ~ ' + ey + '.' + p2(em) + ' (' + months + '개월)';
    }

    return period;
  }

  function p2(n) { return n < 10 ? '0' + n : '' + n; }

  // 상태 정규화: 접수완료/마감임박 → 마감, 디자인그룹나인 기간 만료 체크
  function normalizeStatus(job, siteCode) {
    var status = job.status || '';
    if (status === '접수완료' || status === '마감임박') return '마감';

    if (siteCode === 'designnine' && job.period) {
      var endYM = periodEndYearMonth(job.period);
      if (endYM !== null) {
        var now = new Date();
        var curYM = now.getFullYear() * 100 + (now.getMonth() + 1);
        if (endYM < curYM) return '마감';
      }
    }
    return status;
  }

  // period 문자열에서 종료 년월을 YYYYMM 숫자로 추출
  function periodEndYearMonth(period) {
    period = (period || '').trim();

    // "YYYY-MM-DD ~ YYYY-MM-DD"
    var m1 = period.match(/~\s*(\d{4})-(\d{2})-\d{2}/);
    if (m1) return +m1[1] * 100 + +m1[2];

    // "MM-DD~MM-DD"
    var m2 = period.match(/^(\d{2})-\d{2}\s*~\s*(\d{2})-\d{2}/);
    if (m2) {
      var sy = new Date().getFullYear(), sm = +m2[1], em = +m2[2];
      var ey = em < sm ? sy + 1 : sy;
      return ey * 100 + em;
    }

    // "YYYY.MM ~ YYYY.MM (...)"
    var m3 = period.match(/~\s*(\d{4})\.(\d{2})/);
    if (m3) return +m3[1] * 100 + +m3[2];

    return null;
  }

  function esc(s) {
    if (!s) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // 상태 필터
  document.querySelectorAll('.filter-btn[data-status]').forEach(function (b) {
    b.addEventListener('click', function () { switchStatus(b.dataset.status); });
  });

  // 즐겨찾기 필터 버튼
  var favFilterBtn = document.getElementById('favFilterBtn');
  var tabsEl       = document.getElementById('tabsEl');
  var filtersEl    = document.querySelector('.filters');

  favFilterBtn.addEventListener('click', function () {
    favOnly = !favOnly;
    favFilterBtn.classList.toggle('active', favOnly);
    favFilterBtn.textContent = favOnly ? '★ 즐겨찾기' : '☆ 즐겨찾기';
    tabsEl.style.display    = favOnly ? 'none' : '';
    filtersEl.style.display = favOnly ? 'none' : '';
    render();
  });

  // 즐겨찾기 토글 (이벤트 위임)
  document.getElementById('contentEl').addEventListener('click', function (e) {
    var btn = e.target.closest('.fav-btn');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var url = btn.dataset.url;
    var idx = favorites.indexOf(url);
    if (idx === -1) { favorites.push(url); }
    else { favorites.splice(idx, 1); }
    localStorage.setItem('jobFavorites', JSON.stringify(favorites));
    render();
  });

  // 카드 클릭: 읽음 처리 + 현재 창으로 이동 (새 창 아이콘은 새 탭으로 열리며 별도 처리)
  document.getElementById('contentEl').addEventListener('click', function (e) {
    var newtabLink = e.target.closest('.card-newtab');
    if (newtabLink) {
      markVisited(newtabLink.getAttribute('href'));
      render();
      return;
    }
    if (e.target.closest('.fav-btn')) return;
    var card = e.target.closest('.card');
    if (!card) return;
    markVisited(card.dataset.url);
    window.location.href = card.dataset.url;
  });

  fetchJobs();
})();
</script>
</body>
</html>
