<?php
/**
 * 핫딜 크롤러 통합 실행 스크립트
 * 파일위치: /hotdeal/run_hotdeal_crawler.php
 */

// 실행 시간 제한 해제
set_time_limit(0);

// 메모리 제한 증가
ini_set('memory_limit', '256M');

// 에러 출력
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/PpomppuHotdealCrawler.php');
// 나중에 추가할 크롤러들
// require_once(__DIR__ . '/QuasarzoneHotdealCrawler.php');
// require_once(__DIR__ . '/ClienHotdealCrawler.php');
// require_once(__DIR__ . '/FmkoreaHotdealCrawler.php');

class HotdealCrawlerManager {
    private $crawlers = [];
    private $results = [];
    
    public function __construct() {
        // 현재는 뽐뿌만 등록
        $this->crawlers = [
            'ppomppu' => new PpomppuHotdealCrawler(),
            // 나중에 추가
            // 'quasarzone' => new QuasarzoneHotdealCrawler(),
            // 'clien' => new ClienHotdealCrawler(),
            // 'fmkorea' => new FmkoreaHotdealCrawler(),
        ];
    }
    
    /**
     * 모든 사이트 크롤링 실행
     */
    public function runAll($limit = 50) {
        echo "=== 핫딜 크롤링 시작 (" . date('Y-m-d H:i:s') . ") ===\n\n";
        
        $totalProcessed = 0;
        $startTime = microtime(true);
        
        foreach ($this->crawlers as $siteName => $crawler) {
            try {
                echo "[$siteName] 크롤링 시작...\n";
                $siteStartTime = microtime(true);
                
                $processed = $crawler->crawlHotdeals($limit);
                
                $siteExecutionTime = microtime(true) - $siteStartTime;
                $this->results[$siteName] = [
                    'processed' => $processed,
                    'execution_time' => $siteExecutionTime,
                    'status' => 'success'
                ];
                
                $totalProcessed += $processed;
                
                echo "[$siteName] 완료: {$processed}개 처리, " . round($siteExecutionTime, 2) . "초\n\n";
                
                // 사이트 간 간격
                sleep(2);
                
            } catch (Exception $e) {
                $this->results[$siteName] = [
                    'processed' => 0,
                    'execution_time' => 0,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
                
                echo "[$siteName] 실패: " . $e->getMessage() . "\n\n";
            }
        }
        
        $totalExecutionTime = microtime(true) - $startTime;
        
        echo "=== 크롤링 완료 ===\n";
        echo "총 처리: {$totalProcessed}개\n";
        echo "총 시간: " . round($totalExecutionTime, 2) . "초\n";
        echo "완료 시간: " . date('Y-m-d H:i:s') . "\n\n";
        
        $this->printSummary();
        
        return $totalProcessed;
    }
    
    /**
     * 특정 사이트만 크롤링
     */
    public function runSite($siteName, $limit = 50) {
        if (!isset($this->crawlers[$siteName])) {
            throw new Exception("지원하지 않는 사이트: {$siteName}");
        }
        
        echo "[$siteName] 단독 크롤링 시작...\n";
        
        return $this->crawlers[$siteName]->crawlHotdeals($limit);
    }
    
    /**
     * 결과 요약 출력
     */
    private function printSummary() {
        echo "=== 사이트별 결과 ===\n";
        foreach ($this->results as $site => $result) {
            $status = $result['status'] === 'success' ? '성공' : '실패';
            $processed = $result['processed'];
            $time = round($result['execution_time'], 2);
            
            echo sprintf("%-12s: %s (%d개, %s초)", $site, $status, $processed, $time);
            
            if ($result['status']