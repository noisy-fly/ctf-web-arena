<?php
// ============================================================
// lfi2 · php://filter 读源码
// 漏洞：include($_GET['page'])（无前缀、可控）
// 考点：PHP 文件被 include 时是"执行"，想看源码要用 php://filter
// ============================================================
$page = isset($_GET['page']) ? $_GET['page'] : 'pages/home.php';
$included = null; $not_found = false;
if ($page !== '') {
    if (@file_exists($page) || stream_is_local_wrapper_test($page)) {
        ob_start();
        include($page);                        // ← 漏洞：无前缀直接包含
        $included = ob_get_clean();
    } else {
        $not_found = true;
    }
}
// php:// 等流包装器不算"本地文件"，file_exists 对它们无意义，单独放行
function stream_is_local_wrapper_test($p) {
    return (bool)preg_match('#^php://#i', $p);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>lfi2 · php://filter 读源码 | CTF 教学靶场</title>
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
<span class="badge">文件包含 · 第 2 关</span>
<h1>重构后的官网 —— 用 php://filter 读源码</h1>
<div class="meta">难度：★☆☆☆☆ 入门 | 关卡 ID：<code>lfi2</code> · 运行时：PHP（无需特殊配置）</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>上关被打穿后，程序员"重构"了：现在直接 <code>include($_GET['page'])</code>（页面路径全靠前端传）。同时他把 flag 挪进了 <code>pages/secret_config.php</code> 的<b>变量</b>里，还特意不 echo——"看你怎么偷"。</p>
<p>目标：拿到 <code>pages/secret_config.php</code> 里的 flag。</p>
<nav class="pages-nav" style="margin-top:8px">
<a href="?page=pages/home.php">首页</a><a href="?page=pages/about.php">关于我们</a><a href="?page=pages/news.php">新闻</a>
</nav>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>page：<input type="text" name="page" placeholder="例如 pages/about.php" value="<?= htmlspecialchars($page, ENT_QUOTES) ?>"></label>
<button type="submit">打开页面</button>
</form>
<?php if ($not_found): ?>
<div class="errbox">⚠️ include: 文件 <code><?= htmlspecialchars($page, ENT_QUOTES) ?></code> 不存在</div>
<?php elseif ($included !== null): ?>
<div class="result"><div class="t">include('<?= htmlspecialchars($page, ENT_QUOTES) ?>') 的渲染结果</div>
<pre><?= htmlspecialchars($included, ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码：</p>
<pre>&lt;?php
include($_GET['page']);    // 无前缀、无过滤——比 lfi1 更裸奔
?&gt;</pre>
<p><b>关键矛盾</b>：PHP 文件被 include 时是<b>执行</b>，不是显示。<code>?page=pages/secret_config.php</code> 只会安静地定义几个变量，什么都看不到。</p>
<p><b>php://filter 流包装器</b>专治这个：让 PHP 在读取文件时先套一层过滤器（比如 base64 编码），于是"会被解析执行的 PHP 源码"变成了"一堆 base64 文本"安全输出：</p>
<pre>?page=php://filter/convert.base64-encode/resource=pages/secret_config.php</pre>
<ul>
<li><code>php://filter</code>：包装器名；<b>不需要</b>开 <code>allow_url_include</code>（它不算"远程包含"）。</li>
<li><code>/read= 或 /convert.base64-encode</code>：过滤器，把内容 base64 编码。</li>
<li><code>resource=...</code>：真正要读的文件，可相对可绝对（如 <code>resource=/etc/passwd</code>）。</li>
</ul>
<p>拿到 base64 后本地解码：<code>echo 'PD9waHAg...' | base64 -d</code>（或 CyberChef）。</p>
<p>常见用途：读源码找二次漏洞、读配置文件里的数据库密码、配合过滤器链的奇特利用（php_filter_chain 生成任意文件——进阶拓展）。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先试试直接包含</summary><p><code>?page=pages/secret_config.php</code>——页面毫无输出。因为它是"被执行"，变量定义完就完了，你什么也看不到。</p></details>
<details><summary>提示 2：让文件"读出来"而不是"跑起来"</summary><p>PHP 有个流包装器专门在"读文件"路上加一道处理（比如 base64 编码）。被编码后的内容不再是合法 PHP，include 只能把它们当文本吐出来。</p></details>
<details><summary>提示 3：payload</summary><p><code>?page=php://filter/convert.base64-encode/resource=pages/secret_config.php</code><br>把返回的 base64 解码即可看到 flag。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../lfi1">← 上一关：lfi1</a><a href="../lfi3">下一关：lfi3 · 包含变成执行 →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=lfi2&flag='+encodeURIComponent(v)})
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
