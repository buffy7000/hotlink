<?php
require_once 'BaseCrawler.php';

class PpomppuCrawler extends BaseCrawler {
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
                echo "\n" . str_repeat("=", 60) . "\n";
                echo "뽐뿌 HOT게시글 크롤링 완료: {$savedCount}개 게시글 저장\n";
                echo str_repeat("=", 60) . "\n";
                return $savedCount;
            } else {
                echo "파싱된 게시글이 없습니다.\n";
                file_put_contents('debug_ppomppu.html', $html);
                echo "전체 HTML이 debug_ppomppu.html에 저장되었습니다.\n";
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
        
        // 실제 게시글 링크만 찾기 (게시판명 제외)
        $linkSelectors = [
            "//a[contains(@href, 'zboard.php') and contains(@href, 'no=')]"
        ];
        
        $postLinks = null;
        foreach ($linkSelectors as $selector) {
            $postLinks = $xpath->query($selector);
            if ($postLinks->length > 0) {
                echo "사용된 링크 셀렉터: {$selector}\n";
                echo "찾은 링크: {$postLinks->length}개\n";
                echo str_repeat("-", 60) . "\n";
                break;
            }
        }
        
        if (!$postLinks || $postLinks->length == 0) {
            echo "게시글 링크를 찾을 수 없습니다.\n";
            return $posts;
        }
        
        $count = 0;
        $processedUrls = [];
        $skippedCount = 0;
        
        foreach ($postLinks as $linkNode) {
            if ($count >= $limit) break;
            
            try {
                $post = $this->extractPostFromLink($linkNode, $xpath);
                
                if ($post === null) {
                    $skippedCount++;
                    echo "  -> 광고/무효 게시글로 건너뜀\n";
                    continue;
                }
                
                if ($post && $this->isValidPost($post)) {
                    // 중복 URL 체크
                    if (!in_array($post['url'], $processedUrls)) {
                        $posts[] = $post;
                        $processedUrls[] = $post['url'];
                        $count++;
                        
                        echo sprintf("게시글 #%d\n", $count);
                        echo sprintf("  제목: %s\n", substr($post['title'], 0, 60) . (strlen($post['title']) > 60 ? "..." : ""));
                        echo sprintf("  작성자: %s | 댓글: %d개 | 조회: %d회 | 추천: %d개\n", 
                            $post['author'], 
                            $post['comments_count'], 
                            $post['views_count'], 
                            $post['likes_count']
                        );
                        echo sprintf("  작성일: %s\n", $post['created_at']);
                        echo str_repeat("-", 60) . "\n";
                    }
                } else {
                    $skippedCount++;
                }
            } catch (Exception $e) {
                echo "게시글 추출 실패: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        echo "\n총 {$count}개 게시글 추출 완료 (건너뜀: {$skippedCount}개)\n";
        return $posts;
    }
    
    private function extractPostFromLink($linkNode, $xpath) {
        
        // ✅ 디버깅: linkNode가 어떤 링크인지 확인
    $linkClass = $linkNode->getAttribute('class');
    $linkHref = $linkNode->getAttribute('href');
    echo "  [DEBUG] linkNode class: '{$linkClass}'\n";
    echo "  [DEBUG] linkNode href: '{$linkHref}'\n";    
        
        // URL과 제목 추출
        $title = trim($linkNode->textContent);
        $href = $linkNode->getAttribute('href');
        
        if (strpos($href, 'http') !== 0) {
            if (strpos($href, '/') === 0) {
                $url = $this->baseUrl . $href;
            } else {
                $url = $this->baseUrl . '/' . $href;
            }
        } else {
            $url = $href;
        }
        

        echo "  URL: {$url}\n";
        
        // 방법 4: URL 기반 필터링 (미리 체크)
        if (strpos($url, 'sponsor') !== false) {
            echo "  -> 스폰서 URL 감지 - 건너뜀\n";
            return null;
        }
        
        // 부모 행에서 정보 추출
        $parentRow = $linkNode;
        for ($i = 0; $i < 10; $i++) {
            $parentRow = $parentRow->parentNode;
            if (!$parentRow) break;
            if ($parentRow->nodeName === 'tr') {
                break;
            }
        }
        
        $realTitle = '';
        $author = '익명';
        $viewsCount = 0;
        $likesCount = 0;
        $commentsCount = 0;
        $createdAt = date('Y-m-d H:i:s');
        $boardName = '';
        $thumbnailUrl = null; // 
        
        // ✅ 가장 확실한 단일 XPath
if ($parentRow) {
    $imgNodes = $xpath->query(".//a[contains(@class, 'baseList-thumb')]/img", $parentRow);
    
    if ($imgNodes->length > 0) {
        $imgSrc = $imgNodes->item(0)->getAttribute('src');
        
        // // 로 시작하면 https: 추가
        if (strpos($imgSrc, '//') === 0) {
            $thumbnailUrl = 'https:' . $imgSrc;
        } else if (strpos($imgSrc, '/') === 0) {
            $thumbnailUrl = $this->baseUrl . $imgSrc;
        } else {
            $thumbnailUrl = $imgSrc;
        }
        echo "  썸네일: {$thumbnailUrl}\n";
    }
}
        
        


        
        // 실제 제목이 없으면 링크 텍스트 사용
        if (empty($realTitle)) {
            $realTitle = $title;
            echo "  실제 제목이 없어서 링크 텍스트 사용: '{$realTitle}'\n";
        }
        

        return [
            'title' => $this->cleanTitle($realTitle),
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl, // 
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
            echo "  유효성 검사 실패: 제목 또는 URL이 비어있음\n";
            return false;
        }
        
        if (strlen($post['title']) < 5) {
            echo "  유효성 검사 실패: 제목이 너무 짧음 ({$post['title']})\n";
            return false;
        }
        
        // 방법 1: 제목 기반 필터링 (추가 체크)
        if (strpos($post['title'], 'AD ') === 0 || strpos($post['title'], 'AD') === 0) {
            echo "  유효성 검사 실패: AD 광고글 ({$post['title']})\n";
            return false;
        }
        
        // 광고성 키워드 필터링 (추가 키워드)
        $adKeywords = ['렌탈료', '할인', '최대혜택', '공식판매점', '24시간상담', '무료상담', '최저가', '특가', '이벤트', '상담', '설치', '무료', '혜택'];
        foreach ($adKeywords as $keyword) {
            if (strpos($post['title'], $keyword) !== false) {
                echo "  유효성 검사 실패: 광고 키워드 포함 '{$keyword}' ({$post['title']})\n";
                return false;
            }
        }
        
        // 해시태그가 많이 포함된 경우 (광고 특징)
        if (substr_count($post['title'], '#') >= 3) {
            echo "  유효성 검사 실패: 해시태그 과다 ({$post['title']})\n";
            return false;
        }
        
        // 방법 4: URL 기반 필터링
        if (strpos($post['url'], 'sponsor') !== false) {
            echo "  유효성 검사 실패: 스폰서 URL ({$post['url']})\n";
            return false;
        }
        
        // 기존 게시판명 필터링 (방법 2 포함)
        $invalidTitles = ['자유게시판', 'PC/인터넷', '유머/감동', '보험업체', '뽐뿌스폰서', 'HOT'];
        foreach ($invalidTitles as $invalid) {
            if (trim($post['title']) === $invalid || strpos($post['title'], $invalid) === 0) {
                echo "  유효성 검사 실패: 무효한 제목 ({$post['title']})\n";
                return false;
            }
        }
        
        if (!preg_match('/no=\d+/', $post['url'])) {
            echo "  유효성 검사 실패: URL에 게시글 번호 없음\n";
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
            $year = '20' . $matches[1]; // 23 -> 2023
            return $year . '-' . $matches[2] . '-' . $matches[3] . ' 00:00:00';
        }
        
        // "12-25 15:30" 형태
        if (preg_match('/(\d{2})-(\d{2})\s+(\d{1,2}):(\d{2})/', $dateString, $matches)) {
            $year = date('Y');
            return $year . '-' . $matches[1] . '-' . $matches[2] . ' ' . sprintf('%02d', $matches[3]) . ':' . $matches[4] . ':00';
        }
        
        return date('Y-m-d H:i:s');
    }
    
    // 여러 페이지 크롤링
    public function crawlMultiplePages($pages = 3, $limitPerPage = 20) {
        $allPosts = [];
        
        for ($page = 1; $page <= $pages; $page++) {
            try {
                echo "\n" . str_repeat("=", 60) . "\n";
                echo "뽐뿌 HOT게시글 {$page}페이지 크롤링 중...\n";
                echo str_repeat("=", 60) . "\n";
                
                $url = $this->targetUrl . "&page={$page}";
                $html = $this->makeRequest($url);
                
                $posts = $this->parseHotPosts($html, $limitPerPage);
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
            echo "\n" . str_repeat("=", 60) . "\n";
            echo "전체 크롤링 완료: {$savedCount}개 게시글 저장\n";
            echo str_repeat("=", 60) . "\n";
            return $savedCount;
        }
        
        return 0;
    }
}
?>
