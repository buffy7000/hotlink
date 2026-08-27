<?php
require_once __DIR__ . '/../config/database.php';

// topic/ 폴더를 그대로 스캔한다 (sitemap.php, trending_topics.php와 동일한 패턴).
// 재생성 없이 항상 최신 상태를 보여줌.
$files = glob(__DIR__ . '/*.html');
$keywords = array_map(function ($f) { return basename($f, '.html'); }, $files);

$topics = [];
if (!empty($keywords)) {
    try {
        $db = new Database();
        foreach ($keywords as $kw) {
            $like = '%' . $kw . '%';
            $stmt = $db->query(
                "SELECT COUNT(*) AS post_count, COUNT(DISTINCT community_id) AS community_count,
                        MAX(created_at) AS last_post
                 FROM posts WHERE title LIKE ?",
                [$like]
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $topics[] = [
                'keyword'         => $kw,
                'post_count'      => intval($row['post_count']),
                'community_count' => intval($row['community_count']),
                'last_post'       => $row['last_post'],
            ];
        }
        usort($topics, function ($a, $b) { return $b['post_count'] - $a['post_count']; });
    } catch (Exception $e) {
        $topics = [];
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_dt($dt) { return $dt ? date('n월 j일 H:i', strtotime($dt)) : '-'; }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>토픽 모아보기 | 클리앙·오유·뽐뿌 커뮤니티 반응 - 핫링크</title>
<meta name="description" content="핫링크에서 다루는 커뮤니티 화제의 인물·이슈 토픽을 한눈에 모아봅니다.">
<link rel="canonical" href="https://hotlink.kr/topic/">
<link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="/topic/assets/topic.css">
<link rel="stylesheet" href="/assets/trending-bar.css">
</head>
<body>

<div class="wrap">
  <div class="trending-bar">
    <div class="trending-bar-label">🔥 인기급상승 토픽</div>
    <div class="trending-bar-list"><span class="trending-bar-empty">불러오는 중...</span></div>
  </div>

  <div class="breadcrumb"><a href="/">핫링크</a> › 토픽</div>

  <div class="header">
    <h1>토픽 모아보기</h1>
    <div class="subtitle">커뮤니티에서 화제가 된 인물·이슈를 모아봅니다</div>
  </div>

  <div class="section">
    <div class="panel">
      <?php if (empty($topics)): ?>
        <div class="empty-state">아직 생성된 토픽이 없습니다.</div>
      <?php else: foreach ($topics as $t): ?>
        <div class="community-card">
          <div class="community-card-head">
            <a href="/topic/<?php echo rawurlencode($t['keyword']); ?>" style="font-size:16px;font-weight:800;color:var(--text-color);text-decoration:none;">
              <?php echo h($t['keyword']); ?>
            </a>
            <span class="stats">
              글 <?php echo number_format($t['post_count']); ?>개 · 커뮤니티 <?php echo $t['community_count']; ?>개 · 마지막 <?php echo fmt_dt($t['last_post']); ?>
            </span>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="footer">
    <a href="/">hotlink.kr</a>
  </div>
</div>

<script src="/topic/assets/topic.js"></script>
<script src="/assets/trending-bar.js"></script>
</body>
</html>
