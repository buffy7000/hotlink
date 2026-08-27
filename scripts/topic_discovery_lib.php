<?php
/**
 * 토픽 키워드 후보 자동 추출 (1단계: 추천만, 발행은 사람이 승인).
 *
 * DB에 형태소 분석기가 없어서(PHP 한계) 정교한 개체명 인식 대신,
 * 제목을 공백 기준 어절로 쪼개고 조사를 제거한 뒤, 여러 글/여러
 * 커뮤니티에서 반복 등장하는 어절만 후보로 남기는 빈도 기반 방식을 쓴다.
 * 사람 이름/고유명사는 대부분 단독 어절로 등장하기 때문에 꽤 잘 걸러진다.
 */

// 조사: 어절 끝에서 제거 (긴 것부터 매칭해야 함)
const TOPIC_JOSA_LIST = [
    '으로써', '으로서', '이라고', '이라는', '한테서', '에게서',
    '이라며', '라면서', '이라도', '입니다', '였다고', '했다고',
    '까지는', '에서는', '에서도', '이지만', '하지만',
    '으로', '에서', '에게', '한테', '이라', '라고', '라는',
    '이나', '이든', '든지', '보다', '처럼', '마저', '조차',
    '이며', '이고', '이랑', '이야', '이여', '이랬', '였다', '했다',
    '은', '는', '이', '가', '을', '를', '에', '와', '과', '도',
    '만', '의', '로', '께', '나', '야', '여',
];

// 그 자체로는 '토픽'이 될 수 없는 흔한 일반어 (조사 제거 후 이 목록에 있으면 제외)
const TOPIC_STOPWORDS = [
    '오늘', '어제', '내일', '지금', '진짜', '정말', '완전', '그냥', '근데',
    '그리고', '하지만', '이거', '저거', '그거', '이것', '저것', '뭔가',
    '사람', '사진', '영상', '후기', '이슈', '근황', '사건', '논란', '문제',
    '이유', '방법', '정도', '결국', '역시', '실화', '레전드', '대박',
    '우리', '저는', '제가', '너무', '아니', '대체', '어떻게', '무엇',
    '어디', '누구', '언제', '이런', '저런', '그런', '같은', '하는',
    '되는', '있는', '없는', '한테', '무슨', '이번', '올해', '작년',
    '요즘', '최근', '드디어', '결국', '역대급', '실시간', '속보',
    '단독', '충격', '경악', '소름', '레알', '개인적', '공식',
];

function topic_ensure_candidates_table(Database $db) {
    $db->query(
        "CREATE TABLE IF NOT EXISTS topic_candidates (
            keyword VARCHAR(100) PRIMARY KEY,
            post_count INT NOT NULL DEFAULT 0,
            community_count INT NOT NULL DEFAULT 0,
            score INT NOT NULL DEFAULT 0,
            status ENUM('candidate','published','rejected') NOT NULL DEFAULT 'candidate',
            first_seen_at DATETIME NOT NULL,
            last_checked_at DATETIME NOT NULL
        )"
    );
}

// 제목 하나를 후보 어절 배열로 분해
function topic_tokenize_title($title) {
    $title = preg_replace('/[\[\]\(\)"\'“”‘’\.\,\!\?…:;\|\/\\\\]+/u', ' ', $title);
    $words = preg_split('/\s+/u', trim($title));
    $tokens = [];

    foreach ($words as $w) {
        if ($w === '') continue;

        // 조사 제거 (긴 것부터 시도)
        foreach (TOPIC_JOSA_LIST as $josa) {
            $len = mb_strlen($josa);
            if (mb_substr($w, -$len) === $josa && mb_strlen($w) - $len >= 2) {
                $w = mb_substr($w, 0, mb_strlen($w) - $len);
                break;
            }
        }

        // 완성형 한글 음절이 없으면 제외 (영어/숫자/이모지 + "ㅋㅋ","ㄷㄷ","ㅠㅠ" 같은
        // 자음/모음 낱자 반복도 \p{Hangul}엔 걸리지만 실제 음절은 아니라서 별도 배제)
        if (!preg_match('/[\x{AC00}-\x{D7A3}]/u', $w)) continue;

        // 숫자가 절반 이상이면("1번","300번" 등 카운트성 단어) 제외
        $digitCount = preg_match_all('/[0-9]/u', $w);
        if ($digitCount > 0 && $digitCount >= mb_strlen($w) / 2) continue;

        $len = mb_strlen($w);
        if ($len < 2 || $len > 8) continue;
        if (in_array($w, TOPIC_STOPWORDS, true)) continue;

        $tokens[] = $w;
    }

    return array_unique($tokens);
}

