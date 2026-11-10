<?php
// ============================================================
// varcover1 · PHP extract 变量覆盖
// 漏洞：extract($_GET) 把 GET 参数注册为变量，且会【覆盖】已定义变量
//       校验用 $secret_sign 在 extract 之前定义——照样被覆盖
// 解法：?admin_sign=x&secret_sign=x（两个值相等即过校验）
// ============================================================
$FLAG = 'flag{varcover1_17700873}';

$tickets = array(
    array('#1024', '寄件地址修改', '等待客服'),
    array('#1025', '发票抬头咨询', '处理中'),
);

// 1) 服务器生成随机"管理签名"——任何人都不可能预先知道
$secret_sign = sha1(uniqid((string)mt_rand(), true));
$admin_sign  = '';

// 2) 漏洞：把 GET 参数导入为变量（默认 EXTR_OVERWRITE，覆盖一切同名变量）
extract($_GET);

// 3) 用两个变量做校验
$granted = false;
if (isset($admin_sign) && is_string($admin_sign) && $admin_sign !== '' && $admin_sign === $secret_sign) {
    $granted = true;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>varcover1 · extract 变量覆盖 | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#a3e635;color:#1a2e05;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
input[type=text]{width:200px;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
button:hover{filter:brightness(1.15)}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
.okbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:14px 16px;margin-top:12px}
.nobox{background:#0b1220;border:1px solid #27364d;color:var(--dim);border-radius:8px;padding:14px 16px;margin-top:12px}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">变量覆盖 · 第 1 关</span>
<h1>星云工单系统 · 管理通道</h1>
<div class="meta">难度：★★☆☆☆ 基础 | 关卡 ID：<code>varcover1</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>星云工单系统的首页能看普通工单。工单列表下方有一条<b>管理通道</b>：只要请求里携带的 <code>admin_sign</code> 与服务器内部随机生成的 <code>secret_sign</code> <b>完全相等</b>，就能看到管理员面板。</p>
<p><code>secret_sign</code> 由 <code>sha1(uniqid(...))</code> 每次请求随机生成——爆破、猜测都不现实。但这个系统早年为了"兼容旧接口"，写了一行致命的代码……</p>
</section>

<section class="card">
<h2>实验区（工单列表）</h2>
<table>
<tr><th>工单号</th><th>主题</th><th>状态</th></tr>
<?php foreach ($tickets as $t): ?>
<tr><td><?= htmlspecialchars($t[0]) ?></td><td><?= htmlspecialchars($t[1]) ?></td><td><?= htmlspecialchars($t[2]) ?></td></tr>
<?php endforeach; ?>
</table>

<h2 style="margin-top:18px">管理通道校验</h2>
<form method="get" action="">
<label>admin_sign：<input type="text" name="admin_sign" placeholder="你猜？" value="<?= htmlspecialchars((string)$admin_sign, ENT_QUOTES) ?>"></label>
<button type="submit">进入管理通道</button>
</form>

<?php if ($granted): ?>
<div class="okbox">✅ 管理校验通过（admin_sign === secret_sign）。管理员面板：今日工单 2 · 在线客服 1 · 运维口令：<b><?= $FLAG ?></b></div>
<?php else: ?>
<div class="nobox">❌ 校验失败：admin_sign 与服务器随机 secret_sign 不相等。随机签名你拿不到——除非它能被"改"。</div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关核心代码：</p>
<pre>&lt;?php
$secret_sign = sha1(uniqid(mt_rand(), true));   // ① 随机签名，不可预测
extract($_GET);                                 // ② 漏洞！把 GET 参数变成变量
if ($admin_sign === $secret_sign) {             // ③ 用两个变量互相比对
    echo $FLAG;                                 //    ——其中一个可以被 ② 覆盖
}</pre>
<p><b>变量覆盖</b>：<code>extract()</code> 把数组键当作变量名导入当前作用域，默认模式 <code>EXTR_OVERWRITE</code> 会<b>覆盖所有已存在的同名变量</b>——包括 ① 里刚生成的 <code>$secret_sign</code>。</p>
<p>于是"客户端输入"与"服务端秘密"的边界被打破：攻击者传 <code>?secret_sign=x&admin_sign=x</code>，两个变量都被覆盖成 <code>'x'</code>，严格相等 <code>===</code> 照样通过。<b>校验逻辑本身没被攻破，是"被校验的数据"被换掉了。</b></p>
<p>同族函数/场景：</p>
<ul>
<li><code>parse_str($query)</code> 不带第二参数——同样的导入行为（下一关）；</li>
<li><code>$$var</code> 可变变量、<code>compact()</code> 反向泄露；</li>
<li><code>import_request_variables()</code>（PHP5 老代码）；</li>
<li>框架里的 <code>array_merge</code>/<code>Filter::parse</code> 把用户输入合并进配置数组（换成数组形态的同款漏洞）。</li>
</ul>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：随机签名真的无解吗</summary><p><code>secret_sign</code> 每次请求都变，爆破不可行。但校验是 <code>$admin_sign === $secret_sign</code>——需要"猜中"吗？还是只需要"两个变量相等"就够了？</p></details>
<details><summary>提示 2：看看 extract 对已有变量做了什么</summary><p><code>extract($_GET)</code> 发生在 <code>$secret_sign</code> 生成<b>之后</b>、校验<b>之前</b>。GET 参数里如果出现 <code>secret_sign</code> 这个名字，会发生什么？</p></details>
<details><summary>提示 3：payload</summary><p><code>?admin_sign=x&secret_sign=x</code>（两个值随便，相等即可）——刷新页面进入管理通道。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../redirect2">← 上一关：redirect2</a><a href="../varcover2">下一关：varcover2 · parse_str + $$ →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=varcover1&flag='+encodeURIComponent(v)})
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
