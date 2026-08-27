<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$cache_file = '/tmp/hotlink_cache_trending_topics.json';
$cache_time = 300; // 5분

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    echo file_get_contents($cache_file);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// 토픽 페이지로 생성된 키워드는 topic/ 폴더를 그대로 스캔해서 얻는다
// (별도 목록 파일을 안 두는 게 sitemap.php와 같은 방식이라 관리 포인트가 하나 줄어듦)
$topicDir = __DIR__ . '/../topic';
$files = is_dir($topicDir) ? glob($topicDir . '/*.html') : [];
$keywords = array_map(function ($f) { return basename($f, '.html'); }, $files);

try {
    $db = new Database();
    $results = [];

    foreach ($keywords as $kw) {
        $like = '%' . $kw . '%';
        $stmt = $db->query(
            "SELECT
                SUM(CASE WHEN created_at >= NOW() - INTERVAL 1 DAY THEN 1 ELSE 0 END) AS last24h,
                SUM(CASE WHEN created_at < NOW() - INTERVAL 1 DAY AND created_at >= NOW() - INTERVAL 8 DAY THEN 1 ELSE 0 END) AS prev7d
             FROM posts WHERE title LIKE ?",
            [$like]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $last24h = intval($row['last24h']);
        $prev7dAvg = intval($row['prev7d']) / 7;
        $isTrending = $last24h >= 2 && $last24h > $prev7dAvg * 1.5;

        if ($isTrending) {
            $results[] = ['keyword' => $kw, 'score' => $last24h];
        }
    }

    usort($results, function ($a, $b) { return $b['score'] - $a['score']; });
    $results = array_slice($results, 0, 8);

    $topics = [];
    foreach ($results as $i => $r) {
        $topics[] = [
            'rank'    => $i + 1,
            'keyword' => $r['keyword'],
            'url'     => '/topic/' . rawurlencode($r['keyword']),
        ];
    }

    $response = json_encode(['success' => true, 'topics' => $topics], JSON_UNESCAPED_UNICODE);
    file_put_contents($cache_file, $response);
    echo $response;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
