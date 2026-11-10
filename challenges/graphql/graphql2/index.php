<?php
declare(strict_types=1);
// ============================================================
// graphql2 · index.php —— 星际图书馆 v2 · 会员中心（越权取数）
// 上关事件后图书馆升级：内省已关闭（graphql.php 拒绝 __schema/__type），
// 会员接口加了"登录才能用"——但 member(id) 没做属主校验（IDOR）。
// 实验账号：bob / bob123；admin(id=1) 的 secretNote 是 flag。
// ============================================================
session_start();
require __DIR__ . '/members.php';

$member = gql2_current_member();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>graphql2 · 越权 | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:940px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#f472b6;color:#3b0a22;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
textarea{width:100%;min-height:110px;padding:10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink);font-family:Consolas,monospace;font-size:13px}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer;margin:2px 4px 2px 0}
button:hover{filter:brightness(1.15)}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
#out{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px 14px;margin-top:10px;font-size:13px;white-space:pre-wrap;word-break:break-all;min-height:24px;max-height:420px;overflow:auto}
input{padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
.okbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:12px 14px;margin-top:12px}
a{color:var(--acc)}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">GraphQL · 第 2 关</span>
<h1>星际图书馆 v2 · 会员中心</h1>
<div class="meta">难度：★★★★☆ 进阶 | 关卡 ID：<code>graphql2</code> · 运行时：PHP 8.2 · 手写迷你 GraphQL（内省已关闭）</div>
</header>

<section class="card"><h2>题目描述</h2>
<p>上关事件后，图书馆升级到 v2：<b>内省已关闭</b>（试试 <code>__schema</code> 会得到拒绝），
会员接口也加上了"登录才能用"。你的实验账号：<code>bob / bob123</code>。</p>
<p>会员资料里有个 <code>secretNote</code> 字段——每个人只能看自己的，<b>理论上</b>。
管理员（id=1）的备注里记着重要口令。目标：读到<b>别人的</b> secretNote。</p>
<?php if ($member !== null): ?>
<div class="okbox">✅ 已登录：<b><?php echo htmlspecialchars((string)$member['username'], ENT_QUOTES, 'UTF-8'); ?></b>（id=<?php echo htmlspecialchars((string)$member['id'], ENT_QUOTES, 'UTF-8'); ?>）。往下用 GraphQL 控制台。
（<a href="logout.php">退出登录</a>）</div>
<?php else: ?>
<form method="post" action="login.php" style="margin-top:10px">
用户 <input type="text" name="user" value="bob" size="10">
口令 <input type="text" name="pass" value="bob123" size="10">
<button type="submit">登录（实验账号 bob / bob123）</button>
</form>
<?php endif; ?>
</section>

<section class="card"><h2>实验区 · GraphQL 控制台</h2>
<textarea id="q">{
  me {
    username
    email
    secretNote
  }
}</textarea><br>
<button onclick="run()">▶ 运行</button>
<button onclick="fill('member')">⭐ 填入 member 查询</button>
<button onclick="fill('intro')">🪞 试试内省（会被拒）</button>
<div id="out">（响应区）</div></section>

<section class="card"><h2>知识点讲解</h2>
<p><b>GraphQL 的鉴权错觉</b>：登录检查（authentication）做了，<b>属主/授权检查（authorization）</b>没做——
<code>member(id)</code> 的 resolver 谁登录都能查<b>任何人</b>。这是 GraphQL 版 IDOR，和 REST 世界的
<code>/api/orders/123</code> 无属主校验一模一样，只是换了查询语言外衣。</p>
<p>关闭内省后如何侦察 schema？</p>
<ul>
<li><b>报错提示</b>：查一个不存在的字段，报错常带 "Did you mean 'member'?" 之类的拼写建议——字段名半个字母半个字母试出来；</li>
<li><b>字典/直觉</b>：me/member/user/admin/id 是会员系统的高频命名；</li>
<li><b>旁路文档</b>：前端 bundle 里的查询语句、GraphiQL 残留、上一关同款系统的 schema。</li>
</ul>
<p>另一个常见越权面：<b>批量查询/别名</b>（一条请求 aliases 拉遍 <code>member(id:1)…member(id:50)</code>），
速率限制形同虚设——防御要在 resolver 层做，而不是靠"接口不好找"。</p>
<p>防御正解：resolver 内做<b>资源级授权</b>（<code>if ($obj['id'] !== $ctxMember['id']) throw</code>），
敏感字段（secretNote）单独再校验或干脆不进公共类型；审计告警"单请求大量同构查询"。</p>
<p>curl 等价操作（登录拿会话 Cookie）：</p>
<pre>curl -s -c jar.txt -X POST http://HOST/graphql2/login.php -d "user=bob&amp;pass=bob123"
curl -s -b jar.txt http://HOST/graphql2/graphql.php -H "Content-Type: application/json" \
     -d '{"query":"{ member(id: \"1\") { username email secretNote } }"}'</pre>
</section>

<section class="card"><h2>渐进提示</h2>
<details><summary>提示 1：登录并看清自己</summary><p>用 bob/bob123 登录后运行默认查询 <code>{ me { username secretNote } }</code>——你自己的备注只有"记得还书"。</p></details>
<details><summary>提示 2：还有别的入口吗</summary><p>内省被拒了，但错误信息是友好的：试试查 <code>{ mmber(id: "1") { id } }</code> 这类"拼错的字段"，看报错给不给提示。想想会员系统常见的入口名。</p></details>
<details><summary>提示 3：payload</summary><p><code>{ member(id: "1") { username email secretNote } }</code>——admin 的 secretNote 就是 flag。对比：换成 id:"3" 能看到 carol 的备注（越权面验证）。</p></details>
</section>

<section class="card"><h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button></form><div id="flagResult"></div></section>

<nav class="nav"><a href="../graphql1">← 上一关：graphql1</a><a href="#">本分类最后一关</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
var out=document.getElementById("out");
function show(t){out.textContent=t}
function run(){
  fetch("graphql.php",{method:"POST",headers:{"Content-Type":"application/json"},
    body:JSON.stringify({query:document.getElementById("q").value})})
    .then(function(r){return r.text().then(function(t){show("HTTP "+r.status+"\n"+t)})})
    .catch(function(e){show("ERR "+e)});
}
function fill(kind){
  document.getElementById("q").value = kind==="member"
    ? '{\n  member(id: "2") {\n    username\n    email\n    secretNote\n  }\n}'
    : '{ __schema { queryType { fields { name } } } }';
}
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=graphql2&flag='+encodeURIComponent(v)})
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
