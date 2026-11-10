<?php
// ============================================================
// rce5 · eval 代码执行
// 漏洞：用户输入被拼进 eval('echo (' . $expr . ');')
// 考点：闭合原语句 + 注释尾部；代码执行 vs 命令执行
// ============================================================
$output = null;
$error  = null;
$expr   = isset($_GET['expr']) ? $_GET['expr'] : '';
if ($expr !== '') {
    try {
        ob_start();
        eval('echo (' . $expr . ');');   // ← 漏洞：输入直接进 eval
        $output = ob_get_clean();
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (ob_get_level()) { ob_end_clean(); }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>rce5 · eval 代码执行 | CTF 教学靶场</title>
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
<span class="badge">RCE · 代码执行 · 第 5 关</span>
<h1>迷你公式计算器 —— eval 代码执行</h1>
<div class="meta">难度：★☆☆☆☆ 入门 | 关卡 ID：<code>rce5</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>财务部提了个需求："给运营一个能算佣金公式的网页"。开发随手写了个公式计算器，输入 <code>1+2*3</code> 这类表达式就能得到结果。</p>
<p>提示：它的实现方式比你想的豪放得多。目标：读到当前目录 <code>flag.php</code> 里的 flag。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>公式：<input type="text" name="expr" placeholder="例如 1+2*3" value="<?= htmlspecialchars($expr, ENT_QUOTES) ?>"></label>
<button type="submit">计算</button>
</form>
<?php if ($error !== null): ?>
<div class="errbox">表达式执行出错：<?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php elseif ($output !== null): ?>
<div class="result"><div class="t">计算结果</div>
<pre><?= htmlspecialchars($output, ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码：</p>
<pre>&lt;?php
eval('echo (' . $_GET['expr'] . ');');
?&gt;</pre>
<p><b>eval 是"代码执行"</b>（执行的是 PHP 语言本身），比"命令执行"（system/exec 执行外部程序）更直接——拿到 eval 注入点等于拿到整个 PHP 解释器。</p>
<p>注入的关键动作：<b>闭合原语句 + 注释掉尾部</b>。原代码会把你的输入包进 <code>echo ( ... );</code>，所以：</p>
<table>
<tr><th>输入</th><th>eval 实际执行的代码</th><th>效果</th></tr>
<tr><td><code>1+2*3</code></td><td><code>echo (1+2*3);</code></td><td>正常计算：7</td></tr>
<tr><td><code>1);phpinfo();//</code></td><td><code>echo (1);phpinfo();//);</code></td><td>先结束 echo，执行 phpinfo，<code>//</code> 注释掉多余的 <code>);</code></td></tr>
<tr><td><code>1);system('id');//</code></td><td><code>echo (1);system('id');//);</code></td><td>从代码执行升级到命令执行</td></tr>
</table>
<p>常用原语：<code>phpinfo()</code>（环境侦察）、<code>print_r(scandir('.'))</code>（列目录）、<code>highlight_file()/show_source()</code>（读源码）、<code>system()/exec()</code>（跑命令）。</p>
<p>PHP 里同样危险的还有：<code>assert</code>（字符串断言）、<code>preg_replace + /e</code>（下一关）、<code>create_function</code>、<code>call_user_func</code> 系列、<code>array_map/usort</code> 的回调参数等。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：输入被包进了什么？</summary><p>从源码看，你的输入被包进 <code>echo ( ... );</code> 中间。要让"自己的语句"独立执行，得先把括号和分号闭合掉。</p></details>
<details><summary>提示 2：先跑个无害的</summary><p><code>?expr=1);phpinfo();//</code> 能看到 phpinfo 页面源码输出，说明代码执行成立。也可以 <code>?expr=1);print_r(scandir('.'));//</code> 列出目录。</p></details>
<details><summary>提示 3：读 flag</summary><p><code>?expr=1);highlight_file('flag.php');//</code>（或 <code>show_source</code>、<code>system('cat flag.php')</code>）。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../rce4">← 上一关：rce4</a><a href="../rce6">下一关：rce6 · preg_replace /e →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=rce5&flag='+encodeURIComponent(v)})
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
