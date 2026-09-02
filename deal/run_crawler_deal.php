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
        // 실제 클래스명은 파일명과 다르므로(QuasarzoneMultiPageCrawler, ClienJirumCrawler,
        // EomisaeCrawler, RuliwebCrawler) 각 크롤러 파일의 class 선언과 반드시 일치시켜야 한다.
        // 응답이 느리거나 막힐 위험이 있는 어미새/루리웹을 먼저 실행해, 혹시 스크립트가
        // 도중에 끊기더라도 뒤에 있다는 이유만으로 아예 시도조차 못 하는 일이 없게 한다.
        $this->crawlers = [
            'eomisae' => new EomisaeCrawler(),
            'ruliweb' => new RuliwebCrawler(),
            'quasarzone' => new QuasarzoneMultiPageCrawler(),
            'clien' => new ClienJirumCrawler(),
        ];
    }

    public function runAll($limit = 50) {
        writeLog("핫딜 크롤링 시작");

        $totalProcessed = 0;

        foreach ($this->crawlers as $siteName => $crawler) {
            try {
                writeLog("[$siteName] 크롤링 시작");

                $processed = $this->runCrawler($siteName, $crawler, $limit);
                $totalProcessed += $processed;

                writeLog("[$siteName] 완료: {$processed}개 처리");

                sleep(2);

            } catch (Throwable $e) {
                // 클래스/메서드 불일치 같은 Error도 여기서 잡아 나머지 사이트 크롤링은 계속 진행한다.
                writeLog("[$siteName] 실패: " . $e->getMessage());
            }
        }

        writeLog("크롤링 완료: 총 {$totalProcessed}개 처리");
        return $totalProcessed;
    }

    // 사이트별로 진입 메서드/시그니처가 달라 여기서 흡수한다.
    private function runCrawler($siteName, $crawler, $limit) {
        switch ($siteName) {
            case 'quasarzone':
                return (int) $crawler->crawlMultiplePages($limit, 2);
            case 'clien':
                return (int) $crawler->crawlHotdeals($limit);
            case 'eomisae':
            case 'ruliweb':
                return $crawler->crawl() ? 1 : 0;
            default:
                throw new Exception("등록되지 않은 사이트: {$siteName}");
        }
    }
}

try {
    $manager = new HotdealCrawlerManager();
    $manager->runAll(50);
} catch (Throwable $e) {
    writeLog("ERROR: " . $e->getMessage());
}
?>
