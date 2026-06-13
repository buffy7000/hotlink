<?php
header('Content-Type: application/xml; charset=utf-8');

$base_url = 'https://hotlink.kr';
$last_modified = date('Y-m-d\TH:i:s+00:00');

$communities = [
    'all' => '전체',
    'ppomppu' => '뽐뿌',
//    'clien' => '클리앙', 
    'natepann' => '네이트판',
    'ruliweb' => '루리웹',
    'theqoo' => '더쿠',
    'mlbpark' => '엠팍',
    'bobaedream' => '보배드림',
    'humoruniv' => '웃대',
    'todayhumor' => '오유'
];

// 핫딜 페이지
$hotdeal_pages = [
    'all' => '전체',
    'quasarzone' => '퀘사이존',
//    'clien' => '클리앙',
    'eomisae' => '어미새'
];

// 날짜 범위 설정 (최근 30일)
$date_range_days = 30;
$dates = [];
for ($i = 0; $i < $date_range_days; $i++) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = $date;
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    
    <!-- 메인 페이지 (전체) -->
    <url>
        <loc><?php echo $base_url; ?></loc>
        <lastmod><?php echo $last_modified; ?></lastmod>
        <changefreq>hourly</changefreq>
        <priority>1.0</priority>
    </url>
    
    <!-- 커뮤니티별 페이지 -->
    <?php foreach ($communities as $key => $name): ?>
    <?php if ($key !== 'all'): ?>
    <url>
        <loc><?php echo $base_url; ?>?communities=<?php echo $key; ?></loc>
        <lastmod><?php echo $last_modified; ?></lastmod>
        <changefreq>hourly</changefreq>
        <priority>0.9</priority>
    </url>
    <?php endif; ?>
    <?php endforeach; ?>
    
    <!-- 날짜별 전체 페이지 (최근 30일) -->
    <?php foreach ($dates as $date): ?>
    <url>
        <loc><?php echo $base_url; ?>?date=<?php echo $date; ?></loc>
        <lastmod><?php echo date('Y-m-d\TH:i:s+00:00', strtotime($date)); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>
    
    <!-- 날짜별 커뮤니티 페이지 (최근 7일만) -->
    <?php 
    $recent_dates = array_slice($dates, 0, 7); // 최근 7일만
    foreach ($communities as $key => $name): 
        if ($key === 'all') continue;
        foreach ($recent_dates as $date): 
    ?>
    <url>
        <loc><?php echo $base_url; ?>?communities=<?php echo $key; ?>&amp;date=<?php echo $date; ?></loc>
        <lastmod><?php echo date('Y-m-d\TH:i:s+00:00', strtotime($date)); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.6</priority>
    </url>
    <?php 
        endforeach;
    endforeach; 
    ?>
    
    <!-- 핫딜 메인 페이지 -->
    <url>
        <loc><?php echo $base_url; ?>/deal/</loc>
        <lastmod><?php echo $last_modified; ?></lastmod>
        <changefreq>hourly</changefreq>
        <priority>0.9</priority>
    </url>
    
    <!-- 핫딜 개별 사이트 페이지들 -->
    <url>
        <loc><?php echo $base_url; ?>/deal/?db=quasarzone</loc>
        <lastmod><?php echo $last_modified; ?></lastmod>
        <changefreq>hourly</changefreq>
        <priority>0.8</priority>
    </url>
    
    <url>
        <loc><?php echo $base_url; ?>/deal/?db=clien</loc>
        <lastmod><?php echo $last_modified; ?></lastmod>
        <changefreq>hourly</changefreq>
        <priority>0.8</priority>
    </url>
    
    <url>
        <loc><?php echo $base_url; ?>/deal/?db=eomisae</loc>
        <lastmod><?php echo $last_modified; ?></lastmod>
        <changefreq>hourly</changefreq>
        <priority>0.8</priority>
    </url>
    
</urlset>