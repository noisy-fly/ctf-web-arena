<?php
// sess2 · 弱签名 Cookie 伪造
// 流程：登录(player/Player@123) → 发弱签名 Cookie → /vault.php 验签放行
// 漏洞：签名算法/密钥随"泄露的备份文件" config.php.bak 曝光 → 可离线伪造 admin 凭证
require_once __DIR__ . '/config.php';

$msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $u = isset($_POST['user']) ? (string)$_POST['user'] : '';
    $p = isset($_POST['pass']) ? (string)$_POST['pass'] : '';
    $users = sso_users();
    if (isset($users[$u]) && $users[$u]['pass'] === $p) {
        setcookie('stargate_auth', sso_issue($u, $users[$u]['role']), array('path' => '/', 'httponly' => true));
        $msg = '登录成功：已签发凭证 Cookie stargate_auth（role=' . $users[$u]['role'] . '）。去 <a href="vault.php" style="color:var(--acc)">vault.php</a> 试试。';
    } else {
        $msg = '账号或口令错误。';
    }
}
// 展示当前凭证（教学用：让玩家看清 Cookie 结构）
$cur = '';
if (isset($_COOKIE['stargate_auth'])) {
    $parts = explode('.', $_COOKIE['stargate_auth']);
    $decoded = count($parts) === 2 ? b64url_decode($parts[0]) : '';
    $d = json_decode($decoded, true);
    $cur = is_array($d) ? json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '(无法解析)';
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>sess2 · 弱签名 Cookie | CTF 教学靶场</title>
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
input{padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
.okbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:14px 16px;margin-top:12px}
.errbox{background:#3b1519;border:1px solid #7f1d1d;color:#fca5a5;border-radius:8px;padding:10px 14px;margin-top:12px}
a{color:var(--acc)}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">会话安全 · 第 2 关</span>
<h1>星门 SSO · 会员金库</h1>
<div class="meta">难度：★★★☆☆ 进阶 | 关卡 ID：<code>sess2</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>星门 SSO 用"签名 Cookie"代替服务端会话：登录后发给客户端一个 <code>stargate_auth</code> 凭证——
<code>base64(身份JSON) + "." + 签名</code>。金库 <a href="vault.php">vault.php</a> 只放行 role=admin 的<b>签名有效</b>凭证。</p>
<p>管理员账号的口令没人知道，普通会员实验账号：<code>player / Player@123</code>。</p>
<p>传闻：年初上线的部署脚本在 web 目录留了个 <b>config.php.bak</b>，之后"清理过，应该没事了"……
你的目标：<b>伪造一枚 role=admin 且签名校验通过的凭证</b>，打开金库。</p>
</section>

<section class="card">
<h2>实验区 · 登录</h2>
<form method="post" action="">
用户 <input type="text" name="user" value="player" size="12">
口令 <input type="text" name="pass" value="Player@123" size="14">
<button type="submit">登录签发凭证</button>
</form>
<?php if ($msg): ?>
<div class="<?= strpos($msg, '成功') !== false ? 'okbox' : 'errbox' ?>"><?= $msg ?></div>
<?php endif; ?>
<?php if ($cur): ?>
<h2 style="margin-top:18px">你当前的凭证 payload（base64 解开后）</h2>
<pre><?= h($cur) ?></pre>
<p style="color:var(--dim);font-size:13px">用开发者工具能看到完整 Cookie：<code>stargate_auth=&lt;payload&gt;.&lt;签名&gt;</code>。payload 可读可改——问题只在于：你能不能<b>算出配套的签名</b>。</p>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>服务端验签逻辑（vault.php 核心同款）：</p>
<pre>function sso_sign($user, $role, $exp) {
    return md5($user . '|' . $role . '|' . $exp . '|' . SSO_SECRET);   // 自制 MAC
}</pre>
<p><b>弱签名/自制凭证的三宗罪</b>（本关全部踩中）：</p>
<ul>
<li><b>算法与密钥可被还原</b>：源码（或备份文件，本关的 <code>config.php.bak</code>——PHP 不会执行 .bak，等于明文下载）泄露 <code>SSO_SECRET</code> 后，任何人都能源码级复现签名函数；</li>
<li><b>裸 md5 拼接 ≠ HMAC</b>：md5 拼接存在长度扩展、碰撞等结构性问题，标准做法是 <code>hash_hmac('sha256', $payload, $secret)</code>；</li>
<li><b>信任模型错位</b>：身份字段（role）放在客户端可改的字段里，只靠一层弱签名防守。签名一旦可伪造，权限体系整体失守。</li>
</ul>
<p>伪造步骤（也是本关解法骨架）：读备份拿 SECRET → 构造 payload <code>{"user":"admin","role":"admin","exp":&lt;未来时间戳&gt;}</code> → 按同款算法算签名 → 组装 Cookie 替换 → 访问金库。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先拿到一枚合法凭证看清结构</summary><p>用 player / Player@123 登录，页面上会显示 payload；开发者工具里看完整 Cookie（两段，点号分隔）。</p></details>
<details><summary>提示 2：签名怎么来的</summary><p>页面上已经给了签名函数：md5(user|role|exp|SSO_SECRET)。缺的只是 SSO_SECRET——去把那个"应该没事了"的备份找出来：<code>/sess2/config.php.bak</code>。</p></details>
<details><summary>提示 3：伪造</summary><p>任选未来时间戳 exp，构造 payload：<code>{"user":"admin","role":"admin","exp":4102444800}</code>；base64 后接 <code>.</code> 再接 <code>md5("admin|admin|4102444800|" + SECRET)</code>；替换 Cookie stargate_auth 后打开 <a href="vault.php" style="color:var(--acc)">vault.php</a>。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../sess1">← 上一关：sess1</a><a href="../pp1">下一关：pp1 · 原型链污染 →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=sess2&flag='+encodeURIComponent(v)})
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
