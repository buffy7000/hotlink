<?php
require_once 'BaseCrawler.php';

class ClienCrawler extends BaseCrawler {
    private $baseUrl = 'https://www.clien.net';
    private $targetUrl = 'https://www.clien.net/service/recommend';

    // 새로 추가되는 URL들
    private $parkLikesUrl = 'https://www.clien.net/service/board/park?&od=T33&category=0';
    private $parkCommentsUrl = 'https://www.clien.net/service/board/park?&od=T34&category=0';
    
    public function __construct() {
        parent::__construct(2, 'clien');
    }
    
    public function crawlHotPosts($limit = 50) {
        return $this->crawlRecommendPosts($limit);
    }
    
    public function crawlRecommendPosts($limit = 50) {
        try {
            echo "클리앙 추천글 크롤링 시작...\n";
            echo "대상 URL: {$this->targetUrl}\n";
            echo str_repeat("-", 60) . "\n";
            
            $html = $this->makeRequest($this->targetUrl);
            
            if (strlen($html) < 1000) {
                throw new Exception("HTML 응답이 너무 짧습니다.");
            }
            
            echo "HTML 길이: " . strlen($html) . " 바이트\n";
            echo str_repeat("-", 60) . "\n";
            
            $posts = $this->parseRecommendPosts($html, $limit);
            
            if (!empty($posts)) {
                $savedCount = $this->savePosts($posts);
                echo "\n클리앙 추천글 크롤링 완료: {$savedCount}개 게시글 저장\n";
                return $savedCount;
            } else {
                echo "파싱된 게시글이 없습니다.\n";
                file_put_contents('debug_clien.html', $html);
                echo "전체 HTML이 debug_clien.html에 저장되었습니다.\n";
                return 0;
            }
            
        } catch (Exception $e) {
            echo "크롤링 실패: " . $e->getMessage() . "\n";
            return 0;
        }
    }
    
