<?php
require_once 'BaseCrawler.php';

class InvenCrawler extends BaseCrawler {
    private $baseUrl = 'https://m.inven.co.kr';
    private $targetUrl = 'https://m.inven.co.kr/board/webzine/2097';

    public function __construct() {
        parent::__construct(10, 'inven');
    }

    public function crawlHotPosts($limit = 50) {
        return $this->crawlMultiplePages(3, 20);
    }

    public function crawlMultiplePages($pages = 3, $limitPerPage = 20) {
        $totalSaved = 0;

        for ($page = 1; $page <= $pages; $page++) {
            try {
                echo "\n인벤 {$page}페이지 크롤링 중...\n";
                echo str_repeat("=", 60) . "\n";

                $url = $page === 1 ? $this->targetUrl : $this->targetUrl . "?p={$page}";
                echo "URL: {$url}\n";

                $html = $this->makeRequest($url);

                if (strlen($html) < 1000) {
                    throw new Exception("HTML 응답이 너무 짧습니다.");
                }

                echo "HTML 길이: " . strlen($html) . " 바이트\n";

                $posts = $this->parsePosts($html, $limitPerPage);

                if (!empty($posts)) {
                    $savedCount = $this->savePosts($posts);
                    $totalSaved += $savedCount;
                    echo "\n{$page}페이지: {$savedCount}개 게시글 저장\n";
                }

                if ($page < $pages) {
                    echo "다음 페이지까지 2초 대기...\n";
                    sleep(2);
                }

            } catch (Exception $e) {
                echo "페이지 {$page} 크롤링 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }

        echo "\n전체 인벤 크롤링 완료: 총 {$totalSaved}개 게시글 저장\n";
        return $totalSaved;
    }

    private function parsePosts($html, $limit) {
        $posts = [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // li.list.thumb 선택
        $items = $xpath->query("//li[contains(@class,'list') and contains(@class,'thumb')]");

        if (!$items || $items->length == 0) {
            echo "게시글 리스트를 찾을 수 없습니다.\n";
            return $posts;
        }

        echo "찾은 게시글: {$items->length}개\n";
        echo str_repeat("-", 60) . "\n";

        $count = 0;
        foreach ($items as $item) {
            if ($count >= $limit) break;

            try {
                $post = $this->extractPost($item, $xpath, $count + 1);

                if ($post && $this->isValidPost($post)) {
                    $posts[] = $post;
                    $count++;

                    echo "게시글 #{$count}\n";
                    echo "  제목: {$post['title']}\n";
                    echo "  작성자: {$post['author']}\n";
                    echo "  조회: {$post['views_count']} | 추천: {$post['likes_count']} | 댓글: {$post['comments_count']}\n";
                    echo "  시간: {$post['created_at']}\n";
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

    private function extractPost($item, $xpath, $rank) {
        // 1. URL (a.contentLink)
        $linkNode = $xpath->query(".//a[contains(@class,'contentLink')]", $item)->item(0);
        if (!$linkNode) return null;

        $url = $linkNode->getAttribute('href');
        if (strpos($url, 'http') !== 0) {
            $url = $this->baseUrl . $url;
        }
        $url = preg_replace('/\?.*$/', '', $url);

        // 2. 썸네일 (a.img > img)
        $thumbnailUrl = null;
        $imgNode = $xpath->query(".//a[contains(@class,'img')]//img", $item)->item(0);
        if ($imgNode) {
            $thumbnailUrl = $imgNode->getAttribute('src');
        }

        // 3. 제목 (span.subject)
        $titleNode = $xpath->query(".//span[@class='subject']", $item)->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : '';

        // 4. 작성자 (span.nick의 onclick에서 닉네임 추출)
        $author = '인벤';
        $nickNode = $xpath->query(".//span[@class='nick']", $item)->item(0);
        if ($nickNode) {
            $onclick = $nickNode->getAttribute('onclick');
            if (preg_match("/layerNickName\('([^']+)'/", $onclick, $m)) {
                $author = $m[1];
            }
        }

        // 5. 조회수 (span.view → "조회 506")
        $viewsCount = 0;
        $viewNode = $xpath->query(".//span[@class='view']", $item)->item(0);
        if ($viewNode && preg_match('/(\d+)/', $viewNode->textContent, $m)) {
            $viewsCount = intval($m[1]);
        }

        // 6. 추천수 (span.reco → "추천 1")
        $likesCount = 0;
        $recoNode = $xpath->query(".//span[@class='reco']", $item)->item(0);
        if ($recoNode && preg_match('/(\d+)/', $recoNode->textContent, $m)) {
            $likesCount = intval($m[1]);
        }

        // 7. 시간 (span.time → "07:06" 또는 "25.06.14")
        $createdAt = date('Y-m-d H:i:s');
        $timeNode = $xpath->query(".//span[@class='time']", $item)->item(0);
        if ($timeNode) {
            $timeText = trim($timeNode->textContent);
            if (preg_match('/^(\d{2})\.(\d{2})\.(\d{2})$/', $timeText, $m)) {
                $createdAt = '20' . $m[1] . '-' . $m[2] . '-' . $m[3] . ' 00:00:00';
            } elseif (preg_match('/^(\d{1,2}):(\d{2})$/', $timeText, $m)) {
                $createdAt = date('Y-m-d') . ' ' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2] . ':00';
            }
        }

        // 8. 댓글수 (a.com-btn > span.num)
        $commentsCount = 0;
        $commentNode = $xpath->query(".//a[contains(@class,'com-btn')]//span[@class='num']", $item)->item(0);
        if ($commentNode) {
            $commentsCount = intval(trim($commentNode->textContent));
        }

        return [
            'title'          => $this->cleanTitle($title),
            'url'            => $url,
            'thumbnail_url'  => $thumbnailUrl,
            'author'         => $author,
            'comments_count' => $commentsCount,
            'views_count'    => $viewsCount,
            'likes_count'    => $likesCount,
            'created_at'     => $createdAt,
            'category'       => 'hot',
            'site'           => 'inven',
            'rank'           => $rank
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
        return trim($title);
    }
}

// 실행 코드
echo "<h2>인벤 크롤러 테스트</h2>\n";
echo "<pre>\n";

$crawler = new InvenCrawler();
$result = $crawler->crawlHotPosts(50);

echo "\n테스트 완료: {$result}개 게시글 저장됨\n";
echo "</pre>\n";
?>
