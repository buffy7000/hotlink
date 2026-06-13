<?php
/**
 * 핫딜 크롤러 전용 기본 클래스
 * 파일위치: /hotdeal/BaseHotdealCrawler.php
 * 
 * 기존 커뮤니티 크롤러와 완전 분리된 독립 시스템
 */

// 기존 DB 클래스만 재사용
require_once(__DIR__ . '/../config/database.php');

abstract class BaseHotdealCrawler {
    protected $db;
    protected $sourceId;
    protected $sourceName;
    protected $baseUrl;
    protected $listUrl;
    
    // User-Agent (기존 것 재사용)
    protected $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
    ];
    
    public function __construct($sourceId, $sourceName, $baseUrl, $listUrl) {
        $this->db = new Database();
        $this->sourceId = $sourceId;
        $this->sourceName = $sourceName;
        $this->baseUrl = $baseUrl;
        $this->listUrl = $listUrl;
    }
    
    // 추상 메서드 - 각 사이트별로 구현
    abstract public function crawlHotdeals($limit = 50);
    
    /**
     * HTTP 요청 (기존 BaseCrawler와 동일한 방식)
     */
    protected function makeRequest($url) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => $this->getRandomUserAgent(),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ko-KR,ko;q=0.8,en-US;q=0.5,en;q=0.3',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1'
            ]
        ]);
        
        // 요청 간격 (서버 부하 방지)
        usleep(rand(500000, 1000000)); // 0.5~1초 대기
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP 오류: {$httpCode} for URL: {$url}");
        }
        
        return $response;
    }
    
    protected function getRandomUserAgent() {
        return $this->userAgents[array_rand($this->userAgents)];
    }
    
    /**
     * 핫딜 데이터 저장
     */
    protected function saveHotdeals($hotdeals) {
        if (empty($hotdeals)) {
            echo "[{$this->sourceName}] 크롤링된 핫딜이 없습니다.\n";
            return 0;
        }
        
        $startTime = microtime(true);
        $this->logCrawlStart();
        
        $newCount = 0;
        $updatedCount = 0;
        
        foreach ($hotdeals as $hotdeal) {
            try {
                $result = $this->saveHotdeal($hotdeal);
                if ($result === 'new') {
                    $newCount++;
                } elseif ($result === 'updated') {
                    $updatedCount++;
                }
            } catch (Exception $e) {
                echo "[ERROR] 핫딜 저장 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        $executionTime = microtime(true) - $startTime;
        $this->logCrawlComplete('success', count($hotdeals), $newCount, $updatedCount, $executionTime);
        
        echo "[{$this->sourceName}] 크롤링 완료 - 전체: " . count($hotdeals) . ", 신규: {$newCount}, 업데이트: {$updatedCount}, 시간: " . round($executionTime, 2) . "초\n";
        
        return $newCount + $updatedCount;
    }
    
    /**
     * 개별 핫딜 저장
     */
    private function saveHotdeal($hotdeal) {
        // 필수 필드 검증
        if (empty($hotdeal['original_id']) || empty($hotdeal['title'])) {
            throw new Exception("필수 필드 누락: original_id 또는 title");
        }
        
        // 중복 체크
        $existingSql = "SELECT id, view_count, comment_count, like_count FROM hotdeals 
                       WHERE source_id = ? AND original_id = ?";
        $existing = $this->db->fetch($existingSql, [$this->sourceId, $hotdeal['original_id']]);
        
        if ($existing) {
            // 업데이트 (통계 정보만)
            $updateSql = "UPDATE hotdeals SET 
                         view_count = ?, comment_count = ?, like_count = ?, 
                         hotdeal_score = ?, updated_at = NOW()
                         WHERE id = ?";
            
            $score = $this->calculateHotdealScore($hotdeal);
            
            $this->db->query($updateSql, [
                $hotdeal['view_count'] ?? 0,
                $hotdeal['comment_count'] ?? 0,
                $hotdeal['like_count'] ?? 0,
                $score,
                $existing['id']
            ]);
            
            return 'updated';
        } else {
            // 신규 등록
            $publicId = $this->generatePublicId();
            $score = $this->calculateHotdealScore($hotdeal);
            
            $insertSql = "INSERT INTO hotdeals (
                public_id, source_id, original_id, title, original_url,
                thumbnail_url, author_name, view_count, comment_count, like_count,
                price, original_price, discount_rate, store_name, shipping_info,
                brand_name, brand_image_url, deal_status, hotdeal_score,
                original_created_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
            
            $this->db->query($insertSql, [
                $publicId,
                $this->sourceId,
                $hotdeal['original_id'],
                $hotdeal['title'],
                $hotdeal['original_url'],
                $hotdeal['thumbnail_url'] ?? null,
                $hotdeal['author_name'] ?? '',
                $hotdeal['view_count'] ?? 0,
                $hotdeal['comment_count'] ?? 0,
                $hotdeal['like_count'] ?? 0,
                $hotdeal['price'] ?? null,
                $hotdeal['original_price'] ?? null,
                $hotdeal['discount_rate'] ?? null,
                $hotdeal['store_name'] ?? null,
                $hotdeal['shipping_info'] ?? null,
                $hotdeal['brand_name'] ?? null,
                $hotdeal['brand_image_url'] ?? null,
                $hotdeal['deal_status'] ?? null,
                $score,
                $hotdeal['original_created_at'] ?? date('Y-m-d H:i:s')
            ]);
            
            return 'new';
        }
    }
    
    /**
     * 핫딜 점수 계산
     */
    protected function calculateHotdealScore($hotdeal) {
        $shareScore = ($hotdeal['share_click_count'] ?? 0) * 10;
        $highlightScore = ($hotdeal['highlight_count'] ?? 0) * 15;
        $likeScore = ($hotdeal['like_count'] ?? 0) * 5;
        $commentScore = ($hotdeal['comment_count'] ?? 0) * 3;
        $viewScore = ($hotdeal['view_count'] ?? 0) * 0.1;
        
        // 시간 가중치 (24시간마다 절반으로 감소)
        $hoursOld = $this->getHoursOld($hotdeal['original_created_at'] ?? date('Y-m-d H:i:s'));
        $timeWeight = pow(0.5, $hoursOld / 24.0);
        
        return ($shareScore + $highlightScore + $likeScore + $commentScore + $viewScore) * $timeWeight;
    }
    
    /**
     * 시간 차이 계산
     */
    protected function getHoursOld($createdAt) {
        $created = new DateTime($createdAt);
        $now = new DateTime();
        $diff = $now->diff($created);
        return ($diff->days * 24) + $diff->h;
    }
    
    /**
     * Public ID 생성
     */
    protected function generatePublicId() {
        $today = date('ymd'); // YYMMDD
        
        // 오늘 생성된 마지막 ID 조회
        $sql = "SELECT public_id FROM hotdeals 
                WHERE public_id LIKE ? 
                ORDER BY public_id DESC LIMIT 1";
        $lastId = $this->db->fetchColumn($sql, [$today . '%']);
        
        if ($lastId) {
            $lastNumber = intval(substr($lastId, -3));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $today . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * 제목 정리
     */
    protected function cleanTitle($title) {
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);
        // HTML 태그 제거
        $title = strip_tags($title);
        return $title;
    }
    
    /**
     * 크롤링 시작 로그
     */
    protected function logCrawlStart() {
        $sql = "INSERT INTO hotdeal_crawl_logs (source_id, crawl_type, status, started_at)
                VALUES (?, 'list', 'running', NOW())";
        $this->db->query($sql, [$this->sourceId]);
    }
    
    /**
     * 크롤링 완료 로그
     */
    protected function logCrawlComplete($status, $itemsFound, $itemsNew, $itemsUpdated, $executionTime, $errorMessage = null) {
        $sql = "UPDATE hotdeal_crawl_logs 
                SET status = ?, items_found = ?, items_new = ?, items_updated = ?, 
                    execution_time = ?, error_message = ?, completed_at = NOW()
                WHERE source_id = ? AND status = 'running'
                ORDER BY started_at DESC LIMIT 1";
        
        $this->db->query($sql, [
            $status, $itemsFound, $itemsNew, $itemsUpdated,
            $executionTime, $errorMessage, $this->sourceId
        ]);
        
        // 소스 테이블 업데이트
        $updateSourceSql = "UPDATE hotdeal_sources SET last_crawl_time = NOW() WHERE id = ?";
        $this->db->query($updateSourceSql, [$this->sourceId]);
    }
    
    /**
     * 에러 로그
     */
    protected function logError($message) {
        echo "[ERROR] {$this->sourceName}: {$message}\n";
        $this->logCrawlComplete('error', 0, 0, 0, 0, $message);
    }
}
?>