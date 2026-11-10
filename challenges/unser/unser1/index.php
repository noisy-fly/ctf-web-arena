<?php
// ============================================================
// unser1 · PHP 反序列化 · __wakeup 绕过（反序列化第一课）
// 漏洞点：unserialize($_GET['card']) 且 GiftBox::__wakeup 的
//         "防伪校验"可以被异常中断 —— 但对象图的析构仍会发生
// 考点：  ① PHP 序列化格式 O: 与魔术方法生命周期；
//         ② CVE-2016-7124（属性数量不符跳过 wakeup）的历史与
//            PHP8 现代形态（异常中断，内层对象析构仍触发）
// ============================================================

class GiftBox {
    public $gift;
    public $sig = 'GIFTBOX_V1';

    public function __wakeup() {
        // 防伪：礼盒属性数量必须与发卡程序一致（2 个），不符视为伪造数据
        if (count((array)$this) !== 2) {
            throw new RuntimeException('GiftBox 数据损坏（属性数量不符），拒绝激活');
        }
        if ($this->gift instanceof Gift) {
            $this->gift->level = 0;   // 安全兜底：激活前把会员等级强制归零
        }
    }
}

class Gift {
    public $name = 'guest';
    public $level = 0;
    public $prize = 'prize.txt';

    public function __destruct() {
        if ((int)$this->level >= 5) {
            $content = @file_get_contents($this->prize);
            echo "🏆 尊贵的 {$this->name}，您的奖品兑换码：<pre>"
                . htmlspecialchars((string)$content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
        } else {
            echo "🎫 普通会员 {$this->name}，感谢参与（当前等级 {$this->level}，等级 ≥5 才有奖品）";
        }
    }
}

$msg = '';
if (isset($_GET['card']) && $_GET['card'] !== '') {
    try {
        unserialize($_GET['card']);
    } catch (Throwable $e) {
        $msg = '⚠️ 激活失败：' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>unser1 · __wakeup 绕过 | CTF 教学靶场</title>
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
<span class="badge">反序列化 · 第 1 关</span>
<h1>会员礼盒激活器 —— __wakeup 绕过</h1>
<div class="meta">难度：★★☆☆☆ 进阶 · 关卡 ID：<code>unser1</code> · 运行时：PHP 8</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>商城发放「会员礼盒」：礼盒里装着礼品对象（Gift），后台用 <code>unserialize()</code> 激活。为了防伪，开发在 <code>GiftBox::__wakeup()</code> 里做了校验，并在激活前把礼品等级<b>强制归零</b>——只有等级 ≥5 的礼品才藏有奖品兑换码（flag 在本目录 <code>flag.php</code> 的注释里）。</p>
<p>想办法让一件高等级礼品「活着」走到析构那一刻。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>礼盒数据（序列化字符串）：</label>
<input type="text" name="card" placeholder='O:7:"GiftBox":2:{...}' value="<?= e($_GET['card'] ?? '') ?>">
<div style="margin-top:8px"><button type="submit">激活礼盒</button></div>
</form>
<?php if ($msg !== ''): ?>
<div class="result"><div class="t">激活器输出</div><pre><?= $msg ?></pre></div>
<?php endif; ?>
<p style="color:var(--dim);font-size:13px">先拿这张"正常卡"试试水（等级 5，但会被防伪归零）：
<code>O:7:"GiftBox":2:{s:4:"gift";O:4:"Gift":3:{s:4:"name";s:5:"alice";s:5:"level";i:5;s:5:"prize";s:8:"flag.php";}s:3:"sig";s:10:"GIFTBOX_V1";}</code></p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>PHP 序列化格式</b>速记（O: 对象、s: 字符串、i: 整数；数字代表长度）：</p>
<pre>O:7:"GiftBox":2:{ s:4:"gift"; O:4:"Gift":3:{ s:4:"name";s:5:"alice"; ... } s:3:"sig";s:10:"GIFTBOX_V1"; }
│      │      │          │
类名长度  类名  属性个数   属性名(带长度) → 属性值</pre>
<p><b>魔术方法生命周期</b>：</p>
<table>
<tr><th>时机</th><th>方法</th><th>本题行为</th></tr>
<tr><td>unserialize 还原对象后</td><td><code>__wakeup()</code></td><td>防伪校验（属性数量必须=2）+ 把 <code>gift-&gt;level</code> 归零</td></tr>
<tr><td>对象销毁时（脚本结束/引用清零）</td><td><code>__destruct()</code></td><td>level ≥5 时把 <code>prize</code> 文件内容打出来</td></tr>
</table>
<p><b>本题核心代码</b>（已给出）：</p>
<pre>class GiftBox {
    public $gift; public $sig = 'GIFTBOX_V1';
    public function __wakeup() {
        if (count((array)$this) !== 2) throw new RuntimeException('数据损坏');  // 防伪
        if ($this-&gt;gift instanceof Gift) $this-&gt;gift-&gt;level = 0;               // 强制归零
    }
}
class Gift {
    public $name; public $level; public $prize;
    public function __destruct() { if ($this-&gt;level &gt;= 5) echo file_get_contents($this-&gt;prize); }
}
unserialize($_GET['card']);</pre>
<p><b>历史课：CVE-2016-7124</b>。老 PHP（&lt; 5.6.25 / 7.0.10）有个著名缺陷：序列化串里声明的<b>属性个数与实际不符</b>时，<code>__wakeup()</code> 会被<b>静默跳过</b>——一行 payload 就能绕过一切 wakeup 校验。新版把这个洞修了：属性不符时 wakeup 照样执行。但是别急——wakeup <b>内部抛出异常</b>时会发生什么？ unserialize 会失败，可<b>已经构造完成的内层对象</b>（Gift）在内存回收时，它的 <code>__destruct()</code> 依旧会被调用……</p>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：正常卡为什么没奖品</summary><p>拿实验区给的"正常卡"（等级 5）激活：wakeup 校验通过后把 gift 的 level 归零，析构时 level=0，所以只有"感谢参与"。防伪的降级发生在 <b>wakeup 里</b>——想办法让 wakeup <b>走不到降级那一行</b>。</p></details>
<details><summary>提示 2：wakeup 怎么才会中断</summary><p>看源码第一行：属性数量 ≠ 2 时 wakeup 会 <b>throw</b>。往序列化串里<b>多塞一个属性</b>（这就是 CVE-2016-7124 的经典 payload 形状）让校验抛异常——unserialize 虽然失败了，但 Gift 对象此刻已经构造完成，PHP 回收它时析构函数照样执行，且 level 没被归零。</p></details>
<details><summary>提示 3：完整 payload</summary><p><code>O:7:"GiftBox":3:{s:4:"gift";O:4:"Gift":3:{s:4:"name";s:6:"hacker";s:5:"level";i:5;s:5:"prize";s:8:"flag.php";}s:3:"sig";s:10:"GIFTBOX_V1";s:1:"x";i:1;}</code> —— 注意开头属性个数写成 <b>3</b> 并补上第三个属性 x；gift 的 prize 指向 flag.php。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="/ssti6">← 上一关：ssti6 · Go template</a><a href="/unser2">下一关：unser2 · POP 链 →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=unser1&flag='+encodeURIComponent(v)})
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
