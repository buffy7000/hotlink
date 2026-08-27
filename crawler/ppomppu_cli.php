<?php
// GitHub Actions에서 실행: 뽐뿌 HOT게시글을 크롤링해 JSON으로 stdout 출력
// hotlink.kr 서버 IP가 뽐뿌에서 차단(403)되어 서버 대신 Actions 러너에서 크롤링한다.

$baseUrl   = 'https://www.ppomppu.co.kr';
$targetUrl = $baseUrl . '/hot.php?category=2';
$limit     = 50;

// 뽐뿌는 첫 요청에 302(…&ppck=1)로 쿠키 확인 리다이렉트를 보낸다.
// 쿠키 저장소 없이 리다이렉트를 따라가면 두 번째 요청에 쿠키가 실려가지 않아 403이 뜬다.
$cookieJar = tempnam(sys_get_temp_dir(), 'ppomppu_cookie_');

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $targetUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_COOKIEJAR      => $cookieJar,
    CURLOPT_COOKIEFILE     => $cookieJar,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    CURLOPT_ENCODING       => 'gzip,deflate',
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: ko-KR,ko;q=0.8,en-US;q=0.5,en;q=0.3',
    ],
]);
$html  = curl_exec($ch);
$code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);
@unlink($cookieJar);

if (!$html || $code !== 200) {
    fwrite(STDERR, "크롤링 실패: HTTP {$code}\n");
    fwrite(STDERR, "curl 오류 [{$errno}]: {$error}\n");
    echo json_encode([]);
    exit(0);
}

if (strlen($html) < 1000) {
    fwrite(STDERR, "HTML 응답이 너무 짧습니다 (" . strlen($html) . "바이트)\n");
    echo json_encode([]);
    exit(0);
}

function ppomppu_parse_date($dateString) {
    $dateString = trim($dateString);

    if (strpos($dateString, '방금') !== false) {
        return date('Y-m-d H:i:s');
    }
    if (preg_match('/(\d+)분\s*전/', $dateString, $m)) {
        return date('Y-m-d H:i:s', strtotime("-{$m[1]} minutes"));
    }
    if (preg_match('/(\d+)시간\s*전/', $dateString, $m)) {
        return date('Y-m-d H:i:s', strtotime("-{$m[1]} hours"));
    }
    if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $dateString, $m)) {
        return date('Y-m-d') . ' ' . sprintf('%02d', $m[1]) . ':' . $m[2] . ':' . $m[3];
    }
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $dateString, $m)) {
        return date('Y-m-d') . ' ' . sprintf('%02d', $m[1]) . ':' . $m[2] . ':00';
    }
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $dateString, $m)) {
        return ('20' . $m[1]) . '-' . $m[2] . '-' . $m[3] . ' 00:00:00';
    }
    if (preg_match('/(\d{2})-(\d{2})\s+(\d{1,2}):(\d{2})/', $dateString, $m)) {
        return date('Y') . '-' . $m[1] . '-' . $m[2] . ' ' . sprintf('%02d', $m[3]) . ':' . $m[4] . ':00';
    }
    return date('Y-m-d H:i:s');
}

function ppomppu_clean_title($title) {
    $title = strip_tags($title);
    $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    $title = preg_replace('/\s+/', ' ', $title);
    $title = trim($title);
    $title = preg_replace('/^HOT\s*/', '', $title);
    $title = preg_replace('/^AD\s*/', '', $title);
    return $title;
}

