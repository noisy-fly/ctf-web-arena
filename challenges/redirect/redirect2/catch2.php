<?php
// catch2.php —— 模拟"攻击者的服务器"（v2）
// 与 redirect1 的 catch.php 相同角色：收到什么就记什么
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>attacker2.example · 攻击者的服务器（模拟）</title>
<style>
body{margin:0;font-family:"Segoe UI",system-ui,sans-serif;background:#1a0b0b;color:#fde2e2;line-height:1.8;padding:32px 16px}
.w{max-width:820px;margin:0 auto}
h1{font-size:22px;color:#f87171;border-bottom:2px dashed #7f1d1d;padding-bottom:8px}
pre{background:#120404;border:1px solid #7f1d1d;border-radius:8px;padding:14px;overflow:auto}
.tag{display:inline-block;background:#7f1d1d;color:#fecaca;font-size:12px;padding:2px 10px;border-radius:999px;margin-left:8px}
</style>
</head>
<body>
<div class="w">
<h1>⚠️ attacker2.example<span class="tag">模拟攻击者服务器 catch2.php</span></h1>
<p>我是攻击者 v2。哪怕对方"加了白名单"，只要你的 URL 能让浏览器连到我这里，我照样全量落库：</p>
<pre>GET <?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES) ?>

收到的所有 GET 参数：
<?php
if (empty($_GET)) {
    echo "（无参数——攻击者只记录到一个普通访客）";
} else {
    foreach ($_GET as $k => $v) {
        echo htmlspecialchars(sprintf('  %-16s = %s', $k, $v), ENT_QUOTES) . "\n";
    }
}
?></pre>
<?php if (isset($_GET['share_token'])): ?>
<p style="color:#fbbf24"><b>攻击者视角</b>：share_token 又到手了——白名单被 URL 结构里的"行李位"（userinfo/query/fragment）骗过了。</p>
<?php endif; ?>
<p><a href="index.php" style="color:#f87171">← 回到 redirect2</a></p>
</div>
<script>
// 攻击者的服务器也会读 fragment（hash）——OAuth 隐式模式的 token 就放在这里
if (location.hash.length > 1) {
    var m = location.hash.match(/share_token=([^&]+)/);
    if (m) {
        var p = document.createElement('pre');
        p.textContent = 'fragment 中解析到： share_token      = ' + decodeURIComponent(m[1]);
        document.querySelector('.w').insertBefore(p, document.querySelector('p[style]'));
    }
}
</script>
</body>
</html>
