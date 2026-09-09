<?php
/**
 * 수정된 핫딜 크롤러 기본 클래스 (DB 연결 수정)
 */

require_once __DIR__ . '/../config/db_credentials.php';

// 직접 DB 연결 (기존 config 파일 문제 회피)
class HotdealDatabase {
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                'mysql:host=' . DB_HOST . ':3306;dbname=pricetag_hotdeal;charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("DB 연결 실패: " . $e->getMessage());
        }
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("쿼리 실행 실패: " . $e->getMessage());
        }
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchColumn($sql, $params = []) {
        return $this->query($sql, $params)->fetchColumn();
    }
}

abstract class BaseHotdealCrawler {
    protected $db;
    protected $sourceId;
    protected $sourceName;
    protected $baseUrl;
    protected $listUrl;
    
    // User-Agent
    protected $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0'
    ];
    
    public function __construct($sourceId, $sourceName, $baseUrl, $listUrl) {
        $this->db = new HotdealDatabase();
        $this->sourceId = $sourceId;
        $this->sourceName = $sourceName;
        $this->baseUrl = $baseUrl;
        $this->listUrl = $listUrl;
    }
    
    abstract public function crawlHotdeals($limit = 50);
    
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
                'Upgrade-Insecure-Requests: 1',
                'Referer: https://www.ppomppu.co.kr/'
            ]
        ]);
        
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
    
    protected function saveHotdeals($hotdeals) {
        if (empty($hotdeals)) {
            echo "[{$this->sourceName}] 저장할 핫딜이 없습니다.\n";
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
        
        echo "[{$this->sourceName}] 저장 완료 - 전체: " . count($hotdeals) . ", 신규: {$newCount}, 업데이트: {$updatedCount}, 시간: " . round($executionTime, 2) . "초\n";
        
        return $newCount + $updatedCount;
    }
    
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
            // 업데이트
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
    
    protected function calculateHotdealScore($hotdeal) {
        $shareScore = ($hotdeal['share_click_count'] ?? 0) * 10;
        $highlightScore = ($hotdeal['highlight_count'] ?? 0) * 15;
        $likeScore = ($hotdeal['like_count'] ?? 0) * 5;
        $commentScore = ($hotdeal['comment_count'] ?? 0) * 3;
        $viewScore = ($hotdeal['view_count'] ?? 0) * 0.1;
        
        $hoursOld = $this->getHoursOld($hotdeal['original_created_at'] ?? date('Y-m-d H:i:s'));
        $timeWeight = pow(0.5, $hoursOld / 24.0);
        
        return ($shareScore + $highlightScore + $likeScore + $commentScore + $viewScore) * $timeWeight;
    }
    
    protected function getHoursOld($createdAt) {
        $created = new DateTime($createdAt);
        $now = new DateTime();
        $diff = $now->diff($created);
        return ($diff->days * 24) + $diff->h;
    }
    
    protected function generatePublicId() {
        $today = date('ymd');
        
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
    
    protected function cleanTitle($title) {
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);
        $title = strip_tags($title);
        return $title;
    }
    
    protected function logCrawlStart() {
        try {
            $sql = "INSERT INTO hotdeal_crawl_logs (source_id, crawl_type, status, started_at)
                    VALUES (?, 'list', 'running', NOW())";
            $this->db->query($sql, [$this->sourceId]);
        } catch (Exception $e) {
            echo "[WARNING] 크롤링 로그 시작 기록 실패: " . $e->getMessage() . "\n";
        }
    }
    
    protected function logCrawlComplete($status, $itemsFound, $itemsNew, $itemsUpdated, $executionTime, $errorMessage = null) {
        try {
            $sql = "UPDATE hotdeal_crawl_logs 
                    SET status = ?, items_found = ?, items_new = ?, items_updated = ?, 
                        execution_time = ?, error_message = ?, completed_at = NOW()
                    WHERE source_id = ? AND status = 'running'
                    ORDER BY started_at DESC LIMIT 1";
            
            $this->db->query($sql, [
                $status, $itemsFound, $itemsNew, $itemsUpdated,
                $executionTime, $errorMessage, $this->sourceId
            ]);
            
            $updateSourceSql = "UPDATE hotdeal_sources SET last_crawl_time = NOW() WHERE id = ?";
            $this->db->query($updateSourceSql, [$this->sourceId]);
        } catch (Exception $e) {
            echo "[WARNING] 크롤링 로그 완료 기록 실패: " . $e->getMessage() . "\n";
        }
    }
    
    protected function logError($message) {
        echo "[ERROR] {$this->sourceName}: {$message}\n";
        $this->logCrawlComplete('error', 0, 0, 0, 0, $message);
    }
}
?>