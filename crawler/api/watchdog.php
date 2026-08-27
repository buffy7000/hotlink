<?php
header('Content-Type: application/json; charset=utf-8');

// API 키 검증 (crawler/api/save_posts.php와 동일한 키 재사용)
$secretFile = __DIR__ . '/../config/crawler_api_key.php';
if (!file_exists($secretFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}
require $secretFile; // defines CRAWLER_API_KEY

$receivedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals(CRAWLER_API_KEY, $receivedKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../status_lib.php';
require_once __DIR__ . '/../../config/database.php';

$webhookFile = __DIR__ . '/../config/slack_webhook.php';
if (!file_exists($webhookFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Slack webhook not configured']);
    exit;
}
require $webhookFile; // defines SLACK_WEBHOOK_URL

try {
    $db = new Database();
    crawler_ensure_alert_table($db);

    $sites = crawler_compute_status($db);

    $stmt = $db->query("SELECT community_id, is_down, since_at FROM crawler_alert_state");
    $prevState = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $prevState[intval($row['community_id'])] = $row;
    }

    $notified = [];

    foreach ($sites as $id => $site) {
        $isDownNow = $site['status'] === 'error';
        $wasDown = isset($prevState[$id]) && intval($prevState[$id]['is_down']) === 1;

        if ($isDownNow && !$wasDown) {
            $lastSeen = $site['last_post_at'] ? "{$site['minutes_since']}분 전 ({$site['last_post_at']})" : '기록 없음';
            $errorLine = $site['last_log_error'] ? "\n에러: {$site['last_log_error']}" : '';
            crawler_send_slack(SLACK_WEBHOOK_URL,
                "🔴 *[{$site['label']}]* 크롤링 장애 감지\n마지막 게시글: {$lastSeen}{$errorLine}\n상태 페이지: https://hotlink.kr/status.php"
            );
            $db->query(
                "INSERT INTO crawler_alert_state (community_id, is_down, since_at) VALUES (?, 1, NOW())
                 ON DUPLICATE KEY UPDATE is_down = 1, since_at = NOW()",
                [$id]
            );
            $notified[] = "{$site['label']} DOWN";

        } elseif (!$isDownNow && $wasDown) {
            $sinceAt = $prevState[$id]['since_at'] ?? null;
            $durationText = '';
            if ($sinceAt) {
                $mins = (int) floor((time() - strtotime($sinceAt)) / 60);
                $durationText = " (장애 지속: 약 " . round($mins / 60, 1) . "시간)";
            }
            crawler_send_slack(SLACK_WEBHOOK_URL,
                "🟢 *[{$site['label']}]* 크롤링 복구됨{$durationText}\n상태 페이지: https://hotlink.kr/status.php"
            );
            $db->query(
                "INSERT INTO crawler_alert_state (community_id, is_down, since_at) VALUES (?, 0, NULL)
                 ON DUPLICATE KEY UPDATE is_down = 0, since_at = NULL",
                [$id]
            );
            $notified[] = "{$site['label']} RECOVERED";
        }
    }

    echo json_encode(['success' => true, 'notified' => $notified], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
