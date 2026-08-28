<?php
// OG 태그 기본값
$ogTitle = "핫링크 - 핫딜";
$ogDescription = "퀘사이존 등 주요 핫딜 사이트의 최신 핫딜 정보를 한 곳에서 확인하세요";
$ogImage = "https://hotlink.kr/og_image.png";
$ogUrl = "https://hotlink.kr" . $_SERVER['REQUEST_URI'];

// highlight 파라미터가 있으면 해당 핫딜 정보 조회
if (isset($_GET['highlight']) && !empty($_GET['highlight'])) {
    $highlightId = $_GET['highlight'];
    
    try {
        // 데이터베이스 연결
        require_once(__DIR__ . '/SimpleHotdealDB.php');
        $db = new SimpleHotdealDB();
        
// 수정된 코드 (sources 테이블 없이)
$hotdeal = $db->fetch("
    SELECT title, price, store_name, thumbnail_url, source_id
    FROM hotdeals 
    WHERE public_id = ?
", [$highlightId]);
        
if ($hotdeal) {
    // source_name을 source_id 기반으로 매핑
    $sourceNames = [
        2 => '퀘사이존',
        3 => '클리앙', 
        5 => '어미새',
        6 => '루리웹',
        99 => '쿠팡'
    ];
    $hotdeal['source_name'] = $sourceNames[$hotdeal['source_id']] ?? '알 수 없음';
    
    // OG 제목: "핫링크 - 핫딜" 고정
    $ogTitle = "핫링크 - 핫딜";
    
    // OG 설명: 핫딜 제목 그대로 사용
    $ogDescription = $hotdeal['title'];
    
    // OG 이미지: 썸네일이 있으면 사용
    if (!empty($hotdeal['thumbnail_url'])) {
        $ogImage = $hotdeal['thumbnail_url'];
    }
}
        
    } catch (Exception $e) {
        // 에러 발생시 기본값 유지
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
  <title>핫링크-핫딜</title>
<meta name="description" content="<?php echo escapeOgContent($ogDescription); ?>">
  <meta name="keywords" content="핫딜, 퀘사이존, 할인, 쇼핑, 특가">
  
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
      
      /* 핫딜 사이트별 브랜드 색상 */
      --coupang-color: #ff6b35;
      --quasarzone-color: #FF9200;
      --clien-color: #374373;
      --eomisae-color: #E87A72; 
      --ruliweb-color: #5383E8; 
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

.time-section {
  flex-shrink: 0;
  margin-left: auto;
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

.time-dropdown {
  position: relative;
  flex-shrink: 0;
}

.time-selector-native {
  background: white;
  color: #1e293b;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px 12px;
  padding-right: 28px;
  font-size: 16px;
  cursor: pointer;
  min-width: 80px;
  width: auto;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  outline: none;
  -webkit-tap-highlight-color: transparent;
  background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right 8px center;
  background-size: 12px;
}

.time-selector-native:focus {
  outline: none;
  border: 1px solid var(--primary-color);
  box-shadow: none;
}

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
  background: var(--card-bg-color);
  min-height: calc(100vh - 160px);
  max-width: 800px;
  margin: 0 auto;
  border-radius: 0 0 12px 12px;
  overflow: visible;        /* hidden → visible로 변경 */
  padding: 0 8px;          /* 좌우 패딩 추가 */
}

.list-item {
  display: flex;
  align-items: flex-start;
  padding: 16px;
  border-bottom: 1px solid var(--border-color);
  transition: all 0.15s ease;
  cursor: pointer;
  text-decoration: none;
  color: inherit;
  gap: 12px;
}

.list-item:hover {
  background-color: var(--bg-color);
}

.list-item:last-child {
  border-bottom: none;
}

.hotdeal-thumbnail {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  object-fit: cover;
  flex-shrink: 0;
  background: #f1f5f9;
  border: 1px solid var(--border-color);
}

.no-thumbnail {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted-text-color);
  font-size: 12px;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
}


.rank-number {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  font-weight: 700;
  font-size: 14px;
  color: white;
  border-radius: 6px;
  margin-right: 8px;
  flex-shrink: 0;
  position: relative;
}

.rank-number[data-community="coupang"] {
  background: linear-gradient(135deg, var(--coupang-color), #e55a2b);
  box-shadow: 0 2px 8px rgba(255, 107, 53, 0.3);
}
.rank-number[data-community="quasarzone"] {
  background: linear-gradient(135deg, var(--quasarzone-color), #e6820a);
  box-shadow: 0 2px 8px rgba(255, 146, 0, 0.3);
}
.rank-number[data-community="clien"] {
  background: linear-gradient(135deg, var(--clien-color), #2c3458);
  box-shadow: 0 2px 8px rgba(55, 67, 115, 0.3);
}

.rank-number[data-community="eomisae"] {
  background: var(--eomisae-color);
  box-shadow: 0 2px 8px rgba(232, 122, 114, 0.3);
}
.rank-number[data-community="ruliweb"] {
  background: linear-gradient(135deg, var(--ruliweb-color), #4270d0);
  box-shadow: 0 2px 8px rgba(83, 131, 232, 0.3);
}

.rank-number.rank-1::after {
  content: '👑';
  position: absolute;
  top: -4px;
  right: -4px;
  font-size: 10px;
}
.rank-number.rank-2::after {
  content: '🥈';
  position: absolute;
  top: -4px;
  right: -4px;
  font-size: 8px;
}
.rank-number.rank-3::after {
  content: '🥉';
  position: absolute;
  top: -4px;
  right: -4px;
  font-size: 8px;
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

.source-label[data-community="coupang"] { 
  background: var(--coupang-color); 
}
.source-label[data-community="quasarzone"] { 
  background: var(--quasarzone-color); 
}
.source-label[data-community="clien"] { 
  background: var(--clien-color); 
}

.source-label[data-community="eomisae"] { 
  background: var(--eomisae-color); 
}
.source-label[data-community="ruliweb"] { 
  background: var(--ruliweb-color); 
}

.item-title {
  font-weight: 600 !important;
  font-size: 15px;
  line-height: 1.4;
  flex: 1;
  min-width: 0;
  word-break: break-word;
  margin-bottom: 4px;
}

/* 제목 내부의 모든 텍스트를 동일한 폰트 웨이트로 강제 설정 */
.item-title * {
  font-weight: 600 !important;
}

a.list-item:visited .item-title {
  color: #BBBBBB !important;
  opacity: 0.8 !important;
}

.hotdeal-price {
  font-weight: 700;
  color: #dc2626;
  font-size: 16px;
  margin-bottom: 4px;
}

.hotdeal-store {
  background: #f3f4f6;
  color: #374151;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 500;
  display: inline-block;
  margin-right: 8px;
}

.comments-count {
  color: var(--primary-color);
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;      
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
  .time-selector-native {
    min-width: 80px;
    padding: 6px 10px;
    padding-right: 24px;
    font-size: 14px;
  }

  .sort-section {
    flex: 1;
    min-width: 0;
    overflow: hidden;
  }

  .sort-controls ul {
    justify-content: flex-start;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }

  .sort-controls ul::-webkit-scrollbar {
    display: none;
  }

  .time-section {
    flex-shrink: 0;
    margin-left: 8px;
  }

  .time-selector-native {
    min-width: 60px;
    font-size: 15px;
    padding: 4px 8px;
    padding-right: 20px;
  }

  .sort-controls li a {
    font-size: 12px;
    padding: 4px 6px;
  }

  .community-nav {
    gap: 0;
  }

  .nav-menu {
    font-size: 15px;
    padding: 10px 12px;
  }

  .list-item {
    padding: 12px;
  }
  
  .hotdeal-thumbnail, .no-thumbnail {
    width: 60px;
    height: 60px;
  }
  
  .source-label {
    font-size: 10px;
    padding: 2px 6px;
  }
  
  .item-title {
    font-size: 14px;
  }

  .sort-controls li a {
    font-size: 13px;
    padding: 5px 8px;
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

/* 하이라이트된 핫딜 아이템 스타일 */
.highlighted-item {
  border: 4px solid #ff9200;
  background: linear-gradient(135deg, #fff3e6 0%, #ffe6cc 100%);
  box-shadow: 0 8px 25px rgba(255, 146, 0, 0.3);
  position: relative;
  transform: scale(1.02);
  margin-top: 16px;    /* 상단 여백 추가 */
  margin-bottom: 12px;
  border-radius: 12px;
  transition: all 0.3s ease;
}

/* 클리앙 탭일 때는 파란색으로 */
.highlighted-item.clien-style {
  border-color: #374373;
  background: linear-gradient(135deg, #f0f2ff 0%, #e6eaff 100%);
  box-shadow: 0 8px 25px rgba(55, 67, 115, 0.3);
}

.highlighted-item::before {
  content: '🔥 HOT DEAL';
  position: absolute;
  top: -12px;
  left: 20px;
  background: #ff9200;
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  animation: bounce 1.5s infinite;
  z-index: 10;
}

/* 클리앙 탭일 때 뱃지 색상 변경 */
.highlighted-item.clien-style::before {
  background: #374373;
}

.highlighted-item .item-title {
  color: #d4530a;
  font-weight: 600;
}

/* 클리앙일 때 제목 색상 */
.highlighted-item.clien-style .item-title {
  color: #2a3460;
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
  
  .share-button {
    padding: 6px;
    margin-left: 4px;
  }
  
  .share-button svg {
    width: 14px;
    height: 14px;
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
      <a href="/" class="service-link active">핫딜(beta)</a>
    </div>
  </div>
</div>

<div class="community-section">
  <div class="section-label">핫딜 사이트 선택</div>
  <ul class="community-nav">
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item active" data-source="all">
        <span class="nav-menu">전체</span>
      </a>
    </li>
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item" data-source="coupang">
        <span class="nav-menu">쿠팡</span>
      </a>
    </li>
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item" data-source="quasarzone">
        <span class="nav-menu">퀘사이존</span>
      </a>
    </li>
<!-- 클리앙 삭제
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item" data-source="clien">
        <span class="nav-menu">클리앙</span>
      </a>
    </li>
-->    
      <li class="nav-item-wrapper">
    <a href="#" class="nav-item" data-source="eomisae">
      <span class="nav-menu">어미새</span>
         </a>
  </li>
        <li class="nav-item-wrapper">
    <a href="#" class="nav-item" data-source="ruliweb">
      <span class="nav-menu">루리웹</span>
         </a>
  </li>
  </ul>
</div>

<!-- 배너 -->
<div id="topBanner" style="
  background: linear-gradient(90deg, rgba(100,116,139,0.9) 0%, rgba(71,85,105,0.9) 50%, rgba(51,65,85,0.9) 100%);
  color: white;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  letter-spacing: -0.2px;
  max-width: 800px;
  margin: 0 auto;
  cursor: pointer;
  transition: all 0.3s ease;
" onclick="toggleBanner()">
  <div style="display: flex; justify-content: space-between; align-items: center;">
    <span id="firstLine">🔥 파트너스 활동을 통해 일정액의 수수료를 제공받을 수 있음</span>
    <span id="toggleIcon" style="font-size: 10px;">▼</span>
  </div>
  <div id="bannerContent" style="
    display: none;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,0.2);
  ">
    <div>🔥 [11/20] 클리앙 제외</div>
    <div>🔥 [10/02] 루리웹 추가</div>
    <div>🔥 [09/16] 핫딜 Beta 오픈! - 업데이트가 수시로 진행됩니다.</div>
    <div>🎯 실시간 핫딜 크롤링으로 놓치지 않는 특가 정보</div>
    <div>💰 가격, 할인율, 쇼핑몰 정보 한눈에 확인</div>
    <div>💬 댓글과 조회수로 인기 핫딜 파악</div>
    <div>📱 모바일 최적화로 언제 어디서나 편리하게</div>
  </div>
</div>

<!-- 리스트 컨테이너 -->
<div class="list-container">
  <div id="list">
    <div class="loading">핫딜 데이터를 불러오는 중...</div>
  </div>
</div>

<script>
  (function() {
    'use strict';
    
    var listEl = document.getElementById('list');
    var currentSettings = {
      time: '3d',
      sort: 'latest',
      source: 'all'
    };

    // 배너 토글 함수
    window.toggleBanner = function() {
      var content = document.getElementById('bannerContent');
      var icon = document.getElementById('toggleIcon');
      
      if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▲';
        localStorage.setItem('bannerExpanded', 'true');
      } else {
        content.style.display = 'none';
        icon.textContent = '▼';
        localStorage.setItem('bannerExpanded', 'false');
      }
    };


// 핫딜 사이트 선택
document.addEventListener('click', function(e) {
  var navItem = e.target.closest('.nav-item');
  if (navItem && navItem.dataset.source) {
    e.preventDefault();
    
    var sourceValue = navItem.dataset.source;
    
    // 모든 항목에서 active 클래스 제거
    document.querySelectorAll('.nav-item').forEach(function(btn) {
      btn.classList.remove('active');
    });
    
    // 클릭된 항목만 active 클래스 추가
    navItem.classList.add('active');
    
    // 설정 업데이트
    currentSettings.source = sourceValue;
    
    // 하이라이트 항목 제거 및 URL에서 highlight 파라미터 제거
    removeHighlight();
    
    // URL 업데이트
    updateURL(sourceValue);
    renderList();
  }
});

    var hotdealsData = [];

    function formatNumber(num) {
      if (num >= 10000) return Math.floor(num / 1000) + 'k';
      if (num >= 1000) return (num / 1000).toFixed(1).replace('.0', '') + 'k';
      return String(num);
    }

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

    function formatTimeAgo(dateString) {
      if (!dateString) return '';
      
      var now = new Date();
      var date = new Date(dateString);
      var diffMs = now - date;
      var diffMins = Math.floor(diffMs / 60000);
      var diffHours = Math.floor(diffMins / 60);
      var diffDays = Math.floor(diffHours / 24);
      
      if (diffMins < 1) return '방금 전';
      if (diffMins < 60) return diffMins + '분 전';
      if (diffHours < 24) return diffHours + '시간 전';
      if (diffDays < 7) return diffDays + '일 전';
      
      return date.getFullYear() + '.' + String(date.getMonth() + 1).padStart(2, '0') + '.' + String(date.getDate()).padStart(2, '0');
    }

// 하이라이트 제거 함수
function removeHighlight() {
  // 하이라이트된 항목 DOM에서 제거
  var highlightedItem = document.querySelector('.highlighted-item');
  if (highlightedItem) {
    highlightedItem.remove();
  }
  
  // regular-list 컨테이너가 있다면 제거하고 원래 구조로 복원
  var regularList = document.getElementById('regular-list');
  if (regularList) {
    regularList.remove();
  }
  
  // URL에서 highlight 파라미터 제거
  var url = new URL(window.location);
  url.searchParams.delete('highlight');
  window.history.replaceState({}, '', url.toString());
}


function loadHotdealsData() {
  var url = '/api/hotdeals.php?source=' + currentSettings.source + '&sort=' + currentSettings.sort + '&time=' + currentSettings.time;
  
  // 하이라이트된 항목이 있는지 확인
  var hasHighlight = document.querySelector('.highlighted-item');
  if (!hasHighlight) {
    listEl.innerHTML = '<div class="loading">핫딜 데이터를 불러오는 중...</div>';
  }
      
      fetch(url)
        .then(function(response) {
          return response.json();
        })
        .then(function(result) {
          if (result.success) {
            hotdealsData = result.data;
            renderHotdealsList();
          } else {
            throw new Error(result.error || '데이터 로드 실패');
          }
        })
        .catch(function(error) {
          console.error('API 호출 실패:', error);
          listEl.innerHTML = '<div class="loading">데이터 로딩에 실패했습니다.</div>';
        });
    }

    
function renderHotdealsList() {
  if (!hotdealsData || hotdealsData.length === 0) {
    var targetEl = document.getElementById('regular-list') || listEl;
    targetEl.innerHTML = '<div class="loading">핫딜 데이터가 없습니다.</div>';
    return;
  }
  
  var html = '';
  
  for (var i = 0; i < hotdealsData.length; i++) {
    var item = hotdealsData[i];
    html += renderHotdealItem(item, i + 1);
  }
  
  // 하이라이트된 항목이 있으면 regular-list에, 없으면 listEl에 렌더링
  var targetEl = document.getElementById('regular-list') || listEl;
  targetEl.innerHTML = html;
}



function renderHotdealItem(item, rank) {
  // 사이트 매핑 정보 추가
var siteMapping = {
  2: { key: 'quasarzone', name: '퀘사이존', shortName: '퀘사이존' },
  3: { key: 'clien', name: '클리앙', shortName: '클리앙' },
  5: { key: 'eomisae', name: '어미새', shortName: '어미새' },
  6: { key: 'ruliweb', name: '루리웹', shortName: '루리웹' },
  99: { key: 'coupang', name: '쿠팡', shortName: '쿠팡' }
};

  var communityInfo = siteMapping[item.source_id] || { 
    key: 'quasarzone', name: '퀘사이존', shortName: '퀘사이존'
  };

  var rankClass = getRankClass(rank);
  var commentsHtml = (item.comment_count !== null && item.comment_count !== undefined && item.comment_count > 0) ? 
    '(' + formatNumber(item.comment_count) + ')' : '';
  
  var thumbnailHtml = '';
  if (item.thumbnail_url) {
    thumbnailHtml = '<img src="' + escapeHtml(item.thumbnail_url) + '" alt="상품 이미지" class="hotdeal-thumbnail" onerror="this.outerHTML=\'<div class=&quot;no-thumbnail&quot;>이미지<br>없음</div>\'">';
  } else {
    thumbnailHtml = '<div class="no-thumbnail">이미지<br>없음</div>';
  }

  var priceHtml = item.price ? '<div class="hotdeal-price">' + escapeHtml(item.price) + '</div>' : '';
  var storeHtml = item.store_name ? '<span class="hotdeal-store">' + escapeHtml(item.store_name) + '</span>' : '';

return '<div class="list-item-wrapper" style="display: flex; align-items: flex-start;">' +
    '<a href="' + escapeHtml(item.original_url) + '" class="list-item" data-id="' + item.public_id + '" style="flex: 1;">' +
    '<div class="rank-number ' + rankClass + '" data-community="' + communityInfo.key + '">' + rank + '</div>' +
    thumbnailHtml +
    '<div class="item-content">' + 
    '<div class="item-title">' + escapeHtml(item.title) + 
    (commentsHtml ? ' <span class="comments-count">' + commentsHtml + '</span>' : '') +
    '</div>' +
    
    '<div class="item-meta">' +
    '<div class="source-label" data-community="' + communityInfo.key + '">' + communityInfo.shortName + '</div>' +
    '<span class="meta-item">⏰ ' + formatTimeAgo(item.original_created_at) + '</span>' +
    
    '</div>' +
    '</div>' +
    '</a>' +
    '<button class="share-button" onclick="shareHotdeal(\'' + item.public_id + '\', event)">' +
    '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92S19.61 16.08 18 16.08z"/></svg>' +
    '</button>' +
    '</div>';


}   

function updateURL(source) {
  if (source === 'all') {
    window.history.pushState({}, '', '/deal/');
  } else {
    window.history.pushState({}, '', '/deal/?db=' + source);
  }
}

function renderList() {
      loadHotdealsData();
    }

// 공유 함수
window.shareHotdeal = function(publicId, event) {
  event.preventDefault();
  event.stopPropagation();
  
  var currentUrl = window.location.origin + '/deal/?highlight=' + publicId;
  
  navigator.clipboard.writeText(currentUrl).then(function() {
    alert('공유 링크가 복사되었습니다!');
  }).catch(function() {
    prompt('공유 링크를 복사하세요:', currentUrl);
  });
};

window.loadHighlightedItem = function(publicId) {
  fetch('/api/hotdeals.php?id=' + publicId)
    .then(function(response) {
      return response.json();
    })
    .then(function(result) {
      if (result.success && result.data.length > 0) {
        var highlightedItem = result.data[0];
        renderHighlightedItem(highlightedItem);
        // loadHotdealsData() 제거 - renderHighlightedItem에서 처리
      } else {
        loadHotdealsData();
      }
    })
    .catch(function(error) {
      console.error('하이라이트 항목 로드 실패:', error);
      loadHotdealsData();
    });
};

window.renderHighlightedItem = function(item) {
  var highlightHtml = renderHotdealItem(item, '★');
  
  // 현재 소스에 따라 클래스 추가
  var styleClass = currentSettings.source === 'clien' ? 'clien-style' : '';
  var wrappedHtml = '<div class="highlighted-item ' + styleClass + '">' + highlightHtml + '</div>';
  
  // 하이라이트 항목 먼저 표시
  listEl.innerHTML = wrappedHtml + '<div id="regular-list"><div class="loading">목록을 불러오는 중...</div></div>';
  
  // 일반 목록을 별도로 로드
  setTimeout(function() {
    loadHotdealsData();
  }, 100);
};





function init() {
  // URL 파라미터 확인
  const urlParams = new URLSearchParams(window.location.search);
  const dbSource = urlParams.get('db');
  const highlightId = urlParams.get('highlight');
  
  // db 파라미터에서 source 추출
if (dbSource) {
  const validSources = ['all', 'coupang', 'quasarzone', 'clien', 'eomisae', 'ruliweb'];
  if (validSources.includes(dbSource)) {
    currentSettings.source = dbSource;
  }
}
  
    
      

      
      // 핫딜 사이트 UI 복원
      document.querySelectorAll('.nav-item').forEach(function(btn) {
        if (btn.dataset.source === currentSettings.source) {
          btn.classList.add('active');
        } else {
          btn.classList.remove('active');
        }
      });
      
      // 배너 상태 복원
      if (localStorage.getItem('bannerExpanded') === 'true') {
        toggleBanner();
      }
      
// 하이라이트 ID가 있으면 해당 항목을 우선 로드
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