<?php
// ============================================================
// ssti1 · SSTI 模板注入基础（SSTI 第一课 · PHP 8.2 版）
// ============================================================
// 漏洞点：自制迷你模板引擎 template_render() 把用户输入拼进模板
//         源码，{{ }} 里的表达式会被 eval 当 PHP 代码求值。
// 考点：  ① 用 {{7*7}} 探测 SSTI；② 用 {{config}} / {{config['FLAG']}}
//         读注入到模板上下文里的应用配置。
// 说明：  本题由原 Python/Flask(Jinja2) 版平移而来（原文件见 _original/），
//         教学点不变：输入成为模板源码 → 表达式被服务端执行 → 读敏感配置。
// ============================================================

// 应用配置（等价 Flask 的 app.config；flag.php 直接访问是空白页，只 return 不 echo）
$CONFIG = require __DIR__ . '/flag.php';

// ---------------------- 迷你模板引擎（故意的烂实现） ----------------------
// 长得像 Jinja2：{{ 表达式 }} 求值后把结果拼进输出。
// 致命伤：表达式直接 eval —— 教学期故意留的洞。
function template_render(string $tpl, array $vars): string
{
    $out = preg_replace_callback('/\{\{(.+?)\}\}/s', function (array $m) use ($vars): string {
        extract($vars, EXTR_SKIP);                     // 模板上下文变量（config 等）进入作用域
        $expr = trim($m[1]);
            // 模板语法习惯与 Jinja2 一致：上下文变量不带 $（写 config 而非 $config）
            // 把裸标识符映射为已 extract 进作用域的 PHP 变量再求值
            $keys = array_filter(array_keys($vars), 'is_string');
            if ($keys) {
                $expr = preg_replace('/(?<![\$\\\w>])(' . implode('|', array_map('preg_quote', $keys)) . ')\b/', '\$$1', $expr);
            }
            $value = eval('return ' . $expr . ';');  // ← 漏洞：{{ }} 里的内容被当 PHP 执行
        return tpl_str($value);
    }, $tpl);
    return $out ?? $tpl;  // preg 出错（如回溯超限）时退回原文，绝不 Fatal
}

