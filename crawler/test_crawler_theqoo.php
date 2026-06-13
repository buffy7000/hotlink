<?php
require_once 'BaseCrawler.php';

class TheqooCrawler extends BaseCrawler {
    private $baseUrl = 'https://theqoo.net';
    private $targetUrl = 'https://theqoo.net/hot';
    
    public function __construct() {
        parent::__construct(5, 'theqoo');  // community_id 5번 (더쿠)
    }
    
    public function crawlHotPosts($limit = 50) {
        return $this->crawlBestPosts($limit);
    }
    
    public function crawlBestPosts($limit = 50) {
        $allPosts = [];
        $totalSaved = 0;
        
        // 3페이지 크롤링
        for ($page = 1; $page <= 3; $page++) {
            try {
                echo "\n더쿠 HOT {$page}페이지 크롤링 시작...\n";
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
                
                $posts = $this->parseHotPosts($html, $limit);
                
                if (!empty($posts)) {
                    $savedCount = $this->savePosts($posts);
                    $totalSaved += $savedCount;
                    echo "\n{$page}페이지: {$savedCount}개 게시글 저장\n";
                }
                
                // 페이지 간 대기
                if ($page < 3) {
                    echo "다음 페이지까지 2초 대기...\n";
                    sleep(2);
                }
                
            } catch (Exception $e) {
                echo "페이지 {$page} 크롤링 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        echo "\n전체 더쿠 크롤링 완료: 총 {$totalSaved}개 게시글 저장\n";
        return $totalSaved;
    }
    
    private function parseHotPosts($html, $limit) {
        $posts = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 게시글 목록 찾기
        $rows = $xpath->query('//tr[td[@class="no"]]');
        
        if (!$rows || $rows->length == 0) {
            echo "게시글 리스트를 찾을 수 없습니다.\n";
            return $posts;
        }
        
        echo "찾은 tr 태그: {$rows->length}개\n";
        echo str_repeat("-", 60) . "\n";
        
        $count = 0;
        $skipped = 0;  // 제외된 게시글 카운터
        
        foreach ($rows as $index => $row) {
            if ($count >= $limit) break;
            
            try {
                $post = $this->extractPostFromRow($row, $xpath, $index + 1);
                
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
                    echo "  카테고리: {$post['category']}\n";
                    echo "  댓글: {$post['comments_count']}개 | 조회: {$post['views_count']}회\n";
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
    
    private function extractPostFromRow($row, $xpath, $rank) {
        // 1. 번호 추출
        $noNode = $xpath->query('.//td[@class="no"]', $row)->item(0);
        $postNo = $noNode ? trim($noNode->nodeValue) : '';
        
        // 예외처리 1: 공지사항 제외
        if ($postNo === '공지' || strtolower($postNo) === 'notice') {
            echo "  공지사항 제외: 번호가 '공지'\n";
            return null;
        }
        
        // 2. 카테고리 추출
        $cateNode = $xpath->query('.//td[@class="cate"]/span', $row)->item(0);
        $category = $cateNode ? trim($cateNode->nodeValue) : '';
        
        // 예외처리 2: 카테고리가 없는 글 제외
        if (empty($category)) {
            echo "  카테고리 없음: 수집 제외\n";
            return null;
        }
        
        // 3. 제목과 URL 추출
        $titleLinkNode = $xpath->query('.//td[@class="title"]/a[1]', $row)->item(0);
        if (!$titleLinkNode) {
            echo "  제목 링크를 찾을 수 없음\n";
            return null;
        }
        
        $title = trim($titleLinkNode->nodeValue);
        $href = $titleLinkNode->getAttribute('href');
        
        // ⭐️ 정규식으로 확실하게 파라미터 제거
        $cleanHref = preg_replace('/\?.*$/', '', $href);
        
        // URL 처리 (상대경로를 절대경로로)
        if (strpos($cleanHref, 'http') !== 0) {
            $url = $this->baseUrl . $cleanHref;
        } else {
            $url = $cleanHref;
        }



        
        // 4. 댓글 수 추출
        $commentsCount = 0;
        $replyNode = $xpath->query('.//td[@class="title"]/a[@class="replyNum"]', $row)->item(0);
        if ($replyNode) {
            $commentsCount = intval(trim($replyNode->nodeValue));
        }
        

// 5. 시간 추출 및 변환
$timeNode = $xpath->query('.//td[@class="time"]', $row)->item(0);
$time = $timeNode ? trim($timeNode->nodeValue) : '';
$createdAt = date('Y-m-d H:i:s'); // 기본값은 현재 시각

if (!empty($time)) {
    // 시간 형식 변환
    $createdAt = $this->parsePostTime($time);
}

        
        // 6. 조회수 추출
        $viewsNode = $xpath->query('.//td[@class="m_no"]', $row)->item(0);
        $viewsText = $viewsNode ? trim($viewsNode->nodeValue) : '0';
        // 쉼표 제거
        $viewsCount = intval(str_replace(',', '', $viewsText));
        
        return [
            'title' => $this->cleanTitle($title),
            'url' => $url,
            'author' => '무명의 더쿠',  // 더쿠는 익명 게시판
            'comments_count' => $commentsCount,
            'views_count' => $viewsCount,
            'likes_count' => 0,  // 더쿠는 추천수가 없음
            'created_at' => $createdAt,
            'time' => $time,
            'category' => $category,
            'site' => 'theqoo',
            'rank' => $rank,
            'post_no' => $postNo
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
        
        // HH:MM 형식 (오늘 게시글: 13:17)
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeString, $matches)) {
            $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $minute = $matches[2];
            return date('Y-m-d') . ' ' . $hour . ':' . $minute . ':00';
        }
        
        // MM.DD 형식 (어제 이전 게시글: 08.22)
        if (preg_match('/^(\d{1,2})\.(\d{1,2})$/', $timeString, $matches)) {
            $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            return date('Y') . '-' . $month . '-' . $day . ' 00:00:00';
        }
        
        // 파싱할 수 없는 경우 현재 시각 반환
        return date('Y-m-d H:i:s');
    }
    
}

// 실행 코드
echo "<h2>더쿠 크롤러 테스트</h2>\n";
echo "<pre>\n";

$crawler = new TheqooCrawler();
$result = $crawler->crawlHotPosts(50);

echo "\n테스트 완료: {$result}개 게시글 저장됨\n";
echo "</pre>\n";
?>
