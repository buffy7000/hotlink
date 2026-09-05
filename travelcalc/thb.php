<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>여행 환율 계산기 - 태국 바트(THB) → 원화</title>
<meta name="description" content="태국 바트(THB)를 원화로 환산하는 여행 환율 계산기. 실시간 환율 또는 고정환율을 선택할 수 있습니다.">
<link rel="icon" href="/favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/travelcalc/assets/style.css">
</head>
<body>

<div class="wrap">
  <header>
    <a href="/" class="home-link">← 핫링크</a>
    <h1>✈️ 여행 환율 계산기</h1>
  </header>

  <div class="card rate-card">
    <div class="rate-info">
      <span id="rateInfoText" class="rate-info-main">환율 불러오는 중...</span>
      <button type="button" class="rate-info-toggle" id="rateInfoToggle" aria-expanded="false" aria-label="환율 상세 정보 펼치기" style="display:none;">⌄</button>
    </div>
    <div class="rate-info-detail" id="rateInfoDetail"></div>

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
      <span id="fixedRateUnitLabel">1바트 =</span>
      <input type="text" inputmode="decimal" id="fixedRateInput" placeholder="예: 38.5">
      <span>원</span>
    </div>
  </div>

  <div class="card converter-card">
    <div class="exchange-row">
      <div class="cur-box">
        <button type="button" class="cur-left cur-left-btn" id="curSelectBtn" aria-haspopup="true" aria-expanded="false">
          <div class="cur-left-info">
            <span class="flag">🇹🇭</span>
            <div class="cur-text">
              <div class="cur-name">태국</div>
              <div class="cur-code">THB</div>
            </div>
          </div>
          <span class="chevron">⌄</span>
        </button>
        <div class="cur-right">
          <div class="amount-input-line">
            <input type="text" inputmode="decimal" id="amountInput" class="amount-input" placeholder="0" autocomplete="off">
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
  window.TRAVELCALC_CURRENCY = 'THB';
</script>
<script src="/travelcalc/assets/app.js"></script>

</body>
</html>
