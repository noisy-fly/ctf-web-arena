<?php
// ============================================================
// rce6 · 替代入口 A：assert() 代码执行（PHP 5 / PHP 7 可用）
// 部署：本文件改名 index.php（当 rce6 容器是 PHP 7.x 时使用）
// 需要 zend.assertions=1（docker php 官方镜像默认即为 1）
// 漏洞：assert($_GET['code']) —— 字符串断言会被 eval
// ============================================================
$code_param = isset($_GET['code']) ? $_GET['code'] : '';
$result = null;
$unsupported = (PHP_VERSION_ID >= 80000);   // PHP 8 起字符串断言不再求值
$assertions_off = (intval(ini_get('zend.assertions')) !== 1);
if ($code_param !== '' && !$unsupported && !$assertions_off) {
    ob_start();
    assert($code_param);                    // ← 漏洞：字符串断言 = eval
    $result = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>rce6 · assert 代码执行（PHP7 替代入口）| CTF 教学靶场</title>
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
<span class="badge">RCE · 代码执行 · 第 6 关（PHP7 替代入口）</span>
<h1>古董校验器 —— assert() 代码执行</h1>
<div class="meta">难度：★★★☆☆ 进阶 | 关卡 ID：<code>rce6</code> · 运行时：PHP 5/7（需 zend.assertions=1）</div>
</header>

<?php if ($unsupported): ?>
<div class="card" style="border-color:#7f1d1d"><h2 style="color:#f87171;border-color:#f87171">⚠️ 运行环境不符</h2>
<p>当前 PHP <?= PHP_VERSION ?>：字符串断言自 PHP 8.0 起不再求值，本入口不可解。请部署本目录 <code>alt_call_user_func_php8.php</code>（改名 index.php）。</p></div>
<?php elseif ($assertions_off): ?>
<div class="card" style="border-color:#7f1d1d"><h2 style="color:#f87171;border-color:#f87171">⚠️ 配置不符</h2>
<p>当前 zend.assertions=<?= htmlspecialchars(ini_get('zend.assertions'), ENT_QUOTES) ?>。本关需要 <code>zend.assertions=1</code>（php.ini 或启动参数 <code>-d zend.assertions=1</code>）。</p></div>
<?php endif; ?>

<section class="card">
<h2>题目描述</h2>
<p>这是 rce6 的 PHP7 兼容变体：老代码里用 <code>assert()</code> 做"调试断言"，而断言内容来自用户输入。</p>
<p>在 PHP 5/7 里，传给 <code>assert()</code> 的<b>字符串会被当作 PHP 代码求值</b>（7.x 会打 Deprecated 警告，但照样执行；PHP 8 彻底移除）。目标：读出当前目录 <code>flag.php</code> 里的 flag。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>断言表达式：<input type="text" name="code" placeholder="例如 1==1" value="<?= htmlspecialchars($code_param, ENT_QUOTES) ?>"></label>
<button type="submit">断言</button>
</form>
<?php if ($result !== null): ?>
<div class="result"><div class="t">断言执行输出</div>
<pre><?= htmlspecialchars($result, ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码：</p>
<pre>&lt;?php
assert($_GET['code']);   // 字符串断言 = eval($_GET['code'])
?&gt;</pre>
<table>
<tr><th>输入</th><th>效果</th></tr>
<tr><td><code>1==1</code></td><td>正常断言：为真，无输出</td></tr>
<tr><td><code>phpinfo()</code></td><td>phpinfo 被执行（代码执行成立）</td></tr>
<tr><td><code>system('id')</code></td><td>表达式形式执行命令，输出直接可见</td></tr>
</table>
<p>与 <code>preg_replace /e</code> 一样，assert 字符串断言也是"字符串当代码"的历史包袱：PHP 7 废弃、8 移除。审计老站（PHP5/7）时，<code>assert($</code> 与 <code>preg_replace.*\/e</code> 都是要重点排查的 RCE 点。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1</summary><p>输入的字符串会被 assert 求值，先试 <code>?code=phpinfo()</code>。</p></details>
<details><summary>提示 2</summary><p>assert 求值的是"表达式"，<code>system('cat flag.php')</code> 本身就是合法表达式。</p></details>
<details><summary>提示 3：payload</summary><p><code>?code=system('cat flag.php')</code> 或 <code>?code=highlight_file('flag.php')</code></p></details>
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
