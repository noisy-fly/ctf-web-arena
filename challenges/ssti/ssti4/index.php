<?php
// ============================================================
// ssti4 · Smarty 模板注入（SSTI 第四课 · 又一个 PHP 引擎）
// 漏洞点：$smarty->fetch('string:' . $tpl) —— 用户输入成为 Smarty 模板源码
// 考点：  ① {7*7} 探测（Smarty 语法是单花括号标签）；② 未注册函数标签
//         {system('cmd')} / {if ...} 表达式注入；③ Smarty4 已删 {php} 标签
// 依赖：  lib/Smarty（smarty/smarty v4.5.5 官方源码 vendor，自带 autoload）
// ============================================================
define('SMARTY_DIR', __DIR__ . '/lib/Smarty/');
require SMARTY_DIR . 'Smarty.class.php';

$output = null;
$tpl = isset($_GET['tpl']) ? $_GET['tpl'] : '';
if ($tpl !== '') {
    try {
        $smarty = new Smarty();
        $cdir = sys_get_temp_dir() . '/smarty_c_ssti4';
        if (!is_dir($cdir)) @mkdir($cdir, 0777, true);
        $smarty->setCompileDir($cdir);
        $smarty->setCaching(Smarty::CACHING_OFF);
        // ---------------------- 漏洞点 ----------------------
        $output = $smarty->fetch('string:【公告头条】' . $tpl . '【公告结束】');
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
<title>ssti4 · Smarty 模板注入 | CTF 教学靶场</title>
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
<span class="badge">SSTI · 模板注入 · 第 4 关</span>
<h1>公告模板预览器 —— Smarty 模板注入</h1>
<div class="meta">难度：★★★☆☆ 中等 · 关卡 ID：<code>ssti4</code> · 运行时：PHP 8 + Smarty 4.5</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>集团官网改版用了老牌模板引擎 <b>Smarty</b>。运营同学在后台预览公告时，输入会被拼进 <code>$smarty-&gt;fetch('string:' . $input)</code> 直接渲染。</p>
<p>注意 Smarty 的语法是<b>单花括号标签</b> <code>{...}</code>（不是 <code>{{...}}</code>）。目标：读出本目录 <code>flag.php</code> 的源码（flag 在 PHP 注释里）。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>公告片段：<input type="text" name="tpl" placeholder="先试试 {7*7}" value="<?= e($tpl) ?>"></label>
<button type="submit">预览公告</button>
</form>
<?php if ($output !== null): ?>
<div class="result"><div class="t">渲染结果（开发环境的 Deprecated/Warning 也属于输出，不影响解题）</div>
<pre><?= e($output) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>Smarty</b> 是 PHP 世界最老牌的模板引擎（WordPress 时代的论坛、ShopEx、各类 CMS 都能见到）。本关核心代码：</p>
<pre>&lt;?php
$smarty = new Smarty();
$output = $smarty-&gt;fetch('string:' . $tpl);   // ← 漏洞：'string:' 资源 = 把输入当模板源码编译执行</pre>
<p><b>语法差异速记</b>：</p>
<table>
<tr><th>引擎</th><th>输出表达式</th><th>语句</th><th>本关探测结果</th></tr>
<tr><td>Jinja2</td><td><code>{{expr}}</code></td><td><code>{% ... %}</code></td><td><code>{{7*7}}</code>→49</td></tr>
<tr><td>Twig</td><td><code>{{expr}}</code></td><td><code>{% ... %}</code></td><td><code>{{7*'7'}}</code>→49</td></tr>
<tr><td><b>Smarty</b></td><td><code>{expr}</code></td><td><code>{if}...{/if}</code> 等</td><td><code>{7*7}</code>→49（注意<b>单花括号</b>）</td></tr>
</table>
<p><b>Smarty 4 的命令执行面</b>（本版本实测）：</p>
<table>
<tr><th>载荷</th><th>原理</th><th>状态</th></tr>
<tr><td><code>{system('命令')}</code></td><td>Smarty 把「未注册的函数标签」直接当 PHP 函数调用（官方标记 deprecated，但仍执行）</td><td>✅ 主解</td></tr>
<tr><td><code>{if system('命令')}{/if}</code></td><td><code>{if}</code> 的条件是任意 PHP 表达式</td><td>✅ 可用</td></tr>
<tr><td><code>{类名::静态方法(...)}</code></td><td>未注册类的静态调用（需要类已可自动加载）</td><td>⚠️ 视环境</td></tr>
<tr><td><code>{php}...{/php}</code></td><td>老版本任意 PHP 代码</td><td>❌ Smarty 4 已删除（实测 Syntax error）</td></tr>
</table>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：先确认语法体系</summary><p>输入 <code>{7*7}</code>，公告里出现 <code>49</code> 说明输入被 Smarty 当模板编译了。Smarty 的定界符就是一对花括号本身，所以 <code>{{7*7}}</code> 也会得到 49；但「单花括号也能执行」恰恰是 Smarty 独有的特征——Jinja2/Twig 对 <code>{7*7}</code> 只会原样输出。</p></details>
<details><summary>提示 2：Smarty 标签能调用函数吗</summary><p>能。Smarty 3/4 里 <code>{函数名(参数)}</code> 形式的「未注册函数标签」会被编译成对应的 PHP 函数调用（第一次使用会打一条 Deprecated 警告，但照常执行）。另外 <code>{if 条件}...{/if}</code> 的条件就是 PHP 表达式。</p></details>
<details><summary>提示 3：完整 payload</summary><p><code>?tpl={system('cat flag.php')}</code> —— 公告正文里会混出 flag.php 的源码。Windows 本地复现用 <code>{system('type flag.php')}</code>。备选：<code>{if system('cat flag.php')}{/if}</code>。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="/ssti3">← 上一关：ssti3 · Twig</a><a href="/ssti5">下一关：ssti5 · FreeMarker →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssti4&flag='+encodeURIComponent(v)})
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
