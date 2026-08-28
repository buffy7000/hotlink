<?php
// OG 태그 기본값
$ogTitle = "핫링크 - 알뜰폰 이벤트";
$ogDescription = "KT엠모바일, 이야기모바일, 헬로모바일 등 주요 알뜰폰 사업자의 최신 이벤트 정보를 한 곳에서 확인하세요";
$ogImage = "https://hotlink.kr/og_image.png";
$ogUrl = "https://hotlink.kr" . $_SERVER['REQUEST_URI'];

// highlight 파라미터가 있으면 해당 이벤트 정보 조회
if (isset($_GET['highlight']) && !empty($_GET['highlight'])) {
    $highlightId = $_GET['highlight'];
    
    try {
        require_once('/home/pricetag/hotlink.kr/config/database_mvno.php');
        $db = new SimpleEventDB();
        
        $event = $db->fetch("
            SELECT title, thumbnail_url, carrier, original_url, event_id
            FROM event 
            WHERE event_id = ?
        ", [$highlightId]);
        
        if ($event) {
            $carrierNames = [
                'ktm' => 'KT엠모바일',
                'eyagi' => '이야기모바일',
                'hello' => '헬로모바일'
            ];
            $event['carrier_name'] = $carrierNames[$event['carrier']] ?? '알 수 없음';
            
            $ogTitle = "핫링크 - 알뜰폰 이벤트";
            $ogDescription = $event['title'];
            
            if (!empty($event['thumbnail_url'])) {
                $ogImage = $event['thumbnail_url'];
            }
        }
        
    } catch (Exception $e) {
        error_log("OG 태그 생성 중 오류: " . $e->getMessage());
    }
}

// HTML 특수문자 이스케이프
function escapeOgContent($content) {
    return htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-7QB3K9SQQH"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-7QB3K9SQQH');
</script>

<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "td4w2jbapa");
</script>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>핫링크-알뜰폰 이벤트</title>
  <meta name="description" content="<?php echo escapeOgContent($ogDescription); ?>">
  <meta name="keywords" content="알뜰폰, MVNO, 이벤트, KT엠모바일, 이야기모바일, 헬로모바일">
  
<!-- Open Graph 태그 (동적) -->
<meta property="og:title" content="<?php echo escapeOgContent($ogTitle); ?>">
<meta property="og:description" content="<?php echo escapeOgContent($ogDescription); ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo escapeOgContent($ogUrl); ?>">
<meta property="og:site_name" content="핫링크">
<meta property="og:locale" content="ko_KR">
<meta property="og:image" content="<?php echo escapeOgContent($ogImage); ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo escapeOgContent($ogTitle); ?>">
<meta name="twitter:description" content="<?php echo escapeOgContent($ogDescription); ?>">
<meta name="twitter:image" content="<?php echo escapeOgContent($ogImage); ?>">
  <link rel="icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  
  <style>
    :root {
      --bg-color: #f8fafc;
      --card-bg-color: #ffffff;
      --text-color: #1e293b;
      --muted-text-color: #64748b;
      --border-color: #e2e8f0;
      --primary-color: #3b82f6;
      --secondary-color: #6b7280;
      
      /* 알뜰폰 사업자별 브랜드 색상 */
      --ktm-color: #E8344E;
      --iyagi-color: #FF6B35;
      --hello-color: #00C73C;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
      background: var(--bg-color);
      color: var(--text-color);
      line-height: 1.4;
      -webkit-font-smoothing: antialiased;
    }

.header {
  background: white;
  color: var(--text-color);
  padding: 0px 0px;
}

.community-section {
  position: sticky;
  top: 0;
  z-index: 99;
  background: #082567;
  margin-bottom: 0;
  margin-left: 0;
  margin-right: 0;
  padding: 0 16px;
  padding-bottom: 0;
}

.filter-section {
  position: sticky;
  top: 46px;
  z-index: 98;
  background: var(--card-bg-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 6px;
  padding: 12px 16px 12px 16px;
  margin: 0;
}

.header-nav {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 20px;
  padding-left: 16px;
  padding-right: 16px;
}

.logo-area {
  margin: 0;
  font-size: 0;
}

.logo-link {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  color: inherit;
}

.logo-icon {
  margin-right: 8px;
}

.logo-text {
  font-size: 18px;
  font-weight: 700;
  color: var(--text-color);
  letter-spacing: -0.3px;
}

.service-nav {
  display: flex;
  padding: 4px 0 0 0; 
  gap: 8px;
}

.service-link {
  font-size: 14px;
  font-weight: 500;
  color: var(--muted-text-color);
  text-decoration: none;
  padding: 8px 0;
  border-bottom: 2px solid transparent;
  transition: all 0.2s ease;
}

.service-link:hover {
  color: var(--text-color);
}

.service-link.active {
  color: var(--text-color);
  font-weight: 600;
  border-bottom: none;  
}

.section-label {
  display: none;
}

.community-nav {
  display: flex;
  list-style: none;
  margin: 0;
  padding: 0;
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
  user-select: none;
  -webkit-user-drag: none;
  touch-action: pan-x;
  gap: 0;
  background: #082567;
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
}

.community-nav::-webkit-scrollbar {
  display: none;
}

.nav-item-wrapper {
  flex-shrink: 0;
}

.nav-item {
  display: inline-block;
  padding: 0;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.nav-menu {
  display: inline-block;
  padding: 12px 16px;
  font-size: 16px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.7);
  white-space: nowrap;
  transition: all 0.2s ease;
  border-bottom: 2px solid transparent;
  position: relative;
}

.nav-item.active .nav-menu {
  color: white;
  font-weight: 600;
  border-bottom-color: white;
}

.nav-item:hover:not(.active) .nav-menu {
  color: rgba(255, 255, 255, 0.9);
}

.nav-item:not(.active) .nav-menu {
  border-bottom-color: transparent;
}

.sort-section {
  flex: none;
}

.sort-controls {
  display: flex;
  gap: 0px;
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
  border-radius: 6px;
  overflow: hidden;
}
.sort-controls::-webkit-scrollbar { display: none; }

.sort-controls ul {
  display: flex;
  list-style: none;
  margin: 0;
  padding: 0;
  gap: 0;
}

.sort-controls li {
  flex-shrink: 0;
}

.sort-controls li a {
  display: block;
  background: white;
  color: #1e293b;
  border: 1px solid #d1d5db;
  border-left: none;
  padding: 6px 12px;
  font-size: 16px;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.sort-controls li:first-child a {
  border-radius: 10px 0 0 10px;
  border-left: 1px solid #d1d5db;
}

.sort-controls li:last-child a {
  border-radius: 0 10px 10px 0;
}

.sort-controls li a:hover {
  background: var(--primary-color);
  color: white;
  z-index: 1;
  position: relative;
  transform: translateY(-1px);
}

.sort-controls li.on a {
  background: var(--primary-color);
  color: white;
  z-index: 1;
  position: relative;
}

/* 리스트 컨테이너 */
.list-container {
  background: var(--bg-color);  /* 배경색 변경 */
  min-height: calc(100vh - 160px);
  max-width: 800px;
  margin: 0 auto;
  border-radius: 0 0 12px 12px;
  overflow: visible;
  padding: 16px 32px;  /* 상하 여백 추가 */
}


/* 리스트 아이템 */
.list-item {
  display: block;
  padding: 0;
  margin-bottom: 16px;
  background-color: #fff;
  border: 1px solid #ddd;
  border-radius: 12px;
  transition: all 0.15s ease;
  cursor: pointer;
  text-decoration: none;
  color: inherit;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

.list-item:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  transform: translateY(-2px);
}

.list-item:last-child {
  margin-bottom: 0;
}

.list-item-wrapper {
  display: flex;
  flex-direction: column;
  width: 100%;
  position: relative;  /* 공유 버튼 위치의 기준점 */
}

.event-thumbnail {
  width: 100%;
  height: 280px;
  border-radius: 12px 12px 0 0; 
  object-fit: contain;
  object-position: center; 
  background: #f1f5f9;
  border: none;
  display: block;
}

.no-thumbnail {
  width: 100%;
  height: 200px;
  border-radius: 12px 12px 0 0; 
  background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted-text-color);
  font-size: 16px;
  border: none;
}

.item-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
}

.action-buttons {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 10;
  display: flex;
  gap: 8px;
}

.favorite-button,
.share-button {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.favorite-button:hover,
.share-button:hover {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

.favorite-button.active {
  background: #fbbf24;
  border-color: #fbbf24;
  color: white;
}

.favorite-button svg,
.share-button svg {
  width: 20px;
  height: 20px;
}

.share-button:hover {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

.share-button svg {
  width: 20px;
  height: 20px;
}

/* 모바일 대응 */
@media (max-width: 480px) {
  .event-thumbnail {
    height: 150px;
  }
  
  .no-thumbnail {
    height: 150px;
  }
  
  .item-content {
    padding: 12px;
  }
  
  .share-button {
    width: 36px;
    height: 36px;
    top: 12px;    /* 추가 */
    right: 12px;  /* 추가 */
  }
  
  .share-button svg {
    width: 18px;
    height: 18px;
  }
}


.rank-number {
  display: none;
}

.item-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.title-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.source-label {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  color: white;
  padding: 3px 8px;
  border-radius: 6px;
  flex-shrink: 0;
  line-height: 1;
}

.source-label[data-carrier="ktm"] { 
  background: var(--ktm-color); 
}
.source-label[data-carrier="iyagi"] { 
  background: var(--iyagi-color); 
}
.source-label[data-carrier="hello"] { 
  background: var(--hello-color); 
}

.item-title {
  font-weight: 600 !important;
  font-size: 16px;
  line-height: 1.5;
  flex: 1;
  min-width: 0;
  word-break: break-word;
  margin-bottom: 6px;
}

.item-title * {
  font-weight: 600 !important;
}

a.list-item:visited .item-title {
  color: #BBBBBB !important;
  opacity: 0.8 !important;
}

.event-period {
  background: #f3f4f6;
  color: #374151;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 500;
  display: inline-block;
  margin-right: 8px;
}

.item-meta {
  font-size: 12px;
  color: var(--muted-text-color);
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.meta-item {
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 2px;
}

@media (max-width: 480px) {
  .community-nav {
    gap: 0;
  }

  .nav-menu {
    font-size: 15px;
    padding: 10px 12px;
  }


  .list-container {
    padding: 12px 16px;
  }
  
  .list-item {
    margin-bottom: 12px;
  }
  /* 이미지 크기 설정 삭제 - 위쪽 CSS가 적용되도록 */
  .event-thumbnail {
    min-height: 180px;
  }
  
  .no-thumbnail {
    height: 180px;
  }
  
  .source-label {
    font-size: 10px;
    padding: 2px 6px;
  }
  
  .item-title {
    font-size: 15px;
  }

  .sort-controls li a {
    font-size: 13px;
    padding: 5px 8px;
  }
  
  .item-content {
    padding: 12px;
  }
  
  .share-button {
    width: 36px;
    height: 36px;
    top: 12px;
    right: 12px;
  }
  
  .share-button svg {
    width: 18px;
    height: 18px;
  }
}


.loading {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  color: var(--muted-text-color);
}

.hidden { 
  display: none !important; 
}

@media (max-width: 768px) {
  .community-nav {
    scroll-snap-type: x mandatory;
    padding: 0 8px;
  }
  
  .nav-item-wrapper {
    scroll-snap-align: start;
  }
  
  .nav-menu {
    padding: 12px 14px;
    min-width: 60px;
    text-align: center;
  }
}

/* 공유 버튼 스타일 */
.share-button {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
  margin-left: 8px;
}

.share-button:hover {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

.share-button svg {
  width: 16px;
  height: 16px;
}

/* 하이라이트된 이벤트 아이템 스타일 */
.highlighted-item {
  border: 4px solid #E8344E;
  background: linear-gradient(135deg, #ffe6ea 0%, #ffd6dc 100%);
  box-shadow: 0 8px 25px rgba(232, 52, 78, 0.3);
  position: relative;
  transform: scale(1.02);
  margin-top: 16px;
  margin-bottom: 12px;
  border-radius: 12px;
  transition: all 0.3s ease;
}

.highlighted-item::before {
  content: '🎉 HOT EVENT';
  position: absolute;
  top: -12px;
  left: 20px;
  background: #E8344E;
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  animation: bounce 1.5s infinite;
  z-index: 10;
}

.highlighted-item .item-title {
  color: #c72a3f;
  font-weight: 600;
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-3px); }
}

/* 모바일 대응 */
@media (max-width: 480px) {
  .highlighted-item {
    transform: scale(1.01);
    margin-bottom: 8px;
  }
  
  .highlighted-item::before {
    font-size: 10px;
    padding: 3px 8px;
    left: 16px;
  }
  
  .action-buttons {
    top: 12px;
    right: 12px;
    gap: 6px;
  }
  
  .favorite-button,
  .share-button {
    width: 36px;
    height: 36px;
  }
  
  .favorite-button svg,
  .share-button svg {
    width: 18px;
    height: 18px;
  }
}

  </style>

</head>
<body>

<div class="header">
  <div class="header-nav">
    <h1 class="logo-area">
      <a href="/" class="logo-link">
        <img src="/favicon.ico" width="20" height="20" alt="핫링크" class="logo-icon">
        <span class="logo-text">핫링크</span>
      </a>
    </h1>
    <div class="service-nav">
      <a href="/.." class="service-link">커뮤니티</a>
      <a href="/deal/" class="service-link">핫딜</a>
      <a href="/mvno/" class="service-link active">알뜰폰</a>
    </div>
  </div>
</div>

<div class="community-section">
  <div class="section-label">알뜰폰 사업자 선택</div>
  <ul class="community-nav">
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item active" data-carrier="all">
        <span class="nav-menu">전체</span>
      </a>
    </li>
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item" data-carrier="ktm">
        <span class="nav-menu">KT엠모바일</span>
      </a>
    </li>
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item" data-carrier="eyagi">
        <span class="nav-menu">이야기모바일</span>
      </a>
    </li>
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item" data-carrier="hello">
        <span class="nav-menu">헬로모바일</span>
      </a>
    </li>
  </ul>
</div>

<div class="filter-section">
  <div class="sort-section">
    <nav class="sort-controls">
      <ul>
        <li class="on" data-sort="latest" aria-current="true">
          <a href="javascript:void(0);" data-sort="latest">📅 최신순</a>
        </li>
        <li data-sort="ending" aria-current="false">
          <a href="javascript:void(0);" data-sort="ending">⏰ 마감임박</a>
        </li>
      </ul>
    </nav>
  </div>
</div>

<!-- 배너 -->
<div id="topBanner" style="
  background: linear-gradient(90deg, rgba(100,116,139,0.9) 0%, rgba(71,85,105,0.9) 50%, rgba(51,65,85,0.9) 100%);
  color: white;
  padding: 16px 20px;
  font-size: 15px;
  font-weight: 500;
  letter-spacing: -0.2px;
  max-width: 100%;
  margin: 0;
  cursor: pointer;
  transition: all 0.3s ease;
" onclick="toggleBanner()">


  <div style="display: flex; justify-content: space-between; align-items: center;">
    <span id="firstLine">🎉 주요 알뜰폰 사업자의 최신 이벤트를 한 곳에서!</span>
    <span id="toggleIcon" style="font-size: 10px;">▼</span>
  </div>
  <div id="bannerContent" style="
    display: none;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,0.2);
  ">
    <div>📱 KT엠모바일, 이야기모바일, 헬로모바일 이벤트 정보</div>
    <div>🎁 요금제 할인, 경품 이벤트 등 다양한 혜택</div>
    <div>⏰ 이벤트 기간 자동 업데이트</div>
    <div>💡 놓치기 쉬운 이벤트도 한눈에 확인</div>
  </div>
</div>

<!-- 리스트 컨테이너 -->
<div class="list-container">
  <div id="list">
    <div class="loading">이벤트 데이터를 불러오는 중...</div>
  </div>
</div>

<script>
  (function() {
    'use strict';
    
    var listEl = document.getElementById('list');
    var currentSettings = {
      sort: 'latest',
      carrier: 'all'
    };

    // 배너 토글 함수
    window.toggleBanner = function() {
      var content = document.getElementById('bannerContent');
      var icon = document.getElementById('toggleIcon');
      
      if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▲';
        localStorage.setItem('mvnoBannerExpanded', 'true');
      } else {
        content.style.display = 'none';
        icon.textContent = '▼';
        localStorage.setItem('mvnoBannerExpanded', 'false');
      }
    };

    // 정렬 버튼 클릭
    document.addEventListener('click', function(e) {
      var sortLink = e.target.closest('.sort-controls a');
      if (sortLink && sortLink.dataset.sort) {
        e.preventDefault();
        var sortValue = sortLink.dataset.sort;
        var parentLi = sortLink.parentElement;
        
        document.querySelectorAll('.sort-controls li').forEach(function(li) {
          li.classList.remove('on');
          li.setAttribute('aria-current', 'false');
        });
        
        parentLi.classList.add('on');
        parentLi.setAttribute('aria-current', 'true');
        
        currentSettings.sort = sortValue;
        renderList();
      }
    });

    // 알뜰폰 사업자 선택
    document.addEventListener('click', function(e) {
      var navItem = e.target.closest('.nav-item');
      if (navItem && navItem.dataset.carrier) {
        e.preventDefault();
        
        var carrierValue = navItem.dataset.carrier;
        
        document.querySelectorAll('.nav-item').forEach(function(btn) {
          btn.classList.remove('active');
        });
        
        navItem.classList.add('active');
        
        currentSettings.carrier = carrierValue;
        
        removeHighlight();
        updateURL(carrierValue);
        renderList();
      }
    });

    var eventsData = [];

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/[&<>"']/g, function(s) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[s]);
      });
    }

    function getRankClass(rank) {
      if (rank === 1) return 'rank-1';
      if (rank === 2) return 'rank-2';
      if (rank === 3) return 'rank-3';
      return '';
    }

    function formatNumber(num) {
      if (num >= 10000) return Math.floor(num / 1000) + 'k';
      if (num >= 1000) return (num / 1000).toFixed(1).replace('.0', '') + 'k';
      return String(num);
    }

    function formatDate(dateString) {
      if (!dateString) return '';
      var date = new Date(dateString);
      return date.getFullYear() + '.' + 
             String(date.getMonth() + 1).padStart(2, '0') + '.' + 
             String(date.getDate()).padStart(2, '0');
    }

    function removeHighlight() {
      var highlightedItem = document.querySelector('.highlighted-item');
      if (highlightedItem) {
        highlightedItem.remove();
      }
      
      var regularList = document.getElementById('regular-list');
      if (regularList) {
        regularList.remove();
      }
      
      var url = new URL(window.location);
      url.searchParams.delete('highlight');
      window.history.replaceState({}, '', url.toString());
    }

    function loadEventsData() {
      var url = '/api/mvno_events.php?carrier=' + currentSettings.carrier + '&sort=' + currentSettings.sort;
      
      var hasHighlight = document.querySelector('.highlighted-item');
      if (!hasHighlight) {
        listEl.innerHTML = '<div class="loading">이벤트 데이터를 불러오는 중...</div>';
      }
      
      fetch(url)
        .then(function(response) {
          return response.json();
        })
        .then(function(result) {
          if (result.success) {
            eventsData = result.data;
            renderEventsList();
          } else {
            throw new Error(result.error || '데이터 로드 실패');
          }
        })
        .catch(function(error) {
          console.error('API 호출 실패:', error);
          listEl.innerHTML = '<div class="loading">데이터 로딩에 실패했습니다.</div>';
        });
    }

    function renderEventsList() {
      if (!eventsData || eventsData.length === 0) {
        var targetEl = document.getElementById('regular-list') || listEl;
        targetEl.innerHTML = '<div class="loading">이벤트 데이터가 없습니다.</div>';
        return;
      }
      
      var html = '';
      
      for (var i = 0; i < eventsData.length; i++) {
        var item = eventsData[i];
        html += renderEventItem(item, i + 1);
      }
      
      var targetEl = document.getElementById('regular-list') || listEl;
      targetEl.innerHTML = html;
    }

function renderEventItem(item, rank) {
  var carrierMapping = {
    'ktm': { key: 'ktm', name: 'KT엠모바일', shortName: 'KT엠모바일' },
    'eyagi': { key: 'iyagi', name: '이야기모바일', shortName: '이야기모바일' },
    'hello': { key: 'hello', name: '헬로모바일', shortName: '헬로모바일' }
  };

  var carrierInfo = carrierMapping[item.carrier] || { 
    key: 'ktm', name: 'KT엠모바일', shortName: 'KT엠'
  };

  var rankClass = getRankClass(rank);
  
  var thumbnailHtml = '';
  if (item.thumbnail_url) {
    thumbnailHtml = '<img src="' + escapeHtml(item.thumbnail_url) + '" alt="이벤트 이미지" class="event-thumbnail" onerror="this.outerHTML=\'<div class=&quot;no-thumbnail&quot;>이미지<br>없음</div>\'">';
  } else {
    thumbnailHtml = '<div class="no-thumbnail">이미지<br>없음</div>';
  }

var periodHtml = '';
if (item.start_date && item.end_date) {
  periodHtml = '<span class="event-period">📅 ' + formatDate(item.start_date) + ' ~ ' + formatDate(item.end_date) + '</span>';
} else if (item.start_date) {
  periodHtml = '<span class="event-period">📅 ' + formatDate(item.start_date) + ' ~ 응모마감시까지</span>';
}

var isFavorite = checkFavorite(item.event_id);
var favoriteClass = isFavorite ? 'active' : '';

return '<a href="' + escapeHtml(item.original_url) + '" class="list-item" data-id="' + item.event_id + '">' +
  '<div class="list-item-wrapper">' +
  thumbnailHtml +
  '<div class="item-content">' + 
  '<div class="item-title">' +
  escapeHtml(item.title) + 
  '</div>' +
  '<div class="item-meta">' +
  '<span class="source-label" data-carrier="' + carrierInfo.key + '">' + carrierInfo.shortName + '</span>' +
  periodHtml +
  '</div>' +
  '</div>' +
  '<div class="action-buttons">' +
  '<button class="favorite-button ' + favoriteClass + '" onclick="toggleFavorite(\'' + item.event_id + '\', event)">' +
  '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>' +
  '</button>' +
  '<button class="share-button" onclick="shareEvent(\'' + item.event_id + '\', event)">' +
  '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92S19.61 16.08 18 16.08z"/></svg>' +
  '</button>' +
  '</div>' +
  '</div>' +
  '</a>';


}


    function updateURL(carrier) {
      if (carrier === 'all') {
        window.history.pushState({}, '', '/mvno/');
      } else {
        window.history.pushState({}, '', '/mvno/?carrier=' + carrier);
      }
    }

    function renderList() {
      loadEventsData();
    }

    // 공유 함수
    window.shareEvent = function(eventId, event) {
      event.preventDefault();
      event.stopPropagation();
      
      var currentUrl = window.location.origin + '/mvno/?highlight=' + eventId;
      
      navigator.clipboard.writeText(currentUrl).then(function() {
        alert('공유 링크가 복사되었습니다!');
      }).catch(function() {
        prompt('공유 링크를 복사하세요:', currentUrl);
      });
    };

// 즐겨찾기 관리
window.toggleFavorite = function(eventId, event) {
  event.preventDefault();
  event.stopPropagation();
  
  var favorites = JSON.parse(localStorage.getItem('mvno_favorites') || '[]');
  var index = favorites.indexOf(eventId);
  
  if (index > -1) {
    // 제거
    favorites.splice(index, 1);
    event.currentTarget.classList.remove('active');
  } else {
    // 추가
    favorites.push(eventId);
    event.currentTarget.classList.add('active');
  }
  
  localStorage.setItem('mvno_favorites', JSON.stringify(favorites));
};

function checkFavorite(eventId) {
  var favorites = JSON.parse(localStorage.getItem('mvno_favorites') || '[]');
  return favorites.indexOf(eventId) > -1;
}

    window.loadHighlightedItem = function(eventId) {
      fetch('/api/mvno_events.php?event_id=' + eventId)
        .then(function(response) {
          return response.json();
        })
        .then(function(result) {
          if (result.success && result.data.length > 0) {
            var highlightedItem = result.data[0];
            renderHighlightedItem(highlightedItem);
          } else {
            loadEventsData();
          }
        })
        .catch(function(error) {
          console.error('하이라이트 항목 로드 실패:', error);
          loadEventsData();
        });
    };

    window.renderHighlightedItem = function(item) {
      var highlightHtml = renderEventItem(item, '★');
      
      var wrappedHtml = '<div class="highlighted-item">' + highlightHtml + '</div>';
      
      listEl.innerHTML = wrappedHtml + '<div id="regular-list"><div class="loading">목록을 불러오는 중...</div></div>';
      
      setTimeout(function() {
        loadEventsData();
      }, 100);
    };

    function init() {
      const urlParams = new URLSearchParams(window.location.search);
      const carrierParam = urlParams.get('carrier');
      const highlightId = urlParams.get('highlight');
      
      if (carrierParam) {
        const validCarriers = ['all', 'ktm', 'eyagi', 'hello'];
        if (validCarriers.includes(carrierParam)) {
          currentSettings.carrier = carrierParam;
        }
      }
      
      document.querySelectorAll('.sort-controls li').forEach(function(li) {
        li.classList.remove('on');
        li.setAttribute('aria-current', 'false');
      });
      var activeSortLi = document.querySelector('.sort-controls li[data-sort="' + currentSettings.sort + '"]');
      if (activeSortLi) {
        activeSortLi.classList.add('on');
        activeSortLi.setAttribute('aria-current', 'true');
      }

      document.querySelectorAll('.nav-item').forEach(function(btn) {
        if (btn.dataset.carrier === currentSettings.carrier) {
          btn.classList.add('active');
        } else {
          btn.classList.remove('active');
        }
      });
      
      if (localStorage.getItem('mvnoBannerExpanded') === 'true') {
        toggleBanner();
      }
      
      if (highlightId) {
        loadHighlightedItem(highlightId);
      } else {
        renderList();
      }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      setTimeout(init, 0);
    }
    
  })();
</script>

</body>
</html>
