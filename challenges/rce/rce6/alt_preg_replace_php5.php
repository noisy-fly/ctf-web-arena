<?php
// ============================================================
// rce6 · preg_replace /e 代码执行
// ⚠️ /e 修饰符在 PHP 5.5 起废弃、PHP 7.0 起移除——本入口必须跑在 PHP 5.6！
//    若容器是 PHP 7 → 部署本目录 alt_assert_php7.php（改名 index.php）
//    若容器是 PHP 8 → 部署本目录 alt_call_user_func_php8.php（改名 index.php）
// 漏洞：preg_replace('/\[(.+?)\]/e', '\1', $tpl) —— 捕获组被 eval
// ============================================================
$tpl_param = isset($_GET['tpl']) ? $_GET['tpl'] : '';
$result = null;
$unsupported = (PHP_VERSION_ID >= 70000);
if ($tpl_param !== '') {
    // PHP7+ 会 warning 并返回 null（用 @ 压住，页面继续渲染并给出环境提示）
    $result = @preg_replace('/\[(.+)\]/e', '\\1', $tpl_param);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>rce6 · preg_replace /e 代码执行 | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#0ea5e9;color:#04121f;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
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
.warnbox{background:#3b2e09;border:1px solid #713f12;color:#fde68a;border-radius:8px;padding:10px 14px;margin-top:12px}
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
<span class="badge">RCE · 代码执行 · 第 6 关</span>
<h1>古董模板引擎 —— preg_replace /e</h1>
<div class="meta">难度：★★★☆☆ 进阶 | 关卡 ID：<code>rce6</code> · 运行时：<b>PHP 5.6</b>（特殊）</div>
</header>

<?php if ($unsupported): ?>
<div class="card" style="border-color:#7f1d1d">
<h2 style="color:#f87171;border-color:#f87171">⚠️ 运行环境不符</h2>
<p>当前 PHP 版本：<b><?= PHP_VERSION ?></b>。<code>preg_replace</code> 的 <code>/e</code> 修饰符自 PHP 7.0 起<b>已被彻底移除</b>（5.5 废弃），本关在此环境不可解。</p>
<p>集成说明：请为本关单独部署 <code>php:5.6-fpm</code>；或改用本目录 <code>alt_assert_php7.php</code>（PHP7，assert 版）/ <code>alt_call_user_func_php8.php</code>（PHP8，回调版），把选中的文件改名成 <code>index.php</code> 部署，flag 不变。</p>
</div>
<?php endif; ?>

<section class="card">
<h2>题目描述</h2>
<p>公司官网有个 2010 年留下的"新闻模板引擎"：正文里写在 <code>[ ]</code> 里的内容会被"动态求值"后替换进去。</p>
<p>输入 <code>今天促销，全场[1+1]折</code> 会渲染成 <code>...全场2折</code>。目标：读出当前目录 <code>flag.php</code> 里的 flag。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>模板文本：<input type="text" name="tpl" placeholder="例如 全场[1+1]折" value="<?= htmlspecialchars($tpl_param, ENT_QUOTES) ?>"></label>
<button type="submit">渲染</button>
</form>
<?php if ($tpl_param !== ''): ?>
<div class="result"><div class="t">渲染结果（替换值）</div>
<pre><?php
if ($result !== null) { echo htmlspecialchars(strval($result), ENT_QUOTES); }
echo "\n--- 求值过程的直接输出（phpinfo/system 等 echo 型函数）见上方/本框 ---";
?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码（注意正则结尾的 <code>e</code>）：</p>
<pre>&lt;?php
echo preg_replace('/\[(.+)\]/e', '\\1', $_GET['tpl']);
?&gt;</pre>
<p><code>/e</code>（evaluate）修饰符的工作流程：</p>
<ol>
<li>正则捕获 <code>[ ]</code> 里的内容到后向引用 <code>\1</code>；</li>
<li>把 <code>\1</code> 替换进 replacement 字符串；</li>
<li>把<b>整个 replacement 当作 PHP 代码 eval</b>，用其返回值做最终替换。</li>
</ol>
<p>所以你写在 <code>[ ]</code> 里的任何 PHP 代码都会被执行——这就是"曲线 eval"。也正因为它等价于远程代码执行，PHP 5.5 将其废弃、7.0 移除，官方替代是 <code>preg_replace_callback()</code>。</p>
<p><b>/e 的两个经典坑</b>（实战高频）：</p>
<table>
<tr><th>payload</th><th>结果</th><th>原因</th></tr>
<tr><td><code>[phpinfo();]</code></td><td>✅ 执行</td><td>没有引号，什么都不触发</td></tr>
<tr><td><code>[system('cat flag.php');]</code></td><td>❌ Failed evaluating</td><td>/e 会对捕获组做 <b>addslashes</b>：引号被转义成 <code>\'</code>，代码语法被打碎</td></tr>
<tr><td><code>[`cat flag.php`;]</code></td><td>✅ 执行</td><td>反引号（shell_exec）<b>不需要引号</b>，绕开转义</td></tr>
<tr><td><code>[system($_GET[cmd]);]</code> + <code>&amp;cmd=cat flag.php</code></td><td>✅ 执行</td><td>不含引号；参数从 GET 取。注意本关正则是<b>贪婪</b>匹配，否则 <code>$_GET[cmd]</code> 里的 <code>]</code> 会让捕获提前截断</td></tr>
</table>
<p>同类"历史遗留"代码执行点：<code>assert('字符串')</code>（PHP7 起废弃、PHP8 移除求值，见本目录 alt 版）、<code>create_function()</code>（7.2 废弃、8.0 移除）。它们共同说明：<b>凡是"把字符串当代码"的接口，都是 RCE</b>。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：[1+1] 变成 2 说明什么？</summary><p>括号里的内容不是"文本替换"，而是被<b>求值</b>了。再试试 <code>[phpinfo()]</code>（页面出现 phpinfo 即代码执行成立）。</p></details>
<details><summary>提示 2：/e 的求值语义</summary><p>捕获组就是 replacement 的一部分，而 /e 会 eval 整个 replacement——所以 <code>[你的PHP语句]</code> 里的语句会被直接执行。</p></details>
<details><summary>提示 3：payload</summary><p>引号会被 /e 转义，所以用<b>反引号</b>或 <b>$_GET 传参</b>：<br><code>?tpl=[`cat flag.php`;]</code><br><code>?tpl=[system($_GET[cmd]);]&cmd=cat flag.php</code></p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../rce5">← 上一关：rce5</a><a href="../lfi1">下一关：lfi1 · 本地文件包含 →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=rce6&flag='+encodeURIComponent(v)})
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