    private function parseRecommendPosts($html, $limit) {
        $posts = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 클리앙 추천글 리스트 아이템들 찾기
        $listItems = $xpath->query("//div[contains(@class, 'list_item')]");
        
        if (!$listItems || $listItems->length == 0) {
            echo "list_item을 찾을 수 없습니다.\n";
            return $posts;
        }
        
        echo "찾은 게시글: {$listItems->length}개\n";
        echo str_repeat("-", 60) . "\n";
        
        $count = 0;
        $processedUrls = [];
        
        foreach ($listItems as $item) {
            if ($count >= $limit) break;
            
            try {
                $post = $this->extractPostFromItem($item, $xpath);
                
                if ($post && $this->isValidPost($post)) {
                    if (!in_array($post['url'], $processedUrls)) {
                        $posts[] = $post;
                        $processedUrls[] = $post['url'];
                        $count++;
                        
                        echo "게시글 #{$count}\n";
                        echo "  제목: {$post['title']}\n";
                        echo "  작성자: {$post['author']} | 댓글: {$post['comments_count']}개 | 조회: {$post['views_count']}회 | 추천: {$post['likes_count']}개\n";
                        echo "  작성일: {$post['created_at']}\n";
                        echo "  URL: {$post['url']}\n";
                        echo str_repeat("-", 60) . "\n";
                    }
                }
            } catch (Exception $e) {
                echo "게시글 추출 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        echo "총 {$count}개 게시글 추출 완료\n";
        return $posts;
    }
    
    private function extractPostFromItem($item, $xpath) {
        echo "게시글 아이템 분석 중...\n";
        
        // 1. 추천수 추출
        $likesCount = 0;
        $likesNodes = $xpath->query(".//div[contains(@class, 'list_symph')]//span", $item);
        if ($likesNodes->length > 0) {
            $likesText = trim($likesNodes->item(0)->textContent);
            echo "  추천수 원본: '{$likesText}'\n";
            
            // "99+" 형태 처리
            if (strpos($likesText, '+') !== false) {
                $likesCount = intval(str_replace('+', '', $likesText));
            } else if (preg_match('/(\d+)/', $likesText, $matches)) {
                $likesCount = intval($matches[1]);
            }
            echo "  -> 추천수: {$likesCount}개\n";
        }

// 2. 제목과 URL 추출
$title = '';
$url = '';
$titleNodes = $xpath->query(".//a[contains(@class, 'list_subject')]", $item);
if ($titleNodes->length > 0) {
    $titleLink = $titleNodes->item(0);
    $href = $titleLink->getAttribute('href');
    
    // URL 정리
    if (strpos($href, 'http') !== 0) {
        if (strpos($href, '/') === 0) {
            $url = $this->baseUrl . $href;
        } else {
            $url = $this->baseUrl . '/' . $href;
        }
    } else {
        $url = $href;
    }
    
    // ✅ URL 파라미터 트림 (추천글용)
    $url = $this->trimUrlParameters($url);
    
    // 제목 추출 (subject_fixed 클래스에서)
    $subjectNodes = $xpath->query(".//span[contains(@class, 'subject_fixed')]", $titleLink);
    if ($subjectNodes->length > 0) {
        $title = trim($subjectNodes->item(0)->textContent);
        echo "  -> 제목: '{$title}'\n";
        echo "  -> URL: {$url}\n";
    }
}
        
        
        
        if (empty($title) || empty($url)) {
            echo "  -> 제목 또는 URL을 찾을 수 없음\n";
            return null;
        }
        
        // 3. 댓글수 추출
        $commentsCount = 0;
        $commentNodes = $xpath->query(".//a[contains(@class, 'list_reply')]//span[contains(@class, 'rSymph05')]", $item);
        if ($commentNodes->length > 0) {
            $commentsText = trim($commentNodes->item(0)->textContent);
            $commentsCount = intval($commentsText);
            echo "  -> 댓글수: {$commentsCount}개\n";
        }
        
        // 4. 작성자 추출
        $author = '익명';
        $authorNodes = $xpath->query(".//div[contains(@class, 'list_author')]//span[@class='nickname']//span", $item);
        if ($authorNodes->length > 0) {
            $author = trim($authorNodes->item(0)->textContent);
            echo "  -> 작성자: '{$author}'\n";
        }
        
        // 5. 조회수 추출 (개선된 버전)
        $viewsCount = 0;
        $hitNodes = $xpath->query(".//div[contains(@class, 'list_hit')]//span[@class='hit']", $item);
        if ($hitNodes->length > 0) {
            $hitText = trim($hitNodes->item(0)->textContent);
            echo "  조회수 원본: '{$hitText}'\n";
            
            // "26.9 k" 형태 처리 (소수점 포함)
            if (preg_match('/(\d+(?:\.\d+)?)\s*k/i', $hitText, $matches)) {
                $viewsCount = intval(floatval($matches[1]) * 1000); // 26.9k = 26900
                echo "  -> 조회수 계산: {$matches[1]} * 1000 = {$viewsCount}회\n";
            } 
            // 순수 숫자 형태 처리
            else if (preg_match('/^\d+$/', $hitText)) {
                $viewsCount = intval($hitText);
                echo "  -> 조회수: {$viewsCount}회\n";
            }
        }
        
        // 6. 작성시간 추출 (개선된 버전)
        $createdAt = date('Y-m-d H:i:s');
        $timeNodes = $xpath->query(".//div[contains(@class, 'list_time')]//span[@class='time popover'] | .//div[contains(@class, 'list_time')]//span[@class='time']", $item);
        if ($timeNodes->length > 0) {
            $timeElement = $timeNodes->item(0);
            
            // 먼저 timestamp 스타일이 있는지 확인
            $timestampNodes = $xpath->query(".//span[@class='timestamp']", $timeElement);
            if ($timestampNodes->length > 0) {
                $timestamp = trim($timestampNodes->item(0)->textContent);
                if (!empty($timestamp)) {
                    $createdAt = $timestamp;
                    echo "  -> 작성일 (timestamp): {$createdAt}\n";
                }
            } else {
                // timestamp가 없으면 표시된 시간으로 파싱
                $timeText = trim($timeElement->textContent);
                // timestamp 텍스트가 포함되어 있을 수 있으므로 첫 번째 시간만 추출
                if (preg_match('/(\d{1,2}:\d{2})/', $timeText, $matches)) {
                    $timeText = $matches[1];
                }
                echo "  시간 원본: '{$timeText}'\n";
                $createdAt = $this->parseDate($timeText);
                echo "  -> 작성일 (파싱): {$createdAt}\n";
            }
        }
        
        return [
            'title' => $this->cleanTitle($title),
            'url' => $url,
            'author' => $author,
            'comments_count' => $commentsCount,
            'views_count' => $viewsCount,
            'likes_count' => $likesCount,
            'created_at' => $createdAt,
            'category' => 'recommend',
            'site' => 'clien'
        ];
    }
    
    private function isValidPost($post) {
        if (empty($post['title']) || empty($post['url'])) {
            echo "  유효성 검사 실패: 제목 또는 URL이 비어있음\n";
            return false;
        }
        
        if (strlen($post['title']) < 3) {
            echo "  유효성 검사 실패: 제목이 너무 짧음 ({$post['title']})\n";
            return false;
        }
        
        // 광고성 키워드 필터링
        $adKeywords = ['광고', '홍보', '스폰서'];
        foreach ($adKeywords as $keyword) {
            if (strpos($post['title'], $keyword) !== false) {
                echo "  유효성 검사 실패: 광고 키워드 포함 '{$keyword}' ({$post['title']})\n";
                return false;
            }
        }
        
        // URL 유효성 검사
        if (strpos($post['url'], 'clien.net') === false) {
            echo "  유효성 검사 실패: 클리앙 URL이 아님 ({$post['url']})\n";
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
    
    private function parseDate($dateString) {
        $dateString = trim($dateString);
        
        // "21:44" 형태 (오늘)
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $dateString, $matches)) {
            return date('Y-m-d') . ' ' . sprintf('%02d', $matches[1]) . ':' . $matches[2] . ':00';
        }
        
        // "01-15" 형태 (올해)
        if (preg_match('/^(\d{2})-(\d{2})$/', $dateString, $matches)) {
            return date('Y') . '-' . $matches[1] . '-' . $matches[2] . ' 00:00:00';
        }
        
        // "2025-01-15 22:08:58" 형태 (완전한 timestamp)
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateString)) {
            return $dateString;
        }
        
        return date('Y-m-d H:i:s');
    }
    