function ppomppu_extract_post($linkNode, $xpath, $baseUrl) {
    $title = trim($linkNode->textContent);
    $href  = $linkNode->getAttribute('href');

    if (strpos($href, 'http') !== 0) {
        $url = $baseUrl . (strpos($href, '/') === 0 ? $href : '/' . $href);
    } else {
        $url = $href;
    }

    if (strpos($url, 'sponsor') !== false) {
        return null;
    }

    $parentRow = $linkNode;
    for ($i = 0; $i < 10; $i++) {
        $parentRow = $parentRow->parentNode;
        if (!$parentRow) break;
        if ($parentRow->nodeName === 'tr') break;
    }

    $realTitle      = '';
    $author         = '익명';
    $viewsCount     = 0;
    $likesCount     = 0;
    $commentsCount  = 0;
    $createdAt      = date('Y-m-d H:i:s');
    $thumbnailUrl   = null;

    if ($parentRow) {
        $imgNodes = $xpath->query(".//a[contains(@class, 'baseList-thumb')]/img", $parentRow);
        if ($imgNodes->length > 0) {
            $imgSrc = $imgNodes->item(0)->getAttribute('src');
            if (!empty($imgSrc)) {
                if (strpos($imgSrc, '//') === 0) {
                    $thumbnailUrl = 'https:' . $imgSrc;
                } elseif (strpos($imgSrc, '/') === 0) {
                    $thumbnailUrl = $baseUrl . $imgSrc;
                } else {
                    $thumbnailUrl = $imgSrc;
                }
            }
        }

        $cells = $xpath->query(".//td", $parentRow);
        foreach ($cells as $i => $cell) {
            $cellText = trim($cell->textContent);
            if ($cellText === '') continue;

            if ($i === 0) {
                foreach (['뽐뿌스폰서', '보험업체', '광고'] as $adBoard) {
                    if (strpos($cellText, $adBoard) !== false) return null;
                }
            } elseif ($i === 2) {
                $realTitle = $cellText;
                foreach (['렌탈료', '할인', '최대혜택', '공식판매점', '24시간상담', 'AD '] as $keyword) {
                    if (strpos($realTitle, $keyword) !== false) return null;
                }
                if (preg_match('/(\d+)$/', $realTitle, $m)) {
                    $commentsCount = intval($m[1]);
                    $realTitle = preg_replace('/\s*\d+$/', '', $realTitle);
                }
            } elseif ($i === 3) {
                $author = $cellText;
            } elseif ($i === 4) {
                if (preg_match('/\d{2}:\d{2}/', $cellText)) {
                    $createdAt = ppomppu_parse_date($cellText);
                }
            } elseif ($i === 5) {
                if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $cellText, $m)) {
                    $likesCount = intval($m[1]);
                }
            } elseif ($i === 6) {
                if (preg_match('/^\d+$/', $cellText)) {
                    $viewsCount = intval($cellText);
                }
            }
        }
    }

    if (empty($realTitle)) {
        $realTitle = $title;
    }

    return [
        'community_id'   => 1,
        'title'          => ppomppu_clean_title($realTitle),
        'url'            => $url,
        'thumbnail_url'  => $thumbnailUrl,
        'author'         => $author,
        'comments_count' => $commentsCount,
        'views_count'    => $viewsCount,
        'likes_count'    => $likesCount,
        'created_at'     => $createdAt,
    ];
}

function ppomppu_is_valid($post) {
    if (empty($post['title']) || empty($post['url'])) return false;
    if (strlen($post['title']) < 5) return false;

    foreach (['자유게시판', 'PC/인터넷', '유머/감동', '보험업체', '뽐뿌스폰서', 'HOT'] as $invalid) {
        if (trim($post['title']) === $invalid || strpos($post['title'], $invalid) === 0) return false;
    }
    if (!preg_match('/no=\d+/', $post['url'])) return false;

    return true;
}

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

$postLinks = $xpath->query("//a[contains(@href, 'zboard.php') and contains(@href, 'no=')]");
$posts = [];
$processedUrls = [];

if ($postLinks) {
    foreach ($postLinks as $linkNode) {
        if (count($posts) >= $limit) break;

        $post = ppomppu_extract_post($linkNode, $xpath, $baseUrl);
        if (!$post || !ppomppu_is_valid($post)) continue;
        if (in_array($post['url'], $processedUrls)) continue;

        $posts[] = $post;
        $processedUrls[] = $post['url'];
        fwrite(STDERR, "  파싱: {$post['title']}\n");
    }
}

fwrite(STDERR, "총 " . count($posts) . "개 파싱 완료\n");
echo json_encode($posts, JSON_UNESCAPED_UNICODE);
