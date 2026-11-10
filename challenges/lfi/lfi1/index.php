<?php
// ============================================================
// lfi1 · 基础本地文件包含（LFI）
// 漏洞：include('pages/' . $_GET['page'])，page 可控且未过滤 ../
// 考点：目录穿越读取 web 目录之外的文件
// ============================================================
$page = isset($_GET['page']) ? $_GET['page'] : 'home.php';
$included = null; $not_found = false;
if ($page !== '') {
    $target = 'pages/' . $page;                 // ← 拼接前缀，未过滤
    if (@file_exists($target)) {
        ob_start();
        include($target);                        // ← 漏洞：包含任意路径
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
<title>lfi1 · 本地文件包含 | CTF 教学靶场</title>
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
<span class="badge">文件包含 · 第 1 关</span>
<h1>企业官网 —— 本地文件包含（LFI）</h1>
<div class="meta">难度：★☆☆☆☆ 入门 | 关卡 ID：<code>lfi1</code> · 运行时：PHP</div>
</header>
<!-- OPS-CHECK: /flag_lfi1.txt <?= @file_exists('/flag_lfi1.txt') ? 'READY' : 'MISSING(集成时把 deploy/flag_lfi1.txt 复制到容器根目录)' ?> -->

<section class="card">
<h2>题目描述</h2>
<p>一个小企业官网用"动态页面"技术：导航栏的每个链接都是 <code>?page=xxx.php</code>，后端把对应文件包含进来渲染。</p>
<p>开发把所有页面放在 <code>pages/</code> 目录里，并天真地以为这样就安全了。目标：读到服务器<b>根目录</b>下 <code>/flag_lfi1.txt</code> 里的 flag。</p>
<nav class="pages-nav" style="margin-top:8px">
<a href="?page=home.php">首页</a><a href="?page=about.php">关于我们</a><a href="?page=news.php">新闻</a>
</nav>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>page：<input type="text" name="page" placeholder="例如 about.php" value="<?= htmlspecialchars($page, ENT_QUOTES) ?>"></label>
<button type="submit">打开页面</button>
</form>
<?php if ($not_found): ?>
<div class="errbox">⚠️ include: 文件 <code>pages/<?= htmlspecialchars($page, ENT_QUOTES) ?></code> 不存在</div>
<?php elseif ($included !== null): ?>
<div class="result"><div class="t">include('pages/<?= htmlspecialchars($page, ENT_QUOTES) ?>') 的渲染结果</div>
<pre><?= htmlspecialchars($included, ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码：</p>
<pre>&lt;?php
include('pages/' . $_GET['page']);   // page 完全可控，没有过滤 ../
?&gt;</pre>
<p><b>本地文件包含（Local File Inclusion）</b>：include/require 的目标路径被用户控制时，攻击者可以让服务器包含<b>任意本地文件</b>：</p>
<ul>
<li>读敏感文件：源码、配置（数据库密码）、<code>/etc/passwd</code>、<code>/proc/self/environ</code>……</li>
<li>若被包含文件里有 PHP 代码（哪怕是日志、session），它<b>会被执行</b>（后面几关的主题）。</li>
</ul>
<p>核心技巧——<b>目录穿越（Path Traversal）</b>：</p>
<table>
<tr><th>输入</th><th>实际包含</th><th>说明</th></tr>
<tr><td><code>about.php</code></td><td><code>pages/about.php</code></td><td>正常功能</td></tr>
<tr><td><code>../flag_lfi1.txt</code></td><td><code>flag_lfi1.txt</code>（上一级）</td><td>跳出一层</td></tr>
<tr><td><code>../../../../../flag_lfi1.txt</code></td><td><code>/flag_lfi1.txt</code></td><td>连续跳到根目录；跳过头也无害（路径到根后"封顶"）</td></tr>
<tr><td><code>../../../../etc/passwd</code></td><td><code>/etc/passwd</code></td><td>验证穿越的经典目标</td></tr>
</table>
<p>URL 编码变体：<code>../</code> → <code>%2e%2e%2f</code> → <code>..%2f</code> → <code>%2e%2e/</code>（绕过只替换一次的过滤）。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：看看参数怎么用</summary><p>导航链接都是 <code>?page=xxx.php</code>。试试把 page 改成别的文件名，比如 <code>?page=../index.php</code>（能看到一堆源码被当页面执行/输出？）。</p></details>
<details><summary>提示 2：跳出去</summary><p><code>../</code> 表示上一级目录。web 目录在文件系统的深处，多写几个 <code>../</code> 一路跳到根目录 <code>/</code>。</p></details>
<details><summary>提示 3：payload</summary><p><code>?page=../../../../../flag_lfi1.txt</code>（从 pages/ 算起 5 层到根；多写几层 <code>../</code> 也无害）<br>顺手验证穿越：<code>?page=../../../../../etc/passwd</code></p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../rce6">← 上一关：rce6</a><a href="../lfi2">下一关：lfi2 · 读源码的姿势 →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=lfi1&flag='+encodeURIComponent(v)})
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
