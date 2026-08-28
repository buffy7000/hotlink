<?php
/**
 * 쿠팡 골드박스 API 크롤러
 * 파일명: CoupangGoldboxCrawler.php
 */

// 기존 DB 클래스와 HMAC 클라이언트 로드
require_once(__DIR__ . '/../deal/SimpleHotdealDB.php');
require_once(__DIR__ . '/hmac_client.php');

class CoupangGoldboxCrawler {
    private $db;
    private $sourceId = 99; // 쿠팡 골드박스용 source_id
    private $accessKey = '***REMOVED***';
    private $secretKey = '***REMOVED***';
    
    public function __construct() {
        $this->db = new SimpleHotdealDB();
    }
    
    public function crawlGoldboxProducts() {
        echo "쿠팡 골드박스 API 크롤링 시작\n";
        echo str_repeat("=", 70) . "\n";
        
        try {
            // API 호출
            $response = $this->callGoldboxAPI();
            
            if ($response['rCode'] != '0') {
                throw new Exception("API 응답 오류: " . $response['rMessage']);
            }
            
            echo "API 호출 성공: " . count($response['data']) . "개 상품 수신\n";
            echo "메시지: " . $response['rMessage'] . "\n";
            echo str_repeat("-", 50) . "\n";
            
            // 데이터 파싱 및 저장
            $result = $this->saveGoldboxProducts($response['data']);
            
            echo str_repeat("=", 70) . "\n";
            echo "골드박스 크롤링 완료!\n";
            echo "신규 저장: {$result['new']}개\n";
            echo "업데이트: {$result['updated']}개\n";
            echo "총 처리: " . ($result['new'] + $result['updated']) . "개\n";
            echo str_repeat("=", 70) . "\n";
            
            return $result['new'] + $result['updated'];
            
        } catch (Exception $e) {
            echo "오류 발생: " . $e->getMessage() . "\n";
            return 0;
        }
    }
    
    private function callGoldboxAPI() {
        $method = 'GET';
        $url = 'https://api-gateway.coupang.com/v2/providers/affiliate_open_api/apis/openapi/products/goldbox';
        $query = 'subId=hotlink1&imageSize=200x200'; // 더 큰 이미지 사용
        
        echo "API URL: {$url}?{$query}\n";
        
        return send_http_request($method, $url, $query, null, $this->accessKey, $this->secretKey);
    }
    
