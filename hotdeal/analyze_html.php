<?php
/**
 * HTML 구조 분석 스크립트
 * debug_ppomppu.html 파일을 분석해서 실제 링크 구조 파악
 */

function analyzeHtml() {
    $htmlFile = __DIR__ . '/debug_ppomppu.html';
    
    if (!file_exists($htmlFile)) {
        echo "debug_ppomppu.html 파일이 없습니다.\n";
        echo "먼저 크롤러를 실행해서 HTML 파일을 생성하세요.\n";
        return;
    }
    
    echo "=== HTML 구조 분석 ===\n\n";
    
    $html = file_get_contents($htmlFile);
    echo "HTML 파일 크기: " . strlen($html) . " bytes\n\n";
    
    // EUC-KR에서 UTF-8로 변환
    $encoding = mb_detect_encoding($html, ['UTF-8', 'EUC-KR', 'CP949']);
    echo "감지된 인코딩: {$encoding}\n";
    
    if ($encoding && $encoding !== 'UTF-8') {
        $html = mb_convert_encoding($html, 'UTF-8', $encoding);
        echo "UTF-8로 변환 완료\n\n";
    }
    
    // DOM 파싱
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    echo "=== 링크 분석 ===\n";
    
    // 모든 링크 찾기
    $allLinks = $xpath->query('//a[@href]');
    echo "전체 링크 개수: {$allLinks->length}\n\n";
    
    // 다양한 패턴의 링크 분석
    $patterns = [
        'zboard.php' => "//a[contains(@href, 'zboard.php')]",
        'view.php' => "//a[contains(@href, 'view.php')]", 
        'no=' => "//a[contains(@href, 'no=')]",
        'ppomppu' => "//a[contains(@href, 'ppomppu')]",
        'hotdeal' => "//a[contains(@href, 'hotdeal')]",
        'hot.php' => "//a[contains(@href, 'hot.php')]"
    ];
    
    foreach ($patterns as $name => $pattern) {
        $links = $xpath->query($pattern);
        echo "{$name} 패턴 링크: {$links->length}개\n";
        
        if ($links->length > 0) {
            echo "  샘플 링크들:\n";
            for ($i = 0; $i < min(5, $links->length); $i++) {
                $link = $links->item($i);
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);
                echo "    - {$href}\n";
                echo "      텍스트: " . substr($text, 0, 50) . (strlen($text) > 50 ? "..." : "") . "\n";
            }
            echo "\n";
        }
    }
    
    echo "=== 테이블 구조 분석 ===\n";
    
    // 테이블 분석
    $tables = $xpath->query('//table');
    echo "테이블 개수: {$tables->length}\n\n";
    
    for ($t = 0; $t < min(3, $tables->length); $t++) {
        $table = $tables->item($t);
        $rows = $xpath->query('.//tr', $table);
        echo "테이블 {$t}: {$rows->length}개 행\n";
        
        // 각 행의 클래스 확인
        for ($r = 0; $r < min(5, $rows->length); $r++) {
            $row = $rows->item($r);
            $class = $row->getAttribute('class');
            $cells = $xpath->query('.//td', $row);
            echo "  행 {$r}: class='{$class}', 셀={$cells->length}개\n";
            
            // 링크가 있는 행인지 확인
            $rowLinks = $xpath->query('.//a[@href]', $row);
            if ($rowLinks->length > 0) {
                echo "    링크 {$rowLinks->length}개 포함\n";
                $firstLink = $rowLinks->item(0);
                echo "    첫 번째 링크: " . $firstLink->getAttribute('href') . "\n";
            }
        }
        echo "\n";
    }
    
    echo "=== 특정 클래스/ID 검색 ===\n";
    
    // 뽐뿌 특정 요소들 검색
    $searches = [
        'baseList' => '//*[contains(@class, "baseList")]',
        'hotdeal' => '//*[contains(@class, "hotdeal")]',
        'hot' => '//*[contains(@class, "hot")]',
        'list' => '//*[contains(@class, "list")]',
        'item' => '//*[contains(@class, "item")]',
        'post' => '//*[contains(@class, "post")]'
    ];
    
    foreach ($searches as $name => $selector) {
        $elements = $xpath->query($selector);
        echo "{$name} 요소: {$elements->length}개\n";
        
        if ($elements->length > 0) {
            for ($i = 0; $i < min(3, $elements->length); $i++) {
                $element = $elements->item($i);
                $tag = $element->nodeName;
                $class = $element->getAttribute('class');
                $id = $element->getAttribute('id');
                echo "  {$tag}.{$class}#{$id}\n";
            }
        }
    }
    
    echo "\n=== JavaScript 분석 ===\n";
    
    // JavaScript로 동적 로딩되는지 확인
    $scripts = $xpath->query('//script');
    echo "스크립트 태그: {$scripts->length}개\n";
    
    $hasAjax = false;
    $hasLoad = false;
    
    foreach ($scripts as $script) {
        $content = $script->textContent;
        if (strpos($content, 'ajax') !== false || strpos($content, 'Ajax') !== false) {
            $hasAjax = true;
        }
        if (strpos($content, 'load') !== false || strpos($content, 'onload') !== false) {
            $hasLoad = true;
        }
    }
    
    echo "AJAX 사용 여부: " . ($hasAjax ? "예" : "아니오") . "\n";
    echo "동적 로딩 여부: " . ($hasLoad ? "예" : "아니오") . "\n";
    
    echo "\n=== 권장사항 ===\n";
    
    // 각 링크 개수에 따른 권장사항
    $zboardLinks = $xpath->query("//a[contains(@href, 'zboard.php') and contains(@href, 'no=')]")->length;
    $viewLinks = $xpath->query("//a[contains(@href, 'view.php') and contains(@href, 'no=')]")->length;
    $noLinks = $xpath->query("//a[contains(@href, 'no=')]")->length;
    
    if ($zboardLinks > 0) {
        echo "✅ zboard.php 패턴 링크 {$zboardLinks}개 발견 - 이 패턴 사용 권장\n";
    } elseif ($viewLinks > 0) {
        echo "✅ view.php 패턴 링크 {$viewLinks}개 발견 - 이 패턴 사용 권장\n";
    } elseif ($noLinks > 0) {
        echo "✅ no= 패턴 링크 {$noLinks}개 발견 - 이 패턴 사용 권장\n";
    } else {
        echo "❌ 게시글 링크 패턴을 찾을 수 없음\n";
        echo "   - JavaScript로 동적 로딩될 가능성\n";
        echo "   - 다른 URL 패턴 필요\n";
        echo "   - 로그인이 필요할 가능성\n";
    }
}

// 웹에서 실행시 HTML 출력
if (isset($_SERVER['HTTP_HOST'])) {
    echo "<pre>";
    analyzeHtml();
    echo "</pre>";
} else {
    // CLI에서 실행
    analyzeHtml();
}
?>