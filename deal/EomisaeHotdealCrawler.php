<?php
/**
 * 어미새 크롤러
 * 파일명: EomisaeCrawler.php
 */
// SimpleHotdealDB 클래스 로드
require_once(__DIR__ . '/SimpleHotdealDB.php');

class EomisaeCrawler {
    private $db;
    private $sourceId = 5; // 어미새 source_id = 5
    private $baseUrl = 'https://eomisae.co.kr';
    private $hotdealUrl = 'https://eomisae.co.kr/fs';
    
    public function __construct() {
        $this->db = new SimpleHotdealDB();
    }
    
    public function crawl() {
        echo "어미새 크롤링 시작...\n";
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
        echo "어미새 크롤링 완료\n";
        
        return true;
    }
    
    private function fetchPage($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10, // 응답이 없을 때 뒤 사이트를 굶기지 않도록 짧게 설정
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control: no-cache',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Referer: https://eomisae.co.kr/'
            ],
            CURLOPT_COOKIEJAR => '/tmp/eomisae_cookies.txt',
            CURLOPT_COOKIEFILE => '/tmp/eomisae_cookies.txt'
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
        
        // 어미새 게시글 목록 선택자
        $articleNodes = $xpath->query('//div[@class="rt_area is_tmb"]');
        
        echo "어미새에서 {$articleNodes->length}개 아이템 발견\n";
        
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
        $titleNode = $xpath->query('.//h3/a', $node)->item(0);
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
        
        // URL에서 ID 추출 (https://eomisae.co.kr/fs/162517649)
        preg_match('/\/fs\/(\d+)/', $href, $matches);
        $originalId = $matches[1] ?? null;
        
        if (!$originalId) {
            return null;
        }
        
        // 썸네일 추출
        $thumbnailUrl = null;
        $thumbnailNode = $xpath->query('.//img[@class="tmb"]', $node)->item(0);
        if ($thumbnailNode) {
            $src = $thumbnailNode->getAttribute('src');
            if (!empty($src)) {
                $thumbnailUrl = $this->normalizeImageUrl($src);
            }
        }
        
        // 작성자 추출
        $authorName = '';
        $authorNode = $xpath->query('.//div[@class="info"]//span//div[contains(@class, "member_")]', $node)->item(0);
        if ($authorNode) {
            $authorName = trim($authorNode->textContent);
            // 랭크 아이콘 텍스트 제거
            $authorName = preg_replace('/^\d+/', '', $authorName);
            $authorName = trim($authorName);
        }
        
        // 작성일 추출
        $dateText = '';
        $dateNode = $xpath->query('.//p/span[last()]', $node)->item(0);
        if ($dateNode) {
            $dateText = trim($dateNode->textContent);
        }
        $createdAt = $this->parseDate($dateText);
        
        // 조회수 추출 (ion-ios-eye 아이콘 다음)
        $viewCount = 0;
        $viewNode = $xpath->query('.//span[i[@class="ion-ios-eye"]]', $node)->item(0);
        if ($viewNode) {
            $viewText = trim($viewNode->textContent);
            $viewCount = (int)preg_replace('/[^0-9]/', '', $viewText);
        }
        
        // 댓글수 추출 (ion-ios-chatbubble 아이콘 다음)
        $commentCount = 0;
        $commentNode = $xpath->query('.//span[i[@class="ion-ios-chatbubble"]]', $node)->item(0);
        if ($commentNode) {
            $commentText = trim($commentNode->textContent);
            $commentCount = (int)preg_replace('/[^0-9]/', '', $commentText);
        }
        
        // 추천수 추출 (ion-ios-heart 아이콘 다음)
        $likeCount = 0;
        $likeNode = $xpath->query('.//span[i[@class="ion-ios-heart"]]', $node)->item(0);
        if ($likeNode) {
            $likeText = trim($likeNode->textContent);
            $likeCount = (int)preg_replace('/[^0-9]/', '', $likeText);
        }
        
        // 카테고리 추출
        $category = '';
        $categoryNode = $xpath->query('.//span[@class="cate"]', $node)->item(0);
        if ($categoryNode) {
            $category = trim(str_replace(',', '', $categoryNode->textContent));
        }
        
