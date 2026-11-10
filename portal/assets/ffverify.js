/* ===== ffverify.js: 题目页内 flag 表单的统一 JSON 提交（经 nginx sub_filter 注入） =====
   绑定所有 form[action="/api/verify"]：改为 fetch JSON {"id","flag"}（id 取自
   challenge_id 隐藏字段），结果写回 #flagResult（无则就地插入），阻止整页跳转。 */
(function () {
  function bind(f) {
    if (f.__ffVerify) return;
    f.__ffVerify = true;
    f.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var idEl = f.querySelector('[name=challenge_id]') || f.querySelector('[name=id]');
      var flEl = f.querySelector('[name=flag]');
      var id = idEl ? idEl.value.trim() : '';
      var fl = flEl ? flEl.value.trim() : '';
      var box = document.getElementById('flagResult');
      var show = function (html) {
        if (box) { box.innerHTML = html; return; }
        f.insertAdjacentHTML('afterend',
          '<p class="muted" id="flagResult">' + html + '</p>');
      };
      if (!id || !fl) { show('❌ 请填写完整（id/flag 缺失）'); return; }
      show('提交中…');
      fetch('/api/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, flag: fl })
      }).then(function (r) { return r.json(); }).then(function (j) {
        var t = (j.ok ? '✅ ' : '❌ ') + (j.msg || '');
        if (j.ok && j.next && j.next.path) {
          t += ' 下一题：<a href="' + j.next.path + '">' + j.next.title + '</a>';
        }
        show(t);
      }).catch(function (e) { show('提交失败：' + e); });
    });
  }
  function bindAll() {
    var forms = document.querySelectorAll('form[action="/api/verify"]');
    for (var i = 0; i < forms.length; i++) bind(forms[i]);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindAll);
  } else {
    bindAll();
  }
})();
