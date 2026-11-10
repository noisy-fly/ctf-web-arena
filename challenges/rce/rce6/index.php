<?php
// ============================================================
// rce6 · 替代入口 B：可控回调 call_user_func（PHP 7/8 通用）
// 部署：本文件改名 index.php（当 rce6 容器是 PHP 8.x 时使用）
// 漏洞：函数名与参数均来自用户 → 可调用任意单参函数
// ============================================================
$func = isset($_GET['func']) ? $_GET['func'] : '';
$arg  = isset($_GET['arg'])  ? $_GET['arg']  : '';
$result = null; $error = null;
if ($func !== '') {
    if (!is_callable($func)) {
        $error = "函数 {$func} 不存在或不可调用";
    } else {
        ob_start();
        call_user_func($func, $arg);        // ← 漏洞：函数名可控
        $result = ob_get_clean();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>rce6 · 可控回调代码执行（PHP8 替代入口）| CTF 教学靶场</title>
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
input[type=text]{width:60%;min-width:200px;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
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
<span class="badge">RCE · 代码执行 · 第 6 关（PHP8 替代入口）</span>
<h1>万能调试网关 —— 可控回调 call_user_func</h1>
<div class="meta">难度：★★★☆☆ 进阶 | 关卡 ID：<code>rce6</code> · 运行时：PHP 7/8 通用</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>这是 rce6 的 PHP8 兼容变体：一个"调试网关"接收 <code>func</code>（函数名）和 <code>arg</code>（参数）两个参数，然后在服务器上调用对应的 PHP 函数。</p>
<p><code>/e</code> 和字符串 <code>assert</code> 都进了历史博物馆，但"函数名可控"这个洞至今仍然遍地都是。目标：读出当前目录 <code>flag.php</code> 里的 flag。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>func：<input type="text" name="func" placeholder="例如 md5" value="<?= htmlspecialchars($func, ENT_QUOTES) ?>"></label>
<label>arg：<input type="text" name="arg" placeholder="例如 test" value="<?= htmlspecialchars($arg, ENT_QUOTES) ?>"></label>
<button type="submit">调用</button>
</form>
<?php if ($error !== null): ?>
<div class="errbox"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php elseif ($result !== null): ?>
<div class="result"><div class="t">调用输出</div>
<pre><?= htmlspecialchars($result, ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码：</p>
<pre>&lt;?php
call_user_func($_GET['func'], $_GET['arg']);   // 函数名可控
?&gt;</pre>
<p>函数名一旦可控，攻击者可以直接点名危险函数：</p>
<table>
<tr><th>func</th><th>arg</th><th>效果</th></tr>
<tr><td><code>phpinfo</code></td><td>（任意）</td><td>环境侦察</td></tr>
<tr><td><code>highlight_file</code></td><td><code>flag.php</code></td><td>读源码</td></tr>
<tr><td><code>system</code></td><td><code>cat flag.php</code></td><td>命令执行</td></tr>
</table>
<p>同族漏洞模式（审计关键词）：回调参数来自用户的 <code>array_map</code>、<code>array_filter</code>、<code>usort</code>、<code>call_user_func_array</code>、<code>ob_start</code>、<code>register_shutdown_function</code>、以及动态函数调用 <code>$func($arg)</code>。</p>
<p>和 <code>preg_replace /e</code>、<code>assert('字符串')</code> 一脉相承：本质都是<b>让攻击者决定"执行什么"</b>。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1</summary><p>func 框可以填任何 PHP 函数名。先试 <code>func=phpinfo</code>。</p></details>
<details><summary>提示 2</summary><p>想读文件：PHP 里有现成的单参数函数 <code>highlight_file</code> / <code>show_source</code> / <code>readfile</code>。</p></details>
<details><summary>提示 3：payload</summary><p><code>?func=highlight_file&arg=flag.php</code> 或 <code>?func=system&arg=cat flag.php</code></p></details>
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
