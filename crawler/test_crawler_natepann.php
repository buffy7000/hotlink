```php
<?php
require_once 'BaseCrawler.php';

class NatePannCrawler extends BaseCrawler {
    private $baseUrl = 'https://m.pann.nate.com';
    private $targetUrl = 'https://m.pann.nate.com/talk/talker';
    
    public function __construct() {
        parent::__construct(3, 'natepann');
    }
    
    public function crawlHotPosts($limit = 50) {
        return $this->crawlMultiplePages(5, 20);
    }
    
    public function crawlMultiplePages($pages = 5, $limitPerPage = 20) {
        $allPosts = [];
        
        for ($page = 1; $page <= $pages; $page++) {
            try {
                echo "\n네이트판 {$page}페이지 크롤링 중...\n";
                echo str_repeat("=", 60) . "\n";
                
                $url = $this->targetUrl . "?page={$page}";
                echo "URL: {$url}\n";
                
                $html = $this->makeRequest($url);
                
                if (strlen($html) < 1000) {
                    throw new Exception("HTML 응답이 너무 짧습니다.");
                }
                
                echo "HTML 길이: " . strlen($html) . " 바이트\n";
                echo str_repeat("-", 60) . "\n";
                
                $posts = $this->parseRankingPosts($html, $limitPerPage);
                $allPosts = array_merge($allPosts, $posts);
                
                echo "\n{$page}페이지에서 " . count($posts) . "개 게시글 수집 완료\n";
                
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
            echo "\n전체 네이트판 크롤링 완료: {$savedCount}개 게시글 저장\n";
            return $savedCount;
        }
        
        return 0;
    }
    
    private function parseRankingPosts($html, $limit) {
        $posts = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        $listItems = $xpath->query("//li[.//a[@class='cnbox']]");
        
        if (!$listItems || $listItems->length == 0) {
            echo "게시글 리스트를 찾을 수 없습니다.\n";
            return $posts;
        }
        
        echo "찾은 게시글: {$listItems->length}개\n";
        echo str_repeat("-", 60) . "\n";
        
        $count = 0;
        
        foreach ($listItems as $item) {
            if ($count >= $limit) break;
            
            try {
                $post = $this->extractPostFromItem($item, $xpath);
                
                if ($post && $this->isValidPost($post)) {
                    $posts[] = $post;
                    $count++;
                    
                    echo "게시글 #{$count}\n";
                    echo "  순위: {$post['rank']}\n";
                    echo "  제목: {$post['title']}\n";
                    echo "  댓글: {$post['comments_count']}개 | 조회: {$post['views_count']}회 | 추천: {$post['likes_count']}개\n";
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
    
    private function extractPostFromItem($item, $xpath) {
        $rank = 0;
        $rankNodes = $xpath->query(".//span[contains(@class, 'ranking')]", $item);
        if ($rankNodes->length > 0) {
            $rankText = trim($rankNodes->item(0)->textContent);
            $rank = intval($rankText);
            echo "  순위: {$rank}\n";
        }
        
        $title = '';
        $titleNodes = $xpath->query(".//span[@class='tit']", $item);
        if ($titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
            echo "  제목: '{$title}'\n";
        }
        
        $url = '';
        $linkNodes = $xpath->query(".//a[@class='cnbox']", $item);
        if ($linkNodes->length > 0) {
            $href = $linkNodes->item(0)->getAttribute('href');
            if (strpos($href, 'http') !== 0) {
                $url = $this->baseUrl . $href;
            } else {
                $url = $href;
            }
            // ?currMenu=...&page=N 등 페이지 의존 파라미터 제거 (중복 저장 방지)
            $url = preg_replace('/\?.*$/', '', $url);
            echo "  URL: {$url}\n";
        }
        
        if (empty($title) || empty($url)) {
            echo "  -> 제목 또는 URL을 찾을 수 없음\n";
            return null;
        }
        
        // 썸네일 이미지 추출
        $thumbnailUrl = null;
        $imgNodes = $xpath->query(".//span[@class='thumb']/img", $item);
        if ($imgNodes->length > 0) {
            $imgSrc = $imgNodes->item(0)->getAttribute('src');
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
        
        $commentsCount = 0;
        $commentNodes = $xpath->query(".//span[@class='count']", $item);
        if ($commentNodes->length > 0) {
            $commentsText = trim($commentNodes->item(0)->textContent);
            if (preg_match('/\((\d+)\)/', $commentsText, $matches)) {
                $commentsCount = intval($matches[1]);
            }
            echo "  -> 댓글수: {$commentsCount}개\n";
        }
        
        $viewsCount = 0;
        $likesCount = 0;
        $numNodes = $xpath->query(".//span[@class='sub']//span[@class='num']", $item);
        if ($numNodes->length >= 2) {
            $viewsText = trim($numNodes->item(0)->textContent);
            $viewsCount = intval(str_replace(',', '', $viewsText));
            
            $likesText = trim($numNodes->item(1)->textContent);
            $likesCount = intval(str_replace(',', '', $likesText));
            
            echo "  -> 조회수: {$viewsCount}회, 추천수: {$likesCount}개\n";
        }
        
        return [
            'title' => $this->cleanTitle($title),
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'author' => '네이트판 톡커',
            'comments_count' => $commentsCount,
            'views_count' => $viewsCount,
            'likes_count' => $likesCount,
            'created_at' => date('Y-m-d H:i:s'),
            'category' => 'ranking',
            'site' => 'natepann',
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
}

// 실행 코드
echo "<h2>네이트판 크롤러 테스트</h2>\n";
echo "<pre>\n";

$crawler = new NatePannCrawler();
$result = $crawler->crawlHotPosts(50);

echo "\n테스트 완료: {$result}개 게시글 저장됨\n";
echo "</pre>\n";
?>