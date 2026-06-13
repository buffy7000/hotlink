cat > /home/pricetag/hotlink.kr/deal/test.php << 'EOF'
<?php
echo "현재 디렉토리: " . __DIR__ . "\n";
echo "QuasarzoneHotdealCrawler.php 존재: " . (file_exists(__DIR__ . '/QuasarzoneHotdealCrawler.php') ? 'YES' : 'NO') . "\n";
echo "ClienHotdealCrawler.php 존재: " . (file_exists(__DIR__ . '/ClienHotdealCrawler.php') ? 'YES' : 'NO') . "\n";

if (file_exists(__DIR__ . '/QuasarzoneHotdealCrawler.php')) {
    echo "QuasarzoneHotdealCrawler.php 로딩 시도...\n";
    require_once(__DIR__ . '/QuasarzoneHotdealCrawler.php');
    echo "로딩 성공!\n";
}
?>
EOF
