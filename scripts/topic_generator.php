<?php
/**
 * 토픽 페이지 생성 핵심 로직 (공용 라이브러리).
 *
 * generate_topic.php(단건)와 regenerate_all.php(일괄) 둘 다 여기서 가져다 쓴다.
 * 템플릿 구조/순서를 바꾸고 싶으면 topic_template.html만 고치면 되고,
 * 스타일/동작을 바꾸고 싶으면 topic/assets/topic.css, topic.js만 고치면 된다.
 * (둘 다 이미 생성된 페이지에도 재생성 없이 즉시 반영됨)
 */

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function topic_community_tag($communityId, $meta) {
    $info = $meta[$communityId] ?? ['name' => '기타', 'color' => '#94a3b8'];
    return '<span class="community-tag" style="background:' . h($info['color']) . '">' . h($info['name']) . '</span>';
}

function topic_fmt_datetime($datetime) {
    return date('n월 j일 H:i', strtotime($datetime));
}

function topic_safe_keyword($keyword) {
    return str_replace(['/', '\\', '..', "\0"], '', trim($keyword));
}

/**
 * 키워드 하나에 대한 토픽 페이지를 생성한다.
 *
 * @return array{keyword:string,postCount:int,communityCount:int,isTrending:bool,outPath:string,url:string}|null
 *         관련 글이 없으면 null (페이지를 생성하지 않음)
 */
function generate_topic_page(Database $db, array $communityMeta, string $keyword) {
    $safeKeyword = topic_safe_keyword($keyword);
    if ($safeKeyword === '') return null;

    $likeParam = '%' . $safeKeyword . '%';

    // 1. 전체 집계
    $aggStmt = $db->query(
        "SELECT COUNT(*) AS post_count, COALESCE(SUM(comments_count),0) AS comment_count,
                COUNT(DISTINCT community_id) AS community_count,
                MIN(created_at) AS min_date, MAX(created_at) AS max_date
         FROM posts WHERE title LIKE ?",
        [$likeParam]
    );
    $agg = $aggStmt->fetch(PDO::FETCH_ASSOC);
    $postCount = intval($agg['post_count']);
    if ($postCount === 0) return null;

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

    // 3. 전체 매칭 글 (커뮤니티별/타임라인 집계용, 최대 1000개까지)
    $postsStmt = $db->query(
        "SELECT id, community_id, title, url, views_count, comments_count, created_at
         FROM posts WHERE title LIKE ? ORDER BY created_at DESC LIMIT 1000",
        [$likeParam]
    );
    $allPosts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

    // ── 커뮤니티별 인기글 (전체 노출 + 더보기) ───
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
            . '<div class="community-card-head">' . topic_community_tag($cid, $communityMeta)
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

    // ── 과거 이슈 타임라인 ──────────────────────────────
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
        $restPosts = array_slice($bucketPosts, 1);

        $isCurrent = ($key === $currentKey);
        $timelineItems .= '<div class="timeline-item toggle-container' . ($isCurrent ? ' current' : '') . '">'
            . '<div class="timeline-date">' . h($labelFn($key)) . ($isCurrent ? ' · 현재' : '') . '</div>'
            . '<div class="timeline-title"><a href="' . h($topPost['url']) . '" rel="nofollow">' . h($topPost['title']) . '</a></div>'
            . '<div class="timeline-count">관련 글 ' . count($bucketPosts) . '개</div>';

        if (!empty($restPosts)) {
            $timelineItems .= '<ul class="timeline-posts">';
            foreach ($restPosts as $p) {
                $timelineItems .= '<li class="extra-post" style="display:none">'
                    . topic_community_tag($p['community_id'], $communityMeta)
                    . '<a href="' . h($p['url']) . '" rel="nofollow">' . h($p['title']) . '</a></li>';
            }
            $timelineItems .= '</ul>'
                . '<button type="button" class="more-btn" onclick="topicToggleMore(this)">더보기 (' . count($restPosts) . '개 글 보기)</button>';
        }

        $timelineItems .= '</div>';
    }

    // 급상승 중인 토픽 바는 이제 정적으로 굽지 않는다. topic.js가 매 방문마다
    // /api/trending_topics.php를 호출해서 채운다 (여러 페이지가 항상 같은 최신
    // 상태를 보게 하기 위함 - 재생성 없이도 실시간 반영됨).

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
        '{{LAST_UPDATED}}'             => topic_fmt_datetime($agg['max_date']),
        '{{COMMUNITY_SUMMARY_ROWS}}'   => $communityRows,
        '{{TIMELINE_ITEMS}}'           => $timelineItems,
        '{{GENERATED_AT}}'             => date('Y-m-d H:i:s'),
    ];

    $html = strtr($template, $replacements);

    $outDir = __DIR__ . '/../topic';
    if (!is_dir($outDir)) mkdir($outDir, 0755, true);
    $outPath = $outDir . '/' . $safeKeyword . '.html';
    file_put_contents($outPath, $html);

    return [
        'keyword'         => $safeKeyword,
        'postCount'       => $postCount,
        'communityCount'  => intval($agg['community_count']),
        'isTrending'      => $isTrending,
        'outPath'         => $outPath,
        'url'             => $canonicalUrl,
    ];
}
