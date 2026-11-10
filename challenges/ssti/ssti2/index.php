<?php
// ============================================================
// ssti2 · SSTI 模板注入 · 过滤绕过（SSTI 第二课 · PHP 8.2 版）
// ============================================================
// 背景：  上一关（ssti1）被打穿后，开发做了两件事：
//         ① 「安全过滤器」：昵称里出现 _  '  "  config  self 直接拦截
//         ② 把 FLAG 从配置里挪走，藏进 flag.php 的 PHP 注释里
// 考点：  黑名单挡不住 SSTI —— 引擎 2.0 把 URL 查询参数自动注册为
//         模板变量（?cmd=x → {{ $x }} 可用），把「危险字符串」挪到查询
//         参数里走私；引号被禁还有 chr() 拼接。$_GET / $GLOBALS /
//         get_defined_vars() 这些最顺手的把手名字全带下划线，全被拦。
// 说明：  本题由原 Python/Flask(Jinja2) 版平移而来（原文件见 _original/）。
// ============================================================

// 应用配置（2.0 起 FLAG 已从这里移除；现在只是个摆设）
$CONFIG = [
    'DEBUG'    => false,
    'APP_NAME' => '会员商城 MemberMall',
    'NOTE'     => 'FLAG 已从配置移除（开发：上次就是从这里被打穿的），别翻了',
];

