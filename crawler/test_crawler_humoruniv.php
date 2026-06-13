```php
<?php
require_once 'BaseCrawler.php';

class HumorunivCrawler extends BaseCrawler {
    private $baseUrl = 'https://m.humoruniv.com';
    private $targetUrl = 'https://m.humoruniv.com/board/list.html?table=pds&st=day';
    
    public function __construct() {
        parent::__construct(8, 'humoruniv');  // community_id 8번 (웃긴대학)
    }
    
    public function crawlHotPosts($limit = 30) {
        return $this->crawlBestPosts($limit);
    }
    
    public function crawlBestPosts($limit = 30) {
        $allPosts = [];
        $totalSaved = 0;
        
        // 3페이지 크롤링
        for ($page = 0; $page <= 2; $page++) {  // 웃대는 페이지가 0부터 시작
            try {
                echo "\n웃긴대학 인기글 " . ($page + 1) . "페이지 크롤링 시작...\n";
                echo str_repeat("=", 60) . "\n";
                
                // URL 파라미터 설정
                $url = $this->targetUrl;
                if ($page > 0) {
                    $url .= "&pg={$page}";
                }
                echo "URL: {$url}\n";
                
                $html = $this->makeRequest($url);
                
                if (strlen($html) < 1000) {
                    throw new Exception("HTML 응답이 너무 짧습니다.");
                }
                
                echo "HTML 길이: " . strlen($html) . " 바이트\n";
                echo str_repeat("-", 60) . "\n";
                
                $posts = $this->parseHumorPosts($html, $limit);
                
                if (!empty($posts)) {
                    $savedCount = $this->savePosts($posts);
                    $totalSaved += $savedCount;
                    echo "\n" . ($page + 1) . "페이지: {$savedCount}개 게시글 저장\n";
                }
                
                // 페이지 간 대기
                if ($page < 2) {
                    echo "다음 페이지까지 2초 대기...\n";
                    sleep(2);
                }
                
            } catch (Exception $e) {
                echo "페이지 " . ($page + 1) . " 크롤링 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        echo "\n전체 웃긴대학 크롤링 완료: 총 {$totalSaved}개 게시글 저장\n";
        return $totalSaved;
    }
    
    private function parseHumorPosts($html, $limit) {
        $posts = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 게시글 목록 찾기 - a.list_body_href 구조
        $items = $xpath->query('//a[@class="list_body_href"]');
        
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
                    echo "  추천: {$post['likes_count']} | 비추천: {$post['dislikes_count']}\n";
                    echo "  댓글: {$post['comments_count']}개 | 조회: {$post['views_count']}회\n";
                    echo "  답글추천: {$post['reply_likes']}\n";
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
        // 1. URL 추출
        $href = $item->getAttribute('href');
        if (strpos($href, 'http') !== 0) {
            $url = $this->baseUrl . '/board/' . $href;
        } else {
            $url = $href;
        }
        
        // URL 정리 - 주요 파라미터만 유지
        if (preg_match('/table=([^&]+).*number=([^&]+)/', $href, $matches)) {
            $url = $this->baseUrl . '/board/read.html?table=' . $matches[1] . '&number=' . $matches[2];
        }
        
        // 2. 제목 추출
        $titleNode = $xpath->query('.//span[contains(@id, "title_chk")]', $item)->item(0);
        if (!$titleNode) {
            echo "  제목을 찾을 수 없음\n";
            return null;
        }
        
        $title = trim($titleNode->nodeValue);
        
        // 예외처리: 제목에 '공지' 포함된 글 제외
        if (strpos($title, '공지') !== false || strpos($title, '[공지]') !== false) {
            echo "  공지사항 제외: 제목에 '공지' 포함\n";
            return null;
        }
        
        // 3. 썸네일 이미지 추출
        $thumbnailUrl = null;
        $imgNode = $xpath->query('.//img[@class="img"]', $item)->item(0);
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
        
        // 4. 추천수 추출
        $likesCount = 0;
        $okNode = $xpath->query('.//span[@class="ok_num"]', $item)->item(0);
        if ($okNode) {
            $okText = trim($okNode->nodeValue);
            // 숫자만 추출
            preg_match('/\d+/', $okText, $matches);
            if (isset($matches[0])) {
                $likesCount = intval($matches[0]);
            }
        }
        
        // 5. 비추천수 추출
        $dislikesCount = 0;
        $notOkNode = $xpath->query('.//span[@class="not_ok_num"]', $item)->item(0);
        if ($notOkNode) {
            $notOkText = trim($notOkNode->nodeValue);
            preg_match('/\d+/', $notOkText, $matches);
            if (isset($matches[0])) {
                $dislikesCount = intval($matches[0]);
            }
        }
        
        // 6. 댓글수 추출
        $commentsCount = 0;
        $commentNode = $xpath->query('.//span[@class="comment_num"]', $item)->item(0);
        if ($commentNode) {
            $commentText = trim($commentNode->nodeValue);
            preg_match('/\d+/', $commentText, $matches);
            if (isset($matches[0])) {
                $commentsCount = intval($matches[0]);
            }
        }
        
        // 7. 시간 추출 및 변환 (먼저 처리)
        $time = '';
        $createdAt = date('Y-m-d H:i:s');
        $timeNodes = $xpath->query('.//span[@class="extra"]', $item);
        if ($timeNodes->length > 1) {
            $time = trim($timeNodes->item(1)->nodeValue);
            $time = str_replace(["\xc2\xa0", '&nbsp;'], '', $time);
            $time = trim($time);
            
            if (!empty($time)) {
                $createdAt = $this->parsePostTime($time);
            }
        }

        // 8. 조회수 추출 (시간 추출 후 처리)
        $viewsCount = 0;
        $viewNodes = $xpath->query('.//span[@class="extra"]', $item);
        foreach ($viewNodes as $node) {
            $text = trim($node->nodeValue);
            if (strpos($text, ',') !== false || preg_match('/^\d{1,3}(,\d{3})*$/', $text)) {
                $viewsCount = intval(str_replace(',', '', $text));
                break;
            }
        }

        // 9. 답글추천 추출
        $replyLikes = 0;
        $replyNodes = $xpath->query('.//span[contains(text(), "답글추천")]', $item);
        if ($replyNodes->length > 0) {
            $replyText = trim($replyNodes->item(0)->nodeValue);
            if (preg_match('/\+(\d+)/', $replyText, $matches)) {
                $replyLikes = intval($matches[1]);
            }
        }
        
        // 10. 작성자 추출
        $author = '';
        $nickNode = $xpath->query('.//span[@class="hu_nick_txt"]', $item)->item(0);
        if ($nickNode) {
            $author = trim($nickNode->nodeValue);
        }
        
        // 11. 파일 크기 추출 (선택사항)
        $fileSize = '';
        $sizeNode = $xpath->query('.//span[@class="size"]', $item)->item(0);
        if ($sizeNode) {
            $fileSize = trim($sizeNode->nodeValue);
        }
        
        return [
            'title' => $this->cleanTitle($title),
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'author' => $author ?: '웃대회원',
            'comments_count' => $commentsCount,
            'views_count' => $viewsCount,
            'likes_count' => $likesCount,
            'dislikes_count' => $dislikesCount,
            'reply_likes' => $replyLikes,
            'created_at' => $createdAt,
            'time' => $time,
            'category' => 'pds',  // 자료실
            'site' => 'humoruniv',
            'rank' => $rank,
            'file_size' => $fileSize
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
        
        // "토요일 10:57" 형식 (요일 HH:MM) - 한글 요일 처리
        if (preg_match('/[가-힣]+\s+(\d{1,2}):(\d{2})/', $timeString, $matches)) {
            $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $minute = $matches[2];
            
            // 시간이 현재 시간보다 미래라면 어제 날짜 사용
            $currentTime = date('H:i');
            $postTime = $hour . ':' . $minute;
            
            if ($postTime > $currentTime) {
                // 어제 날짜 사용
                return date('Y-m-d', strtotime('-1 day')) . ' ' . $hour . ':' . $minute . ':00';
            } else {
                // 오늘 날짜 사용
                return date('Y-m-d') . ' ' . $hour . ':' . $minute . ':00';
            }
        }
        
        // HH:MM 형식 (당일 게시글: 10:57)
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeString, $matches)) {
            $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $minute = $matches[2];
            return date('Y-m-d') . ' ' . $hour . ':' . $minute . ':00';
        }
        
        // YYYY-MM-DD 형식 (오늘 이전 날짜: 2025-08-18)
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $timeString, $matches)) {
            $year = $matches[1];
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            return $year . '-' . $month . '-' . $day . ' 00:00:00';
        }
        
        // 파싱할 수 없는 경우 현재 시각 반환
        return date('Y-m-d H:i:s');
    }
    
    // 웃대는 추천수가 중요하므로 가중치 조정
    protected function calculateRankScore($post) {
        $commentsScore = ($post['comments_count'] ?? 0) * 3;
        $likesScore = ($post['likes_count'] ?? 0) * 5;  // 추천 가중치 높임
        $viewsScore = ($post['views_count'] ?? 0) * 0.01;
        $replyLikesScore = ($post['reply_likes'] ?? 0) * 2;  // 답글추천도 반영
        
        // 비추천이 많으면 점수 감소
        $dislikePenalty = ($post['dislikes_count'] ?? 0) * -3;
        
        // 시간 가중치
        $hoursOld = $this->getHoursOld($post['created_at']);
        $timeWeight = pow(0.8, $hoursOld / 24);
        
        return ($commentsScore + $likesScore + $viewsScore + $replyLikesScore + $dislikePenalty) * $timeWeight;
    }
}

// 실행 코드
echo "<h2>웃긴대학 크롤러 테스트</h2>\n";
echo "<pre>\n";

$crawler = new HumorunivCrawler();
$result = $crawler->crawlHotPosts(30);

echo "\n테스트 완료: {$result}개 게시글 저장됨\n";
echo "</pre>\n";
?>
```