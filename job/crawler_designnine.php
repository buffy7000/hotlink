<?php
require_once __DIR__ . '/BaseJobCrawler.php';

class DesignNineCrawler extends BaseJobCrawler {
    private $baseUrl = 'https://www.designnine.co.kr:40000';
    private $listUrl = 'https://www.designnine.co.kr:40000/project/project_list1.html?mode1=search&search_gubun=103&search_part=112';

    public function __construct() {
        parent::__construct(1, 'designnine');
    }

    public function crawl() {
        echo "[디자인그룹나인] 크롤링 시작...\n";

        try {
            $html = $this->makeRequest($this->listUrl);
        } catch (Exception $e) {
            echo "[디자인그룹나인] 요청 실패: " . $e->getMessage() . "\n";
            return 0;
        }

        $jobs = $this->parseJobs($html);
        if (empty($jobs)) {
            echo "[디자인그룹나인] 파싱된 공고 없음\n";
            return 0;
        }

        $saved = $this->syncJobs($jobs);
        echo "[디자인그룹나인] {$saved}개 저장 완료\n";
        return $saved;
    }

    private function parseJobs($html) {
        $xpath = $this->parseHtml($html);
        $jobs  = [];

        // 번호/직종/프로젝트명/기간/상태 5개 td.text00 보유한 tr
        $rows = $xpath->query("//tr[count(.//td[@class='text00']) >= 5]");
        if (!$rows || $rows->length === 0) {
            echo "[디자인그룹나인] 행을 찾지 못했습니다.\n";
            return $jobs;
        }

        echo "[디자인그룹나인] 발견된 행: {$rows->length}개\n";

        foreach ($rows as $row) {
            $tds = $xpath->query("./td[@class='text00']", $row);
            if ($tds->length < 5) continue;

            // 3번째 td: 프로젝트명 + URL
            $linkNode = $xpath->query(".//a", $tds->item(2))->item(0);
            if (!$linkNode) continue;

            $title = $linkNode->getAttribute('title') ?: $this->cleanText($linkNode->textContent);
            $href  = $linkNode->getAttribute('href');
            $url   = $href ? $this->baseUrl . $href : null;

            if (empty($title) || !$url) continue;

            // URL 중복 제거 (num= 파라미터만 유지)
            if (preg_match('/num=(\d+)/', $url, $m)) {
                $url = $this->baseUrl . '/project/project_view2.html?num=' . $m[1];
            }

            $category = $this->cleanText($tds->item(1)->textContent);
            $period   = $this->cleanText($tds->item(3)->textContent);
            $status   = $this->cleanText($tds->item(4)->textContent);

            $jobs[] = [
                'title'    => $title,
                'url'      => $url,
                'category' => $category,
                'period'   => $period,
                'status'   => $status,
            ];

            echo "  - [{$status}] {$title} ({$period})\n";
        }

        return $jobs;
    }
}

// 단독 실행 지원
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $crawler = new DesignNineCrawler();
    $crawler->crawl();
}
?>
