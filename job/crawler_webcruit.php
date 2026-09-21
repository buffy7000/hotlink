<?php
require_once __DIR__ . '/BaseJobCrawler.php';

class WebcruitCrawler extends BaseJobCrawler {
    private $baseUrl = 'http://webcruit.co.kr';
    // work_part[]=2(기획), progress[]=8(접수중) 필터 - 접수중인 공고만 목록에 노출됨
    private $listUrl = 'http://webcruit.co.kr/projects?offset=1&limit=100&not=YES&work_part%5B%5D=2&progress%5B%5D=8&cond=title&val=';

    public function __construct() {
        parent::__construct(5, 'webcruit');
        $this->ensureSiteRow('웹크루트', 'http://webcruit.co.kr/projects?work_part%5B%5D=2&progress%5B%5D=8');
    }

    public function crawl() {
        echo "[웹크루트] 크롤링 시작...\n";

        try {
            $html = $this->makeRequest($this->listUrl);
        } catch (Exception $e) {
            echo "[웹크루트] 요청 실패: " . $e->getMessage() . "\n";
            return 0;
        }

        $jobs = $this->parseJobs($html);
        if (empty($jobs)) {
            echo "[웹크루트] 파싱된 공고 없음\n";
            return 0;
        }

        $saved = $this->syncJobs($jobs);
        echo "[웹크루트] {$saved}개 저장 완료\n";
        return $saved;
    }

    private function parseJobs($html) {
        $xpath = $this->parseHtml($html);
        $jobs  = [];

        $rows = $xpath->query("//ul[contains(@class,'project_list')]/li");
        if (!$rows || $rows->length === 0) {
            echo "[웹크루트] 공고를 찾지 못했습니다.\n";
            return $jobs;
        }

        echo "[웹크루트] 발견된 공고: {$rows->length}개\n";

        foreach ($rows as $row) {
            $linkNode = $xpath->query(".//p[@class='title']/a", $row)->item(0);
            if (!$linkNode) continue;

            $title = $linkNode->getAttribute('title') ?: $this->cleanText($linkNode->textContent);
            $href  = trim($linkNode->getAttribute('href'));
            if (empty($title) || empty($href)) continue;

            $url = $this->baseUrl . '/' . ltrim($href, '/');

            // 분야 아이콘(circle2)의 title 속성 = 카테고리 (예: 기획)
            $category = null;
            $catNode = $xpath->query(".//div[@class='circle2']", $row)->item(0);
            if ($catNode) $category = trim($catNode->getAttribute('title')) ?: null;

            // info_item 4개: 진행상태, 근무기간, 필요경력, 근무위치 (항상 이 순서)
            $infoItems = $xpath->query(".//div[contains(@class,'project_info_box')]/div[@class='info_item']", $row);
            if ($infoItems->length < 4) continue;

            // 진행상태는 info_item 바로 아래 span (조회수 div의 span과 구분하기 위해 직계 자식만 조회)
            $statusSpan = $xpath->query("./span", $infoItems->item(0))->item(0);
            $status = $statusSpan ? $this->cleanText($statusSpan->textContent) : '';

            $period     = $this->extractInfoValue($infoItems->item(1));
            $experience = $this->extractInfoValue($infoItems->item(2));
            $location   = $this->extractInfoValue($infoItems->item(3));

            $jobs[] = [
                'title'      => $title,
                'url'        => $url,
                'category'   => $category,
                'period'     => $period ?: null,
                'status'     => $status ?: '접수중',
                'experience' => $experience ?: null,
                'location'   => $location ?: null,
            ];

            echo "  - [{$status}] {$title}" . ($location ? " {$location}" : '') . "\n";
        }

        return $jobs;
    }

    // "라벨 : 값" 형태의 info_item에서 라벨을 떼고 값만 추출
    private function extractInfoValue($infoItem) {
        $text = $this->cleanText($infoItem->textContent);
        return trim(preg_replace('/^[^:]*:\s*/u', '', $text));
    }
}

// 단독 실행 지원
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $crawler = new WebcruitCrawler();
    $crawler->crawl();
}
?>
