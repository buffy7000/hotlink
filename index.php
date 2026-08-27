<?php
// OG 태그 기본값
$ogTitle = "핫링크 - 커뮤니티 인기글";
$ogDescription = "클리앙, 루리웹, 뽐뿌 등 주요 커뮤니티 인기글을 한 곳에서 확인하세요";
$ogImage = "https://hotlink.kr/og_image.png";
$ogUrl = "https://hotlink.kr" . $_SERVER['REQUEST_URI'];

// post_id 파라미터가 있으면 해당 게시글 정보 조회
if (isset($_GET['highlight']) && !empty($_GET['highlight'])) {
    $postId = intval($_GET['highlight']);  // ← post_id 대신 highlight
    
    try {
        // 데이터베이스 연결
        require_once(__DIR__ . '/config/database.php');
        $db = new Database();
        $debugLog[] = "DB 연결 성공";
        
        // 게시글 정보 조회
        $stmt = $db->query("
            SELECT title, url, thumbnail_url 
            FROM posts 
            WHERE id = ?
        ", [$postId]);
        
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        $debugLog[] = "쿼리 실행 완료";
        
        if ($post) {
            $debugLog[] = "게시글 찾음";
            $debugLog[] = "제목: " . $post['title'];
            $debugLog[] = "썸네일: " . ($post['thumbnail_url'] ?? 'NULL');
            
            // OG 제목: 게시글 제목
            $ogTitle = $post['title'];
            
            // OG 설명: "핫링크 - 커뮤니티 인기글" 고정
            $ogDescription = "핫링크 - 커뮤니티 인기글";
            
            // OG 이미지: 썸네일이 있으면 사용
            if (!empty($post['thumbnail_url'])) {
                $ogImage = $post['thumbnail_url'];
                $debugLog[] = "OG 이미지 설정: " . $ogImage;
            } else {
                $debugLog[] = "썸네일 없음 - 기본 이미지 사용";
            }
        } else {
            $debugLog[] = "게시글을 찾을 수 없음";
        }
        
    } catch (Exception $e) {
        // 에러 발생시 기본값 유지
        $debugLog[] = "에러: " . $e->getMessage();
        error_log("OG 태그 생성 중 오류: " . $e->getMessage());
    }
} else {
    $debugLog[] = "post_id 파라미터 없음";
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
  <title>커뮤니티 모음 사이트 | 클리앙·오유·뽐뿌 인기글 한번에 - 핫링크</title>
  <meta name="description" content="클리앙, 오늘의유머, 뽐뿌, 루리웹 등 주요 커뮤니티 인기글을 한 페이지에서 모아보세요. 실시간 업데이트, 커뮤니티별 반응 비교.">
  <meta name="keywords" content="커뮤니티 인기글, 클리앙, 루리웹, 뽐뿌, 네이트판, 오유, 보배드림">
  
  <!-- Open Graph 태그 -->
  <!-- 동적 OG 태그 -->
<meta property="og:title" content="<?php echo escapeOgContent($ogTitle); ?>">
<meta property="og:description" content="<?php echo escapeOgContent($ogDescription); ?>">
<meta property="og:url" content="<?php echo escapeOgContent($ogUrl); ?>">
<meta property="og:image" content="<?php echo escapeOgContent($ogImage); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
  
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="핫링크">
    <meta property="og:locale" content="ko_KR">
  
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
      
      /* 커뮤니티별 브랜드 색상 */
      --ppomppu-color: #D7D7D7;
      --clien-color: #374373;
      --natepann-color: #be185d;
      --ruliweb-color: #1A70DC;
      --theqoo-color: #344A65;
      --mlbpark-color: #7c3aed;
      --bobaedream-color: #0ea5e9;
      --humoruniv-color: #f59e0b;
      --todayhumor-color: #d97706;
      --inven-color: #1C3F6E;
      --slrclub-color: #C0392B;
      --etoland-color: #3faf42;
    }

    /* @media (prefers-color-scheme: dark) {
      :root {
        --bg-color: #0f172a;
        --card-bg-color: #1e293b;
        --text-color: #f1f5f9;
        --muted-text-color: #94a3b8;
        --border-color: #334155;
      }
      .time-dropdown-menu {
        background: #1e293b;
      }
    }
    */

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

..header {
  background: white;
  color: var(--text-color);
  padding: 0px 0px;
  /* position: sticky 제거 - 헤더는 스크롤과 함께 사라짐 */
}

.community-section {
  position: sticky;
  top: 0;  /* 이제 맨 위에 고정 */
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
  padding: 12px 16px 12px 16px;  /* 상하 패딩을 포함시킴 */
  margin: 0;                     /* 모든 마진 제거 */
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

.blind {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
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
      display: none;  /* 라벨 숨김 */
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
  touch-action: pan-x;  /* 가로 스크롤만 허용 */
  gap: 0;
  background: #082567;
  scroll-behavior: smooth;  /* 부드러운 스크롤 */
  -webkit-overflow-scrolling: touch;  /* iOS에서 부드러운 스크롤 */
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
      color: rgba(255, 255, 255, 0.7);    /* 비활성화된 항목 색상 */
      white-space: nowrap;
      transition: all 0.2s ease;
      border-bottom: 2px solid transparent;
      position: relative;
    }

    /* 활성화된 항목만 밑줄과 흰색 */
    .nav-item.active .nav-menu {
      color: white;  /* 활성화된 항목 색상 */
      font-weight: 600;
      border-bottom-color: white;  /* 흰색 밑줄 */
    }

    /* 호버 효과 (비활성화된 항목에만) */
    .nav-item:hover:not(.active) .nav-menu {
      color: rgba(255, 255, 255, 0.9);
    }

    /* 비활성화된 항목은 밑줄 없음 */
    .nav-item:not(.active) .nav-menu {
      border-bottom-color: transparent;
    }

/* 정렬/시간 선택 영역 - 흰색 배경으로 변경 */



.sort-section {
  flex: none;  /* flex: 1을 none으로 변경 */
}

.time-section {
  flex-shrink: 0;
  margin-left: auto;  /* 오른쪽으로 밀어내기 */
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
  padding: 8px 12px;     /* 패딩 키움 */
  padding-right: 28px;   /* 우측 패딩도 키움 */
  font-size: 16px;       /* 글자 크기 키움 */
  cursor: pointer;
  min-width: 80px;       /* 최소 너비도 키움 */
  width: auto;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  outline: none;
  -webkit-tap-highlight-color: transparent;
  background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");  /* 화살표 색상도 어두운 회색으로 변경 */
  background-repeat: no-repeat;
  background-position: right 8px center;
  background-size: 12px;
}

.time-selector-native:focus {
  outline: none;
  border: 1px solid var(--primary-color);  /* 포커스 시 파란색 테두리 */
  box-shadow: none;
}

    
    .time-selector-native:focus {
      outline: none;
      border: 1px solid rgba(255,255,255,0.5);
      box-shadow: none;
    }
    
    /*@media (prefers-color-scheme: dark) {
      .time-selector-native {
        background: rgba(255,255,255,0.2) !important;
        color: rgba(255,255,255,0.8) !important;
        outline: none !important;
      }
      
      .time-selector-native:focus {
        outline: none !important;
        border: 1px solid rgba(255,255,255,0.5) !important;
      }
      
      .time-selector-native option {
        background: #1e293b;
        color: #f1f5f9;
      }
    }
    */

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
      overflow: hidden;
    }

    .list-item {
      display: flex;
      align-items: center;
      padding: 12px 16px;
      border-bottom: 1px solid var(--border-color);
      transition: all 0.15s ease;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
    }

    .list-item:hover {
      background-color: var(--bg-color);
    }

    .list-item:last-child {
      border-bottom: none;
    }

    .rank-number {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      font-weight: 700;
      font-size: 16px;
      color: white;
      border-radius: 8px;
      margin-right: 12px;
      flex-shrink: 0;
      position: relative;
    }

    .rank-number[data-community="ppomppu"] {
      background: linear-gradient(135deg, var(--ppomppu-color), #BFBFBF);
      box-shadow: 0 2px 8px rgba(215, 215, 215, 0.3);
    }
    .rank-number[data-community="clien"] {
      background: linear-gradient(135deg, var(--clien-color), #374373);
      box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
    }
    .rank-number[data-community="natepann"] {
      background: linear-gradient(135deg, var(--natepann-color), #9f1239);
      box-shadow: 0 2px 8px rgba(190, 24, 93, 0.2);
    }
    .rank-number[data-community="ruliweb"] {
      background: linear-gradient(135deg, var(--ruliweb-color), #1560BD);
      box-shadow: 0 2px 8px rgba(26, 112, 220, 0.2);
    }
    .rank-number[data-community="theqoo"] {
      background: linear-gradient(135deg, var(--theqoo-color), #2c3e50);
      box-shadow: 0 2px 8px rgba(52, 74, 101, 0.2);
    }
    .rank-number[data-community="mlbpark"] {
      background: linear-gradient(135deg, var(--mlbpark-color), #6d28d9);
      box-shadow: 0 2px 8px rgba(124, 58, 237, 0.2);
    }
    .rank-number[data-community="bobaedream"] {
      background: linear-gradient(135deg, var(--bobaedream-color), #0284c7);
      box-shadow: 0 2px 8px rgba(14, 165, 233, 0.2);
    }
    .rank-number[data-community="humoruniv"] {
      background: linear-gradient(135deg, var(--humoruniv-color), #d97706);
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
    }
    .rank-number[data-community="todayhumor"] {
      background: linear-gradient(135deg, var(--todayhumor-color), #b45309);
      box-shadow: 0 2px 8px rgba(217, 119, 6, 0.2);
    }
    .rank-number[data-community="inven"] {
      background: linear-gradient(135deg, var(--inven-color), #0f2a50);
      box-shadow: 0 2px 8px rgba(28, 63, 110, 0.3);
    }
    .rank-number[data-community="slrclub"] {
      background: linear-gradient(135deg, var(--slrclub-color), #922b21);
      box-shadow: 0 2px 8px rgba(192, 57, 43, 0.3);
    }
    .rank-number[data-community="etoland"] {
      background: linear-gradient(135deg, var(--etoland-color), #2a7a2d);
      box-shadow: 0 2px 8px rgba(63, 175, 66, 0.3);
    }

    .rank-number.rank-1::after {
      content: '👑';
      position: absolute;
      top: -4px;
      right: -4px;
      font-size: 12px;
    }
    .rank-number.rank-2::after {
      content: '🥈';
      position: absolute;
      top: -4px;
      right: -4px;
      font-size: 10px;
    }
    .rank-number.rank-3::after {
      content: '🥉';
      position: absolute;
      top: -4px;
      right: -4px;
      font-size: 10px;
    }

    .item-content {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .title-row {
      display: flex;
      align-items: center;
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

    .source-label[data-community="ppomppu"] { background: var(--ppomppu-color); }
    .source-label[data-community="clien"] { background: var(--clien-color); }
    .source-label[data-community="natepann"] { background: var(--natepann-color); }
    .source-label[data-community="ruliweb"] { background: var(--ruliweb-color); }
    .source-label[data-community="theqoo"] { background: var(--theqoo-color); }
    .source-label[data-community="mlbpark"] { background: var(--mlbpark-color); }
    .source-label[data-community="bobaedream"] { background: var(--bobaedream-color); }
    .source-label[data-community="humoruniv"] { background: var(--humoruniv-color); }
    .source-label[data-community="todayhumor"] { background: var(--todayhumor-color); }
    .source-label[data-community="inven"] { background: var(--inven-color); }
    .source-label[data-community="slrclub"] { background: var(--slrclub-color); }
    .source-label[data-community="etoland"] { background: var(--etoland-color); }

    .item-title {
      font-weight: 600;
      font-size: 15px;
      line-height: 1.3;
      flex: 1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: flex;
      align-items: baseline;
      gap: 4px;
    }

    a.list-item:visited .item-title {
      color: #BBBBBB !important;
      opacity: 0.8 !important;
    }

    @media (prefers-color-scheme: dark) {
      a.list-item:visited .item-title {
        color: #a0aec0 !important;
      }
    }

    .comments-count {
      flex-shrink: 0;
      font-size: 13px;
      color: var(--muted-text-color);
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

    .meta-item .time-icon {
      color: var(--muted-text-color);
    }
    
    .item-meta .meta-item .time-icon {
      color: var(--muted-text-color) !important;
    }

@media (max-width: 480px) {
  .time-selector-native {
    min-width: 80px;     /* 모바일에서도 크게 */
    padding: 6px 10px;   /* 모바일 패딩 */
    padding-right: 24px;
    font-size: 14px;     /* 모바일 글자 크기 */
  }
}

  .sort-section {
    flex: 1;
    min-width: 0;  /* flex 아이템이 축소될 수 있도록 */
    overflow: hidden;  /* 넘치는 부분 숨김 */
  }

  .sort-controls ul {
    justify-content: flex-start;  /* 왼쪽 정렬 */
    overflow-x: auto;  /* 가로 스크롤 허용 */
    scrollbar-width: none;
    -ms-overflow-style: none;
  }

  .sort-controls ul::-webkit-scrollbar {
    display: none;
  }

  .time-section {
    flex-shrink: 0;
    margin-left: 8px;  /* auto 대신 고정 마진 */
  }

  .time-selector-native {
    min-width: 60px;  /* 더 작게 */
    font-size: 15px;  /* 더 작은 글자 */
    padding: 4px 8px;
    padding-right: 20px;
  }

  .sort-controls li a {
    font-size: 12px;  /* 13px에서 12px로 줄임 */
    padding: 4px 6px;  /* 패딩도 줄임 */
  }
   

      .community-nav {
        gap: 0;
      }

      .nav-menu {
        font-size: 15px;
        padding: 10px 12px;
      }

      .list-item {
        padding: 10px 12px;
      }
      
      .rank-number {
        width: 28px;
        height: 28px;
        font-size: 14px;
        margin-right: 10px;
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

    .hidden { 
      display: none !important; 
    }

/* 날짜 네비게이션 섹션 */
.date-navigation-section {
  position: sticky;
  top: 94px;
  z-index: 97;
  background: #f8fafc;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-bottom: 1px solid var(--border-color);
}

.date-nav-btn {
  background: white;
  border: 1px solid #d1d5db;
  color: #64748b;
  cursor: pointer;
  padding: 8px 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: all 0.2s;
  flex-shrink: 0;
}

.date-nav-btn:hover:not(:disabled) {
  background: #f1f5f9;
  color: var(--primary-color);
  border-color: var(--primary-color);
}

.date-nav-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.date-nav-btn:active:not(:disabled) {
  transform: scale(0.95);
}

.date-display-wrapper {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px 16px;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}

.date-display-wrapper:hover {
  border-color: var(--primary-color);
  background: #f8fafc;
}

.date-display {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  user-select: none;
  min-width: 90px;
  text-align: center;
  display: inline-block;
}

.date-calendar-btn {
  background: white;
  border: 1px solid #d1d5db;
  color: #64748b;
  cursor: pointer;
  padding: 8px 12px;
  font-size: 16px;
  border-radius: 6px;
  transition: all 0.2s;
  flex-shrink: 0;
}

.date-calendar-btn:hover {
  background: #f1f5f9;
  border-color: var(--primary-color);
}

.date-today-btn {
  background: white;
  border: 1px solid #d1d5db;
  color: #64748b;
  cursor: pointer;
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  border-radius: 6px;
  transition: all 0.2s;
  white-space: nowrap;
  flex-shrink: 0;
}

.date-today-btn:hover:not(:disabled) {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.date-today-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.date-selector-native {
  position: absolute;
  opacity: 0;
  pointer-events: none;
  width: 1px;
  height: 1px;
}

.time-selector-native:disabled {
  background: #f1f5f9;
  color: #cbd5e1;
  cursor: not-allowed;
  opacity: 0.6;
}

/* 모바일에서 스와이프 개선 */
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

    /* 모바일 날짜 네비게이션 */
    @media (max-width: 480px) {
      .date-navigation-section {
        padding: 8px 12px;
        gap: 6px;
      }
      
      .date-nav-btn {
        padding: 6px 8px;
      }
      
      .date-nav-btn svg {
        width: 18px;
        height: 18px;
      }
      
      .date-display-wrapper {
        padding: 6px 12px;
      }
      
      .date-display {
        font-size: 14px;
        min-width: 80px;
      }
      
      .date-calendar-btn {
        padding: 6px 10px;
        font-size: 15px;
      }
      
      .date-today-btn {
        padding: 6px 12px;
        font-size: 13px;
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

/* 하이라이트된 게시글 아이템 스타일 */
.highlighted-item {
  border: 4px solid #374373;
  background: linear-gradient(135deg, #f0f2ff 0%, #e6eaff 100%);
  box-shadow: 0 8px 25px rgba(55, 67, 115, 0.3);
  position: relative;
  transform: scale(1.02);
  margin-top: 16px;
  margin-bottom: 12px;
  border-radius: 12px;
  transition: all 0.3s ease;
}

.highlighted-item::before {
  content: '🔥 HOT POST';
  position: absolute;
  top: -12px;
  left: 20px;
  background: #374373;
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  animation: bounce 1.5s infinite;
  z-index: 10;
}

.highlighted-item .item-title {
  color: #2a3460;
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
  
  .share-button {
    padding: 6px;
    margin-left: 4px;
  }
  
  .share-button svg {
    width: 14px;
    height: 14px;
  }
}

/* 인벤 토스트 알림 */
.toast-notification {
  position: fixed;
  bottom: 24px;
  right: 24px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.15);
  padding: 16px 20px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  z-index: 9999;
  max-width: 320px;
  cursor: pointer;
  border-left: 4px solid var(--inven-color);
}

#slrclubToast {
  border-left-color: var(--slrclub-color);
}

#etolandToast {
  border-left-color: var(--etoland-color); /* #ED3939 공식 브랜드 컬러 */
  transform: translateX(calc(100% + 32px));
  opacity: 0;
  transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.35s ease;
}

.toast-notification.show {
  transform: translateX(0);
  opacity: 1;
}

.toast-notification.hide {
  transform: translateX(calc(100% + 32px));
  opacity: 0;
  transition: transform 0.25s ease, opacity 0.25s ease;
}

.toast-icon {
  font-size: 28px;
  line-height: 1;
  flex-shrink: 0;
}

.toast-content {
  flex: 1;
  min-width: 0;
}

.toast-title {
  font-size: 14px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 4px;
}

.toast-desc {
  font-size: 13px;
  color: #64748b;
  line-height: 1.4;
}

.toast-close {
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0;
  font-size: 16px;
  line-height: 1;
  flex-shrink: 0;
}

.toast-close:hover {
  color: #475569;
}

@media (max-width: 480px) {
  .toast-notification {
    bottom: 16px;
    right: 16px;
    left: 16px;
    max-width: none;
    transform: translateY(calc(100% + 32px));
  }
  .toast-notification.show {
    transform: translateY(0);
  }
  .toast-notification.hide {
    transform: translateY(calc(100% + 32px));
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
      <a href="/" class="service-link active">커뮤니티</a>
      <a href="/deal" class="service-link">핫딜(Beta)</a>
    </div>
  </div>
</div>

<!-- 1. 네이버 엔터테인먼트 스타일 커뮤니티 선택 - 헤더 밖으로 이동 -->
<div class="community-section">
  <div class="section-label">커뮤니티 선택</div>
  <ul class="community-nav">
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item active" data-community="all">
        <span class="nav-menu">전체</span>
      </a>
    </li>
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item" data-community="ppomppu">
        <span class="nav-menu">뽐뿌</span>
      </a>
    </li>
    <li class="nav-item-wrapper">
      <a href="#" class="nav-item" data-community="clien">
        <span class="nav-menu">클리앙</span>
      </a>
    </li>

<li class="nav-item-wrapper">
  <a href="#" class="nav-item" data-community="natepann">
    <span class="nav-menu">네이트판</span>
  </a>
</li>
<li class="nav-item-wrapper">
  <a href="#" class="nav-item" data-community="ruliweb">
    <span class="nav-menu">루리웹</span>
  </a>
</li>
<li class="nav-item-wrapper">
  <a href="#" class="nav-item" data-community="theqoo">
    <span class="nav-menu">더쿠</span>
  </a>
</li>
<li class="nav-item-wrapper">
  <a href="#" class="nav-item" data-community="mlbpark">
    <span class="nav-menu">엠팍</span>
  </a>
</li>
<li class="nav-item-wrapper">
  <a href="#" class="nav-item" data-community="bobaedream">
    <span class="nav-menu">보배드림</span>
  </a>
</li>
<li class="nav-item-wrapper">
  <a href="#" class="nav-item" data-community="humoruniv">
    <span class="nav-menu">웃대</span>
  </a>
</li>
<li class="nav-item-wrapper">
  <a href="#" class="nav-item" data-community="todayhumor">
    <span class="nav-menu">오유</span>
  </a>
</li>
<li class="nav-item-wrapper">
  <a href="#" class="nav-item" data-community="inven">
    <span class="nav-menu">인벤</span>
  </a>
</li>
<li class="nav-item-wrapper">
  <a href="#" class="nav-item" data-community="slrclub">
    <span class="nav-menu">SLR</span>
  </a>
  <a href="#" class="nav-item" data-community="etoland">
    <span class="nav-menu">이토</span>
  </a>
</li>
  </ul>
</div>

<!-- 2. 정렬/시간 선택 -->
<!-- <div class="filter-section">
  <div class="sort-section">
    <nav class="sort-controls">
      <ul>
        <li class="on" data-sort="hot" aria-current="true">
          <a href="javascript:void(0);" data-sort="hot">🔥 인기</a>
        </li>
        <li data-sort="latest" aria-current="false">
  <a href="javascript:void(0);" data-sort="latest">📅 최신</a>
</li>
<li data-sort="comments" aria-current="false">
  <a href="javascript:void(0);" data-sort="comments">💬 댓글</a>
</li>
<li data-sort="views" aria-current="false">
  <a href="javascript:void(0);" data-sort="views">👁 조회</a>
</li>

      </ul>
    </nav>
  </div>
  <div class="time-section">
    <div class="time-dropdown">
      <select class="time-selector-native" id="timeSelect">
        <option value="3h">3시간</option>
        <option value="6h">6시간</option>
        <option value="12h">12시간</option>
        <option value="24h" selected>24시간</option>
        <option value="3d">3일</option>
      </select>
    </div>
  </div>
</div> -->


<!-- 3. 날짜 네비게이션 (새로 추가) -->
<!-- <div class="date-navigation-section">
  <button class="date-nav-btn" id="prevDateBtn">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polyline points="15 18 9 12 15 6"></polyline>
    </svg>
  </button>
  
  <div class="date-display-wrapper" id="dateDisplayWrapper">
    <span class="date-display" id="dateDisplay">오늘</span>
  </div>
  
 
  
  <button class="date-today-btn" id="todayBtn">오늘</button>
  
  <button class="date-nav-btn" id="nextDateBtn" disabled>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polyline points="9 18 15 12 9 6"></polyline>
    </svg>
  </button>
  
  <input type="date" class="date-selector-native" id="dateSelect">
</div> -->

<!-- 2. 정렬만 -->
<div class="filter-section">
  <div class="sort-section">
    <nav class="sort-controls">
      <ul>
        <li class="on" data-sort="hot" aria-current="true">
          <a href="javascript:void(0);" data-sort="hot">🔥 인기</a>
        </li>
        <li data-sort="latest" aria-current="false">
          <a href="javascript:void(0);" data-sort="latest">📅 최신</a>
        </li>
        <li data-sort="comments" aria-current="false">
          <a href="javascript:void(0);" data-sort="comments">💬 댓글</a>
        </li>
        <li data-sort="views" aria-current="false">
          <a href="javascript:void(0);" data-sort="views">👁 조회</a>
        </li>
      </ul>
    </nav>
  </div>
</div>

<!-- 3. 날짜 네비게이션 + 시간 선택 -->
<div class="date-navigation-section">
  <button class="date-nav-btn" id="prevDateBtn">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polyline points="15 18 9 12 15 6"></polyline>
    </svg>
  </button>
  
  <div class="date-display-wrapper" id="dateDisplayWrapper">
    <span class="date-display" id="dateDisplay">오늘</span>
  </div>
  
  <button class="date-today-btn" id="todayBtn">오늘</button>
  
  <button class="date-nav-btn" id="nextDateBtn" disabled>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polyline points="9 18 15 12 9 6"></polyline>
    </svg>
  </button>
  
  <!-- 시간 선택을 여기로 이동 -->
  <select class="time-selector-native" id="timeSelect">
    <option value="3h">3시간</option>
    <option value="6h">6시간</option>
    <option value="12h" selected>12시간</option>
    <option value="24h">24시간</option>
    <option value="3d">3일</option>
  </select>
  
  <input type="date" class="date-selector-native" id="dateSelect">
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
      <span id="firstLine">📢 [11/30] 공유기능 추가, 공유글 상단 하이라이트</span>
      <span id="toggleIcon" style="font-size: 10px;">▼</span>
    </div>
    <div id="bannerContent" style="
      display: none;
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px solid rgba(255,255,255,0.2);
    ">
      <div>📢 놓치지 않는 실시간 커뮤니티 인기글</div>      
      <div>🔥 루리웹, 뽐뿌, 팸코 등 커뮤니티 화제의 글 한 눈에 파악</div>      
      <div> --------------------------</div>
      <div>📌 [11/30] 공유기능 추가, 공유글 상단 하이라이트</div>
      <div>📌 [11/20] 일자별 최신글 보기 추가</div>
      <div>📌 [11/20] 클리앙 수집 제외</div>
      <div>📌 [09/07] '전체' 커뮤니티별 노출 방식으로 변경</div>
      <div>📌 [08/30] 핫링크 리뉴얼(디자인, 스티키 모드)</div>
      <div>📌 [08/24] 크롤링 시간 수정(루리웹, 더쿠, 엠팍, 보배드림, 웃대, 오유)</div>      
      <div>📌 [08/21] 엠팍/보배드림/웃대/오유 추가</div>          
      <div>📌 [08/20] 더쿠 추가</div>
      <div>📌 [08/15] ① 목록개수 200개로 확대 ② 루리웹 추가</div>
    </div>
  </div>

  <!-- 인벤 추가 토스트 알림 -->
  <div class="toast-notification" id="invenToast">
    <div class="toast-icon">🎮</div>
    <div class="toast-content">
      <div class="toast-title">인벤이 추가됐어요!</div>
      <div class="toast-desc">게임 인기글도 이제 핫링크에서 확인하세요</div>
    </div>
    <button class="toast-close" id="invenToastClose" aria-label="닫기">✕</button>
  </div>

  <!-- SLR클럽 추가 토스트 알림 -->
  <div class="toast-notification" id="slrclubToast">
    <div class="toast-icon">📸</div>
    <div class="toast-content">
      <div class="toast-title">SLR클럽이 추가됐어요!</div>
      <div class="toast-desc">카메라·IT 인기글도 이제 핫링크에서 확인하세요</div>
    </div>
    <button class="toast-close" id="slrclubToastClose" aria-label="닫기">✕</button>
  </div>

  <!-- 이토랜드 추가 토스트 알림 -->
  <div class="toast-notification" id="etolandToast">
    <div class="toast-icon">🔥</div>
    <div class="toast-content">
      <div class="toast-title">이토랜드가 추가됐어요!</div>
      <div class="toast-desc">이토랜드 인기글도 이제 핫링크에서 확인하세요</div>
    </div>
    <button class="toast-close" id="etolandToastClose" aria-label="닫기">✕</button>
  </div>

  <!-- 리스트 컨테이너 -->
  <div class="list-container">
    <div id="list">
      <div class="loading">데이터를 불러오는 중...</div>
    </div>
  </div>


<script>
    (function() {
      'use strict';
      
      // 날짜 포맷 함수들
      function formatDate(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '.' + month + '.' + day;
      }

      function formatDateForInput(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
      }

      function isToday(dateString) {
        var today = new Date();
        var checkDate = new Date(dateString);
        today.setHours(0, 0, 0, 0);
        checkDate.setHours(0, 0, 0, 0);
        return today.getTime() === checkDate.getTime();
      }

      // 시간 필터 활성화/비활성화
      function updateTimeFilterState() {
        var timeSelect = document.getElementById('timeSelect');
        
        if (currentSettings.isToday) {
          timeSelect.disabled = false;
        } else {
          timeSelect.disabled = true;
          currentSettings.time = null;
        }
      }

      // 날짜 표시 및 버튼 상태 업데이트
      function updateDateDisplay() {
        var displayDate = new Date(currentSettings.date);
        var dateText = formatDate(displayDate);  // 항상 날짜 형식으로 표시
        
        document.getElementById('dateDisplay').textContent = dateText;
        
        // 오늘 버튼 활성화/비활성화
        var todayBtn = document.getElementById('todayBtn');
        todayBtn.disabled = currentSettings.isToday;
        
        // 다음 버튼 활성화/비활성화
        var nextBtn = document.getElementById('nextDateBtn');
        nextBtn.disabled = currentSettings.isToday;
        
        updateTimeFilterState();
      }
      
      var listEl = document.getElementById('list');
      var currentSettings = {
        time: '12h',
        sort: 'hot',
        communities: ['all'],
        date: formatDateForInput(new Date()),
        isToday: true
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

// 시간 선택 이벤트
      document.addEventListener('DOMContentLoaded', function() {
        
        // 초기 날짜 표시
        updateDateDisplay();
        
        // 이전 날짜 버튼
        document.getElementById('prevDateBtn').addEventListener('click', function() {
          var currentDate = new Date(currentSettings.date);
          currentDate.setDate(currentDate.getDate() - 1);
          
          currentSettings.date = formatDateForInput(currentDate);
          currentSettings.isToday = false;
          document.getElementById('dateSelect').value = currentSettings.date;
          updateDateDisplay();
          renderList();
        });
        
        // 다음 날짜 버튼
        document.getElementById('nextDateBtn').addEventListener('click', function() {
          var currentDate = new Date(currentSettings.date);
          currentDate.setDate(currentDate.getDate() + 1);
          
          currentSettings.date = formatDateForInput(currentDate);
          currentSettings.isToday = isToday(currentSettings.date);
          document.getElementById('dateSelect').value = currentSettings.date;
          updateDateDisplay();
          renderList();
        });
        
       
        // 날짜 표시 클릭
        document.getElementById('dateDisplayWrapper').addEventListener('click', function() {
          document.getElementById('dateSelect').showPicker();
        });
        
        // 오늘 버튼
        document.getElementById('todayBtn').addEventListener('click', function() {
          currentSettings.date = formatDateForInput(new Date());
          currentSettings.isToday = true;
          currentSettings.time = '12h';
          document.getElementById('dateSelect').value = currentSettings.date;
          document.getElementById('timeSelect').value = '12h';
          updateDateDisplay();
          renderList();
        });
        
        // 날짜 선택기 변경
        document.getElementById('dateSelect').addEventListener('change', function(e) {
          currentSettings.date = e.target.value;
          currentSettings.isToday = isToday(currentSettings.date);
          updateDateDisplay();
          renderList();
        });
        
        var timeSelect = document.getElementById('timeSelect');
        if (timeSelect) {
          timeSelect.addEventListener('change', function(e) {
            var timeValue = e.target.value;
            currentSettings.time = timeValue;

            renderList();
          });
        }
      });


// 정렬 버튼 클릭
document.addEventListener('click', function(e) {
  var sortLink = e.target.closest('.sort-controls a');
  if (sortLink && sortLink.dataset.sort) {
    e.preventDefault();
    var sortValue = sortLink.dataset.sort;
    var parentLi = sortLink.parentElement;
    
    // 모든 li에서 on 클래스 제거하고 aria-current를 false로
    document.querySelectorAll('.sort-controls li').forEach(function(li) {
      li.classList.remove('on');
      li.setAttribute('aria-current', 'false');
    });
    
    // 클릭된 항목에 on 클래스 추가하고 aria-current를 true로
    parentLi.classList.add('on');
    parentLi.setAttribute('aria-current', 'true');
    
    currentSettings.sort = sortValue;

    renderList();
  }
});


// 단일 선택 방식 커뮤니티 선택
document.addEventListener('click', function(e) {
  var navItem = e.target.closest('.nav-item');
  if (navItem) {
    e.preventDefault();
    
    var communityValue = navItem.dataset.community;
    
    // 모든 항목에서 active 클래스 제거
    document.querySelectorAll('.nav-item').forEach(function(btn) {
      btn.classList.remove('active');
    });
    
    // 클릭된 항목만 active 클래스 추가
    navItem.classList.add('active');
    
    // 설정 업데이트 (단일 커뮤니티만)
    if (communityValue === 'all') {
      currentSettings.communities = ['all'];
    } else {
      currentSettings.communities = [communityValue];
    }
    
    // 하이라이트 항목 제거 및 URL에서 highlight 파라미터 제거
    removeHighlight();

    // URL 업데이트 추가 (이 줄이 새로 추가됨)
    updateURL(communityValue);

    renderList();

  }
});
   

/*
      function saveSettings() {
        localStorage.setItem('userSettings', JSON.stringify(currentSettings));
      }
*/
      var mirrorData = [];

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


function loadDataFromAPI() {
  // 하이라이트된 항목이 있는지 확인
  var hasHighlight = document.querySelector('.highlighted-item');
  if (!hasHighlight) {
    listEl.innerHTML = '<div class="loading">데이터를 불러오는 중...</div>';
  }
  
  if (currentSettings.communities.indexOf('all') !== -1) {
    loadDataForAllCommunities();
    return;
  }
  
  var communities = currentSettings.communities.join(',');
  var url = '/api/posts.php?communities=' + communities + '&sort=' + currentSettings.sort;
  
  // 오늘이 아니면 date 파라미터, 오늘이면 time 파라미터
  if (!currentSettings.isToday) {
    url += '&date=' + currentSettings.date;
  } else {
    url += '&time=' + currentSettings.time;
  }

  fetch(url)
    .then(function(response) {
      return response.json();
    })
    .then(function(result) {
      if (result.success) {
        mirrorData = result.data;
        renderOriginalList();
      } else {
        throw new Error(result.error || '데이터 로드 실패');
      }
    })
    .catch(function(error) {
      console.error('API 호출 실패:', error);
      listEl.innerHTML = '<div class="loading">데이터 로딩에 실패했습니다.</div>';
    });
}

// 새로운 함수 추가 (Promise.all 사용)

function loadDataForAllCommunities() {
  var hasHighlight = document.querySelector('.highlighted-item');
  if (!hasHighlight) {
    listEl.innerHTML = '<div class="loading">데이터를 불러오는 중...</div>';
  }

  var url = '/api/posts.php?group_limit=5&sort=' + currentSettings.sort;

  if (!currentSettings.isToday) {
    url += '&date=' + currentSettings.date;
  } else {
    url += '&time=' + currentSettings.time;
  }

  fetch(url)
    .then(function(response) { return response.json(); })
    .then(function(result) {
      if (result.success && result.data) {
        mirrorData = result.data;
        renderOriginalList();
      } else {
        throw new Error(result.error || '데이터 로드 실패');
      }
    })
    .catch(function(error) {
      console.error('전체 데이터 로딩 실패:', error);
      listEl.innerHTML = '<div class="loading">데이터 로딩에 실패했습니다.</div>';
    });
}


function renderOriginalList() {
  if (!mirrorData || mirrorData.length === 0) {
    var targetEl = document.getElementById('regular-list') || listEl;
    targetEl.innerHTML = '<div class="loading">데이터가 없습니다.</div>';
    return;
  }
  
  var filteredData = mirrorData;
  
  if (currentSettings.communities.indexOf('all') === -1) {
    filteredData = mirrorData.filter(function(item) {
      return currentSettings.communities.indexOf(item.site) > -1;
    });
  }
  
  var html = '';
  
  // '전체' 선택 시 커뮤니티별로 그룹화
  if (currentSettings.communities.indexOf('all') !== -1) {
    html = renderByCommunityGroups(filteredData, 5);
  } else {
    html = renderNormalList(filteredData);
  }
  
  // 하이라이트된 항목이 있으면 regular-list에, 없으면 listEl에 렌더링
  var targetEl = document.getElementById('regular-list') || listEl;
  targetEl.innerHTML = html;
}

// 커뮤니티별 그룹화 렌더링 함수 추가
function renderByCommunityGroups(data, limitPerCommunity) {
  var communities = ['ppomppu', 'clien', 'natepann', 'ruliweb', 'theqoo', 'mlbpark', 'bobaedream', 'humoruniv', 'todayhumor', 'inven', 'slrclub', 'etoland'];
  var communityNames = {
    'ppomppu': '뽐뿌',
    'clien': '클리앙',
    'natepann': '네이트판',
    'ruliweb': '루리웹',
    'theqoo': '더쿠',
    'mlbpark': '엠팍',
    'bobaedream': '보배드림',
    'humoruniv': '웃긴대학',
    'todayhumor': '오늘의유머',
    'inven': '인벤',
    'slrclub': 'SLR클럽',
    'etoland': '이토랜드'
  };
  
  var html = '';
  
  for (var c = 0; c < communities.length; c++) {
    var community = communities[c];
    var communityData = data.filter(function(item) {
      return item.site === community;
    }).slice(0, limitPerCommunity);
    
    if (communityData.length > 0) {
      // 더보기 링크 생성 (날짜/시간 파라미터 포함)
      var moreLink = '?communities=' + community;
      
      // 날짜 정보 추가
      if (!currentSettings.isToday) {
        moreLink += '&date=' + currentSettings.date;
      } else {
        moreLink += '&time=' + currentSettings.time;
      }
  
 
  
  // 정렬 정보 추가
  moreLink += '&sort=' + currentSettings.sort;
  
      // 커뮤니티 제목과 더보기 버튼
      html += '<div style="background: #f8fafc; padding: 8px 16px; font-weight: 600; color: #64748b; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">' +
              '<span>' + communityNames[community] + '</span>' +
              '<a href="#" class="more-btn" data-community="' + community + '" style="color: #3b82f6; text-decoration: none; font-size: 14px; font-weight: 500;">더보기 ></a>' +
              '</div>';

      for (var i = 0; i < communityData.length; i++) {
        var item = communityData[i];
        html += renderSingleItem(item, i + 1); // 각 커뮤니티 내에서 1번부터 시작
      }
    }
  }
  
  return html;
}

// 일반 리스트 렌더링 함수 추가
function renderNormalList(data) {
  var html = '';
  for (var i = 0; i < data.length; i++) {
    html += renderSingleItem(data[i], i + 1);
  }
  return html;
}

// // 개별 아이템 렌더링 함수 (변경 없음)
// function renderSingleItem(item, rank) {
//   var siteMapping = {
//     'ppomppu': { key: 'ppomppu', name: '뽐뿌', shortName: '뽐뿌' },
//     'clien': { key: 'clien', name: '클리앙', shortName: '클리앙' },
//     'natepann': { key: 'natepann', name: '네이트판', shortName: '네이트판' }, 
//     'ruliweb': { key: 'ruliweb', name: '루리웹', shortName: '루리웹' }, 
//     'theqoo': { key: 'theqoo', name: '더쿠', shortName: '더쿠' },
//     'mlbpark': { key: 'mlbpark', name: '엠팍', shortName: '엠팍' },
//     'bobaedream': { key: 'bobaedream', name: '보배드림', shortName: '보배드림' },
//     'humoruniv': { key: 'humoruniv', name: '웃긴대학', shortName: '웃대' },
//     'todayhumor': { key: 'todayhumor', name: '오늘의유머', shortName: '오유' }
//   };

//   var communityInfo = siteMapping[item.site] || { 
//     key: 'clien', name: '클리앙', shortName: '클리앙'
//   };

//   var rankClass = getRankClass(rank);
//   var commentsHtml = (item.comments !== null && item.comments !== undefined) ? 
//     '(' + formatNumber(item.comments) + ')' : '';

//   return '<a href="' + item.originalUrl + '" class="list-item" data-id="' + item.id + '">' +
//     '<div class="rank-number ' + rankClass + '" data-community="' + communityInfo.key + '">' + rank + '</div>' +
//     '<div class="item-content">' + 
//     '<div class="title-row">' +
//     '<div class="item-title">' + escapeHtml(item.title) + 
//     (commentsHtml ? ' <span class="comments-count">' + commentsHtml + '</span>' : '') +
//     '</div>' +
//     '</div>' +
//     '<div class="item-meta">' +
//     '<div class="source-label" data-community="' + communityInfo.key + '">' +
//     '<span>' + communityInfo.shortName + '</span>' +
//     '</div>' +
//     '<span class="meta-item"><span class="time-icon">⏰</span>' + item.timeAgo + '</span>' +
//     '<span class="meta-item">👁&nbsp;<span class="view-number">' + formatNumber(item.views) + '</span></span>' +
//     '<span class="meta-item">👤 ' + escapeHtml(item.author) + '</span>' +
//     '</div>' +
//     '</div>' +
//     '</a>';
// 

function renderSingleItem(item, rank) {
  var siteMapping = {
    'ppomppu': { key: 'ppomppu', name: '뽐뿌', shortName: '뽐뿌' },
    'clien': { key: 'clien', name: '클리앙', shortName: '클리앙' },
    'natepann': { key: 'natepann', name: '네이트판', shortName: '네이트판' }, 
    'ruliweb': { key: 'ruliweb', name: '루리웹', shortName: '루리웹' }, 
    'theqoo': { key: 'theqoo', name: '더쿠', shortName: '더쿠' },
    'mlbpark': { key: 'mlbpark', name: '엠팍', shortName: '엠팍' },
    'bobaedream': { key: 'bobaedream', name: '보배드림', shortName: '보배드림' },
    'humoruniv': { key: 'humoruniv', name: '웃긴대학', shortName: '웃대' },
    'todayhumor': { key: 'todayhumor', name: '오늘의유머', shortName: '오유' },
    'inven': { key: 'inven', name: '인벤', shortName: '인벤' },
    'slrclub': { key: 'slrclub', name: 'SLR클럽', shortName: 'SLR' },
    'etoland': { key: 'etoland', name: '이토랜드', shortName: '이토' }
  };

  var communityInfo = siteMapping[item.site] || { 
    key: 'clien', name: '클리앙', shortName: '클리앙'
  };

  var rankClass = getRankClass(rank);
  var commentsHtml = (item.comments !== null && item.comments !== undefined) ? 
    '(' + formatNumber(item.comments) + ')' : '';

  return '<div class="list-item-wrapper" style="display: flex; align-items: flex-start;">' +
    '<a href="' + item.originalUrl + '" class="list-item" data-id="' + item.id + '" style="flex: 1;">' +
    '<div class="rank-number ' + rankClass + '" data-community="' + communityInfo.key + '">' + rank + '</div>' +
    '<div class="item-content">' + 
    '<div class="title-row">' +
    '<div class="item-title">' + escapeHtml(item.title) + 
    (commentsHtml ? ' <span class="comments-count">' + commentsHtml + '</span>' : '') +
    '</div>' +
    '</div>' +
    '<div class="item-meta">' +
    '<div class="source-label" data-community="' + communityInfo.key + '">' +
    '<span>' + communityInfo.shortName + '</span>' +
    '</div>' +
    '<span class="meta-item"><span class="time-icon">⏰</span>' + item.timeAgo + '</span>' +
    '<span class="meta-item">👁&nbsp;<span class="view-number">' + formatNumber(item.views) + '</span></span>' +
    '<span class="meta-item">👤 ' + escapeHtml(item.author) + '</span>' +
    '</div>' +
    '</div>' +
    '</a>' +
    '<button class="share-button" onclick="sharePost(\'' + item.id + '\', event)">' +
    '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92S19.61 16.08 18 16.08z"/></svg>' +
    '</button>' +
    '</div>';
}

// goToCommunity 함수 제거하고 이벤트 리스너로 대체
// 기존 이벤트 리스너들 다음에 추가
document.addEventListener('click', function(e) {
  var moreBtn = e.target.closest('.more-btn');
  if (moreBtn) {
    e.preventDefault();
    
    var community = moreBtn.getAttribute('data-community');
    
    // 해당 커뮤니티 선택
    currentSettings.communities = [community];
    
    // UI 업데이트
    document.querySelectorAll('.nav-item').forEach(function(btn) {
      btn.classList.remove('active');
    });
    
    var targetNavItem = document.querySelector(`[data-community="${community}"]`);
    if (targetNavItem) {
      targetNavItem.classList.add('active');
    }
    
    // URL 업데이트
    updateURL(community);
    
    // 데이터 다시 로드
    renderList();
  }
});

      

function renderList() {
  loadDataFromAPI();
}

// URL 업데이트 함수 추가
function updateURL(community) {
  const url = new URL(window.location);
  if (community === 'all') {
    url.searchParams.delete('communities');
  } else {
    url.searchParams.set('communities', community);
  }
  window.history.pushState({}, '', url);
}

function init() {
  // 1. URL 파라미터 먼저 읽기
  const urlParams = new URLSearchParams(window.location.search);
  const urlCommunity = urlParams.get('communities');
  const urlDate = urlParams.get('date');
  const urlSort = urlParams.get('sort');
  const urlTime = urlParams.get('time');
  const highlightId = urlParams.get('highlight');  // ← post_id 대신 highlight
  
  // 2. URL 파라미터가 있으면 우선 적용
  if (urlCommunity) {
    const validCommunities = ['all', 'ppomppu', 'clien', 'natepann', 'ruliweb', 'theqoo', 'mlbpark', 'bobaedream', 'humoruniv', 'todayhumor'];
    
    if (validCommunities.includes(urlCommunity)) {
      currentSettings.communities = [urlCommunity];
    }
  }
  
  // 날짜 파라미터 처리
  if (urlDate && /^\d{4}-\d{2}-\d{2}$/.test(urlDate)) {
    currentSettings.date = urlDate;
    currentSettings.isToday = isToday(urlDate);
    currentSettings.time = null;
  } else if (urlTime) {
    const validTimes = ['3h', '6h', '12h', '24h', '3d'];
    if (validTimes.includes(urlTime)) {
      currentSettings.time = urlTime;
      currentSettings.isToday = true;
      currentSettings.date = formatDateForInput(new Date());
    }
  }
  
  // 정렬 파라미터 처리
  if (urlSort) {
    const validSorts = ['hot', 'latest', 'comments', 'views'];
    if (validSorts.includes(urlSort)) {
      currentSettings.sort = urlSort;
    }
  }

  // 4. UI 복원
  if (currentSettings.time) {
    document.getElementById('timeSelect').value = currentSettings.time;
  }

  // 날짜 표시 업데이트
  updateDateDisplay();
  
  document.querySelectorAll('.sort-controls li').forEach(function(li) {
    li.classList.remove('on');
    li.setAttribute('aria-current', 'false');
  });
  var activeSortLi = document.querySelector('.sort-controls li[data-sort="' + currentSettings.sort + '"]');
  if (activeSortLi) {
    activeSortLi.classList.add('on');
    activeSortLi.setAttribute('aria-current', 'true');
  }

  // 5. 커뮤니티 UI 복원
  document.querySelectorAll('.nav-item').forEach(function(btn) {
    if (currentSettings.communities.includes(btn.dataset.community)) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
  
  // 하이라이트 ID가 있으면 해당 항목을 우선 로드
  if (highlightId) {
    loadHighlightedItem(highlightId);
  } else {
    renderList();
  }
}

// 공유 함수
window.sharePost = function(postId, event) {
  event.preventDefault();
  event.stopPropagation();
  
  var currentUrl = window.location.origin + '/?highlight=' + postId;
  
  navigator.clipboard.writeText(currentUrl).then(function() {
    alert('공유 링크가 복사되었습니다!');
  }).catch(function() {
    prompt('공유 링크를 복사하세요:', currentUrl);
  });
};

// 하이라이트 항목 제거 함수
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

// 하이라이트된 항목 로드
window.loadHighlightedItem = function(postId) {
  var url = '/api/posts.php?highlight=' + postId; 
  
  fetch(url)
    .then(function(response) {
      return response.json();
    })
    .then(function(result) {
      if (result.success && result.data.length > 0) {
        var highlightedItem = result.data[0];
        renderHighlightedItem(highlightedItem);
      } else {
        loadDataFromAPI();
      }
    })
    .catch(function(error) {
      console.error('하이라이트 항목 로드 실패:', error);
      loadDataFromAPI();
    });
};

// 하이라이트된 항목 렌더링
window.renderHighlightedItem = function(item) {
  var highlightHtml = renderSingleItem(item, '★');
  
  var wrappedHtml = '<div class="highlighted-item">' + highlightHtml + '</div>';
  
  // 하이라이트 항목 먼저 표시
  listEl.innerHTML = wrappedHtml + '<div id="regular-list"><div class="loading">목록을 불러오는 중...</div></div>';
  
  // 일반 목록을 별도로 로드
  setTimeout(function() {
    loadDataFromAPI();
  }, 100);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  setTimeout(init, 0);
}

// 토스트 알림 시스템 (인벤 → SLR → 이토랜드 순서)
(function() {
  var INVEN_KEY = 'invenToastSeen';
  var SLR_KEY = 'slrclubToastSeen';
  var ETO_KEY = 'etolandToastSeen';

  function setupToast(toastId, closeBtnId, navCommunity, onPersistDismiss) {
    var toast = document.getElementById(toastId);
    var closeBtn = document.getElementById(closeBtnId);
    var timer = null;

    function hide(persist) {
      clearTimeout(timer);
      toast.classList.remove('show');
      toast.classList.add('hide');
      if (persist && onPersistDismiss) onPersistDismiss();
    }

    toast.addEventListener('click', function(e) {
      if (e.target === closeBtn) return;
      hide(true);
      var nav = document.querySelector('.nav-item[data-community="' + navCommunity + '"]');
      if (nav) {
        nav.click();
        nav.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
      }
    });

    closeBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      hide(true);
    });

    setTimeout(function() {
      toast.classList.add('show');
      timer = setTimeout(function() { hide(false); }, 5000);
    }, 500);
  }

  function showEto() {
    if (localStorage.getItem(ETO_KEY)) return;
    setupToast('etolandToast', 'etolandToastClose', 'etoland', function() {
      localStorage.setItem(ETO_KEY, '1');
    });
  }

  function showSlr() {
    if (localStorage.getItem(SLR_KEY)) return;
    setupToast('slrclubToast', 'slrclubToastClose', 'slrclub', function() {
      localStorage.setItem(SLR_KEY, '1');
      setTimeout(showEto, 400);
    });
  }

  if (!localStorage.getItem(INVEN_KEY)) {
    setupToast('invenToast', 'invenToastClose', 'inven', function() {
      localStorage.setItem(INVEN_KEY, '1');
      setTimeout(showSlr, 400);
    });
  } else if (!localStorage.getItem(SLR_KEY)) {
    showSlr();
  } else {
    showEto();
  }
})();


})();

  </script>
</body>
</html>
