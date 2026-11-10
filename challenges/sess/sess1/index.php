<?php
// ============================================================
// sess1 · 会话固定（Session Fixation）
// 漏洞：会话 ID 可由 URL ?sid= 预置；"登录"成功后【不重新生成 sid】
//   → 攻击者把自选 sid 发给受害者 → 受害者登录 → 该 sid 携带登录态
//   → 攻击者用同一 sid 直接接管会话
// 实现：自研文件会话（data/sess_<sid>.json），完整可控、便于观察
// 修复对照：admin_login.php?fixed=1 登录后强制换新随机 sid（session_regenerate_id 语义）
// ============================================================
define('FLAG', 'flag{sess1_0bbd8971}');
require_once __DIR__ . '/session_lib.php';

// sid 来源：GET ?sid= > Cookie > 新建（"允许 URL 携带 sid"本身就是老系统的病）
$sid = isset($_GET['sid']) ? $_GET['sid'] : (isset($_COOKIE['SESSID']) ? $_COOKIE['SESSID'] : '');
if (!sid_valid($sid)) { $sid = bin2hex(random_bytes(8)); }
$sess = sess_load($sid);
if ($sess === null) { $sess = array('user' => 'guest', 'role' => 'guest'); sess_save($sid, $sess); }
setcookie('SESSID', $sid, array('path' => '/', 'httponly' => false));   // 漏洞伴生：非 httpOnly + URL 可传

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>sess1 · 会话固定 | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#fb923c;color:#431407;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
button.fix{background:var(--ok);color:#052e16}
input{padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
.okbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:14px 16px;margin-top:12px}
.nobox{background:#0b1220;border:1px solid #27364d;color:var(--dim);border-radius:8px;padding:14px 16px;margin-top:12px}
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
a{color:var(--acc)}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">会话安全 · 第 1 关</span>
<h1>星舟社区 · 会话实验站</h1>
<div class="meta">难度：★★☆☆☆ 基础 | 关卡 ID：<code>sess1</code> · 运行时：PHP（自研文件会话）</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>星舟社区最近总发生"账号莫名被接管"。安全组复盘发现这套老系统的会话机制有两个可疑点：</p>
<ul>
<li>会话 ID 允许<b>从 URL 传入</b>：<code>?sid=xxxx</code>（兼容老手机浏览器的遗留逻辑）；</li>
<li>管理员登录成功后<b>不更换会话 ID</b>——登录态直接挂在"进站时用的那个 sid"上。</li>
</ul>
<p>站内有个"模拟管理员登录"入口（扮演<b>受害者管理员</b>：他点开你发给他的链接后，在这里输入账号密码完成了登录）。你的目标：<b>在他登录之后，用你早已选好的同一个 sid 顶替他的会话</b>，读到管理员备忘（flag）。</p>
<?php if ($msg === 'logedin'): ?><div class="okbox">✅ 管理员已在此会话上登录（role=admin）。</div>
<?php elseif ($msg === 'fixed'): ?><div class="okbox">✅（修复版）登录成功且<b>已更换新会话 ID</b>——你手里预置的旧 sid 已作废，只有新链接可用。</div><?php endif; ?>
</section>

<section class="card">
<h2>实验区 · 当前会话状态</h2>
<table>
<tr><th>项目</th><th>值</th></tr>
<tr><td>当前会话 ID（sid）</td><td><code><?= h($sid) ?></code></td></tr>
<tr><td>身份</td><td><code><?= h($sess['user']) ?> / <?= h($sess['role']) ?></code></td></tr>
</table>

<?php if ($sess['role'] === 'admin'): ?>
<div class="okbox">👑 你当前持有<b>管理员会话</b>。管理员备忘：<?= FLAG ?></div>
<?php else: ?>
<div class="nobox">👤 访客会话。管理员备忘需要 role=admin 的会话。</div>
<?php endif; ?>

<h2 style="margin-top:18px">操作</h2>
<form method="post" action="admin_login.php">
<input type="hidden" name="sid" value="<?= h($sid) ?>">
<button type="submit">🎬 模拟管理员登录（在当前 sid 上）</button>
<button class="fix" type="submit" formaction="admin_login.php?fixed=1">🔒 模拟管理员登录（修复版：强制换 sid）</button>
</form>
<p style="color:var(--dim);font-size:13px">想换个自选 sid？直接改地址栏：<code>?sid=iamattacker88</code>（8~64 位字母数字）。<a href="?sid=<?= h(bin2hex(random_bytes(6))) ?>">随机换一个</a></p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>会话固定的攻击链（现实映射）：</p>
<pre>1. 攻击者自选一个 sid（或先访问站点领一个），把它编进链接：
   https://community.example/?sid=iamattacker88
2. 受害者（管理员）点开链接 → 站点"接受 URL 里的 sid" → 他的浏览器从此用这个 sid
3. 受害者登录成功 → 服务器把 user=admin 挂到【同一个 sid】上（没有重新生成）
4. 攻击者用自己的浏览器访问 ?sid=iamattacker88 → 直接持有多出来的管理员会话</pre>
<p><b>根因</b>：会话 ID 的"出生"（谁可以决定它）没有被保护。三个经典入口：</p>
<ul>
<li>URL / 表单传递 sid（<code>session.use_trans_sid=1</code> 时代遗留）；</li>
<li>登录后不调用 <code>session_regenerate_id(true)</code>；</li>
<li>子域可写父域 Cookie（<code>Cookie: SID=x; Domain=.example.com</code>），攻击者用子域 XSS/可控子域给受害者"种"会话。</li>
</ul>
<p><b>修复版按钮</b>演示的就是标准答案：登录（权限变化）时丢弃旧 sid、签发新的，且只接受 Cookie 携带。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：自己决定 sid</summary><p>地址栏访问 <code>?sid=iamattacker88</code>，页面"当前会话 ID"就变成了你选的值——服务器接受了它。</p></details>
<details><summary>提示 2：让"管理员"在这个 sid 上登录</summary><p>保持 <code>?sid=iamattacker88</code>，点"🎬 模拟管理员登录"。这个按钮扮演的是受害者管理员的登录动作。</p></details>
<details><summary>提示 3：接管</summary><p>登录后再看当前会话状态——role 已经是 admin，flag 直接出现。现实中攻击者是在<b>另一台浏览器</b>访问同一 sid 完成接管的；本站单人复现即"同一个你，扮演攻击者"。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../crlf2">← 上一关：crlf2</a><a href="../sess2">下一关：sess2 · 弱签名 Cookie →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=sess1&flag='+encodeURIComponent(v)})
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
