<?php
/**
 * 토픽 페이지 생성기
 *
 * 사용법:
 *   CLI: php generate_topic.php "박수홍"
 *   HTTP: /scripts/generate_topic.php?keyword=박수홍&key=<CRAWLER_API_KEY>
 *
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

// 파일명/URL에 쓰기 위험한 문자 제거 (경로 이탈 방지)
$safeKeyword = str_replace(['/', '\\', '..', "\0"], '', $keyword);
if ($safeKeyword === '') {
    fwrite(STDERR, "유효하지 않은 키워드입니다.\n");
    exit(1);
}

require_once __DIR__ . '/../config/database.php';
$communityMeta = require __DIR__ . '/community_meta.php';

echo "=== 토픽 페이지 생성: {$safeKeyword} ===\n";

$db = new Database();
$likeParam = '%' . $safeKeyword . '%';

// 1. 전체 집계 (COUNT/SUM은 별도 쿼리로 - 아래 fetch 개수 제한과 무관하게 정확히)
$aggStmt = $db->query(
    "SELECT COUNT(*) AS post_count, COALESCE(SUM(comments_count),0) AS comment_count,
            COUNT(DISTINCT community_id) AS community_count,
            MIN(created_at) AS min_date, MAX(created_at) AS max_date
     FROM posts WHERE title LIKE ?",
    [$likeParam]
);
$agg = $aggStmt->fetch(PDO::FETCH_ASSOC);
$postCount = intval($agg['post_count']);

if ($postCount === 0) {
    echo "관련 게시글이 없습니다. 빈 페이지를 생성하지 않고 종료합니다.\n";
    exit(0);
}

// 2. 최근 24시간 글 수 vs 직전 7일 일평균 -> 급상승 여부
$trendStmt = $db->query(
    "SELECT
        SUM(CASE WHEN created_at >= NOW() - INTERVAL 1 DAY THEN 1 ELSE 0 END) AS last24h,
        SUM(CASE WHEN created_at < NOW() - INTERVAL 1 DAY AND created_at >= NOW() - INTERVAL 8 DAY THEN 1 ELSE 0 END) AS prev7d
     FROM posts WHERE title LIKE ?",
    [$likeParam]
);
$trend = $trendStmt->fetch(PDO::FETCH_ASSOC);
$last24h = intval($trend['last24h']);
$prev7dAvg = intval($trend['prev7d']) / 7;
$isTrending = $last24h >= 2 && $last24h > $prev7dAvg * 1.5;

// 3. 전체 매칭 글 (커뮤니티별 집계용으로 최대 1000개까지)
$postsStmt = $db->query(
    "SELECT id, community_id, title, url, views_count, comments_count, created_at
     FROM posts WHERE title LIKE ? ORDER BY created_at DESC LIMIT 1000",
    [$likeParam]
);
$allPosts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function communityTag($communityId, $meta) {
    $info = $meta[$communityId] ?? ['name' => '기타', 'color' => '#94a3b8'];
    return '<span class="community-tag" style="background:' . h($info['color']) . '">' . h($info['name']) . '</span>';
}

function fmtDateTime($datetime) {
    return date('n월 j일 H:i', strtotime($datetime));
}

// ── 섹션: 커뮤니티별 인기글 (매칭된 모든 커뮤니티, 조회수순 전체 노출 + 더보기) ───
$byCommunity = [];
foreach ($allPosts as $p) {
    $cid = $p['community_id'];
    if (!isset($byCommunity[$cid])) {
        $byCommunity[$cid] = ['posts' => [], 'comment_sum' => 0];
    }
    $byCommunity[$cid]['posts'][] = $p;
    $byCommunity[$cid]['comment_sum'] += intval($p['comments_count']);
}
uasort($byCommunity, function ($a, $b) { return count($b['posts']) - count($a['posts']); });

$visibleCount = 5;
$communityRows = '';
foreach ($byCommunity as $cid => $data) {
    $topByViews = $data['posts'];
    usort($topByViews, function ($a, $b) { return $b['views_count'] - $a['views_count']; });
    $total = count($topByViews);

    $communityRows .= '<div class="community-card toggle-container">'
        . '<div class="community-card-head">' . communityTag($cid, $communityMeta)
        . '<span class="stats">글 ' . $total . '개 · 댓글 ' . number_format($data['comment_sum']) . '개</span>'
        . '</div><ul>';
    foreach ($topByViews as $i => $p) {
        $hiddenClass = $i >= $visibleCount ? ' class="extra-post" style="display:none"' : '';
        $communityRows .= '<li' . $hiddenClass . '><a href="' . h($p['url']) . '" rel="nofollow">' . h($p['title']) . '</a></li>';
    }
    $communityRows .= '</ul>';
    if ($total > $visibleCount) {
        $communityRows .= '<button type="button" class="more-btn" onclick="topicToggleMore(this)">더보기 (' . ($total - $visibleCount) . '개 더)</button>';
    }
    $communityRows .= '</div>';
}

// ── 섹션: 과거 이슈 타임라인 ──────────────────────────────
$minDate = strtotime($agg['min_date']);
$maxDate = strtotime($agg['max_date']);
$spanDays = max(1, ($maxDate - $minDate) / 86400);

if ($spanDays >= 365) {
    $bucketFn = function ($ts) { return date('Y', $ts); };
    $labelFn = function ($key) { return $key . '년'; };
} elseif ($spanDays >= 60) {
    $bucketFn = function ($ts) { return date('Y-m', $ts); };
    $labelFn = function ($key) { return date('Y년 n월', strtotime($key . '-01')); };
} else {
    $bucketFn = function ($ts) { return date('o-\WW', $ts); };
    $labelFn = function ($key) {
        [$year, $week] = explode('-W', $key);
        $ts = strtotime($year . 'W' . str_pad($week, 2, '0', STR_PAD_LEFT));
        return date('n월 j일', $ts) . ' 주';
    };
}

$buckets = [];
foreach ($allPosts as $p) {
    $ts = strtotime($p['created_at']);
    $key = $bucketFn($ts);
    if (!isset($buckets[$key])) {
        $buckets[$key] = ['posts' => [], 'sort_ts' => $ts];
    }
    $buckets[$key]['posts'][] = $p;
    if ($ts > $buckets[$key]['sort_ts']) $buckets[$key]['sort_ts'] = $ts;
}
uasort($buckets, function ($a, $b) { return $a['sort_ts'] <=> $b['sort_ts']; });

$timelineItems = '';
$bucketKeys = array_keys($buckets);
$currentKey = end($bucketKeys);
foreach ($buckets as $key => $data) {
    $bucketPosts = $data['posts'];
    usort($bucketPosts, function ($a, $b) { return $b['views_count'] - $a['views_count']; });
    $topPost = $bucketPosts[0];
    $restPosts = array_slice($bucketPosts, 1); // 헤드라인으로 이미 보여준 글 제외 나머지

    $isCurrent = ($key === $currentKey);
    $timelineItems .= '<div class="timeline-item toggle-container' . ($isCurrent ? ' current' : '') . '">'
        . '<div class="timeline-date">' . h($labelFn($key)) . ($isCurrent ? ' · 현재' : '') . '</div>'
        . '<div class="timeline-title">' . h($topPost['title']) . '</div>'
        . '<div class="timeline-count">관련 글 ' . count($bucketPosts) . '개</div>';

    if (!empty($restPosts)) {
        $timelineItems .= '<ul class="timeline-posts">';
        foreach ($restPosts as $p) {
            $timelineItems .= '<li class="extra-post" style="display:none">'
                . communityTag($p['community_id'], $communityMeta)
                . '<a href="' . h($p['url']) . '" rel="nofollow">' . h($p['title']) . '</a></li>';
        }
        $timelineItems .= '</ul>'
            . '<button type="button" class="more-btn" onclick="topicToggleMore(this)">더보기 (' . count($restPosts) . '개 글 보기)</button>';
    }

    $timelineItems .= '</div>';
}

// ── 섹션: 급상승 중인 토픽 (추적 키워드 목록이 아직 없어 비워둠) ──
$relatedTopicsSection = '<div class="panel"><div class="empty-state">아직 준비 중이에요. 곧 다른 급상승 토픽을 함께 보여드릴게요.</div></div>';

// ── 템플릿 조립 ─────────────────────────────────────────────
$template = file_get_contents(__DIR__ . '/topic_template.html');

$title = "{$safeKeyword} 커뮤니티 반응 모음 | 클리앙·오유·뽐뿌 - 핫링크";
$description = "{$safeKeyword} 관련 커뮤니티 반응을 한눈에. 클리앙, 오늘의유머, 뽐뿌 등 실시간 모음.";
$canonicalUrl = "https://hotlink.kr/topic/" . rawurlencode($safeKeyword);

$trendingBadge = $isTrending ? '<span class="badge trending">🔥 급상승</span>' : '';

$replacements = [
    '{{TITLE}}'                    => h($title),
    '{{META_DESCRIPTION}}'         => h($description),
    '{{OG_TITLE}}'                 => h($title),
    '{{OG_DESCRIPTION}}'           => h($description),
    '{{CANONICAL_URL}}'            => h($canonicalUrl),
    '{{KEYWORD}}'                  => h($safeKeyword),
    '{{TRENDING_BADGE}}'           => $trendingBadge,
    '{{COMMUNITY_COUNT}}'          => intval($agg['community_count']),
    '{{POST_COUNT}}'               => number_format($postCount),
    '{{COMMENT_COUNT}}'            => number_format(intval($agg['comment_count'])),
    '{{LAST_UPDATED}}'             => fmtDateTime($agg['max_date']),
    '{{COMMUNITY_SUMMARY_ROWS}}'   => $communityRows,
    '{{TIMELINE_ITEMS}}'           => $timelineItems,
    '{{RELATED_TOPICS_SECTION}}'   => $relatedTopicsSection,
    '{{GENERATED_AT}}'             => date('Y-m-d H:i:s'),
];

$html = strtr($template, $replacements);

$outDir = __DIR__ . '/../topic';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);
$outPath = $outDir . '/' . $safeKeyword . '.html';
file_put_contents($outPath, $html);

echo "글 {$postCount}개, 커뮤니티 {$agg['community_count']}개, 급상승: " . ($isTrending ? 'Y' : 'N') . "\n";
echo "생성 완료: {$outPath}\n";
echo "URL: {$canonicalUrl}\n";
