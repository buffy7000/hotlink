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

        // 실제 HTML 구조: div.recruit-item 안에 span.item-title, span.item-corp_name,
        // span.item-condition_location, span.item-condition_applicants, span.item-dday
        // 링크(a.section-item_link)는 텍스트 없는 오버레이 형태
        $containers = $xpath->query("//div[contains(@class, 'recruit-item')]");
        if (!$containers || $containers->length === 0) {
            echo "[잡코리아] 공고 컨테이너를 찾지 못했습니다.\n";
            return $jobs;
        }

        echo "[잡코리아] 발견된 공고: {$containers->length}개\n";

        foreach ($containers as $container) {
            // URL
            $linkNode = $xpath->query(".//a[contains(@href, '/Recruit/GI_Read/')]", $container)->item(0);
            if (!$linkNode) continue;
            $href = $linkNode->getAttribute('href');
            if (!preg_match('#/Recruit/GI_Read/(\d+)#', $href, $m)) continue;

            $jobId = $m[1];
            if (isset($seen[$jobId])) continue;
            $seen[$jobId] = true;

            $url = $this->baseUrl . '/Recruit/GI_Read/' . $jobId;

            // 제목
            $titleNode = $xpath->query(".//span[contains(@class, 'item-title')]", $container)->item(0);
            if (!$titleNode) continue;
            $title = $this->cleanText($titleNode->textContent);
            if (empty($title)) continue;

            if (!$this->passesFilter($title)) continue;

            // 회사명
            $agencyNode = $xpath->query(".//span[contains(@class, 'item-corp_name')]", $container)->item(0);
            $agency     = $agencyNode ? $this->cleanText($agencyNode->textContent) : null;

            // 지역
            $locationNode = $xpath->query(".//span[contains(@class, 'item-condition_location')]", $container)->item(0);
            $location     = $locationNode ? $this->cleanText($locationNode->textContent) : null;

            // 경력
            $expNode    = $xpath->query(".//span[contains(@class, 'item-condition_applicants')]", $container)->item(0);
            $experience = $expNode ? $this->cleanText($expNode->textContent) : null;

            // 마감일: item-condition_dday에 "D-6" 형태 → 실제 날짜로 변환
            $ddayNode = $xpath->query(".//span[contains(@class, 'item-condition_dday')]", $container)->item(0);
            $deadline = null;
            if ($ddayNode) {
                $ddayText = $this->cleanText($ddayNode->textContent);
                if (preg_match('/D-(\d+)/', $ddayText, $dm)) {
                    $deadline = '마감 ' . date('n.j', strtotime('+' . $dm[1] . ' days'));
                }
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

            echo "  - {$title}" . ($agency ? " [{$agency}]" : '') . ($location ? " {$location}" : '') . "\n";
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

    private function hasNextPage($html, $nextPage) {
        return strpos($html, 'PageNo=' . $nextPage) !== false;
    }
}

// 단독 실행 지원
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $isCli = php_sapi_name() === 'cli';

    if (!$isCli) {
        header('Content-Type: text/html; charset=utf-8');
        ob_start();
    }

    $crawler = new JobKoreaCrawler();
    $crawler->crawl();

    if (!$isCli) {
        $output = ob_get_clean();
        ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>잡코리아 크롤러</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #111; color: #ccc; font-family: 'Menlo', 'Consolas', monospace; font-size: 13px; padding: 24px; line-height: 1.7; }
  h1 { font-size: 15px; color: #fff; margin-bottom: 16px; border-bottom: 1px solid #333; padding-bottom: 8px; }
  .line { display: block; }
  .status { color: #4f9cf9; }
  .ok     { color: #3faf42; font-weight: 600; }
  .skip   { color: #444; }
  .err    { color: #e84040; }
  .sep    { border-top: 1px solid #222; margin: 8px 0; }
</style>
</head>
<body>
<h1>잡코리아 크롤러 실행 결과</h1>
<?php
        foreach (explode("\n", $output) as $line) {
            if (trim($line) === '') { echo '<div class="sep"></div>'; continue; }
            $e = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            if (strpos($line, '[제외-') !== false) {
                echo '<span class="line skip">' . $e . '</span>';
            } elseif (strpos($line, '오류') !== false || strpos($line, '실패') !== false) {
                echo '<span class="line err">' . $e . '</span>';
            } elseif (strpos($line, '[잡코리아]') !== false) {
                echo '<span class="line status">' . $e . '</span>';
            } elseif (substr(ltrim($line), 0, 2) === '- ') {
                echo '<span class="line ok">' . $e . '</span>';
            } else {
                echo '<span class="line">' . $e . '</span>';
            }
        }
        ?>
</body>
</html>
<?php
    }
}
?>
