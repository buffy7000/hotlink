function topicToggleMore(btn) {
  var container = btn.closest('.toggle-container');
  var hidden = container.querySelectorAll('.extra-post');
  var isHidden = hidden.length && hidden[0].style.display === 'none';
  hidden.forEach(function (el) { el.style.display = isHidden ? '' : 'none'; });
  if (!btn.dataset.moreLabel) btn.dataset.moreLabel = btn.textContent;
  btn.textContent = isHidden ? '접기' : btn.dataset.moreLabel;
}

// 인기급상승 토픽 바: 정적 페이지에 박아두지 않고 매 방문마다 최신 상태를 불러온다.
// 이러면 새 토픽 페이지가 추가/변경돼도 기존에 생성된 모든 페이지에 재생성 없이 반영된다.
function topicRenderTrending() {
  var list = document.querySelector('.trending-bar-list');
  if (!list) return;
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

      topics.forEach(function (t) {
        var a = document.createElement('a');
        a.href = t.url;
        var rank = document.createElement('span');
        rank.className = 'rank';
        rank.textContent = t.rank;
        a.appendChild(rank);
        a.appendChild(document.createTextNode(t.keyword));
        list.appendChild(a);
      });
    })
    .catch(function () {});
}

document.addEventListener('DOMContentLoaded', topicRenderTrending);
