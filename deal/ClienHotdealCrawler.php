<?php
/**
 * 클리앙 지름신 게시판 크롤러
 * 파일명: ClienJirumCrawler.php
 */

// SimpleHotdealDB 클래스 로드
require_once(__DIR__ . '/SimpleHotdealDB.php');

class ClienJirumCrawler {
    private $db;
    private $sourceId = 3; // 클리앙 source_id
    private $baseUrl = 'https://www.clien.net';
    private $listUrl = 'https://www.clien.net/service/board/jirum';
    
    public function __construct() {
        $this->db = new SimpleHotdealDB();
    }
    
    public function crawlHotdeals($limit = 30) {
        return $this->crawlSinglePage($limit);
    }    
    
    public function crawlSinglePage($perPageLimit = 30) {
        echo "클리앙 지름신 크롤링 시작...\n";
        echo str_repeat("=", 60) . "\n";
        echo "최대 페이지: 1페이지\n";
        echo "페이지당 한도: {$perPageLimit}개\n";
        echo str_repeat("-", 60) . "\n";
        
        echo "1페이지 크롤링 중...\n";
        echo str_repeat("-", 40) . "\n";
        
        $url = $this->listUrl;
        echo "URL: {$url}\n";
        
        try {
            // HTML 가져오기
            $html = $this->makeRequest($url);
            echo "HTML 길이: " . number_format(strlen($html)) . " 바이트\n";
            
            // 파싱
            $hotdeals = $this->parsePageHotdeals($html, $perPageLimit);
            echo "페이지 1에서 발견된 아이템: " . count($hotdeals) . "개\n";
            
            // 저장
            $result = $this->saveHotdeals($hotdeals);
            
            echo str_repeat("-", 40) . "\n";
            echo "크롤링 완료!\n";
            echo "총 수집: " . count($hotdeals) . "개\n";
            echo "신규 저장: {$result['new']}개\n";
            echo "업데이트: {$result['updated']}개\n";
            echo str_repeat("=", 60) . "\n";
            
            return $result['new'] + $result['updated'];
            
        } catch (Exception $e) {
            echo "오류 발생: " . $e->getMessage() . "\n";
            return 0;
        }
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
    
    private function parsePageHotdeals($html, $limit) {
        $hotdeals = [];
        $processedIds = [];
        
        // DOM 파싱
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 클리앙 게시글 리스트 찾기
        $items = $xpath->query('//div[@class="list_item symph_row jirum" or contains(@class, "list_item symph_row jirum")]');
        
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
                        
                        echo "  [1-{$count}] " . substr($hotdeal['title'], 0, 40) . "...\n";
                        if (!empty($hotdeal['price'])) {
                            echo "    가격: {$hotdeal['price']} | 쇼핑몰: " . ($hotdeal['store_name'] ?: '기타') . "\n";
                        }
                        if ($hotdeal['status'] === 'expired') {
                            echo "    상태: 품절\n";
                        }
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        return $hotdeals;
    }
    
    private function parseHotdealItem($item, $xpath) {
        // data-board-sn에서 ID 추출
        $originalId = $item->getAttribute('data-board-sn');
        if (empty($originalId)) return null;
        
        // 제목과 링크 추출
        $titleLink = $xpath->query('.//span[@class="list_subject"]//a[@data-role="list-title-text"]', $item)->item(0);
        if (!$titleLink) return null;
        
        $href = $titleLink->getAttribute('href');
        $title = trim($titleLink->textContent);
        
        if (empty($title)) return null;
        
        // URL 정리 (파라미터 제거)
        if (strpos($href, 'http') !== 0) {
            $href = $this->baseUrl . $href;
        }
        
        // URL 파라미터 제거
        if (strpos($href, '?') !== false) {
            $href = substr($href, 0, strpos($href, '?'));
        }
        
        // 조회수 추출
        $viewCount = 0;
        $viewElement = $xpath->query('.//div[@class="list_hit"]//span[@class="hit"]', $item)->item(0);
        if ($viewElement) {
            $viewCount = (int)trim($viewElement->textContent);
        }
        
        // 댓글수 추출
        $commentCount = 0;
        $commentElement = $xpath->query('.//span[@class="rSymph05"]', $item)->item(0);
        if ($commentElement) {
            $commentCount = (int)trim($commentElement->textContent);
        }
        
        // 좋아요수 추출
        $likeCount = 0;
        $likeElement = $xpath->query('.//span[@class="list_votes"]', $item)->item(0);
        if ($likeElement) {
            $likeText = trim($likeElement->textContent);
            if (preg_match('/(\d+)/', $likeText, $matches)) {
                $likeCount = (int)$matches[1];
            }
        }
        
        // 작성자 추출
        $authorName = '';
        $authorElement = $xpath->query('.//div[@class="list_author"]//a[@class="nickname"]/span', $item)->item(0);
        if ($authorElement) {
            $authorName = trim($authorElement->getAttribute('title') ?: $authorElement->textContent);
        }
        
        // 작성시간 추출 (숨겨진 timestamp 우선)
        $createdAt = date('Y-m-d H:i:s');
        $timestampElement = $xpath->query('.//span[@class="timestamp"]', $item)->item(0);
        if ($timestampElement) {
            $timestamp = trim($timestampElement->textContent);
            if (!empty($timestamp)) {
                $createdAt = $timestamp;
            }
        } else {
            // timestamp가 없으면 시간 텍스트에서 파싱
            $timeElement = $xpath->query('.//div[@class="list_time"]//span[@class="time popover"]', $item)->item(0);
            if ($timeElement) {
                $timeText = trim($timeElement->textContent);
                $parsedTime = $this->parseTimeText($timeText);
                if ($parsedTime) {
                    $createdAt = $parsedTime;
                }
            }
        }
        
        // 썸네일 추출
        $thumbnailUrl = null;
        $thumbImg = $xpath->query('.//a[@class="list_thumbnail"]//img', $item)->item(0);
        if ($thumbImg) {
            $src = $thumbImg->getAttribute('src');
            if (!empty($src)) {
                $thumbnailUrl = $this->normalizeImageUrl($src);
            }
        }
        
        // 가격 정보 추출 (제목에서)
        $price = '';
        
        // 쇼핑몰 추출 (제목에서)
        $storeName = $this->extractStoreName($title);
        
        // 상태 정보 추출 (품절 체크)
        $status = 'active'; // 기본값
        $statusElement = $xpath->query('.//span[@class="icon_info"]', $item)->item(0);
        if ($statusElement) {
            $statusText = trim($statusElement->textContent);
            if ($statusText === '품절') {
                $status = 'expired';
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
            'like_count' => $likeCount,
            'price' => $price,
            'store_name' => $storeName,
            'original_created_at' => $createdAt,
            'crawled_at' => date('Y-m-d H:i:s'),
            'status' => $status
        ];
    }
    
    private function extractStoreName($title) {
        // 쇼핑몰명 키워드 패턴 (대소문자 구분 없이)
        $storePatterns = [
            // 메이저 쇼핑몰
            '쿠팡' => ['쿠팡', 'coupang'],
            '11번가' => ['11번가', '11st', '일일번가'],
            '알리익스프레스' => ['알리', '알리익스프레스', 'aliexpress', 'ali'],
            '아마존' => ['아마존', 'amazon'],
            '지마켓' => ['지마켓', 'gmarket', 'G마켓'],
            '옥션' => ['옥션', 'auction'],
            'SSG' => ['ssg', 'SSG', '신세계'],
            '롯데온' => ['롯데온', 'lotteon'],
            '티몰' => ['티몰', 'tmall'],
            '타오바오' => ['타오바오', 'taobao'],
            '네이버쇼핑' => ['네이버', 'naver', '네쇼'],
            '이베이' => ['이베이', 'ebay'],
            
            // 브랜드/제조사
            '애플' => ['애플', 'apple'],
            '삼성' => ['삼성', 'samsung'],
            'LG' => ['LG', '엘지'],
            '샤오미' => ['샤오미', 'xiaomi'],
            '화웨이' => ['화웨이', 'huawei'],
            
            // 기타 쇼핑몰
            '위메프' => ['위메프', 'wemakeprice'],
            '티몬' => ['티몬', 'tmon'],
            '인터파크' => ['인터파크', 'interpark'],
            '홈플러스' => ['홈플러스', 'homeplus'],
            '이마트' => ['이마트', 'emart'],
            '코스트코' => ['코스트코', 'costco'],
            '다나와' => ['다나와', 'danawa'],
            '컴퓨존' => ['컴퓨존', 'compuzone'],
            '용산' => ['용산', '용산아이파크'],
            
            // 온라인몰
            '올리브영' => ['올리브영', 'oliveyoung'],
            '무신사' => ['무신사', 'musinsa'],
            '29CM' => ['29cm', '29CM'],
            '마켓컬리' => ['마켓컬리', 'kurly', '컬리'],
            
            // 해외직구
             'iHerb' => ['아이허브', 'iherb'],
            '직구' => ['직구', '해외직구'],
            '미국' => ['미국', 'USA', 'US'],
            '중국' => ['중국', 'china'],
            '일본' => ['일본', 'japan'],
        ];
        
        $title = trim($title);
        
        // 1단계: 대괄호 안의 내용 우선 확인
        if (preg_match('/\[([^\]]+)\]/', $title, $matches)) {
            $bracketContent = trim($matches[1]);
            
            // 대괄호 안의 내용이 쇼핑몰명인지 확인
            foreach ($storePatterns as $storeName => $patterns) {
                foreach ($patterns as $pattern) {
                    if (stripos($bracketContent, $pattern) !== false) {
                        return $storeName;
                    }
                }
            }
            
            // 대괄호 안의 내용이 쇼핑몰명이 아니라면 그대로 반환
            if (strlen($bracketContent) <= 20) { // 너무 긴 것은 쇼핑몰명이 아닐 가능성
                return $bracketContent;
            }
        }
        
        // 2단계: 제목 전체에서 쇼핑몰명 검색
        foreach ($storePatterns as $storeName => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($title, $pattern) !== false) {
                    return $storeName;
                }
            }
        }
        
        // 3단계: 소괄호 안의 내용 확인
        if (preg_match('/\(([^)]+)\)/', $title, $matches)) {
            $parenContent = trim($matches[1]);
            if (strlen($parenContent) <= 15) {
                return $parenContent;
            }
        }
        
        // 4단계: 특수 패턴 확인 (예: "쿠팡에서", "11번가 특가" 등)
        if (preg_match('/([가-힣A-Za-z0-9]+)에서|([가-힣A-Za-z0-9]+)\s*특가|([가-힣A-Za-z0-9]+)\s*할인/', $title, $matches)) {
            $candidate = trim($matches[1] ?: $matches[2] ?: $matches[3]);
            if (strlen($candidate) <= 10) {
                return $candidate;
            }
        }
        
        return '';
    }
    
