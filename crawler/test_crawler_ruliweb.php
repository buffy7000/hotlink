<?php
require_once 'BaseCrawler.php';

class RuliwebCrawler extends BaseCrawler {
    private $baseUrl = 'https://bbs.ruliweb.com';
    private $targetUrl = 'https://bbs.ruliweb.com/best/all';
    
    public function __construct() {
        parent::__construct(4, 'ruliweb');  // community_id 4번
    }
    
    public function crawlHotPosts($limit = 50) {
        return $this->crawlBestPosts('replycount', '1h', $limit);
    }
    
   
    
    public function crawlBestPosts($orderby = 'replycount', $range = '1h', $limit = 50) {
    $allPosts = [];          // ⭐️ 추가: 전체 게시글 저장용
    $totalSaved = 0;         // ⭐️ 추가: 총 저장 개수
    
    // ⭐️ 추가: for 루프로 3페이지 반복
    for ($page = 1; $page <= 3; $page++) {
        try {
            echo "\n루리웹 베스트 {$page}페이지 크롤링 시작...\n";  // ⭐️ 수정: 페이지 번호 표시
            echo str_repeat("=", 60) . "\n";
            
            // URL 파라미터 설정
            $url = $this->targetUrl . "?orderby={$orderby}&range={$range}&page={$page}";  // ⭐️ 수정: &page= 추가
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
                $totalSaved += $savedCount;                                              // ⭐️ 추가: 누적
                echo "\n{$page}페이지: {$savedCount}개 게시글 저장\n";                    // ⭐️ 수정: 페이지별 결과
            }
            
            // ⭐️ 추가: 페이지 간 대기
            if ($page < 3) {
                echo "다음 페이지까지 2초 대기...\n";
                sleep(2);
            }
            
        } catch (Exception $e) {
            echo "페이지 {$page} 크롤링 실패: " . $e->getMessage() . "\n";          // ⭐️ 수정: 페이지 번호
            continue;                                                                // ⭐️ 추가: 다음 페이지 계속
        }
    }  // ⭐️ for 루프 종료
    
