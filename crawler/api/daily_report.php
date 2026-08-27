<?php
header('Content-Type: application/json; charset=utf-8');

$secretFile = __DIR__ . '/../config/crawler_api_key.php';
if (!file_exists($secretFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}
require $secretFile;

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
require $webhookFile;

function fmt_elapsed($minutes) {
    if ($minutes === null) return '기록 없음';
    if ($minutes < 60) return $minutes . '분 전';
    $hours = intdiv($minutes, 60);
    if ($hours < 48) return $hours . '시간 전';
    return intdiv($hours, 24) . '일 전';
}

try {
    $db = new Database();
    $sites = crawler_compute_status($db);

    $counts = ['ok' => 0, 'warning' => 0, 'error' => 0];
    $problemLines = [];
    foreach ($sites as $site) {
        $counts[$site['status']]++;
        if ($site['status'] !== 'ok') {
            $icon = $site['status'] === 'error' ? '🔴' : '🟡';
            $lastSeen = $site['last_post_at'] ? "{$site['last_post_at']} (" . fmt_elapsed($site['minutes_since']) . ')' : '기록 없음';
            $problemLines[] = "{$icon} {$site['label']} — 마지막 게시글 {$lastSeen}";
        }
    }

    $today = date('Y-m-d');
    $text = "📊 *hotlink.kr 크롤러 일일 리포트* — {$today}\n\n";
    $text .= "🟢 정상 {$counts['ok']} · 🟡 지연 {$counts['warning']} · 🔴 장애 {$counts['error']}\n";

    if (!empty($problemLines)) {
        $text .= "\n*이상 있는 사이트*\n" . implode("\n", $problemLines) . "\n";
    }

    $text .= "\n전체 현황: https://hotlink.kr/status.php";

    $sent = crawler_send_slack(SLACK_WEBHOOK_URL, $text);

    echo json_encode(['success' => true, 'sent' => $sent, 'counts' => $counts], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
