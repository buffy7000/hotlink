<?php
/**
 * 토픽 페이지 생성기 (단건)
 *
 * 사용법:
 *   CLI: php generate_topic.php "박수홍"
 *   HTTP: /scripts/generate_topic.php?keyword=박수홍&key=<CRAWLER_API_KEY>
 *
 * 실제 생성 로직은 topic_generator.php에 있음 (regenerate_all.php와 공유).
 * DB에 '토픽/키워드' 컬럼이 없어서, title LIKE '%키워드%' 검색으로 관련 글을 찾는다.
 * 출력: topic/{키워드}.html (정적 파일)
 */

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    $keyword = trim($argv[1] ?? '');
} else {
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
    $keyword = trim($_GET['keyword'] ?? '');
}

if ($keyword === '') {
    fwrite(STDERR, "키워드를 입력하세요. 예: php generate_topic.php \"박수홍\"\n");
    exit(1);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/topic_generator.php';
$communityMeta = require __DIR__ . '/community_meta.php';

echo "=== 토픽 페이지 생성: {$keyword} ===\n";

$db = new Database();
$result = generate_topic_page($db, $communityMeta, $keyword);

if ($result === null) {
    echo "관련 게시글이 없습니다. 빈 페이지를 생성하지 않고 종료합니다.\n";
    exit(0);
}

echo "글 {$result['postCount']}개, 커뮤니티 {$result['communityCount']}개, 급상승: " . ($result['isTrending'] ? 'Y' : 'N') . "\n";
echo "생성 완료: {$result['outPath']}\n";
echo "URL: {$result['url']}\n";
