<?php
require_once 'BaseCrawler.php';

class BobaedreamCrawler extends BaseCrawler {
    private $baseUrl = 'https://m.bobaedream.co.kr';
    private $targetUrl = 'https://m.bobaedream.co.kr/board/new_writing/best';
    
    public function __construct() {
        parent::__construct(7, 'bobaedream');  // community_id 7번 (보배드림)
    }
    
    public function crawlHotPosts($limit = 20) {
        return $this->crawlBestPosts($limit);
    }
    
    public function crawlBestPosts($limit = 20) {
        $allPosts = [];
        $totalSaved = 0;
        
        // 5페이지 크롤링
        for ($page = 1; $page <= 5; $page++) {
            try {
                echo "\n보배드림 베스트 {$page}페이지 크롤링 시작...\n";
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
                
                $posts = $this->parseBestPosts($html, $limit);
                
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
        
        echo "\n전체 보배드림 크롤링 완료: 총 {$totalSaved}개 게시글 저장\n";
        return $totalSaved;
    }
    
    private function parseBestPosts($html, $limit) {
        $posts = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 게시글 목록 찾기 - div.info 구조
        $items = $xpath->query('//div[@class="info"]');
        
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
                    echo "  카테고리: {$post['category']}\n";
                    echo "  댓글: {$post['comments_count']}개 | 조회: {$post['views_count']}회 | 추천: {$post['likes_count']}개\n";
                    echo "  시간: {$post['time']}\n";
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
        // 1. 제목 추출
        $titleNode = $xpath->query('.//span[@class="cont"]', $item)->item(0);
        if (!$titleNode) {
            echo "  제목을 찾을 수 없음\n";
            return null;
        }
        
        $title = trim($titleNode->nodeValue);
        
        // 예외처리 1: 제목에 '공지' 포함된 글 제외
        if (strpos($title, '공지') !== false || strpos($title, '[공지]') !== false) {
            echo "  공지사항 제외: 제목에 '공지' 포함\n";
            return null;
        }
        
        // 2. URL 추출
        $linkNode = $xpath->query('./a', $item)->item(0);
        if (!$linkNode) {
            echo "  링크를 찾을 수 없음\n";
            return null;
        }
        
        $href = $linkNode->getAttribute('href');
        // 상대경로를 절대경로로 변환
        if (strpos($href, 'http') !== 0) {
            $url = $this->baseUrl . $href;
        } else {
            $url = $href;
        }
        
        // 3. 메타 정보 추출 (txt2 영역의 span.block 요소들)
        $metaNodes = $xpath->query('.//div[@class="txt2"]/span[@class="block"]', $item);
        
        $category = '';
        $author = '';
        $time = '';
        $viewsCount = 0;
        $likesCount = 0;
        $createdAt = date('Y-m-d H:i:s'); // 기본값은 현재 시각
        
        if ($metaNodes->length > 0) {
            // 첫 번째: 카테고리
            if ($metaNodes->item(0)) {
                $category = trim($metaNodes->item(0)->nodeValue);
            }
            
            // 두 번째: 작성자
            if ($metaNodes->item(1)) {
                $author = trim($metaNodes->item(1)->nodeValue);
                
                // 예외처리 2: 작성자가 '보배드림'인 글 제외
                if ($author === '보배드림' || $author === '운영자' || $author === '관리자') {
                    echo "  운영자 글 제외: 작성자가 '{$author}'\n";
                    return null;
                }
            }
            
            // 세 번째: 시간
            if ($metaNodes->item(2)) {
                $time = trim($metaNodes->item(2)->nodeValue);
                    if (!empty($time)) {
                // 시간 형식 변환
                $createdAt = $this->parsePostTime($time);
            }
            }
            
            // 네 번째: 조회수
            if ($metaNodes->item(3)) {
                $viewText = trim($metaNodes->item(3)->nodeValue);
                // "조회 218" 형태에서 숫자만 추출
                if (preg_match('/조회\s*(\d+)/', $viewText, $matches)) {
                    $viewsCount = intval($matches[1]);
                }
            }
            
            // 다섯 번째: 추천수 (있는 경우)
            if ($metaNodes->item(4)) {
                $likeText = trim($metaNodes->item(4)->nodeValue);
                // "추천 18" 형태에서 숫자만 추출
                if (preg_match('/추천\s*(\d+)/', $likeText, $matches)) {
                    $likesCount = intval($matches[1]);
                }
            }
        }
        
        // 4. 댓글 수 추출
        $commentsCount = 0;
        $commentNode = $xpath->query('.//div[@class="txt5"]/span[@class="num"]', $item)->item(0);
        if ($commentNode) {
            $commentsCount = intval(trim($commentNode->nodeValue));
        }
        
        // 5. 아이콘 정보 (선택사항)
        $hasImage = false;
        $imageNode = $xpath->query('.//span[@class="icon_img"]', $item)->item(0);
        if ($imageNode) {
            $hasImage = true;
        }
        
        return [
            'title' => $this->cleanTitle($title),
            'url' => $url,
            'author' => $author ?: '보배회원',
            'comments_count' => $commentsCount,
            'views_count' => $viewsCount,
            'likes_count' => $likesCount,
            'created_at' => $createdAt, 
            'time' => $time,
            'category' => $category,
            'site' => 'bobaedream',
            'rank' => $rank,
            'has_image' => $hasImage
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
    
    // HH:MM 형식 (당일 게시글: 03:34)
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeString, $matches)) {
        $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $minute = $matches[2];
        return date('Y-m-d') . ' ' . $hour . ':' . $minute . ':00';
    }
    
    // MM/DD 형식 (오늘 이전 날짜: 06/26)
    if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $timeString, $matches)) {
        $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        return date('Y') . '-' . $month . '-' . $day . ' 00:00:00';
    }
    
    // 파싱할 수 없는 경우 현재 시각 반환
    return date('Y-m-d H:i:s');
}
    
    
}

// 실행 코드
echo "<h2>보배드림 크롤러 테스트</h2>\n";
echo "<pre>\n";

$crawler = new BobaedreamCrawler();
$result = $crawler->crawlHotPosts(20);

echo "\n테스트 완료: {$result}개 게시글 저장됨\n";
echo "</pre>\n";
?>
