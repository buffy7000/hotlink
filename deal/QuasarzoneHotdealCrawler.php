<?php
/**
 * 다중 페이지 지원 퀘사이존 크롤러
 * 파일명: QuasarzoneMultiPageCrawler.php
 */

// SimpleHotdealDB 클래스 로드
require_once(__DIR__ . '/SimpleHotdealDB.php');

class QuasarzoneMultiPageCrawler {
    private $db;
    private $sourceId = 2;
    private $baseUrl = 'https://quasarzone.com';
    private $listUrl = 'https://quasarzone.com/bbs/qb_saleinfo';
    
    public function __construct() {
        $this->db = new SimpleHotdealDB();
    }
    
    public function crawlMultiplePages($perPageLimit = 30, $maxPages = 2) {
        echo "퀘사이존 다중 페이지 크롤링 시작\n";
        echo str_repeat("=", 70) . "\n";
        echo "설정: 페이지당 {$perPageLimit}개 × {$maxPages}페이지 = 최대 " . ($perPageLimit * $maxPages) . "개\n";
        echo str_repeat("=", 70) . "\n\n";
        
        $allHotdeals = [];
        $totalNew = 0;
        $totalUpdated = 0;
        
        for ($page = 1; $page <= $maxPages; $page++) {
            echo "【페이지 {$page}】 크롤링 시작\n";
            echo str_repeat("-", 50) . "\n";
            
            // 페이지 URL 생성
            if ($page == 1) {
                $url = $this->listUrl;
            } else {
                $url = $this->listUrl . "?page=" . $page;
            }
            
            echo "URL: {$url}\n";
            
            try {
                // HTML 가져오기
                $html = $this->makeRequest($url);
                echo "HTML 크기: " . number_format(strlen($html)) . " bytes\n";
                
                // 파싱
                $pageHotdeals = $this->parsePageHotdeals($html, $perPageLimit, $page);
                echo "페이지 {$page} 파싱 완료: " . count($pageHotdeals) . "개\n";
                
                if (count($pageHotdeals) == 0) {
                    echo "페이지 {$page}에서 새 핫딜 없음 - 중단\n";
                    break;
                }
                
                // 저장
                $result = $this->savePageHotdeals($pageHotdeals, $page);
                $totalNew += $result['new'];
                $totalUpdated += $result['updated'];
                
                $allHotdeals = array_merge($allHotdeals, $pageHotdeals);
                
                echo "페이지 {$page} 저장 완료: 신규 {$result['new']}개, 업데이트 {$result['updated']}개\n";
                
                // 페이지 간 딜레이
                if ($page < $maxPages) {
                    echo "다음 페이지까지 3초 대기...\n";
                    sleep(3);
                }
                
            } catch (Exception $e) {
                echo "페이지 {$page} 오류: " . $e->getMessage() . "\n";
                continue;
            }
            
            echo str_repeat("-", 50) . "\n\n";
        }
        
        echo str_repeat("=", 70) . "\n";
        echo "전체 크롤링 완료!\n";
        echo "총 수집: " . count($allHotdeals) . "개\n";
        echo "신규 저장: {$totalNew}개\n";
        echo "업데이트: {$totalUpdated}개\n";
        echo str_repeat("=", 70) . "\n";
        
        return $totalNew + $totalUpdated;
    }
    
