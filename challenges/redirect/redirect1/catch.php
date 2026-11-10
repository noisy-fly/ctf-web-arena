<?php
// catch.php —— 模拟"攻击者的服务器"
// 它与星桥官网毫无关系：只要有人把带参数的请求发过来，攻击者的访问日志里就留下了全部内容
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>attacker.example · 攻击者的服务器（模拟）</title>
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
<h1>⚠️ attacker.example<span class="tag">模拟攻击者服务器 catch.php</span></h1>
<p>我是攻击者。你（或某个网站）刚刚把请求发到了我的服务器上——下面是<b>我的访问日志</b>里多出来的内容（Query String 全量落库）：</p>
<pre>GET <?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES) ?>

收到的所有 GET 参数：
<?php
if (empty($_GET)) {
    echo "（无参数——目前攻击者只记录到一个普通访客）";
} else {
    foreach ($_GET as $k => $v) {
        echo htmlspecialchars(sprintf('  %-16s = %s', $k, $v), ENT_QUOTES) . "\n";
    }
}
?></pre>
<?php if (isset($_GET['share_token'])): ?>
<p style="color:#fbbf24"><b>攻击者视角</b>：一个 <code>share_token</code> 到账了。在真实场景里，这类 token 往往是登录态、邀请凭证、OAuth code——拿到它就能冒充受害者完成下一步操作。本关里，它就是你要的 flag。</p>
<?php else: ?>
<p style="color:#94a3b8">（提示：想让攻击者"有收获"，得先让某个网站把token 拼到 URL 里发过来。）</p>
<?php endif; ?>
<p><a href="index.php" style="color:#f87171">← 回到 redirect1</a></p>
</div>
</body>
</html>
