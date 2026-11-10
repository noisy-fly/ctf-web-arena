/* ===== 门户逻辑：拉取进度、渲染关卡地图、主题切换、重置 ===== */
(function () {
  var STAR_FULL = '★', STAR_EMPTY = '☆';

  function stars(n) { return STAR_FULL.repeat(n) + STAR_EMPTY.repeat(5 - n); }

  function render(state) {
    var box = document.getElementById('tracks');
    box.innerHTML = '';
    document.getElementById('statSolved').textContent = state.progress.solved;
    document.getElementById('statTotal').textContent = state.progress.total;
    document.getElementById('statCats').textContent = state.categories.length;

    state.categories.forEach(function (cat) {
      var solved = cat.challenges.filter(function (c) { return c.solved; }).length;
      var card = document.createElement('div');
      card.className = 'cat-card';
      var head = '<div class="cat-head"><span class="emoji">' + cat.emoji + '</span>' +
        '<h2>' + cat.title + '</h2><span class="cnt">' + solved + ' / ' + cat.challenges.length + ' 已解</span></div>' +
        '<p class="cat-desc">' + cat.desc + '</p>';
      var track = '<div class="track">';
      cat.challenges.forEach(function (c, i) {
        if (i > 0) track += '<div class="link-line"></div>';
        var cls = 'dot ' + (c.solved ? 'solved' : (c.unlocked ? 'open' : 'locked'));
        var icon = c.solved ? '✓' : (c.unlocked ? (i + 1) : '🔒');
        var dis = c.unlocked ? '' : ' disabled';
        track += '<button class="node' + dis + '" data-path="' + c.path + '" data-unlocked="' + (c.unlocked ? 1 : 0) + '" title="' + c.title + '">' +
          '<span class="' + cls + '">' + icon + '</span>' +
          '<span class="n-title">' + c.title + '</span>' +
          '<span class="n-star">' + stars(c.difficulty) + '</span></button>';
      });
      track += '</div>';
      card.innerHTML = head + track;
      box.appendChild(card);
    });

    box.querySelectorAll('.node').forEach(function (n) {
      n.addEventListener('click', function () {
        if (n.dataset.unlocked !== '1') {
          n.animate([{ transform: 'translateX(0)' }, { transform: 'translateX(-4px)' },
                     { transform: 'translateX(4px)' }, { transform: 'translateX(0)' }], { duration: 220 });
          return;
        }
        location.href = n.dataset.path;
      });
    });
  }

  function load() {
    fetch('/api/state').then(function (r) { return r.json(); })
      .then(render)
      .catch(function () {
        document.getElementById('tracks').innerHTML =
          '<div class="cat-card" style="text-align:center;color:var(--danger)">进度服务暂不可用，请稍后刷新重试</div>';
      });
  }

  document.getElementById('themeBtn').addEventListener('click', function () {
    var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', cur);
    try { localStorage.setItem('theme', cur); } catch (e) {}
    syncThemeBtn();
  });
  function syncThemeBtn() {
    document.getElementById('themeBtn').textContent =
      document.documentElement.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙';
  }
  syncThemeBtn();

  document.getElementById('resetBtn').addEventListener('click', function () {
    if (!confirm('确定清空当前浏览器的解题进度吗？此操作不可恢复。')) return;
    fetch('/api/reset', { method: 'POST' }).then(load);
  });

  load();
})();