    // 여러 페이지 크롤링
    public function crawlMultiplePages($pages = 3, $limitPerPage = 20) {
        $allPosts = [];
        
        for ($page = 1; $page <= $pages; $page++) {
            try {
                echo "\n클리앙 추천글 {$page}페이지 크롤링 중...\n";
                echo str_repeat("=", 60) . "\n";
                
                $url = $this->targetUrl . "?po={$page}";
                $html = $this->makeRequest($url);
                
                $posts = $this->parseRecommendPosts($html, $limitPerPage);
                $allPosts = array_merge($allPosts, $posts);
                
                echo "\n{$page}페이지에서 " . count($posts) . "개 게시글 수집 완료\n";
                
                // 페이지 간 딜레이
                if ($page < $pages) {
                    echo "다음 페이지까지 2초 대기...\n";
                    sleep(2);
                }
                
            } catch (Exception $e) {
                echo "페이지 {$page} 크롤링 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        if (!empty($allPosts)) {
            $savedCount = $this->savePosts($allPosts);
            echo "\n전체 클리앙 추천글 크롤링 완료: {$savedCount}개 게시글 저장\n";
            return $savedCount;
        }
        
        return 0;
    }

  // 모두의공원 - 공감순 크롤링 (공감 20개 이상, 2페이지)
    public function crawlParkByLikes($minLikes = 20, $pages = 2) {
        try {
            echo "모두의공원 공감순 크롤링 시작 (공감 {$minLikes}개 이상, {$pages}페이지)...\n";
            echo str_repeat("=", 70) . "\n";
            
            $allPosts = [];
            
            for ($page = 1; $page <= $pages; $page++) {
                echo "\n{$page}페이지 크롤링 중...\n";
                
                $url = $this->parkLikesUrl . "&po={$page}";
                echo "대상 URL: {$url}\n";
                echo str_repeat("-", 60) . "\n";
                
                $html = $this->makeRequest($url);
                
                if (strlen($html) < 1000) {
                    throw new Exception("HTML 응답이 너무 짧습니다.");
                }
                
                $posts = $this->parseParkPosts($html, 50);
                
                // 공감수 필터링
                $filteredPosts = array_filter($posts, function($post) use ($minLikes) {
                    return $post['likes_count'] >= $minLikes;
                });
                
                $allPosts = array_merge($allPosts, $filteredPosts);
                echo "{$page}페이지에서 " . count($filteredPosts) . "개 게시글 수집 (공감 {$minLikes}개 이상)\n";
                
                if ($page < $pages) {
                    echo "다음 페이지까지 2초 대기...\n";
                    sleep(2);
                }
            }
            
            if (!empty($allPosts)) {
                $savedCount = $this->savePosts($allPosts);
                echo "\n모두의공원 공감순 크롤링 완료: {$savedCount}개 게시글 저장\n";
                return $savedCount;
            }
            
            return 0;
        } catch (Exception $e) {
            echo "모두의공원 공감순 크롤링 실패: " . $e->getMessage() . "\n";
            return 0;
        }
    }
    
    // 모두의공원 - 댓글순 크롤링 (댓글 20개 이상, 3페이지)
    public function crawlParkByComments($minComments = 20, $pages = 3) {
        try {
            echo "모두의공원 댓글순 크롤링 시작 (댓글 {$minComments}개 이상, {$pages}페이지)...\n";
            echo str_repeat("=", 70) . "\n";
            
            $allPosts = [];
            
            for ($page = 1; $page <= $pages; $page++) {
                echo "\n{$page}페이지 크롤링 중...\n";
                
                $url = $this->parkCommentsUrl . "&po={$page}";
                echo "대상 URL: {$url}\n";
                echo str_repeat("-", 60) . "\n";
                
                $html = $this->makeRequest($url);
                
                if (strlen($html) < 1000) {
                    throw new Exception("HTML 응답이 너무 짧습니다.");
                }
                
                $posts = $this->parseParkPosts($html, 50);
                
                // 댓글수 필터링
                $filteredPosts = array_filter($posts, function($post) use ($minComments) {
                    return $post['comments_count'] >= $minComments;
                });
                
                $allPosts = array_merge($allPosts, $filteredPosts);
                echo "{$page}페이지에서 " . count($filteredPosts) . "개 게시글 수집 (댓글 {$minComments}개 이상)\n";
                
                if ($page < $pages) {
                    echo "다음 페이지까지 2초 대기...\n";
                    sleep(2);
                }
            }
            
            if (!empty($allPosts)) {
                $savedCount = $this->savePosts($allPosts);
                echo "\n모두의공원 댓글순 크롤링 완료: {$savedCount}개 게시글 저장\n";
                return $savedCount;
            }
            
            return 0;
        } catch (Exception $e) {
            echo "모두의공원 댓글순 크롤링 실패: " . $e->getMessage() . "\n";
            return 0;
        }
    }
    
    // 모두의공원 게시글 파싱
    private function parseParkPosts($html, $limit) {
        $posts = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 모두의공원 게시글 리스트 아이템들 찾기
        $listItems = $xpath->query("//div[contains(@class, 'list_item')]");
        
        if (!$listItems || $listItems->length == 0) {
            echo "list_item을 찾을 수 없습니다.\n";
            return $posts;
        }
        
        echo "찾은 게시글: {$listItems->length}개\n";
        echo str_repeat("-", 60) . "\n";
        
        $count = 0;
        $processedUrls = [];
        
        foreach ($listItems as $item) {
            if ($count >= $limit) break;
            
            try {
                $post = $this->extractParkPostFromItem($item, $xpath);
                
                if ($post && $this->isValidPost($post)) {
                    if (!in_array($post['url'], $processedUrls)) {
                        $posts[] = $post;
                        $processedUrls[] = $post['url'];
                        $count++;
                        
                        echo "게시글 #{$count}\n";
                        echo "  제목: {$post['title']}\n";
                        echo "  작성자: {$post['author']} | 댓글: {$post['comments_count']}개 | 조회: {$post['views_count']}회 | 공감: {$post['likes_count']}개\n";
                        echo "  작성일: {$post['created_at']}\n";
                        echo "  URL: {$post['url']}\n";
                        echo str_repeat("-", 60) . "\n";
                    }
                }
            } catch (Exception $e) {
                echo "게시글 추출 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        echo "총 {$count}개 게시글 추출 완료\n";
        return $posts;
    }
    
    // 모두의공원 게시글 정보 추출
    private function extractParkPostFromItem($item, $xpath) {
        echo "모두의공원 게시글 아이템 분석 중...\n";
        
        // 1. 공감수 추출
        $likesCount = 0;
        $likesNodes = $xpath->query(".//div[contains(@class, 'list_symph')]//span", $item);
        if ($likesNodes->length > 0) {
            $likesText = trim($likesNodes->item(0)->textContent);
            echo "  공감수 원본: '{$likesText}'\n";
            
            if (strpos($likesText, '+') !== false) {
                $likesCount = intval(str_replace('+', '', $likesText));
            } else if (preg_match('/(\d+)/', $likesText, $matches)) {
                $likesCount = intval($matches[1]);
            }
            echo "  -> 공감수: {$likesCount}개\n";
        }
        
   
// 2. 제목과 URL 추출
$title = '';
$url = '';
$titleNodes = $xpath->query(".//a[contains(@class, 'list_subject')]", $item);
if ($titleNodes->length > 0) {
    $titleLink = $titleNodes->item(0);
    $href = $titleLink->getAttribute('href');
    
    if (strpos($href, 'http') !== 0) {
        if (strpos($href, '/') === 0) {
            $url = $this->baseUrl . $href;
        } else {
            $url = $this->baseUrl . '/' . $href;
        }
    } else {
        $url = $href;
    }
    
    // ✅ URL 파라미터 트림 (모두의공원용)
    $url = $this->trimUrlParameters($url);
    
    $subjectNodes = $xpath->query(".//span[contains(@class, 'subject_fixed')]", $titleLink);
    if ($subjectNodes->length > 0) {
        $title = trim($subjectNodes->item(0)->textContent);
        echo "  -> 제목: '{$title}'\n";
        echo "  -> URL: {$url}\n";
    }
}

        
        if (empty($title) || empty($url)) {
            echo "  -> 제목 또는 URL을 찾을 수 없음\n";
            return null;
        }
        
        // 3. 댓글수 추출
        $commentsCount = 0;
        $commentNodes = $xpath->query(".//a[contains(@class, 'list_reply')]//span[contains(@class, 'rSymph05')]", $item);
        if ($commentNodes->length > 0) {
            $commentsText = trim($commentNodes->item(0)->textContent);
            $commentsCount = intval($commentsText);
            echo "  -> 댓글수: {$commentsCount}개\n";
        }
        
        // 4. 작성자 추출 (title 속성 사용)
        $author = '익명';
        $authorNodes = $xpath->query(".//div[contains(@class, 'list_author')]//span[@title]", $item);
        if ($authorNodes->length > 0) {
            $author = $authorNodes->item(0)->getAttribute('title');
            echo "  -> 작성자: '{$author}'\n";
        }
        
        // 5. 조회수 추출
        $viewsCount = 0;
        $hitNodes = $xpath->query(".//div[contains(@class, 'list_hit')]//span[@class='hit']", $item);
        if ($hitNodes->length > 0) {
            $hitText = trim($hitNodes->item(0)->textContent);
            echo "  조회수 원본: '{$hitText}'\n";
            
            if (preg_match('/(\d+(?:\.\d+)?)\s*k/i', $hitText, $matches)) {
                $viewsCount = intval(floatval($matches[1]) * 1000);
                echo "  -> 조회수 계산: {$matches[1]} * 1000 = {$viewsCount}회\n";
            } else if (preg_match('/^\d+$/', $hitText)) {
                $viewsCount = intval($hitText);
                echo "  -> 조회수: {$viewsCount}회\n";
            }
        }
        
        // 6. 작성시간 추출 (timestamp 우선)
        $createdAt = date('Y-m-d H:i:s');
        $timestampNodes = $xpath->query(".//span[@class='timestamp']", $item);
        if ($timestampNodes->length > 0) {
            $timestamp = trim($timestampNodes->item(0)->textContent);
            if (!empty($timestamp)) {
                $createdAt = $timestamp;
                echo "  -> 작성일 (timestamp): {$createdAt}\n";
            }
        } else {
            // timestamp가 없으면 표시된 시간으로 파싱
            $timeNodes = $xpath->query(".//div[contains(@class, 'list_time')]//span[@class='time popover'] | .//div[contains(@class, 'list_time')]//span[@class='time']", $item);
            if ($timeNodes->length > 0) {
                $timeText = trim($timeNodes->item(0)->textContent);
                $createdAt = $this->parseDate($timeText);
                echo "  -> 작성일 (파싱): {$createdAt}\n";
            }
        }
        
        return [
            'title' => $this->cleanTitle($title),
            'url' => $url,
            'author' => $author,
            'comments_count' => $commentsCount,
            'views_count' => $viewsCount,
            'likes_count' => $likesCount,
            'created_at' => $createdAt,
            'category' => 'park',  // 카테고리 변경
            'site' => 'clien'
        ];
    }
// ✅ URL 파라미터 트림 메서드
private function trimUrlParameters($url) {
    // URL에서 ? 이후의 모든 파라미터 제거
    $parsedUrl = parse_url($url);
    
    if (isset($parsedUrl['scheme']) && isset($parsedUrl['host']) && isset($parsedUrl['path'])) {
        $cleanUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];
        echo "  URL 트림: {$url} -> {$cleanUrl}\n";
        return $cleanUrl;
    }
    
    return $url;
}



}
?>