/**
 * 최근 게시글 제목을 분석해 후보 어절을 집계하고 topic_candidates에 upsert.
 *
 * @return array{scanned:int,newCandidates:array,updatedCandidates:array}
 */
function topic_discover_candidates(Database $db, $hoursWindow = 48, $minPosts = 20, $minCommunities = 3, $sampleLimit = 3000) {
    topic_ensure_candidates_table($db);

    // LIMIT/INTERVAL은 PDO 플레이스홀더 대신 정수 캐스팅 후 직접 삽입
    // (이 코드베이스의 기존 관례 - api/posts.php 등과 동일)
    $hoursWindow = intval($hoursWindow);
    $sampleLimit = intval($sampleLimit);

    $stmt = $db->query(
        "SELECT id, community_id, title FROM posts
         WHERE created_at >= NOW() - INTERVAL {$hoursWindow} HOUR
         ORDER BY created_at DESC LIMIT {$sampleLimit}",
        []
    );
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 어절 -> {post_ids: Set, community_ids: Set}
    $tally = [];
    foreach ($posts as $p) {
        $tokens = topic_tokenize_title($p['title']);
        foreach ($tokens as $tok) {
            if (!isset($tally[$tok])) {
                $tally[$tok] = ['posts' => [], 'communities' => []];
            }
            $tally[$tok]['posts'][$p['id']] = true;
            $tally[$tok]['communities'][$p['community_id']] = true;
        }
    }

    // 이미 존재하는 토픽(발행됨/후보/거절됨) 목록은 후보에서 제외
    $existingStmt = $db->query("SELECT keyword FROM topic_candidates", []);
    $existing = array_column($existingStmt->fetchAll(PDO::FETCH_ASSOC), 'keyword');
    $topicDir = __DIR__ . '/../topic';
    $publishedFiles = is_dir($topicDir) ? glob($topicDir . '/*.html') : [];
    foreach ($publishedFiles as $f) {
        $existing[] = basename($f, '.html');
    }
    $existing = array_unique($existing);

    $newCandidates = [];
    $updatedCandidates = [];

    foreach ($tally as $keyword => $data) {
        $postCount = count($data['posts']);
        $communityCount = count($data['communities']);
        if ($postCount < $minPosts || $communityCount < $minCommunities) continue;
        if (in_array($keyword, $existing, true)) continue;

        // 이미 존재하는 다른 후보/토픽의 부분 문자열이면 건너뜀 (중복 노이즈 방지)
        $isSubstring = false;
        foreach ($existing as $ex) {
            if ($ex !== '' && mb_strpos($keyword, $ex) !== false) { $isSubstring = true; break; }
        }
        if ($isSubstring) continue;

        $score = $postCount + ($communityCount * 3); // 커뮤니티 확산에 가중치

        $checkStmt = $db->query("SELECT status FROM topic_candidates WHERE keyword = ?", [$keyword]);
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $db->query(
                "INSERT INTO topic_candidates (keyword, post_count, community_count, score, status, first_seen_at, last_checked_at)
                 VALUES (?, ?, ?, ?, 'candidate', NOW(), NOW())",
                [$keyword, $postCount, $communityCount, $score]
            );
            $newCandidates[] = ['keyword' => $keyword, 'postCount' => $postCount, 'communityCount' => $communityCount, 'score' => $score];
        } elseif ($row['status'] === 'candidate') {
            $db->query(
                "UPDATE topic_candidates SET post_count = ?, community_count = ?, score = ?, last_checked_at = NOW() WHERE keyword = ?",
                [$postCount, $communityCount, $score, $keyword]
            );
            $updatedCandidates[] = ['keyword' => $keyword, 'postCount' => $postCount, 'communityCount' => $communityCount, 'score' => $score];
        }
        // status가 published/rejected면 건드리지 않음
    }

    return [
        'scanned' => count($posts),
        'newCandidates' => $newCandidates,
        'updatedCandidates' => $updatedCandidates,
    ];
}
