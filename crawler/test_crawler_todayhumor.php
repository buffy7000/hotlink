<?php
require_once 'BaseCrawler.php';

class TodayhumorCrawler extends BaseCrawler {
    private $baseUrl = 'https://m.todayhumor.co.kr';
    private $targetUrl = 'https://m.todayhumor.co.kr/list.php?table=todaybest';
    
    public function __construct() {
        parent::__construct(9, 'todayhumor');  // community_id 9번 (오늘의유머)
    }
    
    public function crawlHotPosts($limit = 30) {
        return $this->crawlBestPosts($limit);
    }
    
    public function crawlBestPosts($limit = 30) {
        $allPosts = [];
        $totalSaved = 0;
        
        // 2페이지 크롤링
        for ($page = 1; $page <= 2; $page++) {
            try {
                echo "\n오늘의유머 베스트 {$page}페이지 크롤링 시작...\n";
                echo str_repeat("=", 60) . "\n";
                
                // URL 파라미터 설정
                $url = $this->targetUrl;
                if ($page > 1) {
                    $url .= "&page={$page}";
                }
                echo "URL: {$url}\n";
                
                $html = $this->makeRequest($url);
                
                if (strlen($html) < 1000) {
                    throw new Exception("HTML 응답이 너무 짧습니다.");
                }
                
                echo "HTML 길이: " . strlen($html) . " 바이트\n";
                echo str_repeat("-", 60) . "\n";
                
                $posts = $this->parseTodayPosts($html, $limit);
                
                if (!empty($posts)) {
                    $savedCount = $this->savePosts($posts);
                    $totalSaved += $savedCount;
                    echo "\n{$page}페이지: {$savedCount}개 게시글 저장\n";
                }
                
                // 페이지 간 대기
                if ($page < 2) {
                    echo "다음 페이지까지 2초 대기...\n";
                    sleep(2);
                }
                
            } catch (Exception $e) {
                echo "페이지 {$page} 크롤링 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        echo "\n전체 오늘의유머 크롤링 완료: 총 {$totalSaved}개 게시글 저장\n";
        return $totalSaved;
    }
    
    private function parseTodayPosts($html, $limit) {
        $posts = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 게시글 목록 찾기 - a 태그로 감싸진 구조
        $items = $xpath->query('//a[div[contains(@class, "listLineBox")]]');
        
        if (!$items || $items->length == 0) {
            echo "게시글 리스트를 찾을 수 없습니다.\n";
            return $posts;
        }
        
        echo "찾은 게시글: {$items->length}개\n";
        echo str_repeat("-", 60) . "\n";
        
        $count = 0;
        $skipped = 0;
        
        foreach ($items as $index => $item) {
            if ($count >= $limit) break;
            
            try {
                $post = $this->extractPostFromItem($item, $xpath, $index + 1);
                
                // null이 반환되면 제외된 게시글
                if ($post === null) {
                    $skipped++;
                    continue;
                }
                
                if ($this->isValidPost($post)) {
                    $posts[] = $post;
                    $count++;
                    
                    echo "게시글 #{$count}\n";
                    echo "  제목: {$post['title']}\n";
                    echo "  작성자: {$post['author']}\n";
                    echo "  순위: {$post['ranking']}\n";
                    echo "  댓글: {$post['comments_count']}개 | 조회: {$post['views_count']}회 | 추천: {$post['likes_count']}개\n";
                    echo "  시간: {$post['time']}\n";
                    echo "  게시판: {$post['board_type']}\n";
                    echo "  URL: {$post['url']}\n";
                    echo str_repeat("-", 60) . "\n";
                }
            } catch (Exception $e) {
                echo "게시글 추출 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        echo "총 {$count}개 게시글 추출 완료 (제외: {$skipped}개)\n";
        return $posts;
    }
    
    private function extractPostFromItem($item, $xpath, $rank) {
        // 1. URL 추출
        $href = $item->getAttribute('href');
        if (strpos($href, 'http') !== 0) {
            $url = $this->baseUrl . '/' . $href;
        } else {
            $url = $href;
        }
        
        // URL 정리 - 불필요한 파라미터 제거
        if (preg_match('/table=([^&]+)&no=([^&]+)/', $href, $matches)) {
            $url = $this->baseUrl . '/view.php?table=' . $matches[1] . '&no=' . $matches[2];
        }
        
        // 2. 게시글 ID 추출 (mn 속성)
        $postId = '';
        $divNode = $xpath->query('.//div[contains(@class, "listLineBox")]', $item)->item(0);
        if ($divNode) {
            $postId = $divNode->getAttribute('mn');
        }
        
        // 3. 게시판 종류 추출
        $boardType = '';
        if ($divNode) {
            $classes = $divNode->getAttribute('class');
            if (preg_match('/list_tr_(\w+)/', $classes, $matches)) {
                $boardType = $matches[1];
            }
        }
        
        // 4. 순위 추출
        $ranking = '';
        $rankNode = $xpath->query('.//span[@class="list_no"]', $item)->item(0);
        if ($rankNode) {
            $ranking = trim($rankNode->nodeValue);
        }
        
        // 5. 날짜/시간 추출
        $time = '';
        $dateNode = $xpath->query('.//span[@class="listDate"]', $item)->item(0);
        if ($dateNode) {
            $time = trim($dateNode->nodeValue);
        if (!empty($time)) {
            // 시간 형식 변환
            $createdAt = $this->parsePostTime($time);
        }            
        }
        
        // 6. 작성자 추출
        $author = '';
        $writerNode = $xpath->query('.//span[@class="list_writer"]', $item)->item(0);
        if ($writerNode) {
            $author = trim($writerNode->nodeValue);
            
            // 회원 여부 확인
            $isMember = $writerNode->getAttribute('is_member') === 'yes';
        }
        
        // 7. 제목 추출
        $title = '';
        $titleNode = $xpath->query('.//h2[@class="listSubject"]', $item)->item(0);
        if ($titleNode) {
            // 댓글 수 부분을 제외한 텍스트만 추출
            $titleClone = $titleNode->cloneNode(true);
            $commentNodes = $xpath->query('.//span[@class="list_comment_count"]', $titleClone);
            foreach ($commentNodes as $node) {
                $node->parentNode->removeChild($node);
            }
            $title = trim($titleClone->nodeValue);
        }
        
        // 예외처리: 제목에 '공지' 포함된 글 제외
        if (strpos($title, '공지') !== false || strpos($title, '[공지]') !== false) {
            echo "  공지사항 제외: 제목에 '공지' 포함\n";
            return null;
        }
        
        // 8. 댓글 수 추출
        $commentsCount = 0;
        $commentNode = $xpath->query('.//span[@class="memo_count"]', $item)->item(0);
        if ($commentNode) {
            $commentText = trim($commentNode->nodeValue);
            // [9] 형태에서 숫자만 추출
            if (preg_match('/\[(\d+)\]/', $commentText, $matches)) {
                $commentsCount = intval($matches[1]);
            }
        }
        
        // 9. 조회수 추출
        $viewsCount = 0;
        $viewNode = $xpath->query('.//span[@class="list_viewCount"]', $item)->item(0);
        if ($viewNode) {
            $viewsCount = intval(trim($viewNode->nodeValue));
        }
        
        // 10. 추천수 추출
        $likesCount = 0;
        $likeNode = $xpath->query('.//span[@class="list_okNokCount"]', $item)->item(0);
        if ($likeNode) {
            $likesCount = intval(trim($likeNode->nodeValue));
        }
        
        // 11. 이미지 여부 확인
        $hasImage = false;
        $imageNode = $xpath->query('.//div[@class="list_image_icon"]', $item)->item(0);
        if ($imageNode) {
            $hasImage = true;
        }
        
        // 12. 펌글 여부 확인
        $isPump = false;
        $pumpNode = $xpath->query('.//div[@class="list_icon_shovel"]', $item)->item(0);
        if ($pumpNode) {
            $isPump = true;
        }
                
        return [
            'title' => $this->cleanTitle($title),
            'url' => $url,
            'author' => $author ?: '오유회원',
            'comments_count' => $commentsCount,
            'views_count' => $viewsCount,
            'likes_count' => $likesCount,
            'created_at' => $createdAt, 
            'time' => $time,
            'category' => 'todaybest',
            'site' => 'todayhumor',
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
        
        // 추가 필터링: 제목에 특정 키워드 포함 시 제외
        $excludeKeywords = ['공지', '[공지]', '필독', '안내'];
        foreach ($excludeKeywords as $keyword) {
            if (strpos($post['title'], $keyword) !== false) {
                echo "  유효성 검사 실패: 제목에 '{$keyword}' 포함\n";
                return false;
            }
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
    
    // YYYY/MM/DD HH:MM 형식 (2025/08/23 09:56)
    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})$/', $timeString, $matches)) {
        $year = $matches[1];
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
        $minute = $matches[5];
        return $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute . ':00';
    }
    
    // 파싱할 수 없는 경우 현재 시각 반환
    return date('Y-m-d H:i:s');
}
    
    
}

// 실행 코드
echo "<h2>오늘의유머 크롤러 테스트</h2>\n";
echo "<pre>\n";

$crawler = new TodayhumorCrawler();
$result = $crawler->crawlHotPosts(30);

echo "\n테스트 완료: {$result}개 게시글 저장됨\n";
echo "</pre>\n";
?>
