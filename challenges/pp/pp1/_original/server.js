#!/usr/bin/env node
// ============================================================
// pp1 · Node 原型链污染基础
// 漏洞：/api/profile 用"朴素递归 merge"把用户 JSON 合并进资料对象，
//       键 __proto__ 会顺着原型 getter 递归进去 → 污染 Object.prototype
// 影响：/api/flag 的权限检查用了一个空对象 gate = {}，
//       污染 Object.prototype.admin = true 后 gate.admin === true 成立 → 出 flag
// 运行：node server.js [port]   默认 8316
// ============================================================
const http = require('http');
const PORT = parseInt(process.argv[2] || process.env.PORT || '8316', 10);
const FLAG = 'flag{pp1_db9c6678}';

// —— 全局（进程内共享）资料对象 ——
const profile = { nickname: '星尘旅人', motto: '保持好奇', level: 1 };

// —— 朴素递归 merge：不过滤 __proto__/constructor（漏洞本体） ——
function merge(dst, src) {
  for (const k in src) {
    if (src[k] !== null && typeof src[k] === 'object') {
      if (dst[k] === null || typeof dst[k] !== 'object') dst[k] = {};
      merge(dst[k], src[k]);
    } else {
      dst[k] = src[k];
    }
  }
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    let b = '';
    req.on('data', c => { b += c; if (b.length > 1e6) req.destroy(); });
    req.on('end', () => resolve(b));
    req.on('error', reject);
  });
}

const CSS = `
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#c084fc;color:#2e1065;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
textarea{width:100%;min-height:120px;padding:10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink);font-family:Consolas,monospace;font-size:13px}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer;margin:2px 4px 2px 0}
button:hover{filter:brightness(1.15)}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
#out{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px 14px;margin-top:10px;font-size:14px;white-space:pre-wrap;word-break:break-all;min-height:24px}
a{color:var(--acc)}
`;

const PAGE = `<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>pp1 · 原型链污染基础 | CTF 教学靶场</title><style>${CSS}</style></head><body><div class="wrap">
<header><span class="badge">原型链污染 · 第 1 关</span>
<h1>星云博客 · 资料编辑器</h1>
<div class="meta">难度：★★★☆☆ 基础 | 关卡 ID：<code>pp1</code> · 运行时：Node（零依赖）</div></header>

<section class="card"><h2>题目描述</h2>
<p>星云博客开放了"资料编辑 API"：把 JSON 提交到 <code>/api/profile</code>，服务端把它<b>深合并</b>进你的资料对象。
博客还有一个"高级功能开关"接口 <code>/api/flag</code>：它检查的是一个<b>空对象上的 admin 属性</b>——
<code>const gate = {}; if (gate.admin === true) …</code>。</p>
<p>在 JavaScript 里，<code>{}</code> 是"空"的吗？属性查找找不到时会发生什么？
把这两件事连起来，让 <code>/api/flag</code> 认为你有 admin 标记。</p></section>

<section class="card"><h2>实验区</h2>
<p>资料 JSON（可编辑，点保存 = POST /api/profile）：</p>
<textarea id="j">{
  "nickname": "星尘旅人",
  "motto": "保持好奇"
}</textarea><br>
<button onclick="save()">保存资料</button>
<button onclick="view()">查看当前资料 GET /api/profile</button>
<button onclick="getFlag()">访问开关 GET /api/flag</button>
<button onclick="reset()">清理污染（恢复默认）</button>
<div id="out">（结果区）</div></section>

<section class="card"><h2>知识点讲解</h2>
<p>服务端的合并函数（朴素递归 merge，未过滤危险键）：</p>
<pre>function merge(dst, src) {
  for (const k in src) {
    if (typeof src[k] === 'object' &amp;&amp; src[k] !== null) {
      if (typeof dst[k] !== 'object') dst[k] = {};
      merge(dst[k], src[k]);        // ← k 为 "__proto__" 时，dst["__proto__"] 命中原型 getter → Object.prototype
    } else dst[k] = src[k];
  }
}</pre>
<p><b>原型链污染（Prototype Pollution）</b>：JS 对象查属性时沿原型链上溯。<code>obj["__proto__"]</code> 的 getter 返回其原型；
递归 merge 拿它当"子对象"继续写，就把键值写进了 <code>Object.prototype</code>——<b>此后所有对象</b>（包括别处新造的 <code>const gate = {}</code>）都会"继承"这些属性。</p>
<p>payload 形态（JSON 里带一层 <code>__proto__</code>）：</p>
<pre>{ "__proto__": { "admin": true } }</pre>
<p>历史漏洞家族：lodash <code>_.merge/_.set</code>（CVE-2018-3721 / CVE-2020-8203）、jQuery <code>$.extend(true,…)</code>、
  <code>qs</code>、各类 deep-merge 工具。危害：改权限开关（本关）、改模板引擎选项进而在编译期注入代码（下一关 RCE）、
  影响 Node 子进程参数（child_process options 污染 NODE_OPTIONS）等。</p></section>

<section class="card"><h2>渐进提示</h2>
<details><summary>提示 1：先玩普通字段</summary><p>保存 <code>{"nickname":"pwn"}</code>，再 GET /api/profile 看 merge 是否生效（深合并，未提及的字段保留）。</p></details>
<details><summary>提示 2：merge 会"追进"嵌套对象</summary><p>提交 <code>{"a":{"b":1}}</code> 会递归处理嵌套。那键名写成 <code>__proto__</code> 呢？想想 dst["__proto__"] 在 JS 里取到的是什么。</p></details>
<details><summary>提示 3：payload</summary><p>POST <code>{"__proto__":{"admin":true}}</code> 到 /api/profile，然后 GET /api/flag——空对象 gate 从原型链上"继承"了 admin=true。做完点"清理污染"方便重试。</p></details>
</section>

<section class="card"><h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button></form><div id="flagResult"></div></section>

<nav class="nav"><a href="../sess2">← 上一关：sess2</a><a href="../pp2">下一关：pp2 · 污染到 RCE →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer></div>
<script>
var out=document.getElementById('out');
function show(t){out.textContent=t}
function save(){
  var body;
  try{ body=JSON.stringify(JSON.parse(document.getElementById('j').value)); }
  catch(e){ show('JSON 解析失败：'+e.message); return; }
  fetch('/api/profile',{method:'POST',headers:{'Content-Type':'application/json'},body:body})
    .then(r=>r.text()).then(show).catch(e=>show('ERR '+e));
}
function view(){ fetch('/api/profile').then(r=>r.text()).then(show).catch(e=>show('ERR '+e)); }
function getFlag(){ fetch('/api/flag').then(r=>r.text()).then(show).catch(e=>show('ERR '+e)); }
function reset(){ fetch('/api/reset').then(r=>r.text()).then(show).catch(e=>show('ERR '+e)); }
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=pp1&flag='+encodeURIComponent(v)})
   .then(function(r){return r.text().then(function(t){
      var msg='HTTP '+r.status+': '+t;
      try{var j=JSON.parse(t);
        if(typeof j.correct==='boolean')msg=j.correct?'✅ 回答正确！':'❌ flag 不对，再试试';
        else if(j.message!==undefined)msg=j.message;
      }catch(e){}
      el.textContent=msg;})})
   .catch(function(e){el.textContent='提交失败('+e.message+')：请从靶场门户页提交'});
}
</script></body></html>`;

