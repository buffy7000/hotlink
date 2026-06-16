<?php
require_once __DIR__ . '/BaseJobCrawler.php';

class UneedJobCrawler extends BaseJobCrawler {
    private $baseUrl = 'https://www.uneedjob.co.kr';
    private $listUrl = 'https://www.uneedjob.co.kr/project/project_list.do?work_field=01';

    public function __construct() {
        parent::__construct(2, 'uneedjob');
    }

    public function crawl() {
        echo "[유니드잡] 크롤링 시작...\n";

        try {
            $html = $this->makeRequest($this->listUrl);
        } catch (Exception $e) {
            echo "[유니드잡] 요청 실패: " . $e->getMessage() . "\n";
            return 0;
        }

        $jobs = $this->parseJobs($html);
        if (empty($jobs)) {
            echo "[유니드잡] 파싱된 공고 없음\n";
            return 0;
        }

        $saved = $this->saveJobs($jobs);
        echo "[유니드잡] {$saved}개 저장 완료\n";
        return $saved;
    }

    private function parseJobs($html) {
        $xpath = $this->parseHtml($html);
        $jobs  = [];

        // onclick="goView('숫자')" 형태의 tr
        $rows = $xpath->query("//tr[@onclick and contains(@onclick, 'goView')]");
        if (!$rows || $rows->length === 0) {
            echo "[유니드잡] 행을 찾지 못했습니다.\n";
            return $jobs;
        }

        echo "[유니드잡] 발견된 행: {$rows->length}개\n";

        foreach ($rows as $row) {
            $onclick = $row->getAttribute('onclick');
            if (!preg_match("/goView\('(\d+)'\)/", $onclick, $m)) continue;

            $seq = $m[1];
            $url = $this->baseUrl . '/project/project_view.do?seq=' . $seq;

            $tds = $xpath->query("./td", $row);
            if ($tds->length < 6) continue;

            // 1번째 td: 직종
            $category = $this->cleanText($tds->item(0)->textContent);

            // 2번째 td: 제목 (div.ellipsis > title 속성 또는 텍스트)
            $titleDiv = $xpath->query(".//div[contains(@class,'ellipsis')]", $tds->item(1))->item(0);
            $title = $titleDiv
                ? ($titleDiv->getAttribute('title') ?: $this->cleanText($titleDiv->textContent))
                : $this->cleanText($tds->item(1)->textContent);

            if (empty($title)) continue;

            // 3번째 td: 기간
            $period = $tds->item(2)->getAttribute('title') ?: $this->cleanText($tds->item(2)->textContent);

            // 4번째 td: 근무형태
            $workType = $this->cleanText($tds->item(3)->textContent);

            // 5번째 td: 지역
            $location = $this->cleanText($tds->item(4)->textContent);

            // 6번째 td: 상태 (strong.on)
            $statusNode = $xpath->query(".//strong", $tds->item(5))->item(0);
            $status = $statusNode ? $this->cleanText($statusNode->textContent) : $this->cleanText($tds->item(5)->textContent);

            $jobs[] = [
                'title'    => $title,
                'url'      => $url,
                'category' => $category . ($workType ? ' / ' . $workType : ''),
                'period'   => $period,
                'status'   => $status,
                'location' => $location,
            ];

            echo "  - [{$status}] {$title} ({$period}) {$location}\n";
        }

        return $jobs;
    }
}

// 단독 실행 지원
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $crawler = new UneedJobCrawler();
    $crawler->crawl();
}
?>