    private function saveGoldboxProducts($products) {
        $newCount = 0;
        $updatedCount = 0;
        
        foreach ($products as $index => $product) {
            try {
                // 쿠팡 골드박스 데이터를 핫딜 형식으로 변환
                $hotdeal = $this->convertToHotdealFormat($product);
                
                if (!$this->isValidHotdeal($hotdeal)) {
                    echo "  [" . ($index + 1) . "] 유효하지 않은 상품: " . substr($hotdeal['title'], 0, 30) . "...\n";
                    continue;
                }
                
                // 중복 체크
                $existing = $this->db->fetch(
                    "SELECT id, price FROM hotdeals WHERE source_id = ? AND original_id = ?",
                    [$this->sourceId, $hotdeal['original_id']]
                );
                
                if ($existing) {
                    // 가격 변동이 있으면 업데이트
                    if ($existing['price'] !== $hotdeal['price']) {
                        $this->db->query("
                            UPDATE hotdeals SET 
                            price = ?, title = ?, thumbnail_url = ?, updated_at = NOW()
                            WHERE id = ?
                        ", [
                            $hotdeal['price'],
                            $hotdeal['title'],
                            $hotdeal['thumbnail_url'],
                            $existing['id']
                        ]);
                        $updatedCount++;
                        
                        echo "  [" . ($index + 1) . "] 업데이트: {$hotdeal['title']} (가격: {$existing['price']} → {$hotdeal['price']})\n";
                    } else {
                        echo "  [" . ($index + 1) . "] 변경없음: " . substr($hotdeal['title'], 0, 30) . "...\n";
                    }
                    
                } else {
                    // 신규 저장
                    $publicId = $this->generatePublicId();
                    $this->db->query("
                        INSERT INTO hotdeals (
                            public_id, source_id, original_id, title, original_url,
                            thumbnail_url, author_name, view_count, comment_count, like_count,
                            price, store_name, original_created_at, crawled_at, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ", [
                        $publicId,
                        $this->sourceId,
                        $hotdeal['original_id'],
                        $hotdeal['title'],
                        $hotdeal['original_url'],
                        $hotdeal['thumbnail_url'],
                        $hotdeal['author_name'],
                        $hotdeal['view_count'],
                        $hotdeal['comment_count'],
                        $hotdeal['like_count'],
                        $hotdeal['price'],
                        $hotdeal['store_name'],
                        $hotdeal['original_created_at'],
                        $hotdeal['crawled_at']
                    ]);
                    $newCount++;
                    
                    echo "  [" . ($index + 1) . "] 신규 저장: {$hotdeal['title']} ({$publicId}) - {$hotdeal['price']}\n";
                }
                
            } catch (Exception $e) {
                echo "  [" . ($index + 1) . "] 저장 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        return ['new' => $newCount, 'updated' => $updatedCount];
    }
    
    private function convertToHotdealFormat($product) {
        // 쿠팡 상품 ID를 original_id로 사용
        $originalId = (string)$product['productId'];
        
        // 제목에 카테고리와 가격 정보 포함
        $title = $this->buildTitle($product);
        
        // 가격 포맷팅
        $price = $this->formatPrice($product['productPrice']);
        
        // 쿠팡 → 핫딜 형식 변환
        return [
            'original_id' => $originalId,
            'title' => $title,
            'original_url' => $product['productUrl'],
            'thumbnail_url' => $product['productImage'],
            'author_name' => '쿠팡',
            'view_count' => 0,
            'comment_count' => 0,
            'like_count' => 0,
            'price' => $price,
            'store_name' => '쿠팡',
            'category' => $product['categoryName'],
            'original_created_at' => date('Y-m-d H:i:s'), // 골드박스는 매일 갱신되므로 현재 시간
            'crawled_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function buildTitle($product) {
        $title = $product['productName'];
        $price = $this->formatPrice($product['productPrice']);
        
        // 간단한 제목 구성: 상품명 가격
        $finalTitle = $title;
        
        if (!empty($price)) {
            $finalTitle .= " " . $price;
        }
        
        return $finalTitle;
    }
    
    private function formatPrice($price) {
        if (empty($price) || $price == 0) {
            return '';
        }
        return number_format($price) . '원';
    }
    
    private function isValidHotdeal($hotdeal) {
        if (empty($hotdeal['original_id']) || empty($hotdeal['title'])) {
            return false;
        }
        
        if (strlen($hotdeal['title']) < 10) {
            return false;
        }
        
        return true;
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
    
    /**
     * 다른 크롤러와 동일한 인터페이스를 위한 메소드
     *
     * @param int|null $limit 사용하지 않음 (골드박스는 API에서 모든 상품 반환)
     * @return int 처리된 상품 수
     */
    public function crawlHotdeals($limit = null) {
        return $this->crawlGoldboxProducts();
    }

    /**
     * GitHub Actions 등 외부에서 이미 수집한 쿠팡 원본 상품 배열을 저장한다.
     * (hotlink.kr 서버 IP가 쿠팡에 차단되어 API 호출은 Actions에서 수행하고,
     *  결과 저장만 DB에 접근 가능한 이 서버에서 수행하기 위함)
     *
     * @param array $products 쿠팡 골드박스 API의 원본 data 배열
     * @return array ['new' => int, 'updated' => int]
     */
    public function saveProducts($products) {
        return $this->saveGoldboxProducts($products);
    }
}