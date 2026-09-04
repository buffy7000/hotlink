<?php
abstract class BaseJobCrawler {
    protected $pdo;
    protected $siteId;
    protected $siteCode;

    protected $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0'
    ];

    public function __construct($siteId, $siteCode) {
        $this->siteId   = $siteId;
        $this->siteCode = $siteCode;

        try {
            $this->pdo = new PDO(
                'mysql:host=localhost;dbname=pricetag_job;charset=utf8mb4',
                'pricetag_job',
                '***REMOVED***',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            die("DB 연결 실패: " . $e->getMessage());
        }
    }

    abstract public function crawl();

    protected function makeRequest($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => $this->userAgents[array_rand($this->userAgents)],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => 'gzip,deflate',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.5',
                'Connection: keep-alive',
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            throw new Exception("HTTP {$httpCode} - 요청 실패: {$url}");
        }
        return $response;
    }

    protected function saveJobs(array $jobs) {
        $sql = "INSERT INTO jobs (site_id, title, url, period, status, budget, experience, deadline, location, category, agency, client)
                VALUES (:site_id, :title, :url, :period, :status, :budget, :experience, :deadline, :location, :category, :agency, :client)
                ON DUPLICATE KEY UPDATE
                    title      = VALUES(title),
                    period     = VALUES(period),
                    status     = VALUES(status),
                    budget     = VALUES(budget),
                    experience = VALUES(experience),
                    deadline   = VALUES(deadline),
                    location   = VALUES(location),
                    category   = VALUES(category),
                    agency     = VALUES(agency),
                    client     = VALUES(client),
                    crawled_at = CURRENT_TIMESTAMP";

        $saved = 0;
        foreach ($jobs as $job) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':site_id'    => $this->siteId,
                ':title'      => $job['title']      ?? null,
                ':url'        => $job['url']         ?? null,
                ':period'     => $job['period']      ?? null,
                ':status'     => $job['status']      ?? null,
                ':budget'     => $job['budget']      ?? null,
                ':experience' => $job['experience']  ?? null,
                ':deadline'   => $job['deadline']    ?? null,
                ':location'   => $job['location']    ?? null,
                ':category'   => $job['category']    ?? null,
                ':agency'     => $job['agency']      ?? null,
                ':client'     => $job['client']      ?? null,
            ]);
            $saved++;
        }
        return $saved;
    }

    // 오늘 크롤링된 공고로 저장 + 목록에서 사라진(마감) 공고는 삭제
    // 채용 사이트가 마감 공고를 목록에서 바로 제거하는 경우(OKKY 등) 사용
    protected function syncJobs(array $jobs) {
        $saved = $this->saveJobs($jobs);

        $urls = array_values(array_filter(array_map(function ($j) {
            return $j['url'] ?? null;
        }, $jobs)));

        if (empty($urls)) {
            return $saved;
        }

        $placeholders = implode(',', array_fill(0, count($urls), '?'));
        $sql = "DELETE FROM jobs WHERE site_id = ? AND url NOT IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$this->siteId], $urls));

        $deleted = $stmt->rowCount();
        if ($deleted > 0) {
            echo "[{$this->siteCode}] 마감/삭제된 공고 {$deleted}개 제거\n";
        }

        return $saved;
    }

    protected function parseHtml($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    protected function cleanText($text) {
        return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    }
}
?>
