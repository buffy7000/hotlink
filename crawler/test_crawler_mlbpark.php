<?php
require_once 'BaseCrawler.php';

class MlbparkCrawler extends BaseCrawler {
    private $baseUrl = 'https://mlbpark.donga.com';
    private $targetUrl = 'https://mlbpark.donga.com/mp/honor.php';
    
    public function __construct() {
        parent::__construct(6, 'mlbpark');  // community_id 6번 (MLB파크)
    }
    
    public function crawlHotPosts($limit = 50) {
        return $this->crawlBestPosts($limit);
    }
    
    public function crawlBestPosts($limit = 50) {
        $allPosts = [];
        $totalSaved = 0;
        
        // 5페이지 크롤링
        for ($page = 1; $page <= 5; $page++) {
            try {
                echo "\nMLB파크 명예의전당 {$page}페이지 크롤링 시작...\n";
                echo str_repeat("=", 60) . "\n";
                
                // URL 파라미터 설정
                $url = $this->targetUrl;
                if ($page > 1) {
                    $url .= "?page={$page}";
                }
                echo "URL: {$url}\n";
                
                $html = $this->makeRequest($url);
                
                if (strlen($html) < 1000) {
                    throw new Exception("HTML 응답이 너무 짧습니다.");
                }
                
                echo "HTML 길이: " . strlen($html) . " 바이트\n";
                echo str_repeat("-", 60) . "\n";
                
                $posts = $this->parseHonorPosts($html, $limit);
                
                if (!empty($posts)) {
                    $savedCount = $this->savePosts($posts);
                    $totalSaved += $savedCount;
                    echo "\n{$page}페이지: {$savedCount}개 게시글 저장\n";
                }
                
                // 페이지 간 대기
                if ($page < 5) {
                    echo "다음 페이지까지 2초 대기...\n";
                    sleep(2);
                }
                
            } catch (Exception $e) {
                echo "페이지 {$page} 크롤링 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        echo "\n전체 MLB파크 크롤링 완료: 총 {$totalSaved}개 게시글 저장\n";
        return $totalSaved;
    }
    
    private function parseHonorPosts($html, $limit) {
        $posts = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 게시글 목록 찾기
        $items = $xpath->query('//li[@class="items"]');
        
        if (!$items || $items->length == 0) {
            echo "게시글 리스트를 찾을 수 없습니다.\n";
            return $posts;
        }
        
        echo "찾은 게시글: {$items->length}개\n";
        echo str_repeat("-", 60) . "\n";
        
        $count = 0;
        
        foreach ($items as $index => $item) {
            if ($count >= $limit) break;
            
            try {
                $post = $this->extractPostFromItem($item, $xpath, $index + 1);
                
                if ($post && $this->isValidPost($post)) {
                    $posts[] = $post;
                    $count++;
                    
                    echo "게시글 #{$count}\n";
                    echo "  제목: {$post['title']}\n";
                    echo "  작성자: {$post['author']}\n";
                    echo "  카테고리: {$post['category']}\n";
                    echo "  댓글: {$post['comments_count']}개\n";
                    echo "  시간: {$post['time']}\n";
                    echo "  URL: {$post['url']}\n";
                    echo str_repeat("-", 60) . "\n";
                }
            } catch (Exception $e) {
                echo "게시글 추출 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        echo "총 {$count}개 게시글 추출 완료\n";
        return $posts;
    }
    
    private function extractPostFromItem($item, $xpath, $rank) {
        // 1. 제목과 URL 추출
        $titleLinkNode = $xpath->query('.//div[@class="title"]/a', $item)->item(0);
        if (!$titleLinkNode) {
            echo "  제목 링크를 찾을 수 없음\n";
            return null;
        }
        
        $title = trim($titleLinkNode->nodeValue);
        $href = $titleLinkNode->getAttribute('href');
        
        // URL 정리 - id 파라미터만 유지
        $url = $href;
        if (preg_match('/id=([^&]+)/', $href, $matches)) {
            $url = 'https://mlbpark.donga.com/mp/b.php?id=' . $matches[1];
        }
        
        // 2. 썸네일 이미지 추출
        $thumbnailUrl = null;
        $imgNode = $xpath->query('.//div[@class="photo"]/a/img', $item)->item(0);
        if ($imgNode) {
            $imgSrc = $imgNode->getAttribute('src');
            if (!empty($imgSrc)) {
                if (strpos($imgSrc, 'http') === 0) {
                    $thumbnailUrl = $imgSrc;
                } else if (strpos($imgSrc, '//') === 0) {
                    $thumbnailUrl = 'https:' . $imgSrc;
                } else if (strpos($imgSrc, '/') === 0) {
                    $thumbnailUrl = $this->baseUrl . $imgSrc;
                }
                echo "  -> 썸네일: {$thumbnailUrl}\n";
            }
        }
        
        // 3. 댓글 수 추출
        $commentsCount = 0;
        $replyNode = $xpath->query('.//span[@class="replycont"]', $item)->item(0);
        if ($replyNode) {
            $replyText = trim($replyNode->nodeValue);
            // [32] 형태에서 숫자만 추출
            if (preg_match('/\[(\d+)\]/', $replyText, $matches)) {
                $commentsCount = intval($matches[1]);
            }
        }
        
        // 4. 카테고리 추출
        $categoryNode = $xpath->query('.//span[@class="item_sub"]', $item)->item(0);
        $category = $categoryNode ? trim($categoryNode->nodeValue) : '';
        
        // 5. 작성자 추출
        $authorNode = $xpath->query('.//span[@class="user_name"]', $item)->item(0);
        $author = $authorNode ? trim($authorNode->nodeValue) : 'MLB파크';
        
        // 6. 시간 추출
        $timeNode = $xpath->query('.//span[@class="date"]', $item)->item(0);
        $time = $timeNode ? trim($timeNode->nodeValue) : '';
        $createdAt = date('Y-m-d H:i:s'); // 기본값은 현재 시각

        if (!empty($time)) {
            // 시간 형식 변환
            $createdAt = $this->parsePostTime($time);
        }
        
        return [
            'title' => $this->cleanTitle($title),
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'author' => $author,
            'comments_count' => $commentsCount,
            'views_count' => 0,  // MLB파크는 조회수 정보가 없음
            'likes_count' => 0,  // MLB파크는 추천수 정보가 없음
            'created_at' => $createdAt,
            'time' => $time,
            'category' => $category,
            'site' => 'mlbpark',
            'rank' => $rank
        ];
    }
    
    private function isValidPost($post) {
        if (empty($post['title']) || empty($post['url'])) {
            echo "  유효성 검사 실패: 제목 또는 URL이 비어있음\n";
            return false;
        }
        
        if (strlen($post['title']) < 2) {
            echo "  유효성 검사 실패: 제목이 너무 짧음\n";
            return false;
        }
        
        echo "  유효성 검사 통과\n";
        return true;
    }
    
    private function cleanTitle($title) {
        $title = strip_tags($title);
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);
        return $title;
    }
    
    private function parsePostTime($timeString) {
    if (empty($timeString)) {
        return date('Y-m-d H:i:s');
    }
    
    // HH:MM:SS 형식 (당일 게시글: 05:40:52)
    if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $timeString, $matches)) {
        $hour = intval($matches[1]);
        $minute = $matches[2];
        $second = $matches[3];
        
        $currentHour = intval(date('H'));
        $postTime = date('Y-m-d') . ' ' . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . $minute . ':' . $second;
        
        // 만약 게시글 시간이 현재 시간보다 미래라면 = 어제 게시글
        if ($hour > $currentHour + 1) {  // 1시간 여유
            $postTime = date('Y-m-d', strtotime('-1 day')) . ' ' . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . $minute . ':' . $second;
        }
        
        return $postTime;
    }
    
    // YYYY-MM-DD 형식 (오늘 이전 날짜: 2025-08-23)
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $timeString, $matches)) {
        $year = $matches[1];
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        return $year . '-' . $month . '-' . $day . ' 00:00:00';
    }
    
    // 파싱할 수 없는 경우 현재 시각 반환
    return date('Y-m-d H:i:s');
}
}

// 실행 코드
echo "<h2>MLB파크 크롤러 테스트</h2>\n";
echo "<pre>\n";

$crawler = new MlbparkCrawler();
$result = $crawler->crawlHotPosts(50);

echo "\n테스트 완료: {$result}개 게시글 저장됨\n";
echo "</pre>\n";
?>