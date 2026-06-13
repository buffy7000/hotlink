<?php
/**
 * 에프엠코리아 핫딜 크롤러
 * 파일명: FMKoreaCrawler.php
 */
// SimpleHotdealDB 클래스 로드
require_once(__DIR__ . '/SimpleHotdealDB.php');

class FMKoreaCrawler {
    private $db;
    private $sourceId = 4; // 에프엠코리아 source_id = 4
    private $baseUrl = 'https://www.fmkorea.com';
    private $hotdealUrl = 'https://www.fmkorea.com/index.php?mid=hotdeal';
    
    public function __construct() {
        $this->db = new SimpleHotdealDB();
    }
    
    public function crawl() {
        echo "에프엠코리아 핫딜 크롤링 시작...\n";
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
        echo "에프엠코리아 크롤링 완료\n";
        
        return true;
    }
    
    private function fetchPage($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: ko-KR,ko;q=0.8,en-US;q=0.5,en;q=0.3',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ]
        ]);
        
        usleep(rand(500000, 1000000)); // 0.5~1초 대기
        
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
        
        // DOMDocument로 HTML 파싱
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 에프엠코리아 게시글 목록 선택자 - 실제 구조에 맞게 조정
        $articleNodes = $xpath->query('//li[contains(@class, "li_best")]');
        
        if ($articleNodes->length == 0) {
            // 대안 선택자들 시도
            $articleNodes = $xpath->query('//div[contains(@class, "hotdeal")]//tr');
            if ($articleNodes->length == 0) {
                $articleNodes = $xpath->query('//table//tr[contains(@onclick, "location")]');
            }
        }
        
        echo "에프엠코리아에서 {$articleNodes->length}개 아이템 발견\n";
        
        $count = 0;
        foreach ($articleNodes as $node) {
            if ($count >= 20) break; // 20개 제한
            
            try {
                $item = $this->parseItem($xpath, $node);
                if ($item && $this->isValidHotdeal($item)) {
                    if (!in_array($item['original_id'], $processedIds)) {
                        $items[] = $item;
                        $processedIds[] = $item['original_id'];
                        $count++;
                        
                        echo sprintf("  [%d] %s (ID: %s)\n", 
                            $count, 
                            substr($item['title'], 0, 40) . '...', 
                            $item['original_id']
                        );
                    }
                }
            } catch (Exception $e) {
                echo "아이템 파싱 오류: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        return $items;
    }
    
    private function parseItem($xpath, $node) {
        // 제목과 링크 추출
        $titleNode = $xpath->query('.//a[contains(@href, "document_srl")]', $node)->item(0);
        if (!$titleNode) {
            $titleNode = $xpath->query('.//a[@href]', $node)->item(0);
        }
        
        if (!$titleNode) {
            return null;
        }
        
        $title = trim($titleNode->textContent);
        $href = $titleNode->getAttribute('href');
        
        if (empty($title)) {
            return null;
        }
        
        // 절대 URL로 변환
        if (strpos($href, 'http') !== 0) {
            if (strpos($href, '/') === 0) {
                $href = $this->baseUrl . $href;
            } else {
                $href = $this->baseUrl . '/' . $href;
            }
        }
        
        // URL에서 document_srl 추출
        preg_match('/document_srl=(\d+)/', $href, $matches);
        $originalId = $matches[1] ?? null;
        
        if (!$originalId) {
            // onclick에서 추출 시도
            $onclick = $node->getAttribute('onclick');
            if (preg_match('/document_srl=(\d+)/', $onclick, $matches)) {
                $originalId = $matches[1];
            }
        }
        
        if (!$originalId) {
            return null;
        }
        
        // 조회수 추출
        $viewCount = 0;
        $viewNode = $xpath->query('.//td[contains(@class, "hit")]', $node)->item(0);
        if (!$viewNode) {
            $viewNode = $xpath->query('.//*[contains(text(), "조회")]', $node)->item(0);
        }
        if ($viewNode) {
            $viewText = trim($viewNode->textContent);
            $viewCount = (int)preg_replace('/[^0-9]/', '', $viewText);
        }
        
        // 댓글수 추출
        $commentCount = 0;
        $commentNode = $xpath->query('.//span[contains(@class, "comment")]', $node)->item(0);
        if (!$commentNode) {
            $commentNode = $xpath->query('.//*[contains(text(), "댓글")]', $node)->item(0);
        }
        if ($commentNode) {
            $commentText = trim($commentNode->textContent);
            $commentCount = (int)preg_replace('/[^0-9]/', '', $commentText);
        }
        
        // 작성자 추출
        $authorName = '';
        $authorNode = $xpath->query('.//span[contains(@class, "author")]', $node)->item(0);
        if (!$authorNode) {
            $authorNode = $xpath->query('.//td[contains(@class, "author")]', $node)->item(0);
        }
        if ($authorNode) {
            $authorName = trim($authorNode->textContent);
        }
        
        // 작성일 추출
        $dateNode = $xpath->query('.//td[contains(@class, "time")]', $node)->item(0);
        if (!$dateNode) {
            $dateNode = $xpath->query('.//*[contains(@class, "date")]', $node)->item(0);
        }
        $dateText = $dateNode ? trim($dateNode->textContent) : '';
        $createdAt = $this->parseDate($dateText);
        
        // 썸네일 추출
        $thumbnailUrl = null;
        $thumbnailNode = $xpath->query('.//img[@src]', $node)->item(0);
        if ($thumbnailNode) {
            $src = $thumbnailNode->getAttribute('src');
            if (!empty($src) && strpos($src, 'data:') !== 0) {
                $thumbnailUrl = $this->normalizeImageUrl($src);
            }
        }
        
        return [
            'original_id' => $originalId,
            'title' => $this->cleanTitle($title),
            'original_url' => $href,
            'thumbnail_url' => $thumbnailUrl,
            'author_name' => $authorName,
            'view_count' => $viewCount,
            'comment_count' => $commentCount,
            'like_count' => 0, // 에프엠코리아는 추천수 정보 없음
            'price' => $this->extractPrice($title),
            'store_name' => $this->extractStoreName($title),
            'original_created_at' => $createdAt,
            'source_id' => $this->sourceId
        ];
    }
    
    private function isValidHotdeal($item) {
        if (empty($item['original_id']) || empty($item['title'])) {
            return false;
        }
        
        // 핫딜 관련 키워드 필터링
        $hotdealKeywords = ['할인', '특가', '세일', '무료', '증정', '쿠폰', '적립', '%', '원', '가격', '덤', '이벤트'];
        
        foreach ($hotdealKeywords as $keyword) {
            if (strpos($item['title'], $keyword) !== false) {
                return true;
            }
        }
        
        return strlen($item['title']) >= 5;
    }
    
    private function parseDate($dateText) {
        if (empty($dateText)) {
            return date('Y-m-d H:i:s');
        }
        
        $now = time();
        
        // "21시간 전" → 21시간을 빼기
        if (preg_match('/(\d+)시간\s*전/', $dateText, $matches)) {
            return date('Y-m-d H:i:s', $now - ($matches[1] * 3600));
        }
        
        // "30분 전" → 30분을 빼기  
        if (preg_match('/(\d+)분\s*전/', $dateText, $matches)) {
            return date('Y-m-d H:i:s', $now - ($matches[1] * 60));
        }
        
        // "2일 전" → 2일을 빼기
        if (preg_match('/(\d+)일\s*전/', $dateText, $matches)) {
            return date('Y-m-d H:i:s', $now - ($matches[1] * 86400));
        }
        
        // 오늘 (시:분 형식)
        if (strpos($dateText, ':') !== false && strpos($dateText, '-') === false) {
            return date('Y-m-d H:i:s');
        } elseif (preg_match('/(\d{2})\.(\d{2})/', $dateText, $matches)) {
            // MM.DD 형식
            $month = $matches[1];
            $day = $matches[2];
            $year = date('Y');
            return "{$year}-{$month}-{$day} 00:00:00";
        } elseif (preg_match('/(\d{4})\.(\d{2})\.(\d{2})/', $dateText, $matches)) {
            // YYYY.MM.DD 형식
            return "{$matches[1]}-{$matches[2]}-{$matches[3]} 00:00:00";
        }
        
        return date('Y-m-d H:i:s');
    }
    
    private function cleanTitle($title) {
        // 불필요한 문자 제거
        $title = preg_replace('/\[.*?\]/', '', $title); // 대괄호 제거
        $title = preg_replace('/\s+/', ' ', $title); // 연속 공백 제거
        return trim(strip_tags($title));
    }
    
    private function extractPrice($title) {
        // 가격 패턴 매칭
        if (preg_match('/(\d{1,3}(?:,\d{3})*)\s*원/', $title, $matches)) {
            return $matches[1] . '원';
        }
        
        if (preg_match('/(\d+)\s*%/', $title, $matches)) {
            return $matches[1] . '%';
        }
        
        return null;
    }
    
    private function extractStoreName($title) {
        // 쇼핑몰명 추출 (일반적인 패턴)
        $stores = [
            '11번가', '옥션', 'G마켓', '쿠팡', '위메프', '티몬', '인터파크', 
            '네이버쇼핑', '다나와', '롯데온', 'SSG', '하이마트', '전자랜드',
            '아마존', '알리익스프레스', '타오바오', '이베이', '마켓컬리'
        ];
        
        foreach ($stores as $store) {
            if (strpos($title, $store) !== false) {
                return $store;
            }
        }
        
        // 대괄호 안의 텍스트를 쇼핑몰로 간주
        if (preg_match('/\[([^\]]+)\]/', $title, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    private function normalizeImageUrl($src) {
        if (strpos($src, '//') === 0) {
            return 'https:' . $src;
        } elseif (strpos($src, '/') === 0) {
            return $this->baseUrl . $src;
        }
        return $src;
    }
    
    private function saveItem($item) {
        try {
            // 중복 체크
            $existing = $this->db->fetch(
                "SELECT id, view_count, comment_count FROM hotdeals WHERE source_id = ? AND original_id = ?",
                [$this->sourceId, $item['original_id']]
            );
            
            if ($existing) {
                // 업데이트: 조회수, 댓글수만 갱신
                $this->db->query("
                    UPDATE hotdeals 
                    SET view_count = ?, comment_count = ?, crawled_at = NOW()
                    WHERE id = ?
                ", [
                    $item['view_count'],
                    $item['comment_count'],
                    $existing['id']
                ]);
                
                echo "  업데이트: " . substr($item['title'], 0, 30) . "...\n";
                return false; // 새로운 아이템이 아님
            } else {
                // 새로운 아이템 삽입
                $publicId = $this->generatePublicId();
                
                $this->db->query("
                    INSERT INTO hotdeals (
                        public_id, source_id, original_id, title, original_url, 
                        thumbnail_url, author_name, view_count, comment_count, 
                        like_count, price, store_name, original_created_at, 
                        crawled_at, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
                ", [
                    $publicId,
                    $this->sourceId,
                    $item['original_id'],
                    $item['title'],
                    $item['original_url'],
                    $item['thumbnail_url'],
                    $item['author_name'],
                    $item['view_count'],
                    $item['comment_count'],
                    $item['like_count'],
                    $item['price'],
                    $item['store_name'],
                    $item['original_created_at']
                ]);
                
                echo "  새로 추가: " . substr($item['title'], 0, 30) . "... (ID: {$publicId})\n";
                return true; // 새로운 아이템
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
        
        if ($lastId) {
            $newNumber = intval(substr($lastId, -3)) + 1;
        } else {
            $newNumber = 1;
        }
        
        return $today . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
}

// 실행
if (isset($_SERVER['HTTP_HOST'])) {
    echo "<pre style='font-family: monospace; white-space: pre-wrap;'>";
}

try {
    $crawler = new FMKoreaCrawler();
    $result = $crawler->crawl();
    
    if ($result) {
        echo "\n크롤링 성공\n";
    } else {
        echo "\n크롤링 실패\n";
    }
    
} catch (Exception $e) {
    echo "오류 발생: " . $e->getMessage() . "\n";
}

if (isset($_SERVER['HTTP_HOST'])) {
    echo "</pre>";
}
?>