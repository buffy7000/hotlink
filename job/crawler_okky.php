<?php
require_once __DIR__ . '/BaseJobCrawler.php';

class OkkyCrawler extends BaseJobCrawler {
    private $baseUrl = 'https://jobs.okky.kr';
    private $listUrl = 'https://jobs.okky.kr/contract?positionGroup%5B0%5D=2';

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

        $jobs = $this->parseJobsFromRSC($html);

        if (empty($jobs)) {
            echo "[OKKY] 파싱된 공고 없음\n";
            return 0;
        }

        $saved = $this->saveJobs($jobs);
        echo "[OKKY] {$saved}개 저장 완료\n";
        return $saved;
    }

    private function parseJobsFromRSC($html) {
        // __next_f.push([1, "...RSC 청크..."]) 전체 수집
        preg_match_all('/self\.__next_f\.push\(\[1,\s*"((?:[^"\\\\]|\\\\.)*)"\]\)/s', $html, $matches);

        if (empty($matches[1])) {
            echo "[OKKY] RSC 청크를 찾을 수 없음\n";
            return [];
        }

        // JSON 문자열 언이스케이프 후 연결
        $rsc = '';
        foreach ($matches[1] as $chunk) {
            $decoded = json_decode('"' . $chunk . '"');
            if ($decoded !== null) $rsc .= $decoded;
        }

        // "content":[ 위치 찾기
        $pos = strpos($rsc, '"content":[{');
        if ($pos === false) {
            echo "[OKKY] content 배열을 찾을 수 없음\n";
            return [];
        }

        // [ 위치부터 중첩 괄호 균형 맞춰 JSON 배열 추출
        $start = strpos($rsc, '[', $pos + strlen('"content":'));
        if ($start === false) return [];

        $depth = 0;
        $end   = $start;
        $len   = strlen($rsc);
        for ($i = $start; $i < $len; $i++) {
            $c = $rsc[$i];
            if ($c === '[' || $c === '{') $depth++;
            elseif ($c === ']' || $c === '}') {
                $depth--;
                if ($depth === 0) { $end = $i; break; }
            }
        }

        $contentJson = substr($rsc, $start, $end - $start + 1);
        $items = json_decode($contentJson, true);
        if (!is_array($items)) {
            echo "[OKKY] content JSON 파싱 실패\n";
            return [];
        }

        echo "[OKKY] 발견된 공고: " . count($items) . "개\n";

        $jobs = [];
        foreach ($items as $item) {
            $id    = $item['id']    ?? null;
            $title = trim($item['title'] ?? '');
            if (!$id || !$title) continue;

            $r   = $item['recruitResponse'] ?? [];
            $url = $this->baseUrl . '/recruits/' . $id;

            // 지역
            $location = implode(' ', array_filter([$r['city'] ?? null, $r['district'] ?? null])) ?: null;

            // 단가 (만원/월)
            $budget = null;
            if (!empty($r['minPay'])) {
                $budget = ($r['minPay'] === ($r['maxPay'] ?? 0))
                    ? $r['minPay'] . '만원'
                    : $r['minPay'] . '~' . ($r['maxPay'] ?? $r['minPay']) . '만원';
            }

            // 경력
            $experience = null;
            if (isset($r['minCareer'])) {
                if ($r['minCareer'] == 0) {
                    $experience = '신입 가능';
                } elseif (($r['maxCareer'] ?? 99) >= 99) {
                    $experience = $r['minCareer'] . '년차 이상';
                } else {
                    $experience = $r['minCareer'] . '~' . $r['maxCareer'] . '년차';
                }
            }

            // 마감일
            $deadline = null;
            if (!empty($r['deadline'])) {
                $deadline = '마감 ' . date('n.j', strtotime($r['deadline']));
            } elseif (!empty($r['payDateType']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['payDateType'])) {
                $deadline = '마감 ' . date('n.j', strtotime($r['payDateType']));
            }

            // 투입/기간
            $period = null;
            if (!empty($r['startDate'])) {
                $s = date('Y.m', strtotime($r['startDate']));
                if (!empty($r['workingMonth'])) {
                    $e = date('Y.m', strtotime('+' . $r['workingMonth'] . ' months', strtotime($r['startDate'])));
                    $period = "{$s} ~ {$e} ({$r['workingMonth']}개월)";
                } else {
                    $period = $s . ' 시작';
                }
            }

            // 직무
            $category = $r['dutyName'] ?? $r['positionCategoryName'] ?? null;

            $jobs[] = [
                'title'      => $title,
                'url'        => $url,
                'category'   => $category,
                'period'     => $period,
                'status'     => '접수중',
                'budget'     => $budget,
                'experience' => $experience,
                'deadline'   => $deadline,
                'location'   => $location,
            ];

            echo "  - {$title}" . ($deadline ? " [{$deadline}]" : '') . ($budget ? " {$budget}" : '') . "\n";
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
