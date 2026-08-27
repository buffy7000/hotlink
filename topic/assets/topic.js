function topicToggleMore(btn) {
  var container = btn.closest('.toggle-container');
  var hidden = container.querySelectorAll('.extra-post');
  var isHidden = hidden.length && hidden[0].style.display === 'none';
  hidden.forEach(function (el) { el.style.display = isHidden ? '' : 'none'; });
  if (!btn.dataset.moreLabel) btn.dataset.moreLabel = btn.textContent;
  btn.textContent = isHidden ? '접기' : btn.dataset.moreLabel;
}

// 인기급상승 토픽 바 렌더링 로직은 /assets/trending-bar.js 참고 (index.php와 공유)
