<?php
// ============================================================
// cors1 · CORS 反射 Origin + 凭证
// 漏洞：api.php 把请求的 Origin 原样反射进 Access-Control-Allow-Origin，
//       并附带 Access-Control-Allow-Credentials: true
//       → 任何网站的 JS 都能【带着受害者 Cookie】读取 API 响应
// 安全边界教学：null Origin 被明确拒绝（v2 见 cors2 的翻车现场）
// 攻击者页面 attacker/attack.html 需部署在【另一端口】（不同 Origin、同 Site）
// ============================================================
session_start();
if (!isset($_SESSION['sid'])) { $_SESSION['sid'] = bin2hex(random_bytes(6)); }
$action = isset($_GET['action']) ? $_GET['action'] : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>cors1 · CORS 反射 Origin | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#818cf8;color:#111827;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
button:hover{filter:brightness(1.15)}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
.okbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:12px 14px;margin-top:12px}
a{color:var(--acc)}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">CORS · 第 1 关</span>
<h1>星语笔记 · 私密笔记 API</h1>
<div class="meta">难度：★★☆☆☆ 基础 | 关卡 ID：<code>cors1</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>星语笔记把"我的私密笔记"放在 API 里（<code>api.php</code>），<b>只有带着你的会话 Cookie 才能读</b>，里面记着一句很重要的话（flag）。</p>
<p>同源策略（SOP）本来保证：别的网站的 JS 就算请求了这个 API，也<b>读不到响应</b>。直到某天开发者为了"和合作方联调方便"，写了三行要命的代码……</p>
<p>实验流程（本机关键是体会<b>"别的源也能读"</b>）：</p>
<ol>
<li>先在本站<b>领取身份</b>（浏览器种下会话 Cookie）：<a href="?action=claim">点这里领取</a>，然后本页下方"我的笔记预览"可以正常读到 API。</li>
<li>打开<b>攻击者的页面</b>（部署在<b>另一端口</b>，与你的笔记站不同源）：本机自测为 <code>/cors1/attack.html</code>（启动：<code>cd attacker && php -S 127.0.0.1:8309</code>）。</li>
<li>在攻击者页面点"窃取"——看它的 JS 能不能把<b>你的</b>私密笔记读出来。</li>
</ol>
<?php if ($action === 'claim'): ?>
<div class="okbox">✅ 已为你签发会话：user-<?= htmlspecialchars($_SESSION['sid']) ?>（Cookie 已种下）。刷新本页或直接去攻击者页面。</div>
<?php endif; ?>
</section>

<section class="card">
<h2>实验区 · 我的笔记预览（同源，正常读）</h2>
<button onclick="readMine()">读取 api.php（同源 fetch，带 Cookie）</button>
<div id="mine" style="margin-top:10px;font-size:14px;color:var(--dim)">（点击后这里显示 API 响应）</div>
<script>
function readMine(){
  fetch('api.php', {credentials:'include'})
    .then(r=>r.json())
    .then(j=>{document.getElementById('mine').innerHTML =
      'user: <b>'+j.user+'</b> &nbsp; private_note: <b style="color:#4ade80">'+(j.private_note||j.error)+'</b>';})
    .catch(e=>{document.getElementById('mine').textContent='同源请求失败：'+e;});
}
</script>
<p style="color:var(--dim);font-size:13px">curl 视角：<code>curl -s -H "Origin: http://evil.example" -b "PHPSESSID=&lt;你的会话&gt;" -i http://&lt;host&gt;/cors1/api.php</code> 看 <code>Access-Control-Allow-Origin</code> 反射了什么。</p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>api.php 的致命三行：</p>
<pre>if (isset($_SERVER['HTTP_ORIGIN']) &amp;&amp; $_SERVER['HTTP_ORIGIN'] !== 'null') {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);   // ← 谁来反射谁
    header('Access-Control-Allow-Credentials: true');                    // ← 还允许带 Cookie
}</pre>
<p><b>CORS（跨源资源共享）</b>是" relaxing SOP"的官方机制：服务端用响应头声明"哪些源可以读我的响应"。</p>
<ul>
<li><code>Access-Control-Allow-Origin: &lt;Origin&gt;</code>：允许该源读取。<b>反射任意 Origin</b> = 对全世界开放读取权。</li>
<li><code>Access-Control-Allow-Credentials: true</code>：不仅允许读，还允许<b>带受害者 Cookie</b> 读——私密 API 的灾难组合。</li>
<li>规范本就禁止 <code>ACAO: *</code> 与 credentials 同时生效，于是"聪明"的开发者选择反射 Origin 绕过限制——这正是最常见的 CORS 漏洞成因。</li>
<li>本关明确拒绝 <code>null</code>（安全组提醒过）；下一关你会看到 null 是怎么翻车的。</li>
</ul>
<p>利用条件：受害者（有会话的人）访问攻击者页面 → 页面 JS <code>fetch('http://笔记站/api.php', {credentials:'include'})</code> → 浏览器带上 Cookie 发请求（请求本身永远发得出去）→ 响应头反射了攻击者源 → JS 拿到内容。SOP 防的是"读"，被 CORS 错配放开了。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先拿到自己的身份</summary><p><a href="?action=claim" target="_blank">领取身份</a>，在本页"我的笔记预览"确认 API 正常返回你的 private_note。</p></details>
<details><summary>提示 2：起一个"攻击者的网站"</summary><p>攻击者页面在 <code>attacker/attack.html</code>，用另一个端口跑起来就是另一个源：<code>cd attacker && php -S 127.0.0.1:8309</code>，然后浏览器开 <code>/cors1/attack.html</code>（部署环境：集成方已在另一端口提供同一页面）。</p></details>
<details><summary>提示 3：点"窃取"</summary><p>攻击者页面的 fetch 带了 <code>credentials:'include'</code>——你的 Cookie 随请求而去，而 api.php 把攻击者的 Origin 反射进了 ACAO，于是它的 JS 读到了<b>你的</b> private_note，就是 flag。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../xxe2">← 上一关：xxe2</a><a href="../cors2">下一关：cors2 · null Origin 翻车 →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=cors1&flag='+encodeURIComponent(v)})
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
