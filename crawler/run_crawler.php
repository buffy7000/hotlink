<?php
// 절대경로로 수정
require_once('/home/pricetag/hotlink.kr/config/database.php');

function writeLog($message) {
    // 절대경로로 수정
    $logFile = '/home/pricetag/hotlink.kr/logs/crawler.log';
    $logDir = '/home/pricetag/hotlink.kr/hotpost/logs';
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

writeLog("크롤링 시작");

try {
    // 뽐뿌 크롤링 실행
    writeLog("뽐뿌 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler.php';
    
    // 클리앙 크롤링 실행  
    writeLog("클리앙 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_clien.php';
    
    // 네이트판 크롤링 실행
    writeLog("네이트판 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_natepann.php';

    // 루리웹 크롤링 실행
    writeLog("루리웹 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_ruliweb.php';
    
    // 더쿠 크롤링 실행
    writeLog("더쿠 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_theqoo.php';
    
    // ⭐️ MLB파크 크롤링 실행
    writeLog("MLB파크 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_mlbpark.php';
    
    // ⭐️ 보배드림 크롤링 실행
    writeLog("보배드림 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_bobaedream.php';
    
    // ⭐️ 웃긴대학 크롤링 실행
    writeLog("웃긴대학 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_humoruniv.php';
    
    // ⭐️ 오늘의유머 크롤링 실행
    writeLog("오늘의유머 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_todayhumor.php';

    // ⭐️ 인벤 크롤링 실행
    writeLog("인벤 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_inven.php';

    // ⭐️ SLR클럽 크롤링 실행
    writeLog("SLR클럽 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_slrclub.php';

    // ⭐️ 이토랜드 크롤링 실행
    writeLog("이토랜드 크롤링 중...");
    include '/home/pricetag/hotlink.kr/crawler/test_crawler_etoland.php';

    writeLog("크롤링 완료");
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
} catch (Error $e) {
    writeLog("FATAL ERROR: " . $e->getMessage());
}
?>
