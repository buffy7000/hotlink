<?php
require_once __DIR__ . '/BaseJobCrawler.php';

class OkkyCrawler extends BaseJobCrawler {
    private $baseUrl    = 'https://jobs.okky.kr';
    // 포지션그룹[2] = 기획/디자인
    private $listUrl    = 'https://jobs.okky.kr/contract?positionGroup%5B0%5D=2';

    public function __construct() {
        parent::__construct(3, 'okky');
    }

    public function crawl() {
        echo "[OKKY] 크롤링 시작...\n";

        try {
            $html = $this->makeRequest($this->listUrl);
        } catch (Exception $e) {
            echo "[OKKY] 요청 실패: " . $e->getMessage() . "\n";
            return 0;
        }

        // OKKY jobs는 Next.js SSR로 렌더되므로 HTML에서 바로 파싱 가능
        // 단, 클라이언트 전용 렌더링이면 0개가 나올 수 있음 → 아래에서 JSON 폴백 시도
        $jobs = $this->parseJobsFromHtml($html);

        if (empty($jobs)) {
            echo "[OKKY] HTML 파싱 결과 없음. __NEXT_DATA__ JSON에서 시도...\n";
            $jobs = $this->parseJobsFromNextData($html);
        }

        if (empty($jobs)) {
            echo "[OKKY] 파싱된 공고 없음 (SPA 렌더링 가능성 있음)\n";
            return 0;
        }

        $saved = $this->saveJobs($jobs);
        echo "[OKKY] {$saved}개 저장 완료\n";
        return $saved;
    }

    private function parseJobsFromHtml($html) {
        $xpath = $this->parseHtml($html);
        $jobs  = [];

        // /recruits/숫자 링크 카드
        $cards = $xpath->query("//a[starts-with(@href, '/recruits/')]");
        if (!$cards || $cards->length === 0) {
            return $jobs;
        }

        echo "[OKKY] 발견된 카드: {$cards->length}개\n";

        $seen = [];
        foreach ($cards as $card) {
            $href = $card->getAttribute('href');
            if (!preg_match('#^/recruits/(\d+)$#', $href, $m)) continue;

            $url = $this->baseUrl . $href;
            if (isset($seen[$url])) continue;
            $seen[$url] = true;

            // 제목
            $titleNode = $xpath->query(".//h2[contains(@class,'line-clamp')]", $card)->item(0);
            if (!$titleNode) continue;
            $title = $this->cleanText($titleNode->textContent);
            if (empty($title)) continue;

            // 마감일 (span.bg-gray-500/70 → "마감 7.16(목)")
            $deadlineNode = $xpath->query(".//span[contains(@class,'bg-gray-500')]", $card)->item(0);
            $deadline = $deadlineNode ? $this->cleanText($deadlineNode->textContent) : null;

            // text-sm text-gray-500 span 들 (예산, 연차)
            $smallSpans = $xpath->query(".//span[contains(@class,'text-sm') and contains(@class,'text-gray-500')]", $card);
            $budget     = null;
            $experience = null;
            foreach ($smallSpans as $span) {
                $t = $this->cleanText($span->textContent);
                if (strpos($t, '만원') !== false || strpos($t, '원') !== false) {
                    $budget = $t;
                } elseif (strpos($t, '년차') !== false || strpos($t, '신입') !== false || strpos($t, '경력') !== false) {
                    $experience = $t;
                }
            }

            // 지역 (small.text-gray-600)
            $locationNode = $xpath->query(".//small[contains(@class,'text-gray-600')]", $card)->item(0);
            $location = $locationNode ? $this->cleanText($locationNode->textContent) : null;

            $jobs[] = [
                'title'      => $title,
                'url'        => $url,
                'deadline'   => $deadline,
                'budget'     => $budget,
                'experience' => $experience,
                'location'   => $location,
                'status'     => '모집중',
            ];

            echo "  - {$title}" . ($deadline ? " [{$deadline}]" : '') . ($budget ? " {$budget}" : '') . "\n";
        }

        return $jobs;
    }

    private function parseJobsFromNextData($html) {
        // Next.js __NEXT_DATA__ JSON에서 공고 목록 추출
        if (!preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $m)) {
            return [];
        }

        $json = json_decode($m[1], true);
        if (!$json) return [];

        // 페이지 데이터 경로: props.pageProps.dehydratedState.queries[*].state.data.pages[*].list
        $queries = $json['props']['pageProps']['dehydratedState']['queries'] ?? [];
        $jobs = [];
        $seen = [];

        foreach ($queries as $query) {
            $pages = $query['state']['data']['pages'] ?? [];
            foreach ($pages as $page) {
                $items = $page['list'] ?? $page['content'] ?? $page['data'] ?? [];
                foreach ($items as $item) {
                    $id    = $item['id'] ?? $item['recruitId'] ?? null;
                    $title = $item['title'] ?? $item['projectName'] ?? null;
                    if (!$id || !$title) continue;

                    $url = $this->baseUrl . '/recruits/' . $id;
                    if (isset($seen[$url])) continue;
                    $seen[$url] = true;

                    $jobs[] = [
                        'title'      => $title,
                        'url'        => $url,
                        'deadline'   => $item['deadline'] ?? $item['endDate'] ?? null,
                        'budget'     => $item['pay'] ?? $item['budget'] ?? $item['salary'] ?? null,
                        'experience' => $item['career'] ?? $item['experience'] ?? null,
                        'location'   => $item['location'] ?? $item['area'] ?? null,
                        'status'     => $item['status'] ?? '모집중',
                    ];

                    echo "  - (JSON) {$title}\n";
                }
            }
        }

        return $jobs;
    }
}

// 단독 실행 지원
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $crawler = new OkkyCrawler();
    $crawler->crawl();
}
?>
