<?php
// cors2 · index —— 教学页（漏洞在 quote.php，实验在 lab.html）
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>cors2 · null Origin 翻车 | CTF 教学靶场</title>
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
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
a{color:var(--acc)}
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
.pathbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:14px}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">CORS · 第 2 关</span>
<h1>星帆行情 · 合作方报价接口</h1>
<div class="meta">难度：★★★☆☆ 进阶 | 关卡 ID：<code>cors2</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>星帆行情的 <code>quote.php</code> 是<b>合作方专享</b>报价接口：需要合作方密钥（嗯，密钥已经"泄露在对接文档"里了，页面底部有），返回的内容里藏着一句重要的话（flag）。</p>
<p>上关（cors1）安全组明确要求过"拒绝 null Origin"。这次 v2 的开发者在两处"好心"留了门：</p>
<ul>
<li>为了"支持离线打包的 H5 行情挂件"，放行了 <code>Origin: null</code>；</li>
<li>合作域名的判断写成了"<b>以 stars-partner.com 结尾就放行</b>"（没有点边界）。</li>
</ul>
<p>正常浏览器里你（127.0.0.1 的页面）并不是合作方源——但 <code>null</code> 源谁都能当：<b>沙盒 iframe</b>（<code>&lt;iframe sandbox="allow-scripts"&gt;</code>）、本地 file:// 页面、某些跨源重定向，Origin 都是 <code>null</code>。</p>
<div class="pathbox">🗝️ 合作方密钥（对接文档"泄露"）：<code>STARPARTNER-2024</code> ｜ 实验室：<a href="lab.html" target="_blank">lab.html（沙盒 iframe 实验台）</a></div>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>quote.php 的 CORS 判断：</p>
<pre>if ($origin === 'null') {                          // ① null 也放行
    header('Access-Control-Allow-Origin: null');
} else {
    $host = parse_url($origin, PHP_URL_HOST);
    if (substr($host, -17) === 'stars-partner.com') // ② 后缀匹配无点边界
        header('Access-Control-Allow-Origin: ' . $origin);
}</pre>
<p><b>① null Origin 从哪来</b>：</p>
<ul>
<li>沙盒 iframe：<code>&lt;iframe sandbox="allow-scripts" srcdoc="…"&gt;</code>（不含 allow-same-origin）→ 里面文档的源是 <code>null</code>——<b>任何人</b>的页面都能造出它；</li>
<li><code>file://</code> 本地页面；</li>
<li>跨源 <code>fetch()</code>/重定向链中的某些请求。</li>
</ul>
<p>反射 <code>ACAO: null</code> 后，null 源里的 JS 就能读响应——"我永远不接待 null"才是安全的默认（cors1 正是这么做的）。</p>
<p><b>② 后缀匹配的洞</b>：<code>evil-stars-partner.com</code> 同样"以 stars-partner.com 结尾"。正确写法是精确白名单，或带点边界的 <code>endsWith('.' . $allow)</code> 且校验注册域。本机没有 DNS 可以注册 evil 域名，用 curl 伪造 Origin 头即可演示服务端行为：</p>
<pre>curl -si -H "Origin: https://evil-stars-partner.com" "http://&lt;host&gt;/cors2/quote.php?key=STARPARTNER-2024"
→ Access-Control-Allow-Origin: https://evil-stars-partner.com   ← 放行了"伪合作方"</pre>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先确认接口要什么</summary><p><code>curl -s "http://&lt;host&gt;/cors2/quote.php"</code> → 401 invalid partner key；带上 <code>?key=STARPARTNER-2024</code> → 正常返回。但你要的是"从别的源读它"。</p></details>
<details><summary>提示 2：什么页面能产生 null Origin</summary><p>沙盒 iframe：<code>&lt;iframe sandbox="allow-scripts" srcdoc="…"&gt;</code>。srcdoc 里的脚本源是 null，它 fetch 相对路径 <code>quote.php</code> 仍是跨源请求（null 源 vs 本站源），能否读响应取决于 CORS 头。</p></details>
<details><summary>提示 3：去实验室跑</summary><p>打开 <a href="lab.html" target="_blank">lab.html</a>：按钮②"沙盒 iframe 调 quote.php"就是完整利用——iframe 里的 null 源脚本 fetch 报价接口，服务端反射了 null，脚本读到 JSON 后 postMessage 回父页面，flag 显示在实验台。按钮③是同一脚本打"已修复"的 fixed_quote.php（会 TypeError，作为对照）。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../cors1">← 上一关：cors1</a><a href="../crlf1">下一关：crlf1 · CRLF 响应头注入 →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=cors2&flag='+encodeURIComponent(v)})
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