    private function parseTimeText($timeText) {
        $now = time();
        
        // "방금 전", "1분 전", "2시간 전" 등의 형태
        if (preg_match('/(\d+)분\s*전/', $timeText, $matches)) {
            return date('Y-m-d H:i:s', $now - ($matches[1] * 60));
        }
        if (preg_match('/(\d+)시간\s*전/', $timeText, $matches)) {
            return date('Y-m-d H:i:s', $now - ($matches[1] * 3600));
        }
        if (preg_match('/(\d+)일\s*전/', $timeText, $matches)) {
            return date('Y-m-d H:i:s', $now - ($matches[1] * 86400));
        }
        if (strpos($timeText, '방금') !== false) {
            return date('Y-m-d H:i:s', $now);
        }
        
        // "09-07" 형태의 날짜
        if (preg_match('/(\d{2})-(\d{2})/', $timeText, $matches)) {
            $year = date('Y');
            return sprintf('%04d-%02d-%02d 00:00:00', $year, $matches[1], $matches[2]);
        }
        
        // "12:34" 형태의 시간
        if (preg_match('/(\d{1,2}):(\d{2})/', $timeText, $matches)) {
            $today = date('Y-m-d');
            return $today . ' ' . sprintf('%02d:%02d:00', $matches[1], $matches[2]);
        }
        
        return null;
    }
    
