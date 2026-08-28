<?php
// 임시 확인용 — 확인 후 삭제
require_once(__DIR__ . '/../deal/SimpleHotdealDB.php');
header('Content-Type: text/plain; charset=utf-8');
$db = new SimpleHotdealDB();
$row = $db->fetch("SELECT COUNT(*) as cnt, MAX(crawled_at) as last_crawled FROM hotdeals WHERE source_id = 99");
print_r($row);
$rows = $db->fetchAll("SELECT id, title, crawled_at FROM hotdeals WHERE source_id = 99 ORDER BY id DESC LIMIT 5");
print_r($rows);
