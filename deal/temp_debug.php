<?php
if (isset($_GET['highlight'])) {
    $highlightId = $_GET['highlight'];
    echo "<h3>테스트 ID: " . htmlspecialchars($highlightId) . "</h3>";
    
    try {
        require_once(__DIR__ . '/SimpleHotdealDB.php');
        $db = new SimpleHotdealDB();
        
        // sources 테이블 없이 쿼리 (수정된 부분)
        $hotdeal = $db->fetch("
            SELECT title, price, store_name, thumbnail_url, source_id
            FROM hotdeals 
            WHERE public_id = ?
        ", [$highlightId]);
        
        if ($hotdeal) {
            // source_name 매핑 추가
            $sourceNames = [
                2 => '퀘사이존',
                3 => '클리앙', 
                5 => '어미새'
            ];
            $hotdeal['source_name'] = $sourceNames[$hotdeal['source_id']] ?? '알 수 없음';
            
            echo "<h4>핫딜 정보 찾음:</h4>";
            echo "<pre>" . print_r($hotdeal, true) . "</pre>";
        } else {
            echo "<h4>핫딜 정보 없음</h4>";
        }
        
    } catch (Exception $e) {
        echo "<h4>오류:</h4>";
        echo "<p>" . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>?highlight=250916018 형태로 테스트하세요</p>";
}
?>