    private function saveHotdeals($hotdeals) {
        $newCount = 0;
        $updatedCount = 0;
        
        foreach ($hotdeals as $hotdeal) {
            try {
                // 중복 체크
                $existing = $this->db->fetch(
                    "SELECT id, view_count, comment_count, status FROM hotdeals WHERE source_id = ? AND original_id = ?",
                    [$this->sourceId, $hotdeal['original_id']]
                );
                
                if ($existing) {
                    // 업데이트: 댓글수, 조회수, 상태 업데이트
                    $this->db->query("
                        UPDATE hotdeals SET 
                        view_count = ?, comment_count = ?, status = ?
                        WHERE id = ?
                    ", [
                        $hotdeal['view_count'], 
                        $hotdeal['comment_count'],
                        $hotdeal['status'],
                        $existing['id']
                    ]);
                    $updatedCount++;
                    
                    echo "    업데이트: ID {$hotdeal['original_id']} (댓글: {$existing['comment_count']} → {$hotdeal['comment_count']}, 조회: {$existing['view_count']} → {$hotdeal['view_count']}, 상태: {$existing['status']} → {$hotdeal['status']})\n";
                    
                } else {
                    // 신규 저장
                    $publicId = $this->generatePublicId();
                    $this->db->query("
                        INSERT INTO hotdeals (
                            public_id, source_id, original_id, title, original_url,
                            thumbnail_url, author_name, view_count, comment_count, like_count,
                            price, store_name, original_created_at, crawled_at, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [
                        $publicId, $this->sourceId, $hotdeal['original_id'], $hotdeal['title'],
                        $hotdeal['original_url'], $hotdeal['thumbnail_url'], $hotdeal['author_name'],
                        $hotdeal['view_count'], $hotdeal['comment_count'], $hotdeal['like_count'],
                        $hotdeal['price'], $hotdeal['store_name'], $hotdeal['original_created_at'],
                        $hotdeal['crawled_at'], $hotdeal['status']
                    ]);
                    $newCount++;
                    
                    echo "    신규 저장: ID {$hotdeal['original_id']} ({$publicId}) - 조회수: {$hotdeal['view_count']}, 상태: {$hotdeal['status']}\n";
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
}

// run_crawler_deal.php의 HotdealCrawlerManager가 유일한 실행 경로다.
// (require만 해도 즉시 크롤링이 실행되던 하단 코드는 제거 - 쿠팡 크롤러와 동일한 이중실행 버그였음)