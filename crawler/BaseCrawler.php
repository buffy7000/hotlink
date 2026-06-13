<?php
require_once('/home/pricetag/hotlink.kr/config/database.php');

abstract class BaseCrawler {
    protected $db;
    protected $communityId;
    protected $communityCode;
    
    // User-Agent 목록 (봇 차단 방지)
    protected $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
    ];
    
    public function __construct($communityId, $communityCode) {
        $this->db = new Database();
        $this->communityId = $communityId;
        $this->communityCode = $communityCode;
    }
    
    // 추상 메서드 - 각 사이트별로 구현 필요
    abstract public function crawlHotPosts($limit = 50);
    
    // HTTP 요청 공통 메서드
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
            throw new Exception("HTTP 오류: {$httpCode}");
        }
        
        return $response;
    }
    
    // 랜덤 User-Agent 선택
    protected function getRandomUserAgent() {
        return $this->userAgents[array_rand($this->userAgents)];
    }
    
    // 인기도 점수 계산
    protected function calculateRankScore($post) {
        $commentsScore = ($post['comments_count'] ?? 0) * 3;
        $likesScore = ($post['likes_count'] ?? 0) * 2;
        $viewsScore = ($post['views_count'] ?? 0) * 0.01;
        
        // 시간 가중치 (24시간 기준)
        $hoursOld = $this->getHoursOld($post['created_at']);
        $timeWeight = pow(0.8, $hoursOld / 24);
        
        return ($commentsScore + $likesScore + $viewsScore) * $timeWeight;
    }
    
    // 게시글이 몇 시간 전인지 계산
    protected function getHoursOld($createdAt) {
        $created = new DateTime($createdAt);
        $now = new DateTime();
        $diff = $now->diff($created);
        return ($diff->days * 24) + $diff->h;
    }
    
    // 크롤링한 게시글들을 DB에 저장
    protected function savePosts($posts) {
        $sql = "INSERT INTO posts 
                (community_id, title, url, thumbnail_url, author, comments_count, views_count, likes_count, created_at, rank_score) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                thumbnail_url = VALUES(thumbnail_url),  
                comments_count = VALUES(comments_count),
                views_count = VALUES(views_count),
                likes_count = VALUES(likes_count),
                rank_score = VALUES(rank_score)";
        
        $savedCount = 0;
        foreach ($posts as $post) {
            try {
                $rankScore = $this->calculateRankScore($post);
                
                $this->db->query($sql, [
                    $this->communityId,
                    $post['title'],
                    $post['url'],
                    $post['thumbnail_url'] ?? null, 
                    $post['author'] ?? '',
                    $post['comments_count'] ?? 0,
                    $post['views_count'] ?? 0,
                    $post['likes_count'] ?? 0,
                    $post['created_at'] ?? date('Y-m-d H:i:s'),
                    $rankScore
                ]);
                $savedCount++;
            } catch (Exception $e) {
                // 중복 URL 등의 오류는 무시하고 계속 진행
                continue;
            }
        }
        
        // 크롤링 로그 저장
        $this->logCrawlResult('success', $savedCount);
        
        return $savedCount;
    }
    
    // 크롤링 결과 로그 저장
    protected function logCrawlResult($status, $postsCount, $errorMessage = null) {
        $sql = "INSERT INTO crawl_logs (community_id, status, posts_count, error_message) 
                VALUES (?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $this->communityId,
            $status,
            $postsCount,
            $errorMessage
        ]);
    }
    
    // 에러 로그
    protected function logError($message) {
        echo "[ERROR] {$this->communityCode}: {$message}\n";
        $this->logCrawlResult('error', 0, $message);
    }
}
?>
