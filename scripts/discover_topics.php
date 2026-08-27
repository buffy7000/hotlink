<?php
/**
 * 토픽 키워드 후보 자동 추출 실행기.
 *
 * 사용법:
 *   CLI: php discover_topics.php
 *   HTTP: /scripts/discover_topics.php?key=<CRAWLER_API_KEY>
 *
 * 실행할 때마다 최근 게시글을 분석해 topic_candidates 테이블을 갱신한다.
 * 여기서는 발행하지 않음 - topic-candidates.php에서 사람이 검수 후 승인.
 */

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $secretFile = __DIR__ . '/../crawler/config/crawler_api_key.php';
    if (!file_exists($secretFile)) {
        http_response_code(500);
        exit('API key not configured');
    }
    require $secretFile;
    if (!hash_equals(CRAWLER_API_KEY, $_GET['key'] ?? '')) {
        http_response_code(403);
        exit('Unauthorized');
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/topic_discovery_lib.php';

$db = new Database();
$result = topic_discover_candidates($db);

echo "=== 토픽 후보 추출 완료 ===\n";
echo "분석한 게시글: {$result['scanned']}개\n";
echo "새 후보: " . count($result['newCandidates']) . "개\n";
foreach ($result['newCandidates'] as $c) {
    echo "  + {$c['keyword']} (글 {$c['postCount']}개, 커뮤니티 {$c['communityCount']}개, 점수 {$c['score']})\n";
}
echo "갱신된 후보: " . count($result['updatedCandidates']) . "개\n";