const server = http.createServer(async (req, res) => {
  const u = new URL(req.url, `http://127.0.0.1:${PORT}`);
  const p = u.pathname.replace(/^\/pp1/, '') || '/';

  if (p === '/' && req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(PAGE);
  }
  if (p === '/api/profile' && req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
    return res.end(JSON.stringify({ profile, note: 'Object.prototype 已被污染的字段数: ' + Object.keys(Object.prototype).filter(k => !['constructor', '__defineGetter__', '__defineSetter__', 'hasOwnProperty', '__lookupGetter__', '__lookupSetter__', 'isPrototypeOf', 'propertyIsEnumerable', 'toString', 'toLocaleString', 'valueOf', 'constructor'].includes(k)).length }));
  }
  if (p === '/api/profile' && req.method === 'POST') {
    try {
      const body = JSON.parse((await readBody(req)) || '{}');
      merge(profile, body);
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      return res.end(JSON.stringify({ ok: true, profile }));
    } catch (e) {
      res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
      return res.end(JSON.stringify({ error: 'invalid json: ' + e.message }));
    }
  }
  if (p === '/api/flag' && req.method === 'GET') {
    const gate = {};                       // 每次请求新建的"空对象"
    if (gate.admin === true) {             // 原型被污染后这里会命中
      res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
      return res.end('✅ gate.admin === true（原型链污染生效）。高级功能口令：' + FLAG);
    }
    res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
    return res.end('❌ gate.admin === ' + JSON.stringify(gate.admin) + '，未检测到管理员标记。');
  }
  if (p === '/api/reset') {
    for (const k of Object.keys(Object.prototype)) {
      if (!['constructor', '__defineGetter__', '__defineSetter__', 'hasOwnProperty', '__lookupGetter__', '__lookupSetter__', 'isPrototypeOf', 'propertyIsEnumerable', 'toString', 'toLocaleString', 'valueOf'].includes(k)) {
        delete Object.prototype[k];
      }
    }
    profile.nickname = '星尘旅人'; profile.motto = '保持好奇'; profile.level = 1;
    delete profile.__proto__ && 0;
    for (const k of Object.keys(profile)) if (!['nickname', 'motto', 'level'].includes(k)) delete profile[k];
    res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
    return res.end('已清理 Object.prototype 上的污染键，资料恢复默认。');
  }
  res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
  res.end('not found');
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`[pp1] listening on http://0.0.0.0:${PORT}/  (deploy behind /pp1/)`);
});
