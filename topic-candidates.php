<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>토픽 후보 검수 - hotlink.kr</title>
<style>
  :root {
    --bg: #0f1115;
    --surface: #171a21;
    --surface2: #1f232c;
    --text: #f1f5f9;
    --text2: #94a3b8;
    --border: #2a2f3a;
    --accent: #e82127;
    --ok: #22c55e;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
    min-height: 100vh;
    padding: 24px 16px 60px;
  }
  .wrap { max-width: 720px; margin: 0 auto; }
  h1 { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
  .subtitle { font-size: 13px; color: var(--text2); margin-bottom: 20px; }

  .key-box { display: flex; gap: 8px; margin-bottom: 20px; }
  .key-box input {
    flex: 1; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border);
    background: var(--surface2); color: var(--text); font-size: 13px;
  }
  .key-box button, .btn {
    padding: 10px 16px; border-radius: 8px; border: 1px solid var(--border);
    background: var(--surface2); color: var(--text); font-size: 13px; font-weight: 700; cursor: pointer;
  }
  .key-box button:hover, .btn:hover { border-color: var(--accent); }

  .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
  .candidate-row {
    display: flex; align-items: center; gap: 12px; padding: 14px 16px;
    border-bottom: 1px solid var(--border);
  }
  .candidate-row:last-child { border-bottom: none; }
  .candidate-info { flex: 1; min-width: 0; }
  .candidate-keyword { font-size: 16px; font-weight: 800; }
  .candidate-meta { font-size: 12px; color: var(--text2); margin-top: 2px; }
  .candidate-actions { display: flex; gap: 6px; flex-shrink: 0; }
  .approve-btn { background: rgba(34,197,94,.15); border-color: rgba(34,197,94,.4); color: var(--ok); }
  .approve-btn:hover { border-color: var(--ok); }
  .reject-btn { background: rgba(232,33,39,.1); border-color: rgba(232,33,39,.3); color: var(--accent); }
  .reject-btn:hover { border-color: var(--accent); }

  .empty-state, .loading { padding: 30px 14px; color: var(--text2); font-size: 13px; text-align: center; }
  .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
  .status-msg { font-size: 12px; color: var(--text2); margin-top: 12px; }
</style>
</head>
<body>

<div class="wrap">
  <h1>🔍 토픽 후보 검수</h1>
  <p class="subtitle">최근 게시글에서 자동 추출된 키워드 후보입니다. 승인하면 즉시 토픽 페이지가 생성됩니다.</p>

  <div class="key-box">
    <input type="password" id="apiKey" placeholder="API 키 입력">
    <button onclick="topicCandLoad()">불러오기</button>
    <button onclick="topicCandRunDiscovery()">지금 재분석</button>
  </div>

  <div class="toolbar">
    <span id="countLabel"></span>
  </div>

  <div class="panel">
    <div class="loading" id="loadingMsg">API 키를 입력하고 불러오기를 눌러주세요.</div>
    <div id="candidateList"></div>
  </div>

  <div class="status-msg" id="statusMsg"></div>
</div>

<script>
(function () {
  var savedKey = localStorage.getItem('topicCandKey');
  if (savedKey) document.getElementById('apiKey').value = savedKey;

  function getKey() {
    var key = document.getElementById('apiKey').value.trim();
    if (key) localStorage.setItem('topicCandKey', key);
    return key;
  }

  function setStatus(msg) {
    var el = document.getElementById('statusMsg');
    el.textContent = msg;
    clearTimeout(setStatus._t);
    setStatus._t = setTimeout(function () { el.textContent = ''; }, 5000);
  }

  function render(candidates) {
    var list = document.getElementById('candidateList');
    var countLabel = document.getElementById('countLabel');
    document.getElementById('loadingMsg').style.display = 'none';

    countLabel.textContent = candidates.length + '개 후보';

    if (!candidates.length) {
      list.innerHTML = '<div class="empty-state">검수 대기 중인 후보가 없습니다.</div>';
      return;
    }

    list.innerHTML = candidates.map(function (c) {
      return '<div class="candidate-row" data-keyword="' + encodeURIComponent(c.keyword) + '">' +
        '<div class="candidate-info">' +
          '<div class="candidate-keyword">' + c.keyword + '</div>' +
          '<div class="candidate-meta">글 ' + c.post_count + '개 · 커뮤니티 ' + c.community_count + '개 · 점수 ' + c.score + '</div>' +
        '</div>' +
        '<div class="candidate-actions">' +
          '<button class="btn approve-btn" onclick="topicCandAction(this, \'approve\')">✓ 승인</button>' +
          '<button class="btn reject-btn" onclick="topicCandAction(this, \'reject\')">✕ 거절</button>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  window.topicCandLoad = function () {
    var key = getKey();
    if (!key) { setStatus('API 키를 입력하세요'); return; }
    document.getElementById('loadingMsg').style.display = 'block';
    document.getElementById('loadingMsg').textContent = '불러오는 중...';
    document.getElementById('candidateList').innerHTML = '';

    fetch('/scripts/api/topic_candidates_api.php', { headers: { 'X-API-Key': key } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) { setStatus('오류: ' + (data.error || '알 수 없음')); return; }
        render(data.candidates || []);
      })
      .catch(function () { setStatus('불러오기 실패'); });
  };

  window.topicCandRunDiscovery = function () {
    var key = getKey();
    if (!key) { setStatus('API 키를 입력하세요'); return; }
    setStatus('재분석 중... (몇 초 걸릴 수 있어요)');

    fetch('/scripts/discover_topics.php?key=' + encodeURIComponent(key))
      .then(function (r) { return r.text(); })
      .then(function () {
        setStatus('재분석 완료, 목록을 새로고침합니다');
        topicCandLoad();
      })
      .catch(function () { setStatus('재분석 실패'); });
  };

  window.topicCandAction = function (btn, action) {
    var key = getKey();
    var row = btn.closest('.candidate-row');
    var keyword = decodeURIComponent(row.getAttribute('data-keyword'));

    btn.disabled = true;
    var actionsDiv = row.querySelector('.candidate-actions');
    Array.prototype.forEach.call(actionsDiv.querySelectorAll('button'), function (b) { b.disabled = true; });

    fetch('/scripts/api/topic_candidates_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-API-Key': key },
      body: JSON.stringify({ action: action, keyword: keyword })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          setStatus(keyword + ': ' + (data.error || '실패'));
          Array.prototype.forEach.call(actionsDiv.querySelectorAll('button'), function (b) { b.disabled = false; });
          return;
        }
        setStatus(keyword + (action === 'approve' ? ' 승인, 페이지 생성됨' : ' 거절함'));
        row.remove();
      })
      .catch(function () { setStatus('요청 실패'); });
  };
})();
</script>
</body>
</html>
