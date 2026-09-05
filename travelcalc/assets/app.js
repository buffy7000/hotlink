(function () {
  'use strict';

  // 통화를 추가할 때는 이 목록에 한 줄만 추가하면 모든 페이지의 통화 선택 팝업에 자동 반영됨.
  // unit: 표시 단위 텍스트 / defaultAmount: 페이지 진입 시 기본 입력값
  // rateBase: 환율을 몇 단위 기준으로 보여줄지(동은 100단위, 바트는 1단위) / allowDecimal: 금액 입력에 소수점 허용 여부
  var CURRENCY_DIRECTORY = {
    VND: { name: '베트남', flag: '🇻🇳', href: '/travelcalc/', unit: '동', defaultAmount: 100, rateBase: 100, allowDecimal: false },
    THB: { name: '태국', flag: '🇹🇭', href: '/travelcalc/thb.php', unit: '바트', defaultAmount: 1, rateBase: 1, allowDecimal: true }
  };

  var CURRENCY = window.TRAVELCALC_CURRENCY;
  var CFG = CURRENCY_DIRECTORY[CURRENCY];
  var CUR_UNIT = CFG.unit;
  var DEFAULT_AMOUNT = CFG.defaultAmount;
  var RATE_BASE = CFG.rateBase;
  var ALLOW_DECIMAL = !!CFG.allowDecimal;

  var SETTINGS_KEY = 'travelcalc_settings_' + CURRENCY;
  var RECENT_KEY = 'travelcalc_recent_' + CURRENCY;
  var LAST_CURRENCY_KEY = 'travelcalc_last_currency';
  var MAX_RECENT = 20;

  var CURRENCIES = Object.keys(CURRENCY_DIRECTORY).map(function (code) {
    var c = CURRENCY_DIRECTORY[code];
    return { code: code, name: c.name, flag: c.flag, href: c.href };
  });

  try { localStorage.setItem(LAST_CURRENCY_KEY, CURRENCY); } catch (e) {}

  // 통화명("베트남" 등)을 눌러 다른 통화 페이지로 이동할 수 있는 팝업 목록
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

  // 고정환율 입력란의 단위 라벨("100동 =" / "1바트 =")도 통화 설정값에서 채움
  var fixedRateUnitLabel = document.getElementById('fixedRateUnitLabel');
  if (fixedRateUnitLabel) {
    fixedRateUnitLabel.textContent = RATE_BASE.toLocaleString('ko-KR') + CUR_UNIT + ' =';
  }

  var amountInput = document.getElementById('amountInput');
  amountInput.value = DEFAULT_AMOUNT.toLocaleString('ko-KR');
  var amountSub = document.getElementById('amountSub');
  var clearBtn = document.getElementById('clearBtn');
  var resultValue = document.getElementById('resultValue');
  var resultSub = document.getElementById('resultSub');
  var rateInfoText = document.getElementById('rateInfoText');
  var rateInfoToggle = document.getElementById('rateInfoToggle');
  var rateInfoDetail = document.getElementById('rateInfoDetail');
  var rateCardCollapsible = document.getElementById('rateCardCollapsible');
  var fixedToggle = document.getElementById('fixedToggle');
  var fixedRateWrap = document.getElementById('fixedRateWrap');
  var fixedRateInput = document.getElementById('fixedRateInput');
  var recentList = document.getElementById('recentList');
  var recentClearBtn = document.getElementById('recentClearBtn');

  var liveRatePer1 = null;      // 1 단위 = ? KRW (실시간)
  var liveUpdatedAt = null;
  var liveStale = false;

  function loadSettings() {
    try {
      var raw = localStorage.getItem(SETTINGS_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        // 이전 버전(동 전용 페이지) 설정 형식 호환: fixedRatePer100 -> fixedRate
        if (parsed.fixedRate == null && parsed.fixedRatePer100 != null) {
          parsed.fixedRate = parsed.fixedRatePer100;
        }
        return parsed;
      }
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

  // 환산 결과(원)는 소수점 둘째 자리까지 보여주되, 소수 부분만 작은 글자로 표시
  function formatResultKRW(n) {
    var fixed = n.toFixed(2);
    var dotIdx = fixed.indexOf('.');
    var intPart = parseInt(fixed.slice(0, dotIdx), 10).toLocaleString('ko-KR');
    var decPart = fixed.slice(dotIdx);
    return intPart + '<span class="decimal">' + decPart + '</span>';
  }

  // 환율 표시용: 결과 금액과 달리 반올림하면 값이 달라 보이므로 소수점을 살려서 표시
  function formatRate(n) {
    return n.toLocaleString('ko-KR', { maximumFractionDigits: 2 });
  }

  // 큰 금액을 "1만", "2억 3만"처럼 한글 단위로 읽어주는 보조 표시
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

  // 소수점 입력이 있으면 반올림 없이 그대로, 정수면 한글 단위 표시 사용
  function formatAmountSub(n) {
    if (n % 1 !== 0) return n.toLocaleString('ko-KR', { maximumFractionDigits: 2 });
    return toKoreanUnit(n);
  }

  function parseAmount() {
    if (ALLOW_DECIMAL) {
      var raw = amountInput.value.replace(/[^0-9.]/g, '');
      var num = parseFloat(raw);
      return isNaN(num) ? 0 : num;
    }
    var digits = amountInput.value.replace(/[^0-9]/g, '');
    return digits ? parseInt(digits, 10) : 0;
  }

  function activeRatePer1() {
    if (settings.useFixed && settings.fixedRate) {
      return settings.fixedRate / RATE_BASE;
    }
    return liveRatePer1;
  }

  // 환율 상세(기준일/갱신 실패 여부)와 고정환율 사용 항목은 기본적으로 접어두고,
  // v 버튼으로 한번에 펼치고 접을 수 있게 함
  function setRateDetail(html) {
    rateInfoDetail.innerHTML = html || '';
  }

  rateInfoToggle.addEventListener('click', function () {
    var willShow = !rateCardCollapsible.classList.contains('show');
    rateCardCollapsible.classList.toggle('show', willShow);
    rateInfoToggle.classList.toggle('expanded', willShow);
    rateInfoToggle.setAttribute('aria-expanded', willShow ? 'true' : 'false');
  });

  function renderRateInfo() {
    if (settings.useFixed && settings.fixedRate) {
      rateInfoText.innerHTML = '고정환율 사용 중: <b>' + RATE_BASE.toLocaleString('ko-KR') + CUR_UNIT + ' = ' + formatRate(settings.fixedRate) + '원</b>';
      setRateDetail('');
      return;
    }
    if (liveRatePer1 === null) {
      rateInfoText.textContent = '환율 불러오는 중...';
      setRateDetail('');
      return;
    }
    var perBase = liveRatePer1 * RATE_BASE;
    rateInfoText.innerHTML = '오늘의 환율: <b>' + RATE_BASE.toLocaleString('ko-KR') + CUR_UNIT + ' = ' + formatRate(perBase) + '원</b>';
    var dateStr = '';
    try { dateStr = new Date(liveUpdatedAt).toLocaleDateString('ko-KR'); } catch (e) {}
    var detailHtml = dateStr ? (dateStr + ' 기준') : '';
    if (liveStale) detailHtml += (detailHtml ? ' ' : '') + '<span class="badge-stale">· 최신 갱신 실패, 이전 환율 표시 중</span>';
    setRateDetail(detailHtml);
  }

  function calcAndRender() {
    var amount = parseAmount();
    amountSub.textContent = formatAmountSub(amount) + ' ' + CUR_UNIT;
    clearBtn.style.visibility = amount > 0 ? 'visible' : 'hidden';

    var rate = activeRatePer1();
    if (rate === null || rate === undefined || isNaN(rate)) {
      resultValue.textContent = '-';
      resultSub.textContent = '환율 확인 중...';
      return;
    }
    var result = amount * rate;
    resultValue.innerHTML = formatResultKRW(result);
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
        '<div><div class="from">' + item.amount.toLocaleString('ko-KR', { maximumFractionDigits: 2 }) + CUR_UNIT + '</div><div class="time">' + timeStr + '</div></div>' +
        '<div class="to">' + formatResultKRW(item.krw) + '원</div>' +
        '</div>';
    }).join('');
  }

  function pushRecent(amount, krw) {
    if (!amount) return;
    var list = loadRecent();
    // 바로 직전 값과 동일하면 중복 기록하지 않음
    if (list.length && list[0].amount === amount) return;
    list.unshift({ amount: amount, krw: Math.round(krw * 100) / 100, time: Date.now() });
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
    if (ALLOW_DECIMAL) {
      var raw = amountInput.value.replace(/[^0-9.]/g, '');
      // 소수점은 하나만 허용
      var dotIdx = raw.indexOf('.');
      if (dotIdx !== -1) {
        raw = raw.slice(0, dotIdx + 1) + raw.slice(dotIdx + 1).replace(/\./g, '');
      }
      var parts = raw.split('.');
      var intPart = parts[0] ? parseInt(parts[0], 10).toLocaleString('ko-KR') : '';
      amountInput.value = parts.length > 1 ? (intPart + '.' + parts[1]) : intPart;
    } else {
      var digits = amountInput.value.replace(/[^0-9]/g, '');
      amountInput.value = digits ? parseInt(digits, 10).toLocaleString('ko-KR') : '';
    }
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
      var add = parseFloat(btn.dataset.add);
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
      settings.fixedRate = Math.round(liveRatePer1 * RATE_BASE * 100) / 100;
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