    private function makeRequest($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ko-KR,ko;q=0.8,en-US;q=0.5,en;q=0.3',
                'Connection: keep-alive'
            ]
        ]);
        
        usleep(rand(500000, 1000000)); // 0.5~1초 대기
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP 오류: {$httpCode}");
        }
        
        return $response;
    }
    
    private function parsePageHotdeals($html, $limit, $pageNumber) {
        $hotdeals = [];
        $processedIds = [];
        
        // DOM 파싱
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 퀘사이존 핫딜 리스트 찾기
        $items = $xpath->query('//div[contains(@class, "market-info-list")]');
        echo "페이지 {$pageNumber}에서 {$items->length}개 아이템 발견\n";
        
        $count = 0;
        foreach ($items as $item) {
            if ($count >= $limit) break;
            
            try {
                $hotdeal = $this->parseHotdealItem($item, $xpath);
                if ($hotdeal && $this->isValidHotdeal($hotdeal)) {
                    if (!in_array($hotdeal['original_id'], $processedIds)) {
                        $hotdeals[] = $hotdeal;
                        $processedIds[] = $hotdeal['original_id'];
                        $count++;
                        
                        echo sprintf("  [%d-%d] %s (ID: %s)\n", 
                            $pageNumber, 
                            $count, 
                            substr($hotdeal['title'], 0, 40) . '...', 
                            $hotdeal['original_id']
                        );
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        return $hotdeals;
    }
    
    private function parseHotdealItem($item, $xpath) {
        // 링크 및 제목
        $titleLink = $xpath->query('.//a[contains(@href, "/views/")]', $item)->item(0);
        if (!$titleLink) return null;
        
        $href = $titleLink->getAttribute('href');
        $title = trim($titleLink->textContent);
        
        if (empty($title)) {
            $titleElement = $xpath->query('.//span[contains(@class, "ellipsis")]', $item)->item(0);
            if ($titleElement) {
                $title = trim($titleElement->textContent);
            }
        }
        
        if (empty($title)) return null;
        
        // 절대 URL
        if (strpos($href, 'http') !== 0) {
            $href = $this->baseUrl . $href;
        }
        
        // ID 추출
        $originalId = '';
        if (preg_match('/\/views\/(\d+)/', $href, $matches)) {
            $originalId = $matches[1];
        }
        if (empty($originalId)) return null;
        
// 가격
$price = '';
$priceElement = $xpath->query('.//*[contains(@class, "text-orange")]', $item)->item(0);
if ($priceElement) {
    $rawPrice = trim($priceElement->textContent);
    $price = $this->cleanPrice($rawPrice);
}

        
        // 카테고리
        $category = '';
        $categoryElement = $xpath->query('.//*[contains(@class, "category")]', $item)->item(0);
        if ($categoryElement) {
            $category = trim($categoryElement->textContent);
        }
        
        // 작성자
        $authorName = '';
        $authorElement = $xpath->query('.//*[contains(@class, "user-nick")]', $item)->item(0);
        if ($authorElement) {
            $authorName = trim($authorElement->textContent);
        }
        
        // 댓글수
        $commentCount = 0;
        $commentElement = $xpath->query('.//*[contains(@class, "ctn-count")]', $item)->item(0);
        if ($commentElement) {
            $commentCount = (int)trim($commentElement->textContent);
        }
        
        // 썸네일
        $thumbnailUrl = null;
        $thumbImg = $xpath->query('.//img[@src]', $item)->item(0);
        if ($thumbImg) {
            $src = $thumbImg->getAttribute('src');
            if (!empty($src)) {
                $thumbnailUrl = $this->normalizeImageUrl($src);
            }
        }
        
        // 쇼핑몰 추출
        $storeName = '';
        if (preg_match('/\[([^\]]+)\]/', $title, $matches)) {
            $storeName = trim($matches[1]);
        }
        

// 작성시간 추출
$createdAt = null;
$dateElement = $xpath->query('.//span[@class="date"]', $item)->item(0);
if ($dateElement) {
    $timeText = trim($dateElement->textContent);
    $createdAt = $this->parseQuasarzoneTime($timeText);
}

// 파싱 실패시에만 현재 시간 사용
if (!$createdAt) {
    $createdAt = date('Y-m-d H:i:s');
}

// 제목과 가격 통합 처리
$finalTitle = $this->processTitleAndPrice($title, $price);

return [
    'original_id' => $originalId,
    'title' => $finalTitle,
    'original_url' => $href,
    'thumbnail_url' => $thumbnailUrl,
    'author_name' => $authorName,
    'view_count' => 0,
    'comment_count' => $commentCount,
    'like_count' => 0,
    'price' => $price,
    'store_name' => $storeName,
    'category' => $category,
    'original_created_at' => $createdAt,  // 필드명 수정
    'crawled_at' => date('Y-m-d H:i:s')   // 크롤링 시간 추가
];


    }
    
    private function processTitleAndPrice($title, $price) {
        $cleanTitle = $this->cleanTitle($title);
        
        // 제목에 이미 가격이 포함되어 있는지 확인
        if ($this->hasPriceInTitle($cleanTitle)) {
            // Case 2: 제목에 가격이 이미 있으면 그대로 반환
            return $cleanTitle;
        } else if (!empty($price)) {
            // Case 1: 제목에 가격이 없고 별도 가격이 있으면 제목에 추가
            return $cleanTitle . ' ' . $price;
        } else {
            // 가격 정보가 없는 경우 제목만 반환
            return $cleanTitle;
        }
    }

    private function hasPriceInTitle($title) {
        // 가격 패턴 매칭: 숫자,숫자원 또는 $숫자 등
        return preg_match('/[\d,]+원|[$]\d+/', $title);
    }

    private function savePageHotdeals($hotdeals, $pageNumber) {
        $newCount = 0;
        $updatedCount = 0;
        
        foreach ($hotdeals as $hotdeal) {
            try {
                // 중복 체크
                $existing = $this->db->fetch(
                    "SELECT id, view_count, comment_count FROM hotdeals WHERE source_id = ? AND original_id = ?",
                    [$this->sourceId, $hotdeal['original_id']]
                );
                
                if ($existing) {
                    // 업데이트: 댓글수, 조회수만 업데이트
                    $this->db->query("
                        UPDATE hotdeals SET 
                        view_count = ?, comment_count = ?, updated_at = NOW()
                        WHERE id = ?
                    ", [
                        $hotdeal['view_count'], 
                        $hotdeal['comment_count'], 
                        $existing['id']
                    ]);
                    $updatedCount++;
                    
                    echo "    업데이트: ID {$hotdeal['original_id']} (댓글: {$existing['comment_count']} → {$hotdeal['comment_count']}, 조회: {$existing['view_count']} → {$hotdeal['view_count']})\n";
                    
                } else {
                    // 신규 저장 - crawled_at 필드도 함께 저장
                    $publicId = $this->generatePublicId();
                    $this->db->query("
                        INSERT INTO hotdeals (
                            public_id, source_id, original_id, title, original_url,
                            thumbnail_url, author_name, view_count, comment_count, like_count,
                            price, store_name, original_created_at, crawled_at, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ", [
                        $publicId, $this->sourceId, $hotdeal['original_id'], $hotdeal['title'],
                        $hotdeal['original_url'], $hotdeal['thumbnail_url'], $hotdeal['author_name'],
                        $hotdeal['view_count'], $hotdeal['comment_count'], $hotdeal['like_count'],
                        $hotdeal['price'], $hotdeal['store_name'], $hotdeal['original_created_at'],
                        $hotdeal['crawled_at'] // 실제 크롤링 시간
                    ]);
                    $newCount++;
                    
                    echo "    신규 저장: ID {$hotdeal['original_id']} ({$publicId}) - 조회수: {$hotdeal['view_count']}\n";
                }
            } catch (Exception $e) {
                echo "    저장 실패: ID {$hotdeal['original_id']} - " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        return ['new' => $newCount, 'updated' => $updatedCount];
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
    
    private function normalizeImageUrl($src) {
        if (strpos($src, '//') === 0) {
            return 'https:' . $src;
        } elseif (strpos($src, '/') === 0) {
            return $this->baseUrl . $src;
        }
        return $src;
    }
    
    private function cleanTitle($title) {
        return trim(strip_tags($title));
    }
    
    private function isValidHotdeal($hotdeal) {
        if (empty($hotdeal['original_id']) || empty($hotdeal['title'])) {
            return false;
        }
        return strlen($hotdeal['title']) >= 5;
    }

    private function parseQuasarzoneTime($timeText) {
    $now = time();
    
    // "21시간 전" → 21시간을 빼기
    if (preg_match('/(\d+)시간\s*전/', $timeText, $matches)) {
        return date('Y-m-d H:i:s', $now - ($matches[1] * 3600));
    }
    
    // "30분 전" → 30분을 빼기  
    if (preg_match('/(\d+)분\s*전/', $timeText, $matches)) {
        return date('Y-m-d H:i:s', $now - ($matches[1] * 60));
    }
    
    // "2일 전" → 2일을 빼기
    if (preg_match('/(\d+)일\s*전/', $timeText, $matches)) {
        return date('Y-m-d H:i:s', $now - ($matches[1] * 86400));
    }
    
    // "방금 전"
    if (strpos($timeText, '방금') !== false) {
        return date('Y-m-d H:i:s', $now);
    }

    return null;
}


    private function cleanPrice($priceText) {
    if (empty($priceText)) {
        return '';
    }
    
    // "￦ 25,710원 (KRW)" → "25,710원"
    if (preg_match('/￦\s*([\d,]+)원?\s*\(KRW\)/i', $priceText, $matches)) {
        return number_format((int)str_replace(',', '', $matches[1])) . '원';
    }
    
    // "$ 34.99 (USD)" → "$ 34.99"  
    if (preg_match('/\$\s*([\d,.]+)\s*\(USD\)/i', $priceText, $matches)) {
        return '$ ' . $matches[1];
    }
    
    // "￦ 25,710" → "25,710원"
    if (preg_match('/￦\s*([\d,]+)/', $priceText, $matches)) {
        return number_format((int)str_replace(',', '', $matches[1])) . '원';
    }
    
    // 기본적으로 원본 텍스트에서 불필요한 부분 제거
    $cleaned = preg_replace('/\s*\([^)]+\)/', '', $priceText); // (KRW), (USD) 제거
    $cleaned = trim($cleaned);
    
    return $cleaned;
}

    

}

// run_crawler_deal.php의 HotdealCrawlerManager가 유일한 실행 경로다.
// (require만 해도 즉시 크롤링이 실행되던 하단 코드는 제거 - 쿠팡 크롤러와 동일한 이중실행 버그였음)