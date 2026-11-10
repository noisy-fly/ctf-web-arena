<?php
// ============================================================
// ssrf2 · SSRF 之 file:// 协议读文件
// 故事：调试版工具，开发者放开了 file:// 协议方便"抓本地配置"，忘了关
// 考点：file:// 流包装器 + 协议白名单放宽的危害
// ============================================================
$url = isset($_GET['url']) ? trim($_GET['url']) : '';
$body = null; $error = null;
if ($url !== '') {
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, array('http', 'https', 'file'), true)) {   // ← 放开了 file
        $error = '本调试版支持 http/https/file 协议（其余已禁用）';
    } else {
        $raw = @file_get_contents($url);
        if ($raw === false) {
            $error = '读取失败（文件不存在或不可读）';
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
<title>ssrf2 · file:// 读文件 | CTF 教学靶场</title>
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
<span class="badge">SSRF · 第 2 关</span>
<h1>调试版预览工具 —— file:// 读文件</h1>
<div class="meta">难度：★☆☆☆☆ 入门 | 关卡 ID：<code>ssrf2</code> · 运行时：PHP</div>
</header>
<!-- OPS-CHECK: /flag_ssrf2.txt <?= @file_exists('/flag_ssrf2.txt')?'READY':'MISSING(复制 deploy/flag_ssrf2.txt 到 /)' ?> -->

<section class="card">
<h2>题目描述</h2>
<p>预览工具出了个"调试版"：为了方便开发抓取服务器本地配置，协议白名单里加上了 <code>file://</code>，上线时忘了去掉。</p>
<p>目标：读出服务器根目录 <code>/flag_ssrf2.txt</code> 里的 flag（顺带体会一下任意文件读取的威力）。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>URL：<input type="text" name="url" placeholder="http://… 或 file:///…" value="<?= htmlspecialchars($url, ENT_QUOTES) ?>"></label>
<button type="submit">预览</button>
</form>
<?php if ($error !== null): ?>
<div class="errbox">⚠️ <?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php elseif ($body !== null): ?>
<div class="result"><div class="t">读取 <?= htmlspecialchars($url, ENT_QUOTES) ?> 的内容</div>
<pre><?= htmlspecialchars(substr((string)$body, 0, 4000), ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码：</p>
<pre>&lt;?php
$scheme = strtolower(parse_url($url, PHP_URL_SCHEME));
if (!in_array($scheme, ['http','https','file'])) { 拒绝; }   // ← file 被放行
echo file_get_contents($url);
?&gt;</pre>
<p><b>file:// 流包装器</b>：一旦 SSRF 的取回函数接受 file 协议，SSRF 就退化成<b>任意文件读取</b>：</p>
<table>
<tr><th>payload</th><th>读到的内容</th></tr>
<tr><td><code>file:///etc/passwd</code></td><td>系统用户表（验证任意读）</td></tr>
<tr><td><code>file:///flag_ssrf2.txt</code></td><td>flag</td></tr>
<tr><td><code>file:///var/www/html/ssrf2/index.php</code></td><td>本站源码（源码审计入口）</td></tr>
<tr><td><code>file://localhost/etc/passwd</code></td><td>file:// 的 host 位写 localhost 等价</td></tr>
</table>
<p>格式注意：Linux 绝对路径要写<b>三个斜杠</b>：`file:///` + 路径（前两个是协议分隔符，第三个是根目录）。</p>
<p>能读什么就能"看"什么：源码里的数据库密码、/proc 下的进程信息、部署密钥……SSRF 的危害从"内网探测"直接升级到"信息泄露"。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：白名单里有什么</summary><p>提交一个 <code>ftp://x</code> 之类的 URL，看报错信息——它会告诉你本调试版允许哪些协议。</p></details>
<details><summary>提示 2：file:// 怎么写</summary><p>格式 <code>file:///绝对路径</code>。先用 <code>file:///etc/passwd</code> 练手。</p></details>
<details><summary>提示 3：payload</summary><p><code>?url=file:///flag_ssrf2.txt</code></p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../ssrf1">← 上一关：ssrf1</a><a href="../ssrf3">下一关：ssrf3 · IP 黑名单绕过 →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssrf2&flag='+encodeURIComponent(v)})
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
