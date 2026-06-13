<?php
/**
 * 루리웹 핫딜 크롤러
 * 파일명: RuliwebHotdealCrawler.php
 */
require_once(__DIR__ . '/SimpleHotdealDB.php');

class RuliwebCrawler {
    private $db;
    private $sourceId = 6;
    private $baseUrl = 'https://bbs.ruliweb.com';
    private $hotdealUrl = 'https://bbs.ruliweb.com/news/board/1020?page=1&view=thumbnail';
    
    public function __construct() {
        $this->db = new SimpleHotdealDB();
    }
    
    public function crawl() {
        echo "루리웹 핫딜 크롤링 시작...\n";
        echo str_repeat("=", 50) . "\n";
        
        $html = $this->fetchPage($this->hotdealUrl);
        if (!$html) {
            echo "페이지 로드 실패\n";
            return false;
        }
        
        echo "HTML 크기: " . number_format(strlen($html)) . " bytes\n";
        
        $items = $this->parseItems($html);
        echo "파싱된 아이템 수: " . count($items) . "\n";
        
        $newCount = 0;
        $updateCount = 0;
        
        foreach ($items as $item) {
            if ($this->saveItem($item)) {
                $newCount++;
            } else {
                $updateCount++;
            }
        }
        
        echo str_repeat("=", 50) . "\n";
        echo "새로 추가된 아이템: {$newCount}개\n";
        echo "업데이트된 아이템: {$updateCount}개\n";
        echo "루리웹 크롤링 완료\n";
        
        return true;
    }
    
