<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=pricetag_job;charset=utf8mb4',
        'pricetag_job',
        '***REMOVED***',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $siteFilter   = $_GET['site']   ?? 'all';
    $statusFilter = $_GET['status'] ?? 'all';

    $where  = [];
    $params = [];

    if ($siteFilter !== 'all') {
        $stmt = $pdo->prepare("SELECT id FROM sites WHERE code = ?");
        $stmt->execute([$siteFilter]);
        $row = $stmt->fetch();
        if ($row) {
            $where[]  = 'j.site_id = ?';
            $params[] = $row['id'];
        }
    }

    if ($statusFilter !== 'all') {
        $where[]  = 'j.status = ?';
        $params[] = $statusFilter;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT j.*, s.name AS site_name, s.code AS site_code, s.url AS site_url
            FROM jobs j
            JOIN sites s ON j.site_id = s.id
            {$whereClause}
            ORDER BY j.site_id ASC, j.crawled_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // 사이트별 그룹핑
    $grouped = [];
    foreach ($rows as $row) {
        $code = $row['site_code'];
        if (!isset($grouped[$code])) {
            $grouped[$code] = [
                'site_name' => $row['site_name'],
                'site_url'  => $row['site_url'],
                'jobs'      => []
            ];
        }
        $grouped[$code]['jobs'][] = [
            'id'         => intval($row['id']),
            'title'      => $row['title'],
            'url'        => $row['url'],
            'category'   => $row['category'],
            'period'     => $row['period'],
            'status'     => $row['status'],
            'budget'     => $row['budget'],
            'experience' => $row['experience'],
            'deadline'   => $row['deadline'],
            'location'   => $row['location'],
            'crawledAt'  => $row['crawled_at'],
        ];
    }

    // 사이트 목록도 함께 반환
    $sites = $pdo->query("SELECT id, code, name FROM sites ORDER BY id")->fetchAll();

    echo json_encode([
        'success' => true,
        'sites'   => $sites,
        'data'    => $grouped,
        'total'   => count($rows)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
