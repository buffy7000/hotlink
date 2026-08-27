<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../status_lib.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $sites = array_values(crawler_compute_status($db));

    echo json_encode([
        'success'   => true,
        'checkedAt' => date('Y-m-d H:i:s'),
        'sites'     => $sites,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
