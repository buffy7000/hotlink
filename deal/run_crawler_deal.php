<?php
set_time_limit(0);
ini_set('memory_limit', '256M');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 데이터베이스 연결
require_once(__DIR__ . '/../config/database_deal.php');

// 크롤러 파일들
require_once(__DIR__ . '/QuasarzoneHotdealCrawler.php');
require_once(__DIR__ . '/ClienHotdealCrawler.php');
require_once(__DIR__ . '/EomisaeHotdealCrawler.php');
require_once(__DIR__ . '/RuliwebHotdealCrawler.php');
// 쿠팡은 서버 IP가 쿠팡 측에 전체 도메인 차단되어 있어 여기서 호출하지 않음.
// GitHub Actions(crawl_coupang.yml)가 API 호출 + coupang/api/save_goldbox.php 저장을 대신 수행한다.


function writeLog($message) {
    $logFile = '/home/pricetag/hotlink.kr/logs/crawler_deal.log';
    $logDir = '/home/pricetag/hotlink.kr/logs';
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

class HotdealCrawlerManager {
    private $crawlers = [];
    
    public function __construct() {
        $this->crawlers = [
            'quasarzone' => new QuasarzoneHotdealCrawler(),
            'clien' => new ClienHotdealCrawler(),
            'eomisae' => new EomisaeHotdealCrawler(),
        ];
    }
    
    public function runAll($limit = 50) {
        writeLog("핫딜 크롤링 시작");
        
        $totalProcessed = 0;
        
        foreach ($this->crawlers as $siteName => $crawler) {
            try {
                writeLog("[$siteName] 크롤링 시작");
                
                $processed = $crawler->crawlHotdeals($limit);
                $totalProcessed += $processed;
                
                writeLog("[$siteName] 완료: {$processed}개 처리");
                
                sleep(2);
                
            } catch (Exception $e) {
                writeLog("[$siteName] 실패: " . $e->getMessage());
            }
        }
        
        writeLog("크롤링 완료: 총 {$totalProcessed}개 처리");
        return $totalProcessed;
    }
}

try {
    $manager = new HotdealCrawlerManager();
    $manager->runAll(50);
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
}
?>
