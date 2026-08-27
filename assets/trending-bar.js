// 인기급상승 토픽 바 - index.php와 토픽 페이지(topic/*)가 공유하는 컴포넌트.
// 정적으로 굽지 않고 매 방문마다 최신 상태를 불러와서, 새 토픽이 추가/변경돼도
// 이 파일을 쓰는 모든 페이지에 재생성 없이 즉시 반영된다.
function renderTrendingBar() {
  var list = document.querySelector('.trending-bar-list');
  if (!list) return;
  // 토픽 페이지에서는 <body data-keyword="..">로 자기 자신을 제외시킨다.
  // index.php처럼 이 속성이 없는 페이지는 전부 그대로 보여준다.
  var currentKeyword = document.body.getAttribute('data-keyword') || '';

  fetch('/api/trending_topics.php')
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var topics = (data.topics || []).filter(function (t) { return t.keyword !== currentKeyword; });
      list.innerHTML = '';

      if (!topics.length) {
        var empty = document.createElement('span');
        empty.className = 'trending-bar-empty';
        empty.textContent = '아직 급상승 토픽이 없어요';
        list.appendChild(empty);
        return;
      }

      // 순위가 고정된 느낌이 없도록 방문할 때마다 순서를 섞는다.
      for (var i = topics.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var tmp = topics[i]; topics[i] = topics[j]; topics[j] = tmp;
      }

      topics.forEach(function (t) {
        var a = document.createElement('a');
        a.href = t.url;
        a.textContent = t.keyword;
        list.appendChild(a);
      });
    })
    .catch(function () {});
}

document.addEventListener('DOMContentLoaded', renderTrendingBar);
