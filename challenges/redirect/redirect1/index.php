<?php
// ============================================================
// redirect1 · 开放重定向基础
// 漏洞：jump.php 的 ?url= 未做任何校验，302 跳向任意地址，
//       且"分享奖励"逻辑会把专属 share_token（flag）拼在目标 URL 后面
// catch.php 模拟"攻击者的服务器"，用来接收被泄露的 token
// ============================================================
$url = isset($_GET['url']) ? $_GET['url'] : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>redirect1 · 开放重定向基础 | CTF 教学靶场</title>
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
input[type=text]{width:75%;min-width:260px;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
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
<span class="badge">开放重定向 · 第 1 关</span>
<h1>星桥快递 · 快捷跳转</h1>
<div class="meta">难度：★☆☆☆☆ 入门 | 关卡 ID：<code>redirect1</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>星桥快递官网做了个"快捷跳转"服务：客服发链接、活动页互相导流都靠 <code>jump.php?url=目标</code> 一键 302。</p>
<p>最近运营加了"<b>好友分享奖励</b>"：凡是经由跳转链接去注册的好友，双方都能拿 10 元寄件券——所以系统会自动把<b>你的专属 share_token</b> 拼在目标地址后面，方便统计。</p>
<p>页面上还有一个 <a href="catch.php" target="_blank"><code>catch.php</code></a>——别误会，它<b>不是本站的页面</b>，是我们架来模拟"<b>攻击者的服务器</b>"的靶标。你的目标：让星桥把 <code>share_token</code> 亲手送到攻击者手里。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="jump.php">
<label>目标 URL：<input type="text" name="url" placeholder="https://example.com/some-page" value="<?= htmlspecialchars($url, ENT_QUOTES) ?>"></label>
<button type="submit">跳转</button>
</form>
<p style="color:var(--dim);font-size:13px">也可以用 <code>curl -i "http://&lt;host&gt;/redirect1/jump.php?url=https://example.com"</code> 直接观察响应头。</p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关核心代码（<code>jump.php</code>）：</p>
<pre>&lt;?php
$url = $_GET['url'];                      // ← 用户完全可控，未做任何校验
header('Location: ' . $url . '?share_token=' . $TOKEN);   // ← 302 到任意地址，还捎上了机密 token
</pre>
<p><b>开放重定向（Open Redirect）</b>：应用把用户可控参数直接放进 <code>Location</code> 响应头，跳向任意目标。它本身"不碰服务器数据"，危害常被低估：</p>
<ul>
<li><b>可信钓鱼</b>：<code>star-express.com/jump.php?url=…</code> 的前缀是真官网，短信/邮件里的这种链接极难被普通人识破；</li>
<li><b>凭证泄露</b>：很多登录/SSO 流程会把 token、code 拼到回调地址后面——一旦回调可被改向，token 就进了攻击者的日志（本关利用点）；</li>
<li><b>绕过白名单</b>：一些站内"只允许跳白名单域"的逻辑，会被重定向器二次跳转绕过。</li>
</ul>
<p>判定标准很简单：<b>跳转目标必须由服务端的白名单决定，而不是由用户参数决定</b>。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先看清楚它到底跳去哪</summary><p>用 <code>curl -i "http://&lt;host&gt;/redirect1/jump.php?url=https://example.com/"</code>，看 302 响应里的 <code>Location</code> 头——除了你给的目标，后面还多了个参数。</p></details>
<details><summary>提示 2：share_token 是跟着目标走的</summary><p>题目说了"系统会自动把 share_token 拼在目标 URL 后面"。目标可以是任何网站——那 token 就会被"送"到任何网站。</p></details>
<details><summary>提示 3：payload</summary><p>把目标指到模拟攻击者服务器：<code>jump.php?url=http://&lt;host&gt;/redirect1/catch.php</code>（浏览器里走一遍，或 <code>curl -i -L</code> 跟随跳转），看看"攻击者"收到了什么。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="#">← 本分类第一关</a><a href="../redirect2">下一关：redirect2 · 白名单绕过 →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=redirect1&flag='+encodeURIComponent(v)})
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
