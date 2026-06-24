<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>프리랜서 잡 보드 - 핫링크</title>
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
  .filters { display: flex; gap: 6px; padding: 10px 16px; align-items: center; }
  .filter-label { font-size: 12px; color: var(--text3); margin-right: 2px; }
  .filter-btn { padding: 4px 10px; border-radius: 12px; border: 1px solid var(--border); background: transparent; color: var(--text2); font-size: 12px; cursor: pointer; transition: all .15s; }
  .filter-btn:hover { border-color: #666; color: var(--text); }
  .filter-btn.active { background: var(--surface2); border-color: #555; color: var(--text); font-weight: 600; }

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
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px; margin-bottom: 8px; cursor: pointer; transition: border-color .15s, background .15s; text-decoration: none; display: block; }
  .card:hover { border-color: var(--accent); background: var(--surface2); }
  .card-badges { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
  .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; flex-shrink: 0; }
  .badge-active { background: rgba(63,175,66,.18); color: var(--green); border: 1px solid rgba(63,175,66,.3); }
  .badge-closed { background: rgba(136,136,136,.15); color: var(--text3); border: 1px solid var(--border); }
  .badge-cat { background: rgba(79,156,249,.12); color: var(--accent); border: 1px solid rgba(79,156,249,.25); }
  .card-title { font-size: 14px; font-weight: 600; line-height: 1.45; color: var(--text); flex: 1; }
  .card-meta { display: flex; flex-wrap: wrap; gap: 10px; font-size: 12px; color: var(--text2); }
  .meta-item { display: flex; align-items: center; gap: 3px; }
  .meta-icon { font-size: 11px; }
  .card-bottom { margin-top: 10px; display: flex; justify-content: flex-end; }
  .btn-go { padding: 5px 14px; background: var(--accent); border-radius: 6px; color: #fff; font-size: 12px; font-weight: 600; border: none; cursor: pointer; }

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
  <a class="header-back" href="/">← 핫링크</a>
  <h1>💼 프리랜서 잡</h1>
  <span class="header-sub" id="updatedAt"></span>
</div>

<div class="tabs" id="tabsEl">
  <button class="tab active" data-site="all">전체</button>
</div>

<div class="filters">
  <span class="filter-label">상태</span>
  <button class="filter-btn active" data-status="all">전체</button>
  <button class="filter-btn" data-status="접수중">접수중</button>
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
  var currentStatus = 'all';

  function fetchJobs() {
    fetch('/api/jobs.php')
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) throw new Error(res.error);
        allData = res.data;
        allSites = res.sites;
        buildTabs();
        render();
        document.getElementById('updatedAt').textContent = '총 ' + res.total + '개';
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
    document.querySelectorAll('.filter-btn').forEach(function (b) {
      b.classList.toggle('active', b.dataset.status === status);
    });
    render();
  }

  function render() {
    var contentEl = document.getElementById('contentEl');
    var html = '';
    var sitesToRender = currentSite === 'all'
      ? Object.keys(allData)
      : (allData[currentSite] ? [currentSite] : []);

    sitesToRender.forEach(function (code) {
      var group = allData[code];
      var jobs = group.jobs.filter(function (j) {
        if (currentStatus === 'all') return true;
        return (j.status || '').indexOf(currentStatus) !== -1;
      });

      if (jobs.length === 0) return;

      html += '<div class="section">';
      html += '<div class="section-header">';
      html += '<span class="section-icon">📌</span>';
      html += '<span class="section-title">' + esc(group.site_name) + '</span>';
      html += '<span class="section-count">' + jobs.length + '개</span>';
      if (group.site_url) {
        html += '<a class="section-link" href="' + esc(group.site_url) + '">사이트 →</a>';
      }
      html += '</div>';

      jobs.forEach(function (job) {
        var isActive = job.status && job.status.indexOf('접수') !== -1;
        html += '<a class="card" href="' + esc(job.url) + '">';
        html += '<div class="card-badges">';
        html += '<span class="badge ' + (isActive ? 'badge-active' : 'badge-closed') + '">' + esc(job.status || '-') + '</span>';
        if (job.category) html += '<span class="badge badge-cat">' + esc(job.category) + '</span>';
        html += '</div>';
        html += '<div class="card-title">' + esc(job.title) + '</div>';

        var metas = [];
        if (job.period)     metas.push({ icon: '📅', text: job.period });
        if (job.deadline)   metas.push({ icon: '⏰', text: job.deadline });
        if (job.budget)     metas.push({ icon: '💰', text: job.budget });
        if (job.experience) metas.push({ icon: '👤', text: job.experience });
        if (job.location)   metas.push({ icon: '📍', text: job.location });

        if (metas.length) {
          html += '<div class="card-meta">';
          metas.forEach(function (m) {
            html += '<span class="meta-item"><span class="meta-icon">' + m.icon + '</span>' + esc(m.text) + '</span>';
          });
          html += '</div>';
        }
        html += '</a>';
      });

      html += '</div>';
    });

    contentEl.innerHTML = html || '<div class="empty">표시할 공고가 없습니다.</div>';
  }

  function esc(s) {
    if (!s) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  document.querySelectorAll('.filter-btn').forEach(function (b) {
    b.addEventListener('click', function () { switchStatus(b.dataset.status); });
  });

  fetchJobs();
})();
</script>
</body>
</html>
