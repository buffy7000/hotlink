<?php
require_once 'BaseCrawler.php';

class EtolandCrawler extends BaseCrawler {
    private $baseUrl = 'https://etoland.co.kr';
    private $targetUrl = 'https://etoland.co.kr/hit/list';

    public function __construct() {
        parent::__construct(12, 'etoland');
    }

    public function crawlHotPosts($limit = 50) {
        return $this->crawlMultiplePages(3, 20);
    }

    public function crawlMultiplePages($pages = 3, $limitPerPage = 20) {
        $totalSaved = 0;

        for ($page = 1; $page <= $pages; $page++) {
            try {
                echo "\n이토랜드 {$page}페이지 크롤링 중...\n";
                echo str_repeat("=", 60) . "\n";

                $url = $page === 1 ? $this->targetUrl : $this->targetUrl . '?page=' . $page;
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
                break;
            }
        }

        echo "\n전체 이토랜드 크롤링 완료: 총 {$totalSaved}개 게시글 저장\n";
        return $totalSaved;
    }

    private function parsePosts($html, $limit) {
        $posts = [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $items = $xpath->query("//a[contains(@class,'mobile-list-item')]");

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
        // 1. URL (href에 고유 ID 포함)
        $href = $item->getAttribute('href');
        $url = (strpos($href, 'http') === 0) ? $href : $this->baseUrl . $href;
        $url = preg_replace('/\?.*$/', '', $url);

        if (empty($url) || $url === $this->baseUrl) return null;

        // 2. 제목 (span.body-l.truncate)
        $titleNode = $xpath->query(".//span[contains(@class,'body-l') and contains(@class,'truncate')]", $item)->item(0);
        if (!$titleNode) return null;
        $title = trim($titleNode->textContent);

        // 3. 댓글수 (span.comment-xs) → "(6)"
        $commentsCount = 0;
        $cmtNode = $xpath->query(".//span[contains(@class,'comment-xs')]", $item)->item(0);
        if ($cmtNode && preg_match('/(\d+)/', $cmtNode->textContent, $m)) {
            $commentsCount = intval($m[1]);
        }

        // 4. 시간 (<time> 요소 텍스트 → 상대시간 변환)
        $createdAt = date('Y-m-d H:i:s');
        $timeNode = $xpath->query(".//time", $item)->item(0);
        if ($timeNode) {
            $createdAt = $this->parseRelativeTime(trim($timeNode->textContent));
        }

        // 5. caption-m div에서 작성자, 조회수, 추천수 파싱
        $author = '이토랜드';
        $viewsCount = 0;
        $likesCount = 0;

        $captionNode = $xpath->query(".//div[contains(@class,'caption-m')]", $item)->item(0);
        if ($captionNode) {
            // span 목록: [시간span, 작성자span, 조회span, 추천span, 카테고리span]
            $spans = $xpath->query("./span", $captionNode);
            foreach ($spans as $span) {
                $text = trim($span->textContent);
                if (empty($text)) continue;

                if (preg_match('/조회\s*([\d,.]+K?)/u', $text, $m)) {
                    $viewsCount = $this->parseViewCount($m[1]);
                } elseif (preg_match('/추천\s*(\d+)/u', $text, $m)) {
                    $likesCount = intval($m[1]);
                } elseif (
                    strpos($text, '조회') === false &&
                    strpos($text, '추천') === false &&
                    strpos($text, '[') === false &&
                    $span->getElementsByTagName('time')->length === 0
                ) {
                    // 나머지 조건에 해당하지 않는 span = 작성자
                    $author = $text;
                }
            }
        }

        return [
            'title'          => $this->cleanTitle($title),
            'url'            => $url,
            'thumbnail_url'  => null,
            'author'         => $author,
            'comments_count' => $commentsCount,
            'views_count'    => $viewsCount,
            'likes_count'    => $likesCount,
            'created_at'     => $createdAt,
            'category'       => 'hot',
            'site'           => 'etoland',
            'rank'           => $rank
        ];
    }

    // "2분전", "1시간전", "3일전" → Y-m-d H:i:s
    private function parseRelativeTime($text) {
        $now = time();

        if (preg_match('/(\d+)분전/', $text, $m)) {
            return date('Y-m-d H:i:s', $now - intval($m[1]) * 60);
        }
        if (preg_match('/(\d+)시간전/', $text, $m)) {
            return date('Y-m-d H:i:s', $now - intval($m[1]) * 3600);
        }
        if (preg_match('/(\d+)일전/', $text, $m)) {
            return date('Y-m-d H:i:s', $now - intval($m[1]) * 86400);
        }
        // 날짜 형식: "2026.06.16" or "06.16"
        if (preg_match('/(\d{4})\.(\d{2})\.(\d{2})/', $text, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3] . ' 00:00:00';
        }
        if (preg_match('/(\d{2})\.(\d{2})/', $text, $m)) {
            return date('Y') . '-' . $m[1] . '-' . $m[2] . ' 00:00:00';
        }

        return date('Y-m-d H:i:s');
    }

    // "1.1K" → 1100, "2,345" → 2345
    private function parseViewCount($text) {
        $text = trim($text);
        if (substr($text, -1) === 'K') {
            return intval(floatval(substr($text, 0, -1)) * 1000);
        }
        return intval(str_replace(',', '', $text));
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

echo "<h2>이토랜드 크롤러 테스트</h2>\n";
echo "<pre>\n";

$crawler = new EtolandCrawler();
$result = $crawler->crawlHotPosts(50);

echo "\n테스트 완료: {$result}개 게시글 저장됨\n";
echo "</pre>\n";
?>
