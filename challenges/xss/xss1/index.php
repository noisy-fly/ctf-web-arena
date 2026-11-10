<?php
/* ============================================================
 * 教学靶场 · xss1 反射型 XSS
 * 漏洞点：$_GET['q'] 未经 htmlspecialchars 直接 echo（反射）
 * ============================================================ */
$q = $_GET['q'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>xss1 · 反射型 XSS</title>
<script src="/assets/boot.js"></script>
<link rel="stylesheet" href="/assets/style.css">
<link rel="stylesheet" href="/assets/challenge.css">
<script src="/assets/challenge.js" data-chal="xss1" defer></script>
</head>
<body>
<header class="topbar">
  <div class="logo"><span class="badge">🎭</span> 站内搜索</div>
  <div class="spacer"></div>
  <a class="tag" href="/">关卡地图</a>
</header>
<div class="chal-wrap">
  <div class="chal-env">
    <h1>🔍 搜点什么</h1>
    <p class="env-sub">搜索结果页会把你的关键词展示回来。如果你搜的不是词语，而是一段代码呢？</p>

    <div class="env-card">
      <form method="get" action="/xss1">
        <div class="env-row">
          <input type="search" name="q" placeholder="输入关键词，回车搜索">
          <button class="btn" type="submit">搜索</button>
        </div>
      </form>
    </div>

    <?php if ($q !== ''): ?>
    <div class="env-result">
      <h3>搜索结果</h3>
      <!-- ★ 危险代码 ★：$q 未经转义直接回显 -->
      <p>你搜索的关键词是：<b><?= $q ?></b></p>
      <p style="color:var(--fg-soft)">没有找到与该关键词匹配的内容。</p>
    </div>
    <?php endif; ?>

    <details class="src-details" style="margin-top:16px">
      <summary style="cursor:pointer;color:var(--fg-soft)">🔍 查看本题关键源码</summary>
      <pre>&lt;?php
$q = $_GET['q'] ?? '';
echo "&lt;p&gt;你搜索的关键词是：&lt;b&gt;$q&lt;/b&gt;&lt;/p&gt;";   // ← 未调用 htmlspecialchars，原样回显</pre>
    </details>

    <p style="color:var(--fg-soft);font-size:.8rem;margin-top:14px">⚠️ 本页为教学靶场：Victim Bot 是对浏览器执行的模拟判定（详见右侧面板说明），真实攻击中该向量将窃取用户 Cookie。</p>
  </div>
</div>
</body>
</html>
