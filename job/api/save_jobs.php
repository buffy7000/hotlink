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
    foreach ($jobs as $job) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':site_id'    => intval($job['site_id'] ?? 1),
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
    }

    echo json_encode(['success' => true, 'saved' => $saved]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
