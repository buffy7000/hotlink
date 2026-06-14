<?php
require_once 'BaseCrawler.php';

class SlrclubCrawler extends BaseCrawler {
    private $baseUrl = 'https://m.slrclub.com';
    private $targetUrl = 'https://m.slrclub.com/l/hot_article';

    public function __construct() {
        parent::__construct(11, 'slrclub');
    }

    public function crawlHotPosts($limit = 50) {
        return $this->crawlMultiplePages(3, 20);
    }

    public function crawlMultiplePages($pages = 3, $limitPerPage = 20) {
        $totalSaved = 0;

        for ($page = 1; $page <= $pages; $page++) {
            try {
                echo "\nSLR클럽 {$page}페이지 크롤링 중...\n";
                echo str_repeat("=", 60) . "\n";

                $url = $page === 1 ? $this->targetUrl : $this->targetUrl . '?p=' . $page;
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

        echo "\n전체 SLR클럽 크롤링 완료: 총 {$totalSaved}개 게시글 저장\n";
        return $totalSaved;
    }

    private function parsePosts($html, $limit) {
        $posts = [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // div.article + div.cmt2 를 포함한 li 선택
        $items = $xpath->query("//li[.//div[@class='article'] and .//div[@class='cmt2']]");

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
                    echo "  조회: {$post['views_count']} | 댓글: {$post['comments_count']}\n";
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
        // 1. 제목 + URL (div.subject > a)
        $linkNode = $xpath->query(".//div[@class='subject']//a", $item)->item(0);
        if (!$linkNode) return null;

        $title = trim($linkNode->textContent);

        $href = $linkNode->getAttribute('href');
        $url = (strpos($href, 'http') === 0) ? $href : $this->baseUrl . $href;

        // 2. 작성자 (span.lop)
        $author = 'SLR클럽';
        $authorNode = $xpath->query(".//span[@class='lop']", $item)->item(0);
        if ($authorNode) {
            $author = trim($authorNode->textContent);
            // ★ 등 특수문자 접두어 제거
            $author = ltrim($author, '★☆◆◇♠♣♥♦');
            $author = trim($author);
        }

        // 3. 시간/조회수 (div.article-info 텍스트 파싱)
        $viewsCount = 0;
        $createdAt = date('Y-m-d H:i:s');

        $infoNode = $xpath->query(".//div[@class='article-info']", $item)->item(0);
        if ($infoNode) {
            $infoText = trim($infoNode->textContent);
            $infoText = preg_replace('/\s+/', ' ', $infoText);

            // 시간: "| 18:42 |" 또는 "| 25.06.14 |"
            if (preg_match('/\|\s*(\d{2}\.\d{2}\.\d{2})\s*\|/', $infoText, $m)) {
                $createdAt = '20' . str_replace('.', '-', $m[1]) . ' 00:00:00';
            } elseif (preg_match('/\|\s*(\d{1,2}:\d{2})\s*(?:\||$)/', $infoText, $m)) {
                $createdAt = date('Y-m-d') . ' ' . str_pad($m[1], 5, '0', STR_PAD_LEFT) . ':00';
            }

            // 조회수: "조회 1,048"
            if (preg_match('/조회\s*([\d,]+)/', $infoText, $m)) {
                $viewsCount = intval(str_replace(',', '', $m[1]));
            }
        }

        // 4. 댓글수 (div.cmt2)
        $commentsCount = 0;
        $cmtNode = $xpath->query(".//div[@class='cmt2']", $item)->item(0);
        if ($cmtNode) {
            $cmtText = trim($cmtNode->textContent);
            if (preg_match('/(\d+)/', $cmtText, $m)) {
                $commentsCount = intval($m[1]);
            }
        }

        return [
            'title'          => $this->cleanTitle($title),
            'url'            => $url,
            'thumbnail_url'  => null,
            'author'         => $author ?: 'SLR클럽',
            'comments_count' => $commentsCount,
            'views_count'    => $viewsCount,
            'likes_count'    => 0,
            'created_at'     => $createdAt,
            'category'       => 'hot',
            'site'           => 'slrclub',
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
echo "<h2>SLR클럽 크롤러 테스트</h2>\n";
echo "<pre>\n";

$crawler = new SlrclubCrawler();
$result = $crawler->crawlHotPosts(50);

echo "\n테스트 완료: {$result}개 게시글 저장됨\n";
echo "</pre>\n";
?>