// ---------------------- 迷你模板引擎 2.0（故意的烂实现） ----------------------
// 与 ssti1 同款：{{ 表达式 }} 直接 eval；2.0 新特性：查询参数自动注册为模板变量。
function template_render(string $tpl, array $vars): string
{
    $out = preg_replace_callback('/\{\{(.+?)\}\}/s', function (array $m) use ($vars): string {
        extract($vars, EXTR_SKIP);                     // config 等上下文变量 + 2.0 的 URL 参数变量
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
    return $out ?? $tpl;  // preg 出错时退回原文，绝不 Fatal
}

function tpl_str($v): string
{
    if ($v === null)  return '';
    if ($v === true)  return 'true';
    if ($v === false) return 'false';
    if (is_array($v)) return print_r($v, true);
    if (is_object($v)) {
        if (method_exists($v, '__toString')) return (string)$v;
        return print_r($v, true);
    }
    return (string)$v;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// 「安全过滤器」：拦截这些字符/词（大小写不敏感）
$BLACKLIST = ['_', "'", '"', 'config', 'self'];

$name = (isset($_GET['name']) && is_string($_GET['name'])) ? $_GET['name'] : '';   // 对齐 request.args.get：只取字符串
$rendered = '';
$blocked = null;
if ($name !== '') {
    $low = strtolower($name);
    foreach ($BLACKLIST as $w) {
        if (str_contains($low, $w)) {
            $blocked = $w;
            break;
        }
    }
    if ($blocked !== null) {
        $rendered = '🚫 安全过滤器：检测到禁止字符/关键词「' . e($blocked) . '」，已拦截！';
    } else {
        // ---------------------- 漏洞仍在这里 ----------------------
        // 过滤了常见关键字，但输入依旧被拼进模板源码；而且引擎 2.0 把
        // 未经黑名单检查的 $_GET 也注入了模板上下文（参数走私通道）
        try {
            $rendered = template_render(
                '欢迎回来 <b>__(' . $name . ')__</b>，祝您购物愉快！',
                ['config' => $CONFIG] + $_GET
            );
        } catch (Throwable $e) {
            $rendered = '模板渲染出错：' . $e->getMessage();
        }
    }
}
$blocklistHtml = implode(' ', array_map('e', $BLACKLIST));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ssti2 · SSTI 过滤绕过 | CTF 教学靶场</title>
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
<span class="badge">SSTI · 模板注入 · 第 2 关</span>
<h1>欢迎语生成器 2.0 —— 过滤绕过</h1>
<div class="meta">难度：★★☆☆☆ 进阶 · 关卡 ID：<code>ssti2</code> · 运行时：PHP 8.2 · 自制迷你模板引擎 2.0</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>上一关被打穿之后，开发连夜上了两件事：① 「安全过滤器」——昵称中出现 <code><?= $blocklistHtml ?></code>（转义显示，含下划线、两种引号与两个关键词）任意一个就直接拦截；② 把 FLAG 从应用配置里挪走，藏进了本目录 <code>flag.php</code> 的 PHP 注释里（Web 直接访问该文件是空白页）。</p>
<p>同时模板引擎升级到 2.0：为了方便运营在链接里传参，新引擎会把 <b>URL 查询参数自动注册为模板变量</b>（<code>?foo=1</code> → <code>{{ $foo }}</code> 可用）。过滤之后的输入<b>仍然</b>会被拼进模板源码。过滤是死的，模板上下文是活的——想办法绕过去。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>会员昵称：<input type="text" name="name" placeholder="{{7*7}} 还能用吗？" value="<?= e($name) ?>"></label>
<button type="submit">生成欢迎语</button>
</form>
<div class="result"><div class="t">渲染结果</div>
<pre><?= e($rendered) ?></pre></div>
<p style="color:var(--dim);font-size:13px">提示：拦截只针对 name 参数本身，查询字符串里的其他参数不受限；引擎源码已给出（见下方），黑盒白盒结合玩。</p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>黑名单为什么挡不住 SSTI</b>：过滤器只能看到「字面字符」，而表达式的写法远不止一种。本题引擎的 <code>{{ }}</code> 里就是 PHP 表达式（实现见 ssti1，2.0 只是多了参数变量注入），等价替代随手就是一把：</p>
<table>
<tr><th>被禁的写法</th><th>等价替代</th><th>说明</th></tr>
<tr><td><code>'字符串'</code>（引号）</td><td><code>?cmd=cat flag.php</code> → <code>{{exec($cmd)}}</code></td><td>把字符串放到 URL 参数里，引擎 2.0 自动注册成模板变量，模板体只引用变量</td></tr>
<tr><td><code>'字符串'</code>（不想走私）</td><td><code>chr(99).chr(97).chr(116)...</code></td><td>用字符码逐个拼出任意字符串，全程零引号</td></tr>
<tr><td><code>$_GET</code> / <code>$GLOBALS</code> / <code>get_defined_vars()</code></td><td>——</td><td>PHP 里最顺手的几个把手名字全带下划线，直接被拦（对应原 Flask 版里 <code>url_for</code> 自带下划线被拦的梗）</td></tr>
<tr><td><code>config</code>（关键词）</td><td>——</td><td>老路被堵；而且 2.0 起 FLAG 已不在配置里，本关出口是 <code>flag.php</code> 文件</td></tr>
</table>
<p>本关核心代码：</p>
<pre>$BLACKLIST = ['_', "'", '"', 'config', 'self'];          // 大小写不敏感地拦截
if (str_contains(strtolower($name), $w)) { 拦截; }
// 引擎 2.0：查询参数自动注册为模板变量 —— 模板体只写变量名
template_render('欢迎回来 &lt;b&gt;__(' . $name . ')__&lt;/b&gt;...',
               ['config' =&gt; $CONFIG] + $_GET);           // ← 过滤后仍然进了模板源码，且 $_GET 不经过黑名单</pre>
<p>命令执行面（引擎表达式即 PHP，回调/命令函数名里一个违禁字都没有）：<code>exec</code>（返回输出最后一行，正好回显）、<code>system</code>（边执行边输出，最后再回显一行）、<code>passthru</code>（原样直出）、<code>readfile</code>（直读文件）……思路都是同一个：<b>换一种等价语法 + 把危险内容挪出被过滤的位置</b>。</p>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：先确认注入还在 & 感受过滤</summary><p>输入 <code>{{7*7}}</code> —— 这些字符都不在黑名单里。回显 <code>49</code> 说明 SSTI 依然存在，只是「拿 config」的老路被关键词+下划线+引号堵死了。再试试 <code>{{$_GET}}</code>：PHP 里最顺手的超全局 <code>$_GET</code>（还有 <code>$GLOBALS</code>、<code>get_defined_vars()</code>）名字<b>自带下划线</b>，直接被拦——跟原 Flask 版里 <code>url_for</code> 被拦是同一个梗。</p></details>
<details><summary>提示 2：模板里能拿到 URL 参数吗？</summary><p>能！引擎 2.0 的新特性就是本关钥匙：<code>?cmd=xxx</code> 会让 <code>{{ }}</code> 里出现 <code>$cmd</code>，而 URL 查询字符串<b>不经过</b> name 的黑名单过滤。把「命令」这类违禁内容放进查询参数里走私，模板体内只写 <code>$变量名</code>。再想想哪个 PHP 内置函数能执行 shell 命令、且函数名里没有下划线和引号（exec / system / passthru）。</p></details>
<details><summary>提示 3：完整 payload</summary><p><code>/?name={{exec($cmd)}}&amp;cmd=cat flag.php</code> —— 模板体一个违禁字都没有，命令藏在查询参数里。<code>exec</code> 的返回值是命令输出的最后一行，正好是 flag.php 那行注释，flag 就在欢迎语里。（Windows 本地复现用 <code>cmd=type flag.php</code>。）不想走私也可以用 chr() 拼接：<code>{{exec(chr(99).chr(97).chr(116).chr(32).chr(102).chr(108).chr(97).chr(103).chr(46).chr(112).chr(104).chr(112))}}</code>。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="/ssti1">← 上一关：ssti1 · SSTI 基础</a><a href="/ssti3">下一关：ssti3 · Twig →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssti2&flag='+encodeURIComponent(v)})
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
