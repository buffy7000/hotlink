<?php
require_once __DIR__ . '/../config/database.php';
$secretFile = __DIR__ . '/../crawler/config/crawler_api_key.php';
require $secretFile;
if (!hash_equals(CRAWLER_API_KEY, $_GET['key'] ?? '')) {
    http_response_code(403);
    exit('Unauthorized');
}
$db = new Database();
$db->query("TRUNCATE TABLE topic_candidates", []);
echo "truncated\n";
