<?php
// ============================================================
// ssrf1 · 基础 SSRF
// 漏洞：?url= 由服务器发起请求（file_get_contents），可指向任意 http(s) 目标
// 内网目标：internal.php（管理接口，仅允许本机 127.0.0.1 访问）
// ============================================================
$url = isset($_GET['url']) ? trim($_GET['url']) : '';
$body = null; $error = null;
if ($url !== '') {
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, array('http', 'https'), true)) {
        $error = '仅支持 http:// 和 https:// 协议';
    } else {
        $ctx = stream_context_create(array('http' => array('timeout' => 4, 'ignore_errors' => true)));
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            $error = '请求失败（目标不可达或超时）';
        } else {
            $body = $raw;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ssrf1 · 基础 SSRF | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#a855f7;color:#1b0630;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
input[type=text]{width:75%;min-width:260px;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
button:hover{filter:brightness(1.15)}
.result{background:#0b1220;border:1px solid #27364d;border-radius:8px;margin-top:12px}
.result .t{padding:6px 12px;border-bottom:1px solid #27364d;color:var(--dim);font-size:12px}
.result pre{border:0;margin:0}
.errbox{background:#3b1519;border:1px solid #7f1d1d;color:#fca5a5;border-radius:8px;padding:10px 14px;margin-top:12px}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">SSRF · 第 1 关</span>
<h1>链接预览小工具 —— 基础 SSRF</h1>
<div class="meta">难度：★☆☆☆☆ 入门 | 关卡 ID：<code>ssrf1</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>运营想要一个"贴个链接就能预览摘要"的小工具：你提交 URL，<b>服务器</b>替你去访问并把内容拿回来显示。</p>
<p>已知情报（见 <a href="robots.txt" style="color:var(--acc)">robots.txt</a>）：这台服务器上似乎有个管理接口 <code>internal.php</code>，"仅允许本机访问"。你的浏览器当然不是本机……但你能让谁替你访问？</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>URL：<input type="text" name="url" placeholder="http://example.com" value="<?= htmlspecialchars($url, ENT_QUOTES) ?>"></label>
<button type="submit">预览</button>
</form>
<?php if ($error !== null): ?>
<div class="errbox">⚠️ <?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php elseif ($body !== null): ?>
<div class="result"><div class="t">服务器请求 <?= htmlspecialchars($url, ENT_QUOTES) ?> 的响应</div>
<pre><?= htmlspecialchars(substr((string)$body, 0, 4000), ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码：</p>
<pre>&lt;?php
$url = $_GET['url'];
if (协议是 http/https) {
    echo file_get_contents($url);     // ← 由"服务器"发起请求
}
?&gt;</pre>
<p><b>SSRF（Server-Side Request Forgery，服务端请求伪造）</b>：攻击者借服务器的手，去访问"服务器自己能访问、而你不能"的目标：</p>
<ul>
<li>本机回环：127.0.0.1 上的内部服务（本关）</li>
<li>内网其他机器：10.x / 172.16-31.x / 192.168.x（真实环境重灾区）</li>
<li>云元数据：169.254.169.254（云上可拿临时凭证）</li>
<li>其他协议：file://（下一关）、gopher://、dict://（ssrf4）</li>
</ul>
<p>判断依据：响应到底是谁发出的？——你提交的 URL 由<b>服务器</b>去请求（服务器视角里，请求方 IP 是服务器自己），所以"仅允许本机"的接口会被它"自己"访问成功。</p>
<p>常见触发函数：`file_get_contents`、`fsockopen`、`curl_exec`、`Guzzle` 等 HTTP 客户端库。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先玩明白工具</summary><p>预览一个外网 URL（如 <code>http://example.com</code>），确认拿回的内容是"服务器视角"的响应。</p></details>
<details><summary>提示 2：internal.php 在哪、怎么访问</summary><p>它就在本站目录里，路径是 <code>/ssrf1/internal.php</code>（对应服务器上的 <code>http://127.0.0.1/ssrf1/internal.php</code>）。直接用浏览器访问会被"仅本机"拦住——让预览工具替你访问。</p></details>
<details><summary>提示 3：payload</summary><p><code>?url=http://127.0.0.1/ssrf1/internal.php</code><br>（若每题独立容器/root 挂载，也可能是 <code>?url=http://127.0.0.1/internal.php</code>——两种都试）</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../lfi5">← 上一关：lfi5</a><a href="../ssrf2">下一关：ssrf2 · file:// 读文件 →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssrf1&flag='+encodeURIComponent(v)})
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
