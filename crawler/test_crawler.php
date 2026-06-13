<?php
// simple_test.php - HTML 출력 버전 (데이터 추출 개선)
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>크롤러 테스트</title>
    <style>
        body { font-family: monospace; white-space: pre-wrap; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .warning { color: orange; }
    </style>
</head>
<body>
<?php
echo "<div class='info'>뽐뿌 크롤러 테스트 시작</div>\n";
echo "============================================================\n";

// PHP 오류 표시 활성화
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "<div class='info'>1. 파일 로드 중...</div>\n";
    require_once 'BaseCrawler.php';
    
    // 데이터 추출이 개선된 PpomppuCrawler 클래스
    class ImprovedPpomppuCrawler extends BaseCrawler {
        private $baseUrl = 'https://www.ppomppu.co.kr';
        private $targetUrl = 'https://www.ppomppu.co.kr/hot.php?category=2';
        
        public function __construct() {
            parent::__construct(1, 'ppomppu');
        }
        
        public function crawlHotPosts($limit = 50) {
            try {
                echo "뽐뿌 HOT게시글 크롤링 시작...\n";
                echo "대상 URL: {$this->targetUrl}\n";
                echo str_repeat("-", 60) . "\n";
                
                $html = $this->makeRequest($this->targetUrl);
                
                if (strlen($html) < 1000) {
                    throw new Exception("HTML 응답이 너무 짧습니다.");
                }
                
                echo "HTML 길이: " . strlen($html) . " 바이트\n";
                echo str_repeat("-", 60) . "\n";
                
                $posts = $this->parseHotPosts($html, $limit);
                
                if (!empty($posts)) {
                    $savedCount = $this->savePosts($posts);
                    echo "\n뽐뿌 HOT게시글 크롤링 완료: {$savedCount}개 게시글 저장\n";
                    return $savedCount;
                } else {
                    echo "파싱된 게시글이 없습니다.\n";
                    return 0;
                }
                
            } catch (Exception $e) {
                echo "크롤링 실패: " . $e->getMessage() . "\n";
                return 0;
            }
        }
        
        private function parseHotPosts($html, $limit) {
            $posts = [];
            
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // 게시글 링크 찾기
            $postLinks = $xpath->query("//a[contains(@href, 'zboard.php') and contains(@href, 'no=')]");
            
            if (!$postLinks || $postLinks->length == 0) {
                echo "게시글 링크를 찾을 수 없습니다.\n";
                return $posts;
            }
            
            echo "찾은 링크: {$postLinks->length}개\n";
            echo str_repeat("-", 60) . "\n";
            
            $count = 0;
            $processedUrls = [];
            
            foreach ($postLinks as $linkNode) {
                if ($count >= $limit) break;
                
                try {
                    $post = $this->extractPostFromLink($linkNode, $xpath);
                    
                    if ($post && $this->isValidPost($post)) {
                        if (!in_array($post['url'], $processedUrls)) {
                            $posts[] = $post;
                            $processedUrls[] = $post['url'];
                            $count++;
                            
                            // 추출된 게시글 정보 출력
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
        
        private function extractPostFromLink($linkNode, $xpath) {
            $title = trim($linkNode->textContent);
            $href = $linkNode->getAttribute('href');
            
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
            
            // 스폰서 URL 필터링
            if (strpos($url, 'sponsor') !== false) {
                return null;
            }
            
            // 부모 행(tr) 찾기
            $parentRow = $linkNode;
            for ($i = 0; $i < 10; $i++) {
                $parentRow = $parentRow->parentNode;
                if (!$parentRow) break;
                if ($parentRow->nodeName === 'tr') {
                    break;
                }
            }
            
            // 기본값 설정
            $realTitle = '';
            $author = '익명';
            $viewsCount = 0;
            $likesCount = 0;
            $commentsCount = 0;
            $createdAt = date('Y-m-d H:i:s');
            $thumbnailUrl = null; // ✅ 추가
            
            if ($parentRow) {
                
    // ✅ 썸네일 이미지 추출 (먼저 실행)
    $imgNodes = $xpath->query(".//a[contains(@class, 'baseList-thumb')]/img", $parentRow);
    if ($imgNodes->length > 0) {
        $imgSrc = $imgNodes->item(0)->getAttribute('src');
        if (!empty($imgSrc)) {
            if (strpos($imgSrc, '//') === 0) {
                $thumbnailUrl = 'https:' . $imgSrc;
            } else if (strpos($imgSrc, '/') === 0) {
                $thumbnailUrl = $this->baseUrl . $imgSrc;
            } else {
                $thumbnailUrl = $imgSrc;
            }
            echo "      -> 썸네일: {$thumbnailUrl}\n";
        }
    }           
                
                $cells = $xpath->query(".//td", $parentRow);
                
                echo "  분석 중: " . substr($title, 0, 30) . "...\n";
                echo "    셀 개수: {$cells->length}개\n";
                
                foreach ($cells as $i => $cell) {
                    $cellText = trim($cell->textContent);
                    if (!empty($cellText)) {
                        echo "    셀 {$i}: '{$cellText}'\n";
                        
                        // 셀 0: 게시판명 - 광고 게시판 필터링
                        if ($i == 0) {
                            $adBoards = ['뽐뿌스폰서', '보험업체', '광고'];
                            foreach ($adBoards as $adBoard) {
                                if (strpos($cellText, $adBoard) !== false) {
                                    echo "      -> 광고 게시판 감지, 건너뜀\n";
                                    return null;
                                }
                            }
                        }
                        
                        // 셀 2: 실제 게시글 제목
                        else if ($i == 2) {
                            $realTitle = $cellText;
                            
                            // 광고 키워드 필터링
                            $adKeywords = ['렌탈료', '할인', '최대혜택', '공식판매점', '24시간상담', 'AD '];
                            foreach ($adKeywords as $keyword) {
                                if (strpos($realTitle, $keyword) !== false) {
                                    echo "      -> 광고 키워드 '{$keyword}' 감지, 건너뜀\n";
                                    return null;
                                }
                            }
                            
                            // 제목 끝의 숫자는 댓글수
                            if (preg_match('/(\d+)$/', $realTitle, $matches)) {
                                $commentsCount = intval($matches[1]);
                                $realTitle = preg_replace('/\s*\d+$/', '', $realTitle);
                                echo "      -> 댓글수 추출: {$commentsCount}개\n";
                            }
                        }
                        
                        // 셀 3: 작성자
                        else if ($i == 3) {
                            $author = $cellText;
                            echo "      -> 작성자: {$author}\n";
                        }
                        
                        // 셀 4: 작성일시
                        else if ($i == 4) {
                            if (preg_match('/\d{2}:\d{2}/', $cellText)) {
                                $createdAt = $this->parseDate($cellText);
                                echo "      -> 작성일: {$createdAt}\n";
                            }
                        }
                        
                        // 셀 5: 추천수 (형태: "52 - 0")
                        else if ($i == 5) {
                            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $cellText, $matches)) {
                                $likesCount = intval($matches[1]);
                                echo "      -> 추천수: {$likesCount}개\n";
                            }
                        }
                        
                        // 셀 6: 조회수
                        else if ($i == 6) {
                            if (preg_match('/^\d+$/', $cellText)) {
                                $viewsCount = intval($cellText);
                                echo "      -> 조회수: {$viewsCount}회\n";
                            }
                        }
                    }
                }
            }
            
            // 실제 제목이 없으면 링크 텍스트 사용
            if (empty($realTitle)) {
                $realTitle = $title;
            }
            
            return [
                'title' => $this->cleanTitle($realTitle),
                'url' => $url,
                'thumbnail_url' => $thumbnailUrl, // ✅ 추가
                'author' => $author,
                'comments_count' => $commentsCount,
                'views_count' => $viewsCount,
                'likes_count' => $likesCount,
                'created_at' => $createdAt,
                'category' => 'shopping',
                'site' => 'ppomppu'
            ];
        }
        
        private function isValidPost($post) {
            if (empty($post['title']) || empty($post['url'])) {
                return false;
            }
            
            if (strlen($post['title']) < 5) {
                return false;
            }
            
            // 게시판명 필터링
            $invalidTitles = ['자유게시판', 'PC/인터넷', '유머/감동', '보험업체', '뽐뿌스폰서', 'HOT'];
            foreach ($invalidTitles as $invalid) {
                if (trim($post['title']) === $invalid || strpos($post['title'], $invalid) === 0) {
                    return false;
                }
            }
            
            if (!preg_match('/no=\d+/', $post['url'])) {
                return false;
            }
            
            return true;
        }
        
        private function cleanTitle($title) {
            $title = strip_tags($title);
            $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
            $title = preg_replace('/\s+/', ' ', $title);
            $title = trim($title);
            $title = preg_replace('/^HOT\s*/', '', $title);
            $title = preg_replace('/^AD\s*/', '', $title);
            return $title;
        }
        
        private function parseDate($dateString) {
            $dateString = trim($dateString);
            
            // "방금", "1분전" 등
            if (strpos($dateString, '방금') !== false) {
                return date('Y-m-d H:i:s');
            }
            
            if (preg_match('/(\d+)분\s*전/', $dateString, $matches)) {
                return date('Y-m-d H:i:s', strtotime("-{$matches[1]} minutes"));
            }
            
            if (preg_match('/(\d+)시간\s*전/', $dateString, $matches)) {
                return date('Y-m-d H:i:s', strtotime("-{$matches[1]} hours"));
            }
            
            // "17:02:01" 형태 (오늘)
            if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $dateString, $matches)) {
                return date('Y-m-d') . ' ' . sprintf('%02d', $matches[1]) . ':' . $matches[2] . ':' . $matches[3];
            }
            
            // "17:02" 형태 (오늘)
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $dateString, $matches)) {
                return date('Y-m-d') . ' ' . sprintf('%02d', $matches[1]) . ':' . $matches[2] . ':00';
            }
            
            // "16.57.01" 형태 (오늘)
            if (preg_match('/^(\d{1,2})\.(\d{2})\.(\d{2})$/', $dateString, $matches)) {
                return date('Y-m-d') . ' ' . sprintf('%02d', $matches[1]) . ':' . $matches[2] . ':' . $matches[3];
            }
            
            // "23/07/01" 형태 (년/월/일)
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $dateString, $matches)) {
                $year = '20' . $matches[1];
                return $year . '-' . $matches[2] . '-' . $matches[3] . ' 00:00:00';
            }
            
            // "12-25 15:30" 형태
            if (preg_match('/(\d{2})-(\d{2})\s+(\d{1,2}):(\d{2})/', $dateString, $matches)) {
                $year = date('Y');
                return $year . '-' . $matches[1] . '-' . $matches[2] . ' ' . sprintf('%02d', $matches[3]) . ':' . $matches[4] . ':00';
            }
            
            return date('Y-m-d H:i:s');
        }
    }
    
    echo "<div class='info'>2. 크롤러 생성 중...</div>\n";
    $crawler = new ImprovedPpomppuCrawler();
    
    echo "<div class='info'>3. 크롤링 시작...</div>\n";
    $result = $crawler->crawlHotPosts(5); // 5개만 테스트
    
    echo "<div class='success'>4. 완료: {$result}개 저장</div>\n";
    
} catch (Error $e) {
    echo "<div class='error'>PHP 오류: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    echo "<div class='error'>파일: " . htmlspecialchars($e->getFile()) . "</div>\n";
    echo "<div class='error'>라인: " . $e->getLine() . "</div>\n";
} catch (Exception $e) {
    echo "<div class='error'>예외 발생: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

echo "============================================================\n";
echo "<div class='info'>테스트 완료</div>\n";
?>
</body>
</html>
