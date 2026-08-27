<?php
// simple_test_clien.php - 클리앙 크롤러 테스트
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>클리앙 크롤러 테스트</title>
    <style>
        body { font-family: monospace; white-space: pre-wrap; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .section { color: purple; font-weight: bold; }
    </style>
</head>
<body>
<?php
echo "<div class='info'>클리앙 크롤러 테스트 시작</div>\n";
echo "============================================================\n";

// PHP 오류 표시 활성화
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "<div class='info'>1. 파일 로드 중...</div>\n";
    require_once 'ClienCrawler.php';
    
    echo "<div class='info'>2. 크롤러 생성 중...</div>\n";
    $crawler = new ClienCrawler();
    
    // 추천글(/service/recommend)은 클리앙에서 폐지되어 HTTP 410을 반환함 (2026-08 확인).
    // 매 실행마다 확정적으로 실패하는 요청을 보낼 필요가 없어 호출 자체를 제거.
    $result1 = 0;

    // ✅ 모두의공원 공감순 크롤링 테스트
    echo "<div class='section'>=== 모두의공원 공감순 크롤링 테스트 ===</div>\n";
    echo "<div class='info'>3-2. 공감순 크롤링 시작 (공감 20개 이상, 2페이지)...</div>\n";
    $result2 = $crawler->crawlParkByLikes(20, 2);
    echo "<div class='success'>공감순 완료: {$result2}개 저장</div>\n";
    
    echo "\n";
    
    // ✅ 모두의공원 댓글순 크롤링 테스트
    echo "<div class='section'>=== 모두의공원 댓글순 크롤링 테스트 ===</div>\n";
    echo "<div class='info'>3-3. 댓글순 크롤링 시작 (댓글 20개 이상, 3페이지)...</div>\n";
    $result3 = $crawler->crawlParkByComments(20, 3);
    echo "<div class='success'>댓글순 완료: {$result3}개 저장</div>\n";
    
    echo "\n";
    
    // ✅ 전체 결과 요약
    echo "<div class='section'>=== 전체 크롤링 결과 요약 ===</div>\n";
    $totalSaved = $result1 + $result2 + $result3;
    echo "<div class='success'>추천글: 폐지됨(HTTP 410) - 수집 안 함</div>\n";
    echo "<div class='success'>공감순: {$result2}개</div>\n";
    echo "<div class='success'>댓글순: {$result3}개</div>\n";
    echo "<div class='success'>총 저장: {$totalSaved}개 게시글</div>\n";
    
} catch (Error $e) {
    echo "<div class='error'>PHP 오류: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    echo "<div class='error'>파일: " . htmlspecialchars($e->getFile()) . "</div>\n";
    echo "<div class='error'>라인: " . $e->getLine() . "</div>\n";
} catch (Exception $e) {
    echo "<div class='error'>예외 발생: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

echo "============================================================\n";
echo "<div class='info'>전체 테스트 완료</div>\n";
?>
</body>
</html>
