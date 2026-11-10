/* ===== 题目面板：所有题目页共用。
   用法：<script src="/assets/challenge.js" data-chal="sql1" defer></script>
   从 /api/meta/<id> 拉取 描述/难度/分类/前置提示/知识点，渲染抽屉 + flag 提交 + Victim Bot。 ===== */
(function () {
  var id = (document.currentScript && document.currentScript.dataset.chal) || '';
  if (!id) return;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function stars(n) { return '★'.repeat(n) + '☆'.repeat(5 - n); }

  fetch('/api/meta/' + encodeURIComponent(id)).then(function (r) {
    return r.json().then(function (j) { return { ok: r.ok, j: j }; });
  }).then(function (res) { build(res); }).catch(function () { buildLocked('题目面板加载失败，请刷新重试'); });

  function buildLocked(msg) {
    var p = shell();
    p.querySelector('.panel-body').innerHTML =
      '<div class="banner bad" style="display:block">🔒 ' + esc(msg || '题目未解锁') + '</div>' +
      '<p style="font-size:.85rem;color:var(--fg-soft)">回到 <a href="/">关卡地图</a>，先完成前置题即可解锁本题。</p>';
    p.classList.add('open');
  }

  function shell() {
    var p = document.createElement('aside');
    p.className = 'panel';
    p.innerHTML = '<button class="panel-toggle" title="展开/收起题目面板">📋</button>' +
      '<div class="panel-head"></div><div class="panel-body"></div>';
    document.body.appendChild(p);
    p.querySelector('.panel-toggle').addEventListener('click', function () { p.classList.toggle('open'); });
    return p;
  }

  function build(res) {
    if (!res.ok) { buildLocked(res.j && res.j.msg); return; }
    var m = res.j;
    var p = shell();

    var solved = m.solved;
    p.querySelector('.panel-head').innerHTML =
      '<div class="cat-line"><a href="/">← 关卡地图</a> · ' + esc(m.category_title) + '</div>' +
      '<h2>' + esc(m.title) + '</h2>' +
      '<div class="diff">' + stars(m.difficulty) +
      (m.prereq_title ? ' · 前置：<a href="' + esc(m.prereq_path) + '">' + esc(m.prereq_title) + '</a>' : ' · 本线第一题') + '</div>';

    var body = '<h4>📖 题目描述</h4><div class="desc">' + m.description_html + '</div>';

    if ((m.hints || []).length) {
      body += '<h4>💡 渐进提示（按需展开）</h4>';
      m.hints.forEach(function (h, i) {
        body += '<details><summary>提示 ' + (i + 1) + '</summary><div class="hint-body">' + h + '</div></details>';
      });
    }
    if ((m.knowledge || []).length) {
      body += '<h4>📚 相关知识</h4><ul class="knowledge-list">';
      m.knowledge.forEach(function (k) {
        body += '<li><a href="' + esc(k.url) + '" target="_blank" rel="noopener">' + esc(k.name) + '</a></li>';
      });
      body += '</ul>';
    }

    if (m.bot) {
      body += '<h4>🤖 Victim Bot（教学模拟）</h4>' +
        '<div class="bot-box">构造好 payload 并让它出现在当前页面 URL 后，点下面按钮——' +
        '虚拟访客会访问你的链接，若脚本得以执行则"窃取"其 Cookie 作为 flag。' +
        '<div style="margin-top:8px"><button class="btn small" id="botBtn">🤖 让 Victim 访问当前链接</button></div>' +
        '<div class="bot-out" id="botOut"></div></div>';
    }

    body += '<h4>🚩 提交 flag</h4>' +
      '<div class="submit-row"><input id="flagInput" placeholder="flag{...}" ' + (solved ? 'disabled' : '') + '>' +
      '<button class="btn small" id="flagBtn"' + (solved ? 'disabled' : '') + '>提交</button></div>' +
      '<div class="banner" id="flagBanner"></div>' +
      (solved ? '<p style="font-size:.85rem"><span class="solved-flag">✔ 本题已解出</span>，去 <a href="/">关卡地图</a> 看看下一关。</p>' : '');

    if (m.next) {
      body += '<h4>⏭️ 下一题</h4><p style="font-size:.88rem">解出本题后解锁：' +
        '<a href="' + esc(m.next.path) + '">' + esc(m.next.title) + '</a></p>';
    }
    p.querySelector('.panel-body').innerHTML = body;
    p.classList.add('open');

    var banner = p.querySelector('#flagBanner');
    function show(cls, html) { banner.className = 'banner ' + cls; banner.innerHTML = html; }
    if (solved) show('ok', '✔ 你已解出本题。');

    p.querySelector('#flagBtn').addEventListener('click', function () {
      var flag = p.querySelector('#flagInput').value.trim();
      if (!flag) return;
      p.querySelector('#flagBtn').disabled = true;
      fetch('/api/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, flag: flag })
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (j.ok) {
          show('ok', '🎉 正确！' + (j.next ? '已解锁下一题：<a href="' + esc(j.next.path) + '">' + esc(j.next.title) + '</a>' : '本条关卡线暂告一段落。'));
          p.querySelector('#flagInput').disabled = true;
        } else {
          show('bad', '❌ ' + esc(j.msg || 'flag 不正确，再想想？'));
          p.querySelector('#flagBtn').disabled = false;
        }
      }).catch(function () { show('bad', '网络错误，请重试'); p.querySelector('#flagBtn').disabled = false; });
    });

    var botBtn = p.querySelector('#botBtn');
    if (botBtn) botBtn.addEventListener('click', function () {
      var out = p.querySelector('#botOut');
      out.textContent = 'Victim 正在访问 ' + location.pathname + location.search + ' …';
      botBtn.disabled = true;
      fetch('/api/xssbot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url: location.pathname + location.search })
      }).then(function (r) { return r.json(); }).then(function (j) {
        botBtn.disabled = false;
        if (j.executed) {
          out.innerHTML = '✅ 脚本在 Victim 浏览器中执行了！(' + esc(j.vector) + ')<br>偷到的 Cookie: <code>' + esc(j.cookie) + '</code>';
        } else {
          out.textContent = '❌ 未检测到脚本执行：' + (j.reason || '再构造一下 payload？');
        }
      }).catch(function () { botBtn.disabled = false; out.textContent = '网络错误，请重试'; });
    });
  }
})();