    echo "\n전체 루리웹 크롤링 완료: 총 {$totalSaved}개 게시글 저장\n";            // ⭐️ 수정: 전체 결과
    return $totalSaved;                                                            // ⭐️ 수정: 총 개수 반환
}

    
    
    private function parseBestPosts($html, $limit) {
        $posts = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 게시글 목록 찾기 - 정확한 클래스명으로
        $rows = $xpath->query('//tr[@class="table_body blocktarget mode_list"]');
        
        if (!$rows || $rows->length == 0) {
            echo "게시글 리스트를 찾을 수 없습니다.\n";
            return $posts;
        }
        
        echo "찾은 게시글: {$rows->length}개\n";
        echo str_repeat("-", 60) . "\n";
        
        $count = 0;
        
        foreach ($rows as $index => $row) {
            if ($count >= $limit) break;
            
            try {
                $post = $this->extractPostFromRow($row, $xpath, $index + 1);
                
                if ($post && $this->isValidPost($post)) {
                    $posts[] = $post;
                    $count++;
                    
                    echo "게시글 #{$count}\n";
                    echo "  제목: {$post['title']}\n";
                    echo "  작성자: {$post['author']}\n";
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
        
        echo "총 {$count}개 게시글 추출 완료\n";
        return $posts;
    }
    
    private function extractPostFromRow($row, $xpath, $rank) {
        // 1. ID 추출
        $idNode = $xpath->query('.//td[@class="id padding_w_5 text_over screen_out"]', $row)->item(0);
        $postId = $idNode ? trim($idNode->nodeValue) : '';
        
        // 2. 제목과 URL 추출 (td[@class="subject"] 내부)
        $linkNode = $xpath->query('.//td[@class="subject"]//a[@class="subject_link deco flex center"]', $row)->item(0);
        if (!$linkNode) {
            echo "  링크 노드를 찾을 수 없음\n";
            return null;
        }
        
 
        $href = $linkNode->getAttribute('href');  // ⭐️ 이 줄을 추가해야 합니다!
        $cleanHref = preg_replace('/\?.*$/', '', $href);  // 파라미터 제거
        $url = $this->baseUrl . $cleanHref;
        

        
        // 3. 제목 텍스트 추출
        $titleNode = $xpath->query('.//span[@class="text_over"]', $linkNode)->item(0);
        $title = $titleNode ? trim($titleNode->nodeValue) : '';
        
        // 4. 댓글 수 추출
        $commentsCount = 0;
        $commentNode = $xpath->query('.//span[@class="num_reply flex_item_1"]', $linkNode)->item(0);
        if ($commentNode) {
            $commentText = trim($commentNode->nodeValue);
            preg_match('/\((\d+)\)/', $commentText, $matches);
            $commentsCount = isset($matches[1]) ? intval($matches[1]) : 0;
        }
        
        // 5. 작성자 추출
        $writerNode = $xpath->query('.//td[@class="writer text_over screen_out"]', $row)->item(0);
        $author = $writerNode ? trim($writerNode->nodeValue) : '루리웹';
        
        // 6. 추천수 추출
        $recomdNode = $xpath->query('.//td[@class="recomd"]', $row)->item(0);
        $likesCount = $recomdNode ? intval(trim($recomdNode->nodeValue)) : 0;
        
        // 7. 조회수 추출
        $hitNode = $xpath->query('.//td[@class="hit"]', $row)->item(0);
        $viewsCount = $hitNode ? intval(trim($hitNode->nodeValue)) : 0;
        

        
        // 8. 시간 추출 및 디버깅
        $timeNode = $xpath->query('.//td[@class="time"]', $row)->item(0);
        $time = '';
        $createdAt = date('Y-m-d H:i:s');
        
        echo "  [DEBUG] timeNode 존재 여부: " . ($timeNode ? "있음" : "없음") . "\n";
        
        if ($timeNode) {
            // input 태그 제거하고 텍스트만 추출
            $timeClone = $timeNode->cloneNode(true);
            $inputs = $xpath->query('.//input', $timeClone);
            foreach ($inputs as $input) {
                $input->parentNode->removeChild($input);
            }
            $time = trim($timeClone->nodeValue);
            
            echo "  [DEBUG] 추출된 원본 시간: '{$time}'\n";
            
            $createdAt = $this->parsePostTime($time);
            
            echo "  [DEBUG] 변환된 시간: '{$createdAt}'\n";
            echo "  [DEBUG] 현재 시간: '" . date('Y-m-d H:i:s') . "'\n";
        } else {
            echo "  [DEBUG] timeNode가 없어서 현재 시간 사용\n";
        }
        
        return [
            'title' => $this->cleanTitle($title),
            'url' => $url,
            'author' => $author,
            'comments_count' => $commentsCount,
            'views_count' => $viewsCount,
            'likes_count' => $likesCount,
            'created_at' => $createdAt,
            'time' => $time,  // 원본 시간 정보 저장
            'category' => 'best',
            'site' => 'ruliweb',
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
    
    // 여기에 새로운 메서드를 추가하세요!
private function parsePostTime($timeString) {
    echo "  [DEBUG parsePostTime] 입력값: '{$timeString}'\n";
    
    if (empty($timeString)) {
        echo "  [DEBUG parsePostTime] 빈 문자열 -> 현재 시간 반환\n";
        return date('Y-m-d H:i:s');
    }
    
    // HH:MM 형식 (오늘 게시글: 21:13)
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeString, $matches)) {
        $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $minute = $matches[2];
        $result = date('Y-m-d') . ' ' . $hour . ':' . $minute . ':00';
        echo "  [DEBUG parsePostTime] HH:MM 패턴 매칭 -> '{$result}'\n";
        return $result;
    }
    
    // YY.MM.DD 형식 (과거 게시글: 25.08.18)
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{2})$/', $timeString, $matches)) {
        $year = '20' . $matches[1];  // 25 -> 2025
        $month = $matches[2];
        $day = $matches[3];
        $result = $year . '-' . $month . '-' . $day . ' 00:00:00';
        echo "  [DEBUG parsePostTime] YY.MM.DD 패턴 매칭 -> '{$result}'\n";
        return $result;
    }
    
    // 파싱할 수 없는 경우 현재 시각 반환
    echo "  [DEBUG parsePostTime] 패턴 매칭 실패 -> 현재 시간 반환\n";
    return date('Y-m-d H:i:s');
}



}

// 실행 코드
echo "<h2>루리웹 크롤러 테스트</h2>\n";
echo "<pre>\n";

$crawler = new RuliwebCrawler();
$result = $crawler->crawlHotPosts(50);

echo "\n테스트 완료: {$result}개 게시글 저장됨\n";
echo "</pre>\n";
?>
