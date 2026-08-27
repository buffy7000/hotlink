<?php
header('Content-Type: application/json; charset=utf-8');

// API 키 검증
$secretFile = __DIR__ . '/../config/crawler_api_key.php';
if (!file_exists($secretFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}
require $secretFile; // defines CRAWLER_API_KEY constant

$receivedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals(CRAWLER_API_KEY, $receivedKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$body = file_get_contents('php://input');
$payload = json_decode($body, true);

// 두 가지 형식 지원:
// 1) 봉투 형식(신규): {"community_id":1,"status":"success"|"error","error_message":"...","posts":[...]}
// 2) 평면 배열(기존): [{"community_id":1, ...}, ...]  — posts에 community_id가 각자 들어있음
$reportedFailure = null; // ['community_id' => .., 'error_message' => ..]

if (isset($payload['posts']) && is_array($payload['posts'])) {
    $posts = $payload['posts'];
    if (($payload['status'] ?? 'success') === 'error') {
        $reportedFailure = [
            'community_id' => intval($payload['community_id'] ?? 0),
            'error_message' => $payload['error_message'] ?? '알 수 없는 오류',
        ];
    }
} elseif (is_array($payload)) {
    $posts = $payload;
} else {
    $posts = [];
}

if (empty($posts) && !$reportedFailure) {
    echo json_encode(['success' => true, 'saved' => 0, 'message' => '저장할 데이터 없음']);
    exit;
}

function calc_rank_score($post) {
    $commentsScore = intval($post['comments_count'] ?? 0) * 3;
    $likesScore    = intval($post['likes_count'] ?? 0) * 2;
    $viewsScore    = intval($post['views_count'] ?? 0) * 0.01;

    $createdAt = $post['created_at'] ?? date('Y-m-d H:i:s');
    $now       = new DateTime();
    $created   = new DateTime($createdAt);
    $diff      = $now->diff($created);
    $hoursOld  = ($diff->days * 24) + $diff->h;
    $timeWeight = pow(0.8, $hoursOld / 24);

    return ($commentsScore + $likesScore + $viewsScore) * $timeWeight;
}

try {
    $db = new Database();

    $sql = "INSERT INTO posts
            (community_id, title, url, thumbnail_url, author, comments_count, views_count, likes_count, created_at, rank_score)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                thumbnail_url = VALUES(thumbnail_url),
                comments_count = VALUES(comments_count),
                views_count = VALUES(views_count),
                likes_count = VALUES(likes_count),
                rank_score = VALUES(rank_score)";

    $saved = 0;
    $communityIds = [];
    foreach ($posts as $post) {
        if (empty($post['title']) || empty($post['url'])) continue;

        try {
            $communityId = intval($post['community_id'] ?? 0);
            if ($communityId <= 0) continue;

            $db->query($sql, [
                $communityId,
                $post['title'],
                $post['url'],
                $post['thumbnail_url'] ?? null,
                $post['author'] ?? '',
                intval($post['comments_count'] ?? 0),
                intval($post['views_count'] ?? 0),
                intval($post['likes_count'] ?? 0),
                $post['created_at'] ?? date('Y-m-d H:i:s'),
                calc_rank_score($post),
            ]);
            $saved++;
            $communityIds[$communityId] = true;
        } catch (Exception $e) {
            continue;
        }
    }

    foreach (array_keys($communityIds) as $cid) {
        $db->query(
            "INSERT INTO crawl_logs (community_id, status, posts_count, error_message) VALUES (?, 'success', ?, NULL)",
            [$cid, $saved]
        );
    }

    // 크롤 자체가 실패했다고 보고된 경우, 게시글 0개여도 반드시 실패 로그를 남긴다.
    if ($reportedFailure && $reportedFailure['community_id'] > 0) {
        $db->query(
            "INSERT INTO crawl_logs (community_id, status, posts_count, error_message) VALUES (?, 'error', 0, ?)",
            [$reportedFailure['community_id'], $reportedFailure['error_message']]
        );
    }

    echo json_encode(['success' => true, 'saved' => $saved]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
