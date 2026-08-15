/* 국기 데이터 + 렌더링 도구
   viewBox: 0 0 300 200 (모든 국기를 3:2 비율로 통일 - 색칠/게임 UI 일관성을 위한 단순화)
   각 국기는 "부위(part)" 배열로 구성됨. 각 part = { id, d, fill, fillRule? }
   - 실제 색으로 채우면 국기, 흰 배경/외곽선만 남기면 색칠 도안이 됨
*/
(function (global) {
  'use strict';

  // ── 기본 도형 생성 도구 ─────────────────────────────
  function field(color, id) { return { id: id || 'field', d: 'M0,0H300V200H0Z', fill: color }; }
  function rect(x, y, w, h, color, id) { return { id: id, d: 'M' + x + ',' + y + 'H' + (x + w) + 'V' + (y + h) + 'H' + x + 'Z', fill: color }; }
  function hStripes(colors, ids) {
    var n = colors.length, h = 200 / n, out = [];
    for (var i = 0; i < n; i++) out.push(rect(0, i * h, 300, h, colors[i], (ids && ids[i]) || ('s' + i)));
    return out;
  }
  function vStripes(colors, ids) {
    var n = colors.length, w = 300 / n, out = [];
    for (var i = 0; i < n; i++) out.push(rect(i * w, 0, w, 200, colors[i], (ids && ids[i]) || ('s' + i)));
    return out;
  }
  function circlePath(cx, cy, r) {
    return 'M' + (cx - r) + ',' + cy + ' A' + r + ',' + r + ' 0 1,0 ' + (cx + r) + ',' + cy + ' A' + r + ',' + r + ' 0 1,0 ' + (cx - r) + ',' + cy + ' Z';
  }
  function circle(cx, cy, r, color, id) { return { id: id, d: circlePath(cx, cy, r), fill: color }; }
  function star(cx, cy, rOuter, rInner, points, color, id, rot) {
    rot = rot === undefined ? -90 : rot;
    var d = '', step = Math.PI / points;
    for (var i = 0; i < points * 2; i++) {
      var r = i % 2 === 0 ? rOuter : rInner;
      var ang = rot * Math.PI / 180 + i * step;
      var x = cx + r * Math.cos(ang), y = cy + r * Math.sin(ang);
      d += (i === 0 ? 'M' : 'L') + x.toFixed(2) + ',' + y.toFixed(2) + ' ';
    }
    d += 'Z';
    return { id: id, d: d, fill: color };
  }
  function poly(pts, color, id) {
    var d = pts.map(function (p, i) { return (i === 0 ? 'M' : 'L') + p[0] + ',' + p[1]; }).join(' ') + ' Z';
    return { id: id, d: d, fill: color };
  }
  function crescent(cx, cy, r, offset, color, id) {
    var d = circlePath(cx, cy, r) + ' ' + circlePath(cx + offset, cy, r * 0.8);
    return { id: id, d: d, fill: color, fillRule: 'evenodd' };
  }
  function hexagram(cx, cy, r, color, id) {
    function tri(rot) {
      var pts = [];
      for (var i = 0; i < 3; i++) {
        var ang = (rot + i * 120) * Math.PI / 180;
        pts.push([(cx + r * Math.cos(ang)).toFixed(2), (cy + r * Math.sin(ang)).toFixed(2)]);
      }
      return pts;
    }
    var t1 = tri(-90), t2 = tri(-30);
    var d = 'M' + t1.map(function (p) { return p.join(','); }).join('L') + 'Z M' + t2.map(function (p) { return p.join(','); }).join('L') + 'Z';
    return { id: id, d: d, fill: color };
  }
  function nordicCross(bg, cross, bgId, crossId, vFrac) {
    vFrac = vFrac || 0.35;
    var vx = 300 * vFrac, barW = 28;
    return [
      field(bg, bgId),
      rect(vx - barW / 2, 0, barW, 200, cross, crossId + 'v'),
      rect(0, 100 - barW / 2, 300, barW, cross, crossId + 'h')
    ];
  }
  function sunRays(cx, cy, r, rayLen, count, color, id) {
    var pts = [], step = 360 / (count * 2);
    for (var i = 0; i < count * 2; i++) {
      var rr = i % 2 === 0 ? r + rayLen : r;
      var ang = (i * step - 90) * Math.PI / 180;
      pts.push([(cx + rr * Math.cos(ang)).toFixed(2), (cy + rr * Math.sin(ang)).toFixed(2)]);
    }
    return poly(pts, color, id);
  }
  function maskCanton(w, h, color, id) { return rect(0, 0, w, h, color, id); }

  // ── 국기 47+3 = 50개국 데이터 ────────────────────────
  var W = '#FFFFFF', BLK = '#000000';

  var FLAGS = [
    // 동아시아
    { code: 'kr', name: '대한민국', build: function () {
        var parts = [field(W)];
        // 태극 - 축이 좌상↔우하가 아닌 우상(양/빨강)↔좌하(음/파랑) 대각선이 되도록 45도 회전한 좌표 사용
        parts.push({ id: 'taegeuk-red', d: 'M121.72,71.72 A40,40 0 0,1 178.28,128.28 A20,20 0 0,1 150,100 A20,20 0 0,0 121.72,71.72 Z', fill: '#C60C30' });
        parts.push({ id: 'taegeuk-blue', d: 'M178.28,128.28 A40,40 0 0,1 121.72,71.72 A20,20 0 0,1 150,100 A20,20 0 0,0 178.28,128.28 Z', fill: '#003478' });
        // 4괘 (건곤감리) - 각 괘의 실제 3선 패턴(false=이어진 선, true=끊긴 선)과 대각 배치를 반영
        function trigram(x, y, pattern, color) {
          var out = [], bw = 26, bh = 5, gap = 8;
          for (var i = 0; i < 3; i++) {
            var yy = y + i * gap;
            if (pattern[i]) {
              out.push(rect(x, yy, bw * 0.42, bh, color, 'tg'));
              out.push(rect(x + bw * 0.58, yy, bw * 0.42, bh, color, 'tg'));
            } else {
              out.push(rect(x, yy, bw, bh, color, 'tg'));
            }
          }
          return out;
        }
        parts = parts.concat(trigram(64, 44, [false, false, false], BLK));  // 건(乾) 좌상: 이어진 선 3개
        parts = parts.concat(trigram(210, 44, [true, false, true], BLK));   // 감(坎) 우상: 끊김-이어짐-끊김
        parts = parts.concat(trigram(64, 132, [false, true, false], BLK)); // 리(離) 좌하: 이어짐-끊김-이어짐
        parts = parts.concat(trigram(210, 132, [true, true, true], BLK));   // 곤(坤) 우하: 끊긴 선 3개
        return parts;
      } },
    { code: 'kp', name: '북한', build: function () {
        return [
          field('#024FA2'),
          rect(0, 26, 300, 148, W),
          rect(0, 40, 300, 120, '#ED1C27'),
          circle(70, 100, 26, W),
          star(70, 100, 15, 6, 5, '#ED1C27')
        ];
      } },
    { code: 'jp', name: '일본', build: function () { return [field(W), circle(150, 100, 42, '#BC002D')]; } },
    { code: 'cn', name: '중국', build: function () {
        function smallStar(cx, cy, rot) { return star(cx, cy, 8, 3.2, 5, '#FFDE00', 's', rot); }
        return [
          field('#DE2910'),
          star(60, 50, 26, 10, 5, '#FFDE00', 'big', -90),
          smallStar(110, 24, -66),
          smallStar(126, 42, -20),
          smallStar(126, 66, 26),
          smallStar(110, 82, 72)
        ];
      } },
    { code: 'tw', name: '대만', build: function () {
        return [
          field('#FE0000'),
          maskCanton(150, 100, '#000095', 'canton'),
          circle(75, 50, 22, W, 'sun-disc'),
          sunRays(75, 50, 22, 16, 12, W, 'sun-rays')
        ];
      } },
    // 남·동남아시아
    { code: 'mn', name: '몽골', build: function () {
        var parts = vStripes(['#DA2032', '#015197', '#DA2032']);
        parts.push(circle(50, 60, 12, '#F9CE30', 'soyombo'));
        return parts;
      } },
    { code: 'in', name: '인도', build: function () {
        var parts = hStripes(['#FF9933', W, '#138808']);
        parts.push(circle(150, 100, 22, '#0000FF', 'chakra-ring'));
        parts.push(circle(150, 100, 20, W, 'chakra-bg'));
        for (var i = 0; i < 24; i++) {
          var ang = i * 15 * Math.PI / 180;
          var x1 = 150 + 3 * Math.sin(ang), y1 = 100 - 3 * Math.cos(ang);
          var x2 = 150 + 19 * Math.sin(ang), y2 = 100 - 19 * Math.cos(ang);
          var px = 0.7 * Math.cos(ang), py = 0.7 * Math.sin(ang);
          parts.push(poly([[x1 - px, y1 - py], [x1 + px, y1 + py], [x2 + px, y2 + py], [x2 - px, y2 - py]], '#0000FF', 'spoke' + i));
        }
        return parts;
      } },
    { code: 'pk', name: '파키스탄', build: function () {
        return [
          field('#01411C'),
          rect(0, 0, 70, 200, W, 'hoist'),
          crescent(190, 100, 34, 12, W, 'crescent'),
          star(240, 60, 16, 6, 5, W, 'star')
        ];
      } },
    { code: 'th', name: '태국', build: function () { return hStripes(['#A51931', W, '#2D2A4A', W, '#A51931']); } },
    { code: 'vn', name: '베트남', build: function () { return [field('#DA251D'), star(150, 100, 30, 12, 5, '#FFFF00')]; } },
    { code: 'ph', name: '필리핀', build: function () {
        return [
          rect(0, 0, 300, 100, '#0038A8', 'top'),
          rect(0, 100, 300, 100, '#CE1126', 'bottom'),
          poly([[0, 0], [110, 100], [0, 200]], W, 'tri'),
          sunRays(45, 100, 14, 12, 8, '#FCD116', 'sun'),
          star(20, 30, 8, 3, 5, '#FCD116', 'st1'),
          star(20, 170, 8, 3, 5, '#FCD116', 'st2'),
          star(95, 100, 8, 3, 5, '#FCD116', 'st3')
        ];
      } },
    { code: 'id', name: '인도네시아', build: function () { return hStripes(['#FF0000', W]); } },
    { code: 'my', name: '말레이시아', build: function () {
        var colors = [], ids = [];
        for (var i = 0; i < 14; i++) { colors.push(i % 2 === 0 ? '#CC0001' : W); ids.push('s' + i); }
        var parts = hStripes(colors, ids);
        parts.push(maskCanton(150, 100, '#010066', 'canton'));
        parts.push(crescent(90, 50, 28, 10, '#FFCC00', 'crescent'));
        parts.push(star(140, 50, 18, 7, 14, '#FFCC00', 'star'));
        return parts;
      } },
    { code: 'sg', name: '싱가포르', build: function () {
        var parts = hStripes(['#ED2939', W]);
        parts.push(crescent(70, 50, 24, 9, W, 'crescent'));
        var stars = [[110,30],[130,50],[122,75],[98,75],[90,50]];
        stars.forEach(function(p,i){ parts.push(star(p[0],p[1],8,3,5,W,'st'+i)); });
        return parts;
      } },
    // 중동
    { code: 'sa', name: '사우디아라비아', build: function () {
        return [field('#006C35'), rect(40, 150, 220, 18, W, 'band'), poly([[40,150],[70,150],[40,168]], '#006C35', 'blade')];
      } },
    { code: 'ae', name: '아랍에미리트', build: function () {
        return [
          hStripes([ '#00732F', W, '#000000' ])[0],
          rect(0, 0, 300, 66.6, '#00732F'),
          rect(0, 66.6, 300, 66.6, W),
          rect(0, 133.4, 300, 66.6, '#000000'),
          rect(0, 0, 75, 200, '#FF0000', 'hoist')
        ];
      } },
    { code: 'il', name: '이스라엘', build: function () {
        return [
          field(W),
          rect(0, 30, 300, 20, '#0038B8', 'top'),
          rect(0, 150, 300, 20, '#0038B8', 'bottom'),
          hexagram(150, 100, 32, '#0038B8', 'star')
        ];
      } },
    { code: 'tr', name: '튀르키예', build: function () {
        return [field('#E30A17'), crescent(130, 100, 32, 12, W, 'crescent'), star(190, 100, 16, 6, 5, W, 'star')];
      } },
    // 유럽
    { code: 'gb', name: '영국', build: function () {
        var parts = [field('#012169')];
        parts.push(rect(0, 85, 300, 30, W, 'cross-h-w'));
        parts.push(rect(135, 0, 30, 200, W, 'cross-v-w'));
        parts.push(rect(0, 92, 300, 16, '#C8102E', 'cross-h-r'));
        parts.push(rect(142, 0, 16, 200, '#C8102E', 'cross-v-r'));
        parts.push(poly([[0,0],[40,0],[0,26]], W, 'd1w'));
        parts.push(poly([[300,0],[260,0],[300,26]], W, 'd2w'));
        parts.push(poly([[0,200],[40,200],[0,174]], W, 'd3w'));
        parts.push(poly([[300,200],[260,200],[300,174]], W, 'd4w'));
        return parts;
      } },
    { code: 'fr', name: '프랑스', build: function () { return vStripes(['#0055A4', W, '#EF4135']); } },
    { code: 'de', name: '독일', build: function () { return hStripes(['#000000', '#DD0000', '#FFCE00']); } },
    { code: 'it', name: '이탈리아', build: function () { return vStripes(['#008C45', W, '#CD212A']); } },
    { code: 'es', name: '스페인', build: function () {
        return [rect(0,0,300,50,'#AA151B','t'), rect(0,50,300,100,'#F1BF00','m'), rect(0,150,300,50,'#AA151B','b'), circle(80,100,16,'#AA151B','emblem')];
      } },
    { code: 'pt', name: '포르투갈', build: function () {
        return [rect(0,0,120,200,'#006600','g'), rect(120,0,180,200,'#FF0000','r'), circle(120,100,26,'#FFCC00','emblem-bg'), circle(120,100,20,W,'emblem')];
      } },
    { code: 'nl', name: '네덜란드', build: function () { return hStripes(['#AE1C28', W, '#21468B']); } },
    { code: 'be', name: '벨기에', build: function () { return vStripes(['#000000', '#FAE042', '#ED2939']); } },
    { code: 'ch', name: '스위스', build: function () {
        return [field('#D52B1E'), rect(130, 55, 40, 90, W, 'v'), rect(105, 80, 90, 40, W, 'h')];
      } },
    { code: 'at', name: '오스트리아', build: function () { return hStripes(['#ED2939', W, '#ED2939']); } },
    { code: 'se', name: '스웨덴', build: function () { return nordicCross('#006AA7', '#FECC02', 'bg', 'cross'); } },
    { code: 'no', name: '노르웨이', build: function () {
        var parts = nordicCross('#EF2B2D', '#002868', 'bg', 'crossblue', 0.35);
        parts.push(rect(300*0.35-16,0,32,200,W,'crossw-v'));
        parts.push(rect(0,100-16,300,32,W,'crossw-h'));
        return [parts[0], parts[3], parts[4], parts[1], parts[2]];
      } },
    { code: 'dk', name: '덴마크', build: function () { return nordicCross('#C60C30', W, 'bg', 'cross'); } },
    { code: 'fi', name: '핀란드', build: function () { return nordicCross(W, '#002F6C', 'bg', 'cross'); } },
    { code: 'pl', name: '폴란드', build: function () { return hStripes([W, '#DC143C']); } },
    { code: 'ru', name: '러시아', build: function () { return hStripes([W, '#0039A6', '#D52B1E']); } },
    { code: 'ua', name: '우크라이나', build: function () { return hStripes(['#005BBB', '#FFD500']); } },
    { code: 'gr', name: '그리스', build: function () {
        var colors = [], ids = [];
        for (var i = 0; i < 9; i++) { colors.push(i % 2 === 0 ? '#0D5EAF' : W); ids.push('s' + i); }
        var parts = hStripes(colors, ids);
        parts.push(maskCanton(89, 111, '#0D5EAF', 'canton'));
        parts.push(rect(35,44,20,66,W,'cross-v'));
        parts.push(rect(0,66,89,20,W,'cross-h'));
        return parts;
      } },
    // 아메리카
    { code: 'us', name: '미국', build: function () {
        var colors = [], ids = [];
        for (var i = 0; i < 13; i++) { colors.push(i % 2 === 0 ? '#B22234' : W); ids.push('s' + i); }
        var parts = hStripes(colors, ids);
        parts.push(maskCanton(120, 107.7, '#3C3B6E', 'canton'));
        // 단순화: 50개 별 대신 격자 형태로 대표 별 18개만 배치
        var positions = [[20,15],[45,15],[70,15],[95,15],[32,32],[58,32],[83,32],[20,48],[45,48],[70,48],[95,48],[32,65],[58,65],[83,65],[20,82],[45,82],[70,82],[95,82]];
        positions.forEach(function(p,i){ parts.push(star(p[0],p[1],7,2.6,5,W,'star'+i)); });
        return parts;
      } },
    { code: 'ca', name: '캐나다', build: function () {
        return [
          rect(0,0,75,200,'#FF0000','l'),
          rect(75,0,150,200,W,'m'),
          rect(225,0,75,200,'#FF0000','r'),
          star(150, 92, 42, 15, 8, '#FF0000', 'leaf'),
          poly([[146,128],[154,128],[150,152]], '#FF0000', 'leaf-stem')
        ];
      } },
    { code: 'mx', name: '멕시코', build: function () {
        return [rect(0,0,100,200,'#006341','g'), rect(100,0,100,200,W,'w'), rect(200,0,100,200,'#CE1126','r'), circle(150,100,22,'#8B5E3C','emblem')];
      } },
    { code: 'br', name: '브라질', build: function () {
        return [
          field('#009739'),
          poly([[150,20],[280,100],[150,180],[20,100]], '#FEDD00', 'diamond'),
          circle(150,100,42,'#012169','globe'),
          { id:'band', d:'M108,90 A45,20 0 0,0 192,110', fill: W, fillRule:'evenodd' }
        ];
      } },
    { code: 'ar', name: '아르헨티나', build: function () {
        var parts = hStripes(['#74ACDF', W, '#74ACDF']);
        parts.push(sunRays(150,100,18,14,16,'#F6B40E','sun-rays'));
        parts.push(circle(150,100,18,'#F6B40E','sun'));
        return parts;
      } },
    { code: 'cl', name: '칠레', build: function () {
        return [
          rect(0,0,300,100,W,'top'),
          rect(0,100,300,100,'#D52B1E','bottom'),
          maskCanton(100,100,'#0039A6','canton'),
          star(50,50,20,8,5,W,'star')
        ];
      } },
    { code: 'pe', name: '페루', build: function () {
        return [rect(0,0,100,200,'#D91023','r1'), rect(100,0,100,200,W,'w'), rect(200,0,100,200,'#D91023','r2'), circle(150,100,20,'#D4AF37','emblem')];
      } },
    { code: 'cu', name: '쿠바', build: function () {
        var colors=['#002A8F',W,'#002A8F',W,'#002A8F'], ids=['s0','s1','s2','s3','s4'];
        var parts = hStripes(colors, ids);
        parts.push(poly([[0,0],[130,100],[0,200]], '#CC0000', 'tri'));
        parts.push(star(45,100,20,8,5,W,'star'));
        return parts;
      } },
    // 아프리카
    { code: 'eg', name: '이집트', build: function () {
        var parts = hStripes(['#CE1126', W, '#000000']);
        parts.push(circle(150,100,20,'#C09300','emblem'));
        return parts;
      } },
    { code: 'za', name: '남아프리카공화국', build: function () {
        return [
          field(W),
          poly([[38,0],[300,0],[300,85],[150,92]], '#DE3831', 'red'),
          poly([[38,200],[300,200],[300,115],[150,108]], '#002395', 'blue'),
          poly([[0,0],[38,0],[150,92],[130,100]], '#007A4D', 'green-top'),
          poly([[0,200],[38,200],[150,108],[130,100]], '#007A4D', 'green-bottom'),
          rect(130,85,170,30,'#007A4D','green-mid'),
          poly([[0,44],[0,156],[140,100]], W, 'hoist-white'),
          poly([[0,56],[0,144],[128,100]], '#FFB612', 'hoist-gold'),
          poly([[0,68],[0,132],[116,100]], '#000000', 'hoist-black')
        ];
      } },
    { code: 'ng', name: '나이지리아', build: function () { return vStripes(['#008751', W, '#008751']); } },
    { code: 'ke', name: '케냐', build: function () {
        var parts = hStripes(['#000000', '#BB0000', '#006600']);
        parts.push(rect(0,60,300,13,W,'f1'));
        parts.push(rect(0,127,300,13,W,'f2'));
        parts.push(poly([[110,70],[190,70],[210,140],[150,180],[90,140]], '#BB0000', 'shield'));
        return parts;
      } },
    // 오세아니아
    { code: 'au', name: '호주', build: function () {
        var parts = [field('#00008B')];
        parts.push(maskCanton(150,100,'#00205B','canton'));
        parts.push(rect(150-8,0,16,100,W,'uv'));
        parts.push(rect(0,50-8,150,16,W,'uh'));
        parts.push(rect(150-5,0,10,100,'#C8102E','ur'));
        parts.push(rect(0,50-5,150,10,'#C8102E','uh2'));
        parts.push(star(75,150,26,10,7,W,'cwstar'));
        var stars=[[220,40],[260,70],[230,120],[200,150]];
        stars.forEach(function(p,i){parts.push(star(p[0],p[1],14,5,7,W,'sc'+i));});
        parts.push(star(255,140,8,3,5,W,'sc4'));
        return parts;
      } },
    { code: 'nz', name: '뉴질랜드', build: function () {
        var parts = [field('#00247D')];
        parts.push(maskCanton(150,100,'#00205B','canton'));
        parts.push(rect(150-8,0,16,100,W,'uv'));
        parts.push(rect(0,50-8,150,16,W,'uh'));
        parts.push(rect(150-5,0,10,100,'#C8102E','ur'));
        parts.push(rect(0,50-5,150,10,'#C8102E','uh2'));
        var stars=[[220,50],[250,90],[225,140],[195,160]];
        stars.forEach(function(p,i){
          parts.push(star(p[0],p[1],16,6,5,W,'sw'+i));
          parts.push(star(p[0],p[1],13,5,5,'#C8102E','sr'+i));
        });
        return parts;
      } }
  ];

  global.FlagKit = { field: field, rect: rect, hStripes: hStripes, vStripes: vStripes, circle: circle, star: star, poly: poly, crescent: crescent, hexagram: hexagram, nordicCross: nordicCross, sunRays: sunRays };
  global.FLAGS = FLAGS;

})(window);