    private function fetchPage($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ko-KR,ko;q=0.8',
            ]
        ]);
        
        usleep(rand(500000, 1000000));
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            echo "CURL Error: " . curl_error($ch) . "\n";
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            echo "HTTP Error: {$httpCode}\n";
            return false;
        }
        
        return $html;
    }
    
    private function parseItems($html) {
        $items = [];
        $processedIds = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // div.article.col_12 선택
        $articleNodes = $xpath->query('//div[@class="article col_12"]');
        
        echo "루리웹에서 {$articleNodes->length}개 아이템 발견\n";
        
        $count = 0;
        foreach ($articleNodes as $node) {
            if ($count >= 30) break;
            
            try {
                $item = $this->parseItem($xpath, $node);
                
                if ($item && !in_array($item['original_id'], $processedIds)) {
                    $items[] = $item;
                    $processedIds[] = $item['original_id'];
                    $count++;
                    
                    echo sprintf("  [%d] %s (ID: %s)\n", 
                        $count, 
                        mb_substr($item['title'], 0, 40) . '...', 
                        $item['original_id']
                    );
                }
            } catch (Exception $e) {
                echo "아이템 파싱 오류: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        return $items;
    }
    
    private function parseItem($xpath, $node) {
        // 1. 게시글 ID
        $idInput = $xpath->query('.//input[@class="info_article_id"]', $node)->item(0);
        if (!$idInput) return null;
        $originalId = $idInput->getAttribute('value');
        
        // 2. 제목과 URL
        $subjectLink = $xpath->query('.//a[contains(@class, "subject_link")]', $node)->item(0);
        if (!$subjectLink) return null;
        
        $title = trim($subjectLink->textContent);
        $url = $subjectLink->getAttribute('href');
        if (empty($title) || empty($url)) return null;
        
        // URL 정리
        $cleanUrl = $this->removeUrlParams($url);
        if (strpos($cleanUrl, 'http') !== 0) {
            $cleanUrl = $this->baseUrl . $cleanUrl;
        }
        
        // 3. 카테고리
        $titleWrapper = $xpath->query('.//div[contains(@class, "title_wrapper")]', $node)->item(0);
        $category = null;
        if ($titleWrapper && preg_match('/^\s*\[([^\]]+)\]/', $titleWrapper->textContent, $m)) {
            $category = trim($m[1]);
        }
        
        // 4. 작성자
        $nickLink = $xpath->query('.//div[contains(@class, "nick")]//a', $node)->item(0);
        $authorName = $nickLink ? trim($nickLink->textContent) : '';
        
        // 공지글 필터링
        if ($authorName === '핫딜관리자' || $category === '업체핫딜' || $category === 'BEST') {
            return null;
        }
        
        // 5. 썸네일
        $thumbnailUrl = null;
        $thumbnail = $xpath->query('.//a[@class="thumbnail col_12"]', $node)->item(0);
        if ($thumbnail) {
            $style = $thumbnail->getAttribute('style');
            if (preg_match('/url\(["\']?(https:\/\/[^)"\']+\.(?:jpg|png))["\']?\)/', $style, $m)) {
                $thumbnailUrl = $m[1];
            }
        }
        
        // 6. 조회수
        $hitStrong = $xpath->query('.//span[@class="hit"]//strong', $node)->item(0);
        $viewCount = $hitStrong ? (int)preg_replace('/\D/', '', $hitStrong->textContent) : 0;
        
        // 7. 댓글수
        $replySpan = $xpath->query('.//span[@class="num_reply"]', $node)->item(0);
        $commentCount = 0;
        if ($replySpan && preg_match('/\((\d+)\)/', $replySpan->textContent, $m)) {
            $commentCount = (int)$m[1];
        }
        
        // 8. 추천수
        $recomdStrong = $xpath->query('.//span[@class="recomd"]//strong', $node)->item(0);
        $likeCount = $recomdStrong ? (int)preg_replace('/\D/', '', $recomdStrong->textContent) : 0;
        
        // 9. 작성시간
        $timeSpan = $xpath->query('.//span[@class="time"]', $node)->item(0);
        $dateText = $timeSpan ? str_replace('날짜', '', $timeSpan->textContent) : '';
        $createdAt = $this->parseDate(trim($dateText));
        
        return [
            'original_id' => $originalId,
            'title' => $this->cleanTitle($title),
            'original_url' => $cleanUrl,
            'thumbnail_url' => $thumbnailUrl,
            'author_name' => $authorName,
            'view_count' => $viewCount,
            'comment_count' => $commentCount,
            'like_count' => $likeCount,
            'price' => null,
            'store_name' => $this->extractStoreName($title),
            'category' => $category,
            'original_created_at' => $createdAt,
            'source_id' => $this->sourceId
        ];
    }
    
    private function removeUrlParams($url) {
        $parsed = parse_url($url);
        $clean = ($parsed['scheme'] ?? '') ? $parsed['scheme'] . '://' : '';
        $clean .= $parsed['host'] ?? '';
        $clean .= $parsed['path'] ?? '';
        return $clean ?: ($parsed['path'] ?? $url);
    }
    
    private function parseDate($dateText) {
        if (empty($dateText)) return date('Y-m-d H:i:s');
        
        $now = time();
        $today = date('Y-m-d');
        
        // HH:MM 형식
        if (preg_match('/^(\d{2}):(\d{2})$/', $dateText, $m)) {
            return $today . ' ' . $m[1] . ':' . $m[2] . ':00';
        }
        
        if (preg_match('/(\d+)시간\s*전/', $dateText, $m)) {
            return date('Y-m-d H:i:s', $now - ($m[1] * 3600));
        }
        
        if (preg_match('/(\d+)분\s*전/', $dateText, $m)) {
            return date('Y-m-d H:i:s', $now - ($m[1] * 60));
        }
        
        if (preg_match('/(\d+)일\s*전/', $dateText, $m)) {
            return date('Y-m-d H:i:s', $now - ($m[1] * 86400));
        }
        
        if (preg_match('/(\d{2})\.(\d{2})/', $dateText, $m)) {
            return date('Y') . '-' . $m[1] . '-' . $m[2] . ' 00:00:00';
        }
        
        return date('Y-m-d H:i:s');
    }
    
    private function cleanTitle($title) {
        $title = preg_replace('/\s*\(\d+\)\s*$/', '', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        return trim(strip_tags($title));
    }
    
    private function extractStoreName($title) {
        if (preg_match('/\[([^\]]+)\]/', $title, $m)) {
            $store = trim($m[1]);
            if (mb_strlen($store) <= 15) {
                return $store;
            }
        }
        return null;
    }
    
    private function saveItem($item) {
        try {
            $existing = $this->db->fetch(
                "SELECT id FROM hotdeals WHERE source_id = ? AND original_id = ?",
                [$this->sourceId, $item['original_id']]
            );
            
            if ($existing) {
                $this->db->query("
                    UPDATE hotdeals 
                    SET view_count = ?, comment_count = ?, like_count = ?, crawled_at = NOW()
                    WHERE id = ?
                ", [
                    $item['view_count'],
                    $item['comment_count'],
                    $item['like_count'],
                    $existing['id']
                ]);
                return false;
            } else {
                $publicId = $this->generatePublicId();
                
                $this->db->query("
                    INSERT INTO hotdeals (
                        public_id, source_id, original_id, title, original_url, 
                        thumbnail_url, author_name, view_count, comment_count, 
                        like_count, price, store_name, original_created_at, 
                        crawled_at, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
                ", [
                    $publicId, $this->sourceId, $item['original_id'],
                    $item['title'], $item['original_url'], $item['thumbnail_url'],
                    $item['author_name'], $item['view_count'], $item['comment_count'],
                    $item['like_count'], $item['price'], $item['store_name'],
                    $item['original_created_at']
                ]);
                return true;
            }
        } catch (Exception $e) {
            echo "  DB 오류: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function generatePublicId() {
        $today = date('ymd');
        $lastId = $this->db->fetchColumn(
            "SELECT public_id FROM hotdeals WHERE public_id LIKE ? ORDER BY public_id DESC LIMIT 1",
            [$today . '%']
        );
        
        $newNumber = $lastId ? intval(substr($lastId, -3)) + 1 : 1;
        return $today . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
}

// 실행
if (isset($_SERVER['HTTP_HOST'])) {
    echo "<pre style='font-family: monospace; white-space: pre-wrap;'>";
}

try {
    $crawler = new RuliwebCrawler();
    $result = $crawler->crawl();
    echo $result ? "\n크롤링 성공\n" : "\n크롤링 실패\n";
} catch (Exception $e) {
    echo "오류 발생: " . $e->getMessage() . "\n";
}

if (isset($_SERVER['HTTP_HOST'])) {
    echo "</pre>";
}
?>