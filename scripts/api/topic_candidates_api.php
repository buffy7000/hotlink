<?php
header('Content-Type: application/json; charset=utf-8');

$secretFile = __DIR__ . '/../../crawler/config/crawler_api_key.php';
if (!file_exists($secretFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}
require $secretFile;

$receivedKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
if (!hash_equals(CRAWLER_API_KEY, $receivedKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../topic_discovery_lib.php';
require_once __DIR__ . '/../topic_generator.php';

$db = new Database();
topic_ensure_candidates_table($db);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->query(
            "SELECT keyword, post_count, community_count, score, first_seen_at
             FROM topic_candidates WHERE status = 'candidate' AND post_count >= 20
             ORDER BY score DESC LIMIT 50",
            []
        );
        echo json_encode(['success' => true, 'candidates' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $body['action'] ?? ($_POST['action'] ?? '');
        $keyword = trim($body['keyword'] ?? ($_POST['keyword'] ?? ''));

        if ($keyword === '') {
            echo json_encode(['success' => false, 'error' => 'keyword required']);
            exit;
        }

        if ($action === 'reject') {
            $db->query("UPDATE topic_candidates SET status = 'rejected' WHERE keyword = ?", [$keyword]);
            echo json_encode(['success' => true, 'action' => 'rejected', 'keyword' => $keyword]);
            exit;
        }

        if ($action === 'approve') {
            $communityMeta = require __DIR__ . '/../community_meta.php';
            $result = generate_topic_page($db, $communityMeta, $keyword);

            if ($result === null) {
                echo json_encode(['success' => false, 'error' => '관련 게시글이 없어 생성하지 못했습니다']);
                exit;
            }

            $db->query("UPDATE topic_candidates SET status = 'published' WHERE keyword = ?", [$keyword]);
            echo json_encode(['success' => true, 'action' => 'approved', 'result' => $result], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'unknown action']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