        return [
            'original_id' => $originalId,
            'title' => $this->cleanTitle($title),
            'original_url' => $href,
            'thumbnail_url' => $thumbnailUrl,
            'author_name' => $authorName,
            'view_count' => $viewCount,
            'comment_count' => $commentCount,
            'like_count' => $likeCount,
            'price' => $this->extractPrice($title),
            'store_name' => $this->extractStoreName($title, $category),
            'original_created_at' => $createdAt,
            'source_id' => $this->sourceId
        ];
    }
    
    private function isValidHotdeal($item) {
        if (empty($item['original_id']) || empty($item['title'])) {
            return false;
        }
        
        return strlen($item['title']) >= 3;
    }
    
private function parseDate($dateText) {
    if (empty($dateText)) {
        return date('Y-m-d H:i:s');
    }
    
    $currentTime = date('H:i:s'); // 현재 크롤링 시간 사용
    
    // "25.09.15" 형식 처리
    if (preg_match('/(\d{2})\.(\d{2})\.(\d{2})/', $dateText, $matches)) {
        $year = '20' . $matches[1]; // 25 -> 2025
        $month = $matches[2];
        $day = $matches[3];
        return "{$year}-{$month}-{$day} {$currentTime}";
    }
    
    // "2025.09.15" 형식 처리
    if (preg_match('/(\d{4})\.(\d{2})\.(\d{2})/', $dateText, $matches)) {
        return "{$matches[1]}-{$matches[2]}-{$matches[3]} {$currentTime}";
    }
    
    return date('Y-m-d H:i:s');
}

    private function cleanTitle($title) {
        return trim(strip_tags($title));
    }
    
    private function extractPrice($title) {
        // 가격 패턴 매칭
        if (preg_match('/(\d{1,3}(?:,\d{3})*)\s*원/', $title, $matches)) {
            return number_format((int)str_replace(',', '', $matches[1])) . '원';
        }
        
        if (preg_match('/(\d+)\s*%\s*할인/', $title, $matches)) {
            return $matches[1] . '%할인';
        }
        
        if (preg_match('/\$\s*([\d,.]+)/', $title, $matches)) {
            return '$' . $matches[1];
        }
        
        return null;
    }
    
    private function extractStoreName($title, $category = '') {
        // 쇼핑몰명 추출
        $stores = [
            '배달의민족', '배민', '쿠팡', '11번가', '옥션', 'G마켓', '위메프', '티몬', 
            '인터파크', '네이버쇼핑', '다나와', '롯데온', 'SSG', '하이마트', '전자랜드',
            '아마존', '알리익스프레스', '타오바오', '이베이', '마켓컬리', '올리브영',
            '스타벅스', '맥도날드', 'KFC', '버거킹', '도미노피자', '피자헛'
        ];
        
        foreach ($stores as $store) {
            if (strpos($title, $store) !== false) {
                return $store;
            }
        }
        
        // 카테고리를 쇼핑몰로 사용 (기타국내 등)
        if (!empty($category) && $category !== '기타국내') {
            return $category;
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
    
    public function saveItem($item) {
        try {
            // 중복 체크
            $existing = $this->db->fetch(
                "SELECT id, view_count, comment_count FROM hotdeals WHERE source_id = ? AND original_id = ?",
                [$this->sourceId, $item['original_id']]
            );
            
            if ($existing) {
                // 업데이트: 제목, 조회수, 댓글수, 이미지URL 갱신
                $this->db->query("
                    UPDATE hotdeals 
                    SET title = ?,view_count = ?, comment_count = ?, thumbnail_url = ?, crawled_at = NOW()
                    WHERE id = ?
                ", [
                    $item['title'],
                    $item['view_count'],
                    $item['comment_count'],
                    $item['thumbnail_url'],
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

// run_crawler_deal.php의 HotdealCrawlerManager가 유일한 실행 경로다.
// (require만 해도 즉시 크롤링이 실행되던 하단 코드는 제거 - 쿠팡 크롤러와 동일한 이중실행 버그였음)