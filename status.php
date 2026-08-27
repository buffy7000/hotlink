<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>크롤러 상태 - hotlink.kr</title>
<style>
  :root {
    --bg: #0f1115;
    --surface: #171a21;
    --surface2: #1f232c;
    --text: #f1f5f9;
    --text2: #94a3b8;
    --border: #2a2f3a;
    --ok: #22c55e;
    --warning: #f59e0b;
    --error: #e82127;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
    min-height: 100vh;
    padding: 24px 16px 60px;
  }
  .wrap { max-width: 900px; margin: 0 auto; }
  .topbar { display: flex; align-items: baseline; gap: 12px; margin-bottom: 6px; flex-wrap: wrap; }
  h1 { font-size: 22px; font-weight: 800; }
  .subtitle { font-size: 13px; color: var(--text2); margin-bottom: 22px; }
  .back-link { font-size: 13px; color: var(--text2); text-decoration: none; margin-left: auto; }
  .back-link:hover { color: var(--error); }

  .summary { display: flex; gap: 8px; flex-wrap: wrap; }
  .summary-chip {
    padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;
    background: var(--surface2); border: 1px solid var(--border);
  }

  .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap; }
  th { color: var(--text2); font-weight: 700; font-size: 12px; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: var(--surface2); }

  .badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;
  }
  .badge.ok { background: rgba(34,197,94,.15); color: var(--ok); }
  .badge.warning { background: rgba(245,158,11,.15); color: var(--warning); }
  .badge.error { background: rgba(232,33,39,.15); color: var(--error); }
  .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }

  .error-msg { color: var(--text2); font-size: 11px; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .muted { color: var(--text2); }

  .footer { margin-top: 16px; font-size: 12px; color: var(--text2); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
  .loading { padding: 40px; text-align: center; color: var(--text2); font-size: 13px; }

  @media (max-width: 640px) {
    table, thead, tbody, th, td, tr { display: block; }
    thead { display: none; }
    tr { border-bottom: 1px solid var(--border); padding: 10px 14px; }
    td { border: none; padding: 4px 0; white-space: normal; }
    td::before { content: attr(data-label); display: inline-block; width: 100px; color: var(--text2); font-size: 11px; }
  }
</style>
</head>
<body>

<div class="wrap">
  <div class="topbar">
    <h1>🩺 크롤러 상태</h1>
    <a class="back-link" href="/">← hotlink.kr</a>
  </div>
  <p class="subtitle">사이트별 크롤링 정상 여부를 확인합니다. 장애 발생 시 Slack으로 자동 알림이 갑니다.</p>

  <div class="panel">
    <div class="loading" id="loadingMsg">불러오는 중...</div>
    <table id="statusTable" style="display:none;">
      <thead>
        <tr>
          <th>사이트</th>
          <th>상태</th>
          <th>마지막 게시글</th>
          <th>경과</th>
          <th>24시간 수집</th>
          <th>최근 로그</th>
        </tr>
      </thead>
      <tbody id="statusBody"></tbody>
    </table>
  </div>

  <div class="footer">
    <span id="checkedAt"></span>
    <span class="summary" id="summary"></span>
  </div>
</div>

<script>
(function () {
  var STATUS_LABEL = { ok: '정상', warning: '지연', error: '장애' };

  function fmtMinutes(mins) {
    if (mins === null || mins === undefined) return '기록 없음';
    if (mins < 0) return '방금 전';
    if (mins < 60) return mins + '분 전';
    var h = Math.floor(mins / 60), m = mins % 60;
    return h + '시간 ' + m + '분 전';
  }

  function render(data) {
    var summary = document.getElementById('summary');
    var counts = { ok: 0, warning: 0, error: 0 };
    data.sites.forEach(function (s) { counts[s.status]++; });
    summary.innerHTML =
      '<span class="summary-chip badge ok">정상 ' + counts.ok + '</span>' +
      '<span class="summary-chip badge warning">지연 ' + counts.warning + '</span>' +
      '<span class="summary-chip badge error">장애 ' + counts.error + '</span>';

    var body = document.getElementById('statusBody');
    body.innerHTML = data.sites.map(function (s) {
      var badge = '<span class="badge ' + s.status + '"><span class="dot"></span>' + STATUS_LABEL[s.status] + '</span>';
      var errMsg = s.last_log_error ? '<div class="error-msg" title="' + s.last_log_error.replace(/"/g, '&quot;') + '">' + s.last_log_error + '</div>' : '<span class="muted">-</span>';
      return '<tr>' +
        '<td data-label="사이트"><b>' + s.label + '</b> <span class="muted">(' + s.name + ')</span></td>' +
        '<td data-label="상태">' + badge + '</td>' +
        '<td data-label="마지막 게시글">' + (s.last_post_at || '-') + '</td>' +
        '<td data-label="경과">' + fmtMinutes(s.minutes_since) + '</td>' +
        '<td data-label="24시간 수집">' + s.posts_24h + '개</td>' +
        '<td data-label="최근 로그">' + errMsg + '</td>' +
        '</tr>';
    }).join('');

    document.getElementById('loadingMsg').style.display = 'none';
    document.getElementById('statusTable').style.display = 'table';
    document.getElementById('checkedAt').textContent = '마지막 확인: ' + data.checkedAt;
  }

  function load() {
    fetch('/crawler/api/status.php')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) render(data);
      })
      .catch(function () {});
  }

  // 매시 10분에 맞춰 자동 갱신 (그 후 1시간 간격 유지)
  function scheduleHourlyRefresh() {
    var now = new Date();
    var next = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours(), 10, 0, 0);
    if (next <= now) next.setHours(next.getHours() + 1);
    var delay = next - now;
    setTimeout(function () {
      load();
      setInterval(load, 60 * 60 * 1000);
    }, delay);
  }

  load();
  scheduleHourlyRefresh();
})();
</script>
</body>
</html>
