<?php
// 크롤러 상태 대시보드 + 워치독이 공용으로 쓰는 로직.
// 사이트를 추가/조정할 땐 이 파일의 crawler_site_map()만 고치면 된다.

function crawler_site_map() {
    return [
        1  => ['name' => 'ppomppu',   'label' => '뽐뿌',      'threshold_min' => 150],
        2  => ['name' => 'clien',     'label' => '클리앙',    'threshold_min' => 360],
        3  => ['name' => 'natepann',  'label' => '네이트판',  'threshold_min' => 360],
        4  => ['name' => 'ruliweb',   'label' => '루리웹',    'threshold_min' => 360],
        5  => ['name' => 'theqoo',    'label' => '더쿠',      'threshold_min' => 360],
        6  => ['name' => 'mlbpark',   'label' => 'MLB파크',   'threshold_min' => 360],
        7  => ['name' => 'bobaedream','label' => '보배드림',  'threshold_min' => 360],
        8  => ['name' => 'humoruniv', 'label' => '웃긴대학',  'threshold_min' => 360],
        9  => ['name' => 'todayhumor','label' => '오늘의유머','threshold_min' => 360],
        10 => ['name' => 'inven',     'label' => '인벤',      'threshold_min' => 360],
        11 => ['name' => 'slrclub',   'label' => 'SLR클럽',   'threshold_min' => 360],
        12 => ['name' => 'etoland',   'label' => '이토랜드',  'threshold_min' => 360],
    ];
}

// 사이트별 현재 상태 계산: posts 테이블의 최신 글 시각을 근거로 판단한다.
// (서버 cron 크롤러 11개는 성공시에만 로그를 남기던 기존 구조라, crawl_logs보다
//  posts.created_at 최신성이 훨씬 신뢰도 높은 "살아있음" 신호다.)
function crawler_compute_status(Database $db) {
    $sites = crawler_site_map();
    $result = [];

    foreach ($sites as $id => $meta) {
        $stmt = $db->query(
            "SELECT MAX(created_at) AS last_post,
                    SUM(CASE WHEN created_at >= NOW() - INTERVAL 24 HOUR THEN 1 ELSE 0 END) AS cnt24h
             FROM posts WHERE community_id = ?",
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $lastPost = $row['last_post'] ?? null;
        $minutesSince = $lastPost ? (int) floor((time() - strtotime($lastPost)) / 60) : null;
        $threshold = $meta['threshold_min'];

        if ($minutesSince === null) {
            $status = 'error';
        } elseif ($minutesSince > $threshold) {
            $status = 'error';
        } elseif ($minutesSince > $threshold * 0.6) {
            $status = 'warning';
        } else {
            $status = 'ok';
        }

        $logStmt = $db->query(
            "SELECT status, error_message, created_at
             FROM crawl_logs WHERE community_id = ?
             ORDER BY created_at DESC LIMIT 1",
            [$id]
        );
        $lastLog = $logStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $result[$id] = [
            'community_id'      => $id,
            'name'              => $meta['name'],
            'label'             => $meta['label'],
            'status'            => $status,
            'last_post_at'      => $lastPost,
            'minutes_since'     => $minutesSince,
            'threshold_min'     => $threshold,
            'posts_24h'         => intval($row['cnt24h'] ?? 0),
            'last_log_status'   => $lastLog['status'] ?? null,
            'last_log_error'    => $lastLog['error_message'] ?? null,
            'last_log_at'       => $lastLog['created_at'] ?? null,
        ];
    }

    return $result;
}

function crawler_ensure_alert_table(Database $db) {
    $db->query(
        "CREATE TABLE IF NOT EXISTS crawler_alert_state (
            community_id INT PRIMARY KEY,
            is_down TINYINT NOT NULL DEFAULT 0,
            since_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );
}

function crawler_send_slack($webhookUrl, $text) {
    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['text' => $text], JSON_UNESCAPED_UNICODE),
    ]);
    curl_exec($ch);
    $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    return $ok;
}