// 把 eval 的返回值转成模板输出文本（对应 Jinja2 渲染时的对象字符串化）
function tpl_str($v): string
{
    if ($v === null)  return '';
    if ($v === true)  return 'true';
    if ($v === false) return 'false';
    if (is_array($v)) return print_r($v, true);        // {{config}} → 打印整个配置数组
    if (is_object($v)) {
        if (method_exists($v, '__toString')) return (string)$v;
        return print_r($v, true);
    }
    return (string)$v;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$name = (isset($_GET['name']) && is_string($_GET['name'])) ? $_GET['name'] : '';   // 对齐 request.args.get：只取字符串
$rendered = '';
if ($name !== '') {
    // ---------------------- 漏洞点 ----------------------
    // 用户输入直接拼进模板源码：{{7*7}} 会被引擎当表达式执行
    try {
        $rendered = template_render(
            '欢迎光临 <b>__(' . $name . ')__</b>，祝您购物愉快！',
            ['config' => $CONFIG]                      // 引擎把应用配置注入了模板上下文
        );
    } catch (Throwable $e) {
        $rendered = '模板渲染出错：' . $e->getMessage() . '（这也说明你的输入确实进了模板引擎）';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ssti1 · SSTI 模板注入基础 | CTF 教学靶场</title>
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
<span class="badge">SSTI · 模板注入 · 第 1 关</span>
<h1>会员欢迎语生成器 —— SSTI 模板注入基础</h1>
<div class="meta">难度：★☆☆☆☆ 入门 · 关卡 ID：<code>ssti1</code> · 运行时：PHP 8.2 · 自制迷你模板引擎</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>商城上线了「个性化欢迎语」功能：输入会员昵称，首页横幅就会显示一段欢迎语。开发同学图省事，用自研的迷你模板引擎渲染欢迎语，把你的昵称直接拼进了<b>模板字符串</b>里。</p>
<p>flag 就藏在应用的配置里（引擎把它以模板变量 <code>config</code> 注入了渲染上下文），想办法让模板引擎把它「说出来」。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>会员昵称：<input type="text" name="name" placeholder="先试试输入 {{7*7}}" value="<?= e($name) ?>"></label>
<button type="submit">生成欢迎语</button>
</form>
<div class="result"><div class="t">渲染结果（引擎输出已做 HTML 转义展示——防得住 XSS，防不住 SSTI）</div>
<pre><?= e($rendered) ?></pre></div>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>服务端模板注入（SSTI, Server-Side Template Injection）</b>：用户输入被拼进模板<b>源码</b>，模板引擎在服务端解析执行。攻击者插入模板语法（如 <code>{{ 表达式 }}</code>），表达式会被服务端求值并把结果拼进页面——无论引擎是 Jinja2（Python）还是本关这个 PHP 自制引擎，病灶完全一样。</p>
<p>本关核心代码（已给出，黑白盒结合学习）：</p>
<pre>// 迷你模板引擎（index.php 顶部，完整源码就在页面里）
function template_render($tpl, $vars) {
    return preg_replace_callback('/\{\{(.+?)\}\}/s', function ($m) use ($vars) {
        extract($vars, EXTR_SKIP);                                // config 等上下文变量进入作用域
        $expr = trim($m[1]);
            // 模板语法习惯与 Jinja2 一致：上下文变量不带 $（写 config 而非 $config）
            // 把裸标识符映射为已 extract 进作用域的 PHP 变量再求值
            $keys = array_filter(array_keys($vars), 'is_string');
            if ($keys) {
                $expr = preg_replace('/(?<![\$\\\w>])(' . implode('|', array_map('preg_quote', $keys)) . ')\b/', '\$$1', $expr);
            }
            $value = eval('return ' . $expr . ';');             // ← {{ }} 里的内容被当 PHP 执行
        return tpl_str($value);
    }, $tpl);
}

$name = $_GET['name'] ?? '';
template_render('欢迎光临 &lt;b&gt;__(' . $name . ')__&lt;/b&gt;，祝您购物愉快！',   // ← 漏洞：输入成为模板源码
               ['config' =&gt; $CONFIG]);</pre>
<p>它与安全写法的区别：</p>
<table>
<tr><th>写法</th><th>含义</th><th><code>{{7*7}}</code> 的结果</th></tr>
<tr><td><code>template_render('hi ' . $name, ...)</code></td><td>输入是<b>模板的一部分</b>，表达式会被执行</td><td><code>hi 49</code>（被计算了！）</td></tr>
<tr><td>模板文件固定 <code>hi {{ name }}</code> + 变量传值</td><td>输入只是<b>数据</b>，模板源码固定</td><td><code>hi {{7*7}}</code>（原样显示）</td></tr>
</table>
<p>常用探测与利用（本引擎 = PHP 语法）：</p>
<table>
<tr><th>载荷</th><th>作用</th></tr>
<tr><td><code>{{7*7}}</code></td><td>探测：回显 49 说明存在 SSTI</td></tr>
<tr><td><code>{{7*'7'}}</code></td><td>指纹：本引擎（PHP 系）回显 <code>49</code>（弱类型相乘）；Jinja2 会回显 <code>7777777</code>（字符串重复）</td></tr>
<tr><td><code>{{config}}</code></td><td>打印整个应用配置数组（含自定义配置）</td></tr>
<tr><td><code>{{config['FLAG']}}</code></td><td>精确取出某一项配置（PHP 下标语法）</td></tr>
</table>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：先证明输入被当成了「模板」</summary><p>输入 <code>{{7*7}}</code> 提交。如果欢迎语里出现的是 <b>49</b> 而不是 <code>{{7*7}}</code> 这串字符，说明花括号里的内容被服务端<b>计算</b>了——这就是 SSTI。</p></details>
<details><summary>提示 2：模板渲染时能「看到」什么？</summary><p>引擎把应用配置以模板变量 <code>config</code>（PHP 数组）注入了渲染上下文，<code>{{ }}</code> 里能直接访问。试着打印整个配置数组看看（输出会是 print_r 的数组样式）。</p></details>
<details><summary>提示 3：取出 flag</summary><p>flag 存在配置数组的 <code>FLAG</code> 字段：<code>?name={{config}}</code> 在输出里搜 FLAG；精确写法是 <code>?name={{config['FLAG']}}</code>（注意用 PHP 数组下标语法，<code>config.FLAG</code> 这种点号写法会被当成常量而报错——这本身就是 PHP 与 Jinja2 的语法差异知识点）。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><span></span><a href="/ssti2">下一关：ssti2 · 过滤绕过 →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssti1&flag='+encodeURIComponent(v)})
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
