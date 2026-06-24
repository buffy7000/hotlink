<?php
require_once __DIR__ . '/BaseJobCrawler.php';

class JobKoreaCrawler extends BaseJobCrawler {
    private $baseUrl   = 'https://m.jobkorea.co.kr';
    private $searchUrl = 'https://m.jobkorea.co.kr/Search/Adv?Keyword=%EC%9B%B9%EA%B8%B0%ED%9A%8D&FeatureCode=WRK&jobType=6&sort=22';

    // 제목에 하나라도 포함되어야 함
    private $includeKeywords = ['기획'];

    // 제목에 하나라도 포함되면 제외
    private $excludeKeywords = [];

    public function __construct() {
        parent::__construct(4, 'jobkorea');
    }

    public function crawl() {
        echo "[잡코리아] 크롤링 시작...\n";

        $allJobs = [];
        $page    = 1;

        while (true) {
            $url = $this->searchUrl . '&PageNo=' . $page;
            try {
                $html = $this->makeRequest($url);
            } catch (Exception $e) {
                echo "[잡코리아] 요청 실패 (페이지 {$page}): " . $e->getMessage() . "\n";
                break;
            }

            $jobs = $this->parseJobs($html);
            echo "[잡코리아] 페이지 {$page}: " . count($jobs) . "개 수집\n";

            if (empty($jobs)) break;
            $allJobs = array_merge($allJobs, $jobs);

            if (!$this->hasNextPage($html, $page + 1)) break;
            $page++;
            sleep(1);
        }

        if (empty($allJobs)) {
            echo "[잡코리아] 수집된 공고 없음\n";
            return 0;
        }

        $saved = $this->saveJobs($allJobs);
        echo "[잡코리아] 총 {$saved}개 저장 완료\n";
        return $saved;
    }

    private function parseJobs($html) {
        $xpath = $this->parseHtml($html);
        $jobs  = [];
        $seen  = [];

        $links = $xpath->query("//a[contains(@href, '/Recruit/GI_Read/')]");
        if (!$links || $links->length === 0) {
            echo "[잡코리아] 공고 링크를 찾지 못했습니다.\n";
            return $jobs;
        }

        echo "[잡코리아] 발견된 링크: {$links->length}개\n";

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (!preg_match('#/Recruit/GI_Read/(\d+)#', $href, $m)) continue;

            $jobId = $m[1];
            if (isset($seen[$jobId])) continue;
            $seen[$jobId] = true;

            $title = $this->cleanText($link->textContent);
            if (empty($title)) continue;

            if (!$this->passesFilter($title)) continue;

            $url       = $this->baseUrl . '/Recruit/GI_Read/' . $jobId;
            $container = $this->findJobContainer($link);

            $agency     = null;
            $location   = null;
            $experience = null;
            $deadline   = null;

            if ($container) {
                $agency                           = $this->extractAgency($xpath, $container, $title);
                [$location, $experience, $deadline] = $this->extractMeta($xpath, $container);
            }

            $jobs[] = [
                'title'      => $title,
                'url'        => $url,
                'status'     => '접수중',
                'agency'     => $agency,
                'location'   => $location,
                'experience' => $experience,
                'deadline'   => $deadline,
            ];

            echo "  - {$title}" . ($agency ? " [{$agency}]" : '') . ($deadline ? " {$deadline}" : '') . "\n";
        }

        return $jobs;
    }

    private function passesFilter($title) {
        $matched = false;
        foreach ($this->includeKeywords as $kw) {
            if (mb_strpos($title, $kw) !== false) { $matched = true; break; }
        }
        if (!$matched) {
            echo "  [제외-미포함] {$title}\n";
            return false;
        }

        foreach ($this->excludeKeywords as $kw) {
            if (mb_strpos($title, $kw) !== false) {
                echo "  [제외-키워드:{$kw}] {$title}\n";
                return false;
            }
        }

        return true;
    }

    // 링크에서 가장 가까운 li/article 컨테이너를 찾고, 없으면 4단계 위 반환
    private function findJobContainer($link) {
        $node = $link->parentNode;
        for ($i = 0; $i < 8; $i++) {
            if (!$node || $node->nodeName === 'body') break;
            $tag = strtolower($node->nodeName);
            if ($tag === 'li' || $tag === 'article') return $node;
            $node = $node->parentNode;
        }
        $node = $link->parentNode;
        for ($i = 0; $i < 4 && $node && $node->parentNode && $node->parentNode->nodeName !== 'body'; $i++) {
            $node = $node->parentNode;
        }
        return $node;
    }

    // 공고 제목 링크가 아닌 회사명 추출
    private function extractAgency($xpath, $container, $title) {
        $candidates = $xpath->query(".//*[self::strong or self::b or self::em or self::span or self::p or self::div[not(.//*[self::ul or self::ol])]]", $container);
        foreach ($candidates as $node) {
            // 자식이 많은 노드는 컨테이너이므로 스킵
            if ($node->childNodes->length > 3) continue;

            $text = $this->cleanText($node->textContent);
            if (empty($text) || $text === $title) continue;
            if (mb_strlen($text) < 2 || mb_strlen($text) > 60) continue;

            // 숫자/날짜/지역/경력/메타 패턴 제외
            if (preg_match('/D-\d+|마감|경력|신입|년차|무관|\d{2}\/\d{2}|서울|경기|인천|부산|대구|광주|대전|울산|세종|강원|충북|충남|전북|전남|경북|경남|제주/', $text)) continue;

            return $text;
        }
        return null;
    }

    // 지역 / 경력 / 마감일 추출
    private function extractMeta($xpath, $container) {
        $location   = null;
        $experience = null;
        $deadline   = null;

        $textNodes = $xpath->query(".//text()", $container);
        foreach ($textNodes as $textNode) {
            $text = trim($textNode->nodeValue);
            if (empty($text)) continue;

            if ($deadline === null && preg_match('/D-(\d+)/', $text, $dm)) {
                $days     = (int)$dm[1];
                $deadline = '마감 ' . date('n.j', strtotime("+{$days} days"));
            }
            if ($location === null && preg_match('/^(서울|경기|인천|부산|대구|광주|대전|울산|세종|강원|충북|충남|전북|전남|경북|경남|제주)/', $text)) {
                $location = $text;
            }
            if ($experience === null && preg_match('/(신입|무관|경력\s*\d+|\d+년차)/', $text)) {
                $experience = $text;
            }
        }

        return [$location, $experience, $deadline];
    }

    private function hasNextPage($html, $nextPage) {
        return strpos($html, 'PageNo=' . $nextPage) !== false;
    }
}

// 단독 실행 지원
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $crawler = new JobKoreaCrawler();
    $crawler->crawl();
}
?>
