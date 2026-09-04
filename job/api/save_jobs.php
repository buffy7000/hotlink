<?php
header('Content-Type: application/json; charset=utf-8');

// API 키 검증
$secretFile = __DIR__ . '/../config/job_api_key.php';
if (!file_exists($secretFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}
require $secretFile; // defines JOB_API_KEY constant

$receivedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals(JOB_API_KEY, $receivedKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = file_get_contents('php://input');
$jobs = json_decode($body, true);

if (!is_array($jobs) || empty($jobs)) {
    echo json_encode(['success' => true, 'saved' => 0, 'message' => '저장할 데이터 없음']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=pricetag_job;charset=utf8mb4',
        'pricetag_job',
        '***REMOVED***',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = "INSERT INTO jobs (site_id, title, url, period, status, budget, experience, deadline, location, category)
            VALUES (:site_id, :title, :url, :period, :status, :budget, :experience, :deadline, :location, :category)
            ON DUPLICATE KEY UPDATE
                title      = VALUES(title),
                period     = VALUES(period),
                status     = VALUES(status),
                budget     = VALUES(budget),
                experience = VALUES(experience),
                deadline   = VALUES(deadline),
                location   = VALUES(location),
                category   = VALUES(category),
                crawled_at = CURRENT_TIMESTAMP";

    $saved = 0;
    $urlsBySite = [];
    foreach ($jobs as $job) {
        $siteId = intval($job['site_id'] ?? 1);

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':site_id'    => $siteId,
            ':title'      => $job['title']      ?? null,
            ':url'        => $job['url']         ?? null,
            ':period'     => $job['period']      ?? null,
            ':status'     => $job['status']      ?? null,
            ':budget'     => $job['budget']      ?? null,
            ':experience' => $job['experience']  ?? null,
            ':deadline'   => $job['deadline']    ?? null,
            ':location'   => $job['location']    ?? null,
            ':category'   => $job['category']    ?? null,
        ]);
        $saved++;

        if (!empty($job['url'])) {
            $urlsBySite[$siteId][] = $job['url'];
        }
    }

    // 사이트별로 이번 크롤링 결과에 없는 기존 공고는 '마감' 처리
    // (해당 사이트가 마감 공고를 목록에서 바로 제거하는 경우)
    $closed = 0;
    foreach ($urlsBySite as $siteId => $urls) {
        $placeholders = implode(',', array_fill(0, count($urls), '?'));
        $stmt = $pdo->prepare("UPDATE jobs SET status = '마감'
            WHERE site_id = ? AND status != '마감' AND url NOT IN ({$placeholders})");
        $stmt->execute(array_merge([$siteId], $urls));
        $closed += $stmt->rowCount();
    }

    echo json_encode(['success' => true, 'saved' => $saved, 'closed' => $closed]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
