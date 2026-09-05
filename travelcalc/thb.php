<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>여행 환율 계산기 - 태국 바트(THB) → 원화</title>
<meta name="description" content="태국 바트(THB)를 원화로 환산하는 여행 환율 계산기. 실시간 환율 또는 고정환율을 선택할 수 있습니다.">
<link rel="icon" href="/favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --bg: #f4f6fb;
    --card: #ffffff;
    --border: #e2e8f0;
    --text: #1e293b;
    --muted: #64748b;
    --accent: #059669;
  }

  body {
    font-family: 'Pretendard', 'Apple SD Gothic Neo', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    padding-bottom: 40px;
  }

  .wrap {
    max-width: 480px;
    margin: 0 auto;
    padding: 0 16px;
  }

  header {
    padding: 20px 0 12px;
  }

  header a.home-link {
    font-size: 13px;
    color: var(--muted);
    text-decoration: none;
  }

  header h1 {
    font-size: 21px;
    font-weight: 800;
    margin-top: 6px;
  }

  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    margin-top: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  }

  .converter-card { padding: 10px; }

  .exchange-row {
    position: relative;
  }

  .cur-box {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
  }

  .cur-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    background: #eef1f6;
    padding: 10px 14px;
  }

  .cur-left-btn {
    border: none;
    font: inherit;
    text-align: left;
    cursor: pointer;
  }

  .cur-left .flag { font-size: 24px; line-height: 1; }

  .cur-text .cur-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
    line-height: 1.25;
    display: flex;
    align-items: center;
    gap: 3px;
  }

  .cur-text .cur-name .chevron {
    font-size: 11px;
    color: var(--muted);
  }

  .cur-text .cur-code {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.25;
  }

  .currency-popover {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    min-width: 190px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 10px 28px rgba(0,0,0,0.14);
    padding: 6px;
    z-index: 30;
  }

  .currency-popover.show { display: block; }

  .currency-popover .cp-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
  }

  .currency-popover .cp-item:active { background: #f1f5f9; }
  .currency-popover .cp-item.active { color: var(--primary); }
  .currency-popover .cp-item .flag { font-size: 18px; }
  .currency-popover .cp-item .cp-code {
    font-size: 12px;
    font-weight: 500;
    color: var(--muted);
    margin-left: auto;
  }

  .cur-right {
    text-align: right;
    min-width: 0;
    flex: 1;
    background: #ffffff;
    padding: 10px 14px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .amount-input-line {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
  }

  .cur-right .amount-input {
    width: 100%;
    min-width: 0;
    border: none;
    background: transparent;
    outline: none;
    text-align: right;
    font-size: 24px;
    font-weight: 800;
    font-family: inherit;
    color: var(--text);
    padding: 0;
  }

  .clear-icon-btn {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border: none;
    border-radius: 50%;
    background: #e2e6ee;
    color: var(--muted);
    font-size: 12px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
  }

  .clear-icon-btn:active { background: #cbd2e0; }

  .cur-right .result-main {
    font-size: 24px;
    font-weight: 800;
    color: var(--primary-dark);
    word-break: break-all;
  }

  .cur-right .cur-sub {
    font-size: 12px;
    color: var(--muted);
    margin-top: 1px;
  }

  /* X 아이콘(22px)+간격(6px)만큼 오른쪽을 비워서 숫자 입력란의 우측 끝과 세로로 맞춤 */
  #amountSub {
    padding-right: 28px;
  }

  .equals-divider {
    text-align: center;
    color: #b0b6c2;
    font-size: 15px;
    font-weight: 700;
    line-height: 1;
    padding: 4px 0;
  }

  .quick-add-row {
    display: flex;
    gap: 5px;
    margin-top: 10px;
  }

  .quick-add-btn {
    flex: 1;
    min-width: 0;
    padding: 9px 0;
    border: 1px solid var(--border);
    background: #fff;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 600;
    color: var(--primary);
    cursor: pointer;
    font-family: inherit;
    white-space: nowrap;
    transition: background 0.15s ease;
  }

  .quick-add-btn:active { background: #eff6ff; }

  /* 소형 화면(~380px 이하)에서도 5개 버튼 텍스트가 안 잘리도록 축소 */
  @media (max-width: 380px) {
    .quick-add-row { gap: 3px; }
    .quick-add-btn { font-size: 9.5px; }
  }

  .rate-info {
    font-size: 12.5px;
    color: var(--muted);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
  }

  .rate-info b { color: var(--text); }

  .badge-stale {
    color: #b45309;
    font-weight: 600;
  }

  .fixed-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;
  }

  .fixed-toggle-row label {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
  }

  .switch {
    position: relative;
    width: 40px;
    height: 22px;
    flex-shrink: 0;
  }
  .switch input { opacity: 0; width: 0; height: 0; }
  .slider {
    position: absolute; cursor: pointer; inset: 0;
    background: #cbd5e1; border-radius: 22px; transition: 0.2s;
  }
  .slider::before {
    content: ""; position: absolute; height: 16px; width: 16px;
    left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.2s;
  }
  input:checked + .slider { background: var(--primary); }
  input:checked + .slider::before { transform: translateX(18px); }

  .fixed-rate-input-wrap {
    display: none;
    margin-top: 10px;
    align-items: center;
    gap: 8px;
  }
  .fixed-rate-input-wrap.show { display: flex; }
  .fixed-rate-input-wrap span { font-size: 13px; color: var(--muted); white-space: nowrap; }
  .fixed-rate-input-wrap input {
    flex: 1;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
  }

  .recent-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
  }

  .section-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--muted);
  }

  .recent-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
  }
  .recent-item:last-child { border-bottom: none; }
  .recent-item:active { background: #f8fafc; }

  .recent-item .from { font-size: 14px; font-weight: 600; }
  .recent-item .to { font-size: 14px; font-weight: 700; color: var(--primary-dark); }
  .recent-item .time { font-size: 11px; color: var(--muted); margin-top: 2px; }

  .empty-recent {
    font-size: 13px;
    color: var(--muted);
    text-align: center;
    padding: 12px 0;
  }

  .recent-clear {
    background: none;
    border: none;
    font-size: 12px;
    color: var(--muted);
    text-decoration: underline;
    cursor: pointer;
    font-family: inherit;
  }
</style>
</head>
<body>

<div class="wrap">
  <header>
    <a href="/" class="home-link">← 핫링크</a>
    <h1>✈️ 여행 환율 계산기</h1>
  </header>

  <div class="card rate-card">
    <div class="rate-info">
      <span id="rateInfoText">환율 불러오는 중...</span>
    </div>

    <div class="fixed-toggle-row">
      <label>
        <div class="switch">
          <input type="checkbox" id="fixedToggle">
          <span class="slider"></span>
        </div>
        고정환율 사용
      </label>
    </div>

    <div class="fixed-rate-input-wrap" id="fixedRateWrap">
      <span>1바트 =</span>
      <input type="text" inputmode="decimal" id="fixedRateInput" placeholder="예: 38.5">
      <span>원</span>
    </div>
  </div>

  <div class="card converter-card">
    <div class="exchange-row">
      <div class="cur-box">
        <button type="button" class="cur-left cur-left-btn" id="curSelectBtn" aria-haspopup="true" aria-expanded="false">
          <span class="flag">🇹🇭</span>
          <div class="cur-text">
            <div class="cur-name">태국 <span class="chevron">⌄</span></div>
            <div class="cur-code">THB</div>
          </div>
        </button>
        <div class="cur-right">
          <div class="amount-input-line">
            <input type="text" inputmode="numeric" id="amountInput" class="amount-input" placeholder="0" autocomplete="off">
            <button type="button" class="clear-icon-btn" id="clearBtn" aria-label="입력 지우기">✕</button>
          </div>
          <div class="cur-sub" id="amountSub">0 바트</div>
        </div>
      </div>
      <div class="currency-popover" id="currencyPopover"></div>
    </div>

    <div class="quick-add-row">
      <button class="quick-add-btn" data-add="100">+100</button>
      <button class="quick-add-btn" data-add="500">+500</button>
      <button class="quick-add-btn" data-add="1000">+1,000</button>
      <button class="quick-add-btn" data-add="5000">+5,000</button>
      <button class="quick-add-btn" data-add="10000">+10,000</button>
    </div>

    <div class="equals-divider">=</div>

    <div class="exchange-row">
      <div class="cur-box">
        <div class="cur-left">
          <span class="flag">🇰🇷</span>
          <div class="cur-text">
            <div class="cur-name">대한민국</div>
            <div class="cur-code">KRW</div>
          </div>
        </div>
        <div class="cur-right">
          <div class="result-main" id="resultValue">0</div>
          <div class="cur-sub" id="resultSub">0 원</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="recent-header">
      <div class="section-title">최근 계산 내역</div>
      <button class="recent-clear" id="recentClearBtn" style="display:none;">초기화</button>
    </div>
    <div id="recentList"><div class="empty-recent">아직 계산 내역이 없습니다</div></div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var CURRENCY = 'THB';
  var CUR_UNIT = '바트';
  var DEFAULT_AMOUNT = 1; // 태국 바트는 화폐 기본단위인 1을 기본 입력값으로 사용
  var SETTINGS_KEY = 'travelcalc_settings_' + CURRENCY;
  var RECENT_KEY = 'travelcalc_recent_' + CURRENCY;
  var LAST_CURRENCY_KEY = 'travelcalc_last_currency';
  var MAX_RECENT = 20;

  // 통화가 늘어날 때 이 목록에 페이지만 추가하면 상단 통화 선택기에 반영됨
  var CURRENCIES = [
    { code: 'VND', name: '베트남', flag: '🇻🇳', href: '/travelcalc/' },
    { code: 'THB', name: '태국', flag: '🇹🇭', href: '/travelcalc/thb.php' }
  ];

  try { localStorage.setItem(LAST_CURRENCY_KEY, CURRENCY); } catch (e) {}

  // 통화명("태국" 등)을 눌러 다른 통화 페이지로 이동할 수 있는 팝업 목록
  var curSelectBtn = document.getElementById('curSelectBtn');
  var currencyPopover = document.getElementById('currencyPopover');

  currencyPopover.innerHTML = CURRENCIES.map(function (c) {
    return '<div class="cp-item' + (c.code === CURRENCY ? ' active' : '') + '" data-href="' + c.href + '">' +
      '<span class="flag">' + c.flag + '</span><span>' + c.name + '</span><span class="cp-code">' + c.code + '</span>' +
      '</div>';
  }).join('');

  function closeCurrencyPopover() {
    currencyPopover.classList.remove('show');
    curSelectBtn.setAttribute('aria-expanded', 'false');
  }

  curSelectBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    var willShow = !currencyPopover.classList.contains('show');
    currencyPopover.classList.toggle('show', willShow);
    curSelectBtn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
  });

  currencyPopover.addEventListener('click', function (e) {
    var item = e.target.closest('.cp-item');
    if (item && item.dataset.href) window.location.href = item.dataset.href;
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('#curSelectBtn') && !e.target.closest('#currencyPopover')) closeCurrencyPopover();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeCurrencyPopover();
  });

  var amountInput = document.getElementById('amountInput');
  amountInput.value = DEFAULT_AMOUNT.toLocaleString('ko-KR');
  var amountSub = document.getElementById('amountSub');
  var clearBtn = document.getElementById('clearBtn');
  var resultValue = document.getElementById('resultValue');
  var resultSub = document.getElementById('resultSub');
  var rateInfoText = document.getElementById('rateInfoText');
  var fixedToggle = document.getElementById('fixedToggle');
  var fixedRateWrap = document.getElementById('fixedRateWrap');
  var fixedRateInput = document.getElementById('fixedRateInput');
  var recentList = document.getElementById('recentList');
  var recentClearBtn = document.getElementById('recentClearBtn');

  var liveRatePer1 = null;      // 1 THB = ? KRW (실시간)
  var liveUpdatedAt = null;
  var liveStale = false;

  function loadSettings() {
    try {
      var raw = localStorage.getItem(SETTINGS_KEY);
      if (raw) return JSON.parse(raw);
    } catch (e) {}
    return { useFixed: false, fixedRate: null };
  }

  function saveSettings(settings) {
    try { localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings)); } catch (e) {}
  }

  var settings = loadSettings();

  function loadRecent() {
    try {
      var raw = localStorage.getItem(RECENT_KEY);
      if (raw) return JSON.parse(raw);
    } catch (e) {}
    return [];
  }

  function saveRecent(list) {
    try { localStorage.setItem(RECENT_KEY, JSON.stringify(list)); } catch (e) {}
  }

  function formatNumber(n) {
    return Math.round(n).toLocaleString('ko-KR');
  }

  // 환율 표시용: 결과 금액과 달리 반올림하면 값이 달라 보이므로 소수점을 살려서 표시
  function formatRate(n) {
    return n.toLocaleString('ko-KR', { maximumFractionDigits: 2 });
  }

  // 큰 금액을 "1만", "2억 3만"처럼 한글 단위로 읽어주는 보조 표시 (참고 이미지의 서브텍스트 스타일)
  function toKoreanUnit(n) {
    n = Math.round(n);
    if (n === 0) return '0';
    var eok = Math.floor(n / 100000000);
    var man = Math.floor((n % 100000000) / 10000);
    var rest = n % 10000;
    var parts = [];
    if (eok) parts.push(eok.toLocaleString('ko-KR') + '억');
    if (man) parts.push(man.toLocaleString('ko-KR') + '만');
    if (rest || parts.length === 0) parts.push(rest.toLocaleString('ko-KR'));
    return parts.join(' ');
  }

  function parseAmount() {
    var raw = amountInput.value.replace(/[^0-9]/g, '');
    return raw ? parseInt(raw, 10) : 0;
  }

  function activeRatePer1() {
    if (settings.useFixed && settings.fixedRate) {
      return settings.fixedRate;
    }
    return liveRatePer1;
  }

  function renderRateInfo() {
    if (settings.useFixed && settings.fixedRate) {
      rateInfoText.innerHTML = '고정환율 사용 중: <b>1바트 = ' + formatRate(settings.fixedRate) + '원</b>';
      return;
    }
    if (liveRatePer1 === null) {
      rateInfoText.textContent = '환율 불러오는 중...';
      return;
    }
    var dateStr = '';
    try { dateStr = new Date(liveUpdatedAt).toLocaleDateString('ko-KR'); } catch (e) {}
    var html = '오늘의 환율: <b>1바트 = ' + formatRate(liveRatePer1) + '원</b>' + (dateStr ? ' (' + dateStr + ' 기준)' : '');
    if (liveStale) html += ' <span class="badge-stale">· 최신 갱신 실패, 이전 환율 표시 중</span>';
    rateInfoText.innerHTML = html;
  }

  function calcAndRender() {
    var amount = parseAmount();
    amountSub.textContent = toKoreanUnit(amount) + ' ' + CUR_UNIT;
    clearBtn.style.visibility = amount > 0 ? 'visible' : 'hidden';

    var rate = activeRatePer1();
    if (rate === null || rate === undefined || isNaN(rate)) {
      resultValue.textContent = '-';
      resultSub.textContent = '환율 확인 중...';
      return;
    }
    var result = amount * rate;
    resultValue.textContent = formatNumber(result);
    resultSub.textContent = toKoreanUnit(result) + ' 원';
  }

  function renderRecent() {
    var list = loadRecent();
    if (!list.length) {
      recentList.innerHTML = '<div class="empty-recent">아직 계산 내역이 없습니다</div>';
      recentClearBtn.style.display = 'none';
      return;
    }
    recentClearBtn.style.display = 'block';
    recentList.innerHTML = list.map(function (item, idx) {
      var timeStr = '';
      try {
        var d = new Date(item.time);
        timeStr = d.toLocaleDateString('ko-KR') + ' ' + d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' });
      } catch (e) {}
      return '<div class="recent-item" data-idx="' + idx + '">' +
        '<div><div class="from">' + formatNumber(item.amount) + CUR_UNIT + '</div><div class="time">' + timeStr + '</div></div>' +
        '<div class="to">' + formatNumber(item.krw) + '원</div>' +
        '</div>';
    }).join('');
  }

  function pushRecent(amount, krw) {
    if (!amount) return;
    var list = loadRecent();
    // 바로 직전 값과 동일하면 중복 기록하지 않음
    if (list.length && list[0].amount === amount) return;
    list.unshift({ amount: amount, krw: Math.round(krw), time: Date.now() });
    if (list.length > MAX_RECENT) list = list.slice(0, MAX_RECENT);
    saveRecent(list);
    renderRecent();
  }

  // 히스토리 기록 방식: 입력/버튼 클릭마다 쌓지 않고,
  // 1) 계산기를 "떠날 때"(포커스 이탈, 계산기 바깥 클릭, 페이지 이탈) 즉시 기록하거나
  // 2) 버튼만 누르고 아무 데도 안 벗어나는 경우를 위해, 5초간 조작이 없으면 자동으로 기록한다.
  // 두 트리거 모두 같은 commitIfPending()을 호출해 중복 기록되지 않는다.
  var pendingCommit = false;
  var idleTimer = null;
  var IDLE_COMMIT_MS = 5000;

  function markDirty() {
    pendingCommit = true;
    clearTimeout(idleTimer);
    idleTimer = setTimeout(commitIfPending, IDLE_COMMIT_MS);
  }

  function commitIfPending() {
    clearTimeout(idleTimer);
    if (!pendingCommit) return;
    pendingCommit = false;
    var amount = parseAmount();
    var rate = activeRatePer1();
    if (amount > 0 && rate) {
      pushRecent(amount, amount * rate);
    }
  }

  // 입력 이벤트
  amountInput.addEventListener('input', function () {
    var digits = amountInput.value.replace(/[^0-9]/g, '');
    amountInput.value = digits ? parseInt(digits, 10).toLocaleString('ko-KR') : '';
    calcAndRender();
    markDirty();
  });

  amountInput.addEventListener('blur', commitIfPending);

  amountInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') amountInput.blur();
  });

  // 계산기 카드 바깥을 탭/클릭하면 그때까지의 최종 금액을 기록
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.converter-card')) commitIfPending();
  });

  // 탭 전환/페이지 이탈 시에도 유실되지 않도록 기록
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) commitIfPending();
  });
  window.addEventListener('pagehide', commitIfPending);

  // 빠른 입력 버튼
  document.querySelectorAll('.quick-add-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var add = parseInt(btn.dataset.add, 10);
      var current = parseAmount();
      var next = current + add;
      amountInput.value = next.toLocaleString('ko-KR');
      calcAndRender();
      markDirty();
    });
  });

  clearBtn.addEventListener('click', function () {
    amountInput.value = '';
    calcAndRender();
    clearTimeout(idleTimer);
    pendingCommit = false;
    amountInput.focus();
  });

  // 고정환율 토글
  fixedToggle.checked = !!settings.useFixed;
  fixedRateWrap.classList.toggle('show', !!settings.useFixed);
  if (settings.fixedRate) {
    fixedRateInput.value = settings.fixedRate;
  }

  fixedToggle.addEventListener('change', function () {
    settings.useFixed = fixedToggle.checked;
    if (settings.useFixed && !settings.fixedRate && liveRatePer1 !== null) {
      // 처음 켤 때 현재 실시간 환율값을 기본값으로 채워줌
      settings.fixedRate = Math.round(liveRatePer1 * 100) / 100;
      fixedRateInput.value = settings.fixedRate;
    }
    saveSettings(settings);
    fixedRateWrap.classList.toggle('show', settings.useFixed);
    renderRateInfo();
    calcAndRender();
  });

  fixedRateInput.addEventListener('input', function () {
    var val = parseFloat(fixedRateInput.value.replace(/,/g, ''));
    settings.fixedRate = isNaN(val) ? null : val;
    saveSettings(settings);
    renderRateInfo();
    calcAndRender();
  });

  recentClearBtn.addEventListener('click', function () {
    saveRecent([]);
    renderRecent();
  });

  recentList.addEventListener('click', function (e) {
    var item = e.target.closest('.recent-item');
    if (!item) return;
    var list = loadRecent();
    var entry = list[parseInt(item.dataset.idx, 10)];
    if (!entry) return;
    amountInput.value = entry.amount.toLocaleString('ko-KR');
    calcAndRender();
  });

  // 실시간 환율 불러오기 (서버가 하루 1회만 실제로 갱신, 여기선 그냥 호출)
  fetch('/travelcalc/api/rate.php?from=' + CURRENCY)
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data && data.success) {
        liveRatePer1 = data.rate;
        liveUpdatedAt = data.updated_at;
        liveStale = !!data.stale;
      } else {
        rateInfoText.textContent = '환율 정보를 가져오지 못했습니다';
      }
      renderRateInfo();
      calcAndRender();
    })
    .catch(function () {
      rateInfoText.textContent = '환율 정보를 가져오지 못했습니다 (네트워크 오류)';
      calcAndRender();
    });

  renderRecent();
  calcAndRender();
})();
</script>

</body>
</html>
