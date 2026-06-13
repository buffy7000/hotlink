<?php
// 절대경로로 수정
require_once('/home/pricetag/hotlink.kr/config/database_deal.php');

function writeLog($message) {
    // 절대경로로 수정
    $logFile = '/home/pricetag/hotlink.kr/logs/crawler_deal.log';
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
    // 퀘사이존 크롤링 실행
    writeLog("퀘사이존 크롤링 중...");
    include '/home/pricetag/hotlink.kr/deal/QuasarzoneHotdealCrawler.php';
    
    // 클리앙 크롤링 실행  
    writeLog("클리앙 크롤링 중...");
    include '/home/pricetag/hotlink.kr/deal/ClienHotdealCrawler.php';
   
    
    writeLog("크롤링 완료");
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
} catch (Error $e) {
    writeLog("FATAL ERROR: " . $e->getMessage());
}
?>
