#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ============================================================
# crlf2 · CRLF 进阶：注入 memcached 文本协议
# 架构：一个进程两个监听——
#   :HTTP_PORT(8312)  Web 应用（http.server，标准库）
#   :MEM_PORT(8313)   迷你 memcached（文本协议子集 get/set/delete，模拟内网缓存）
# 漏洞：/view?key=<用户输入> 把 key 原样拼进 "get <key>\r\n" 发给 memcached，
#       未过滤 \r\n → 可注入第二条命令（set role …）污染缓存 → /admin 放行
# 判定：/admin 读 get role，值为 admin 时给 flag（缓存是服务端状态，手改无效）
# 对照：/fixed/view?key= 对 key 做白名单校验（[A-Za-z0-9_.-]{1,64}）
# 运行：py -3 server.py [http_port] [mem_port]   默认 8312 8313
# ============================================================
import http.server
import re
import socket
import sys
import threading
import urllib.parse

FLAG = 'flag{crlf2_ec647c45}'
HTTP_PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8312
MEM_PORT = int(sys.argv[2]) if len(sys.argv) > 2 else 8313

# ---------------- 迷你 memcached（文本协议子集） ----------------
MEM = {
    'role': 'guest',                                    # 权限角色（本关目标）
    'announcement': '系统维护公告：周四 02:00-04:00 停机',   # 首页展示
    'motto': 'Cache me if you can.',
}

def mem_handle(conn):
    buf = b''
    try:
        conn.sendall(b'STATS mini-memcached ready\r\n')
        while True:
            data = conn.recv(4096)
            if not data:
                return
            buf += data
            # 逐命令解析（支持管道化：一条连接里多条命令、含 set 的数据块）
            while True:
                line_end = buf.find(b'\r\n')
                if line_end < 0:
                    break
                line = buf[:line_end].decode('utf-8', 'replace').strip()
                buf = buf[line_end + 2:]
                if not line:
                    continue
                parts = line.split(' ')
                cmd = parts[0].lower()
                if cmd in ('quit', 'exit'):
                    return
                if cmd == 'get':
                    out = b''
                    for k in parts[1:]:
                        if k in MEM:
                            v = MEM[k].encode('utf-8')
                            out += ('VALUE %s 0 %d\r\n' % (k, len(v))).encode() + v + b'\r\n'
                    out += b'END\r\n'
                    conn.sendall(out)
                elif cmd == 'set':
                    # set <key> <flags> <exptime> <bytes>\r\n<data block>\r\n
                    if len(parts) < 5:
                        conn.sendall(b'CLIENT_ERROR bad command line\r\n')
                        continue
                    key, n = parts[1], int(parts[4])
                    while len(buf) < n + 2:
                        more = conn.recv(4096)
                        if not more:
                            return
                        buf += more
                    val = buf[:n].decode('utf-8', 'replace')
                    buf = buf[n + 2:]
                    MEM[key] = val
                    conn.sendall(b'STORED\r\n')
                elif cmd == 'delete':
                    k = parts[1] if len(parts) > 1 else ''
                    if k in MEM:
                        del MEM[k]
                        conn.sendall(b'DELETED\r\n')
                    else:
                        conn.sendall(b'NOT_FOUND\r\n')
                elif cmd == 'flush_all':
                    MEM.clear()
                    conn.sendall(b'OK\r\n')
                else:
                    conn.sendall(b'ERROR unknown command\r\n')
    except Exception:
        pass
    finally:
        try:
            conn.close()
        except Exception:
            pass

def memcached():
    srv = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    srv.bind(('127.0.0.1', MEM_PORT))
    srv.listen(16)
    print('[crlf2] mini-memcached on 127.0.0.1:%d' % MEM_PORT)
    while True:
        c, _ = srv.accept()
        threading.Thread(target=mem_handle, args=(c,), daemon=True).start()

def mc_send(payload, read_until=b'END\r\n', timeout=2.0):
    """发原始文本给 memcached，读响应（模拟应用的缓存客户端）。
    读到边界标记后再补读一小段，以便回显管道化命令（如注入的 set）的后续回复。"""
    s = socket.create_connection(('127.0.0.1', MEM_PORT), timeout)
    try:
        s.sendall(payload.encode('utf-8'))
        s.settimeout(timeout)
        out = b''
        while True:
            try:
                chunk = s.recv(4096)
            except socket.timeout:
                break
            if not chunk:
                break
            out += chunk
            if read_until and read_until in out:
                # 已见边界：短暂补读，收集同管道里后续命令的回复
                s.settimeout(0.4)
                try:
                    while True:
                        more = s.recv(4096)
                        if not more:
                            break
                        out += more
                except socket.timeout:
                    pass
                break
        return out.decode('utf-8', 'replace')
    finally:
        s.close()

def mc_get(key):
    """应用正常的 get 封装。"""
    r = mc_send('get %s\r\n' % key)
    m = re.search(r'VALUE \S+ 0 \d+\r\n(.*?)\r\nEND', r, re.S)
    return m.group(1) if m else None

# ---------------- Web 应用 ----------------
CSS = """
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#34d399;color:#022c22;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.okbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:14px 16px;margin-top:12px}
.nobox{background:#0b1220;border:1px solid #27364d;color:var(--dim);border-radius:8px;padding:14px 16px;margin-top:12px}
.errbox{background:#3b1519;border:1px solid #7f1d1d;color:#fca5a5;border-radius:8px;padding:10px 14px;margin-top:12px}
.result{background:#0b1220;border:1px solid #27364d;border-radius:8px;margin-top:12px}
.result .t{padding:6px 12px;border-bottom:1px solid #27364d;color:var(--dim);font-size:12px}
.result pre{border:0;margin:0}
a{color:var(--acc)}footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
input{padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
"""

VERIFY_JS = """
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=crlf2&flag='+encodeURIComponent(v)})
   .then(function(r){return r.text().then(function(t){
      var msg='HTTP '+r.status+': '+t;
      try{var j=JSON.parse(t);
        if(typeof j.correct==='boolean')msg=j.correct?'✅ 回答正确！':'❌ flag 不对，再试试';
        else if(j.message!==undefined)msg=j.message;
      }catch(e){}
      el.textContent=msg;})})
   .catch(function(e){el.textContent='提交失败('+e.message+')：请从靶场门户页提交'});
}
"""

def esc(s):
    return (s.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;'))

def page(body_extra):
    return ('<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
            '<meta name="viewport" content="width=device-width,initial-scale=1">'
            '<title>crlf2 · memcached 注入 | CTF 教学靶场</title><style>' + CSS + '</style></head>'
            '<body><div class="wrap">'
            '<header><span class="badge">CRLF · 第 2 关</span>'
            '<h1>星云 CDN · 缓存查询台</h1>'
            '<div class="meta">难度：★★★★☆ 高阶 | 关卡 ID：<code>crlf2</code> · 运行时：Python（http.server + 迷你 memcached）</div></header>'
            + body_extra +
            '<section class="card"><h2>提交 Flag</h2>'
            '<form id="flagForm" class="flagrow" onsubmit="return false">'
            '<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">'
            '<button onclick="submitFlag()">提交</button></form><div id="flagResult"></div></section>'
            '<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer></div>'
            '<script>' + VERIFY_JS + '</script></body></html>')

HOME_BODY = """
<section class="card"><h2>题目描述</h2>
<p>星云 CDN 的页面内容走 <b>memcached</b> 缓存（内网 <code>127.0.0.1:%MEMPORT%</code>，本站自带一个文本协议迷你版）。
运营后台有个"缓存查询台"：<code>/view?key=announcement</code> ——输入 key，应用把它拼进文本协议发查询。</p>
<p>控制室的 <code>/admin</code> 页按缓存里的 <code>role</code> 值放行（初始 <code>guest</code>）。应用层<b>没有任何写缓存的接口</b>。
但文本协议的每条命令，是用 <code>\\r\\n</code> 分隔的……</p>
<table>
<tr><th>缓存键</th><th>当前值（服务端实时）</th></tr>
<tr><td><code>announcement</code></td><td>%ANN%</td></tr>
<tr><td><code>role</code></td><td>%ROLE%</td></tr>
</table>
<p><a href="/admin">进入 /admin 控制室</a> ｜ <a href="/reset">重置缓存（恢复 role=guest）</a></p>
</section>

<section class="card"><h2>实验区 · 缓存查询台</h2>
<form method="get" action="/view">
<label>key：<input type="text" name="key" value="announcement" size="40"></label>
<button type="submit">查询</button>
</form>
<p style="color:var(--dim);font-size:13px">查询台会把 key 原样拼进 <code>get &lt;key&gt;\\r\\n</code> 发给缓存服务。
修复对照版（key 白名单校验）：<code>/fixed/view?key=…</code></p>
%VIEWRESULT%
</section>

<section class="card"><h2>知识点讲解</h2>
<p>应用的查询实现：</p>
<pre>sock.sendall("get " + key + "\\r\\n")     # key 未过滤 → \\r\\n 可注入第二条命令</pre>
<p><b>文本协议注入</b>：memcached / Redis / SMTP / FTP 这类"一行一命令"的协议，用 <code>\\r\\n</code> 做命令边界。
凡把不可信输入拼进命令的位置，都和 crlf1 的响应头同源——只是"受害者"从 HTTP 头换成了后端协议。
历史上最著名的两个场景：</p>
<ul>
<li><b>memcached/Redis 注入</b>：常经由 SSRF 的 gopher:// 打进内网（B 组 ssrf4 已演过 gopher 打 Redis）；
本题则是应用自身把用户输入拼进命令，更直接。</li>
<li><b>HTTP 响应拆分 → 缓存投毒</b>：crlf1 的近亲，注入的响应体被 CDN 缓存（SPEC 规划的缓存投毒专题）。</li>
</ul>
<p>注入 set 命令的形态（注意 set 的数据块也要用 \\r\\n 收尾）：</p>
<pre>get x\\r\\n
set role 0 0 5\\r\\n
admin\\r\\n</pre>
<p>查询台对每条命令的回复都会回显（END / STORED …），便于你观察注入是否生效。</p>
</section>

<section class="card"><h2>渐进提示</h2>
<details><summary>提示 1：先玩查询台</summary><p><code>/view?key=announcement</code> 能看到公告；<code>/view?key=role</code> 能看到 guest。注意 %0d%0a 会被 URL 解码成命令分隔符。</p></details>
<details><summary>提示 2：目标是什么命令</summary><p>应用没有写接口，但 memcached 自己有 <code>set</code>。让"查询"裂变成"查询 + 写入"：写 <code>role</code> 为 <code>admin</code>（5 字节）。</p></details>
<details><summary>提示 3：payload</summary><p><code>/view?key=x%0d%0aset%20role%200%200%205%0d%0aadmin%0d%0a</code><br>回显应出现 <code>STORED</code>；然后访问 <code>/admin</code>。对照：<code>/fixed/view</code> 同 payload 会被 key 白名单拒绝。</p></details>
</section>
"""

class Handler(http.server.BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, fmt, *args):
        pass

    def send_html(self, html, status=200):
        b = html.encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Content-Length', str(len(b)))
        self.end_headers()
        self.wfile.write(b)

    def do_GET(self):
        u = urllib.parse.urlsplit(self.path)
        qs = dict(urllib.parse.parse_qsl(u.query, keep_blank_values=True))
        path = u.path
        if path not in ('/', '/view', '/fixed/view', '/admin', '/reset'):
            # 兼容反代前缀 /crlf2/...
            path = re.sub(r'^/crlf2', '', path) or '/'
        if path == '/':
            body = (HOME_BODY
                    .replace('%MEMPORT%', str(MEM_PORT))
                    .replace('%ANN%', esc(mc_get('announcement') or '(未命中)'))
                    .replace('%ROLE%', '<code>' + esc(mc_get('role') or '') + '</code>')
                    .replace('%VIEWRESULT%', ''))
            return self.send_html(page(body))

        if path == '/view':
            key = qs.get('key', '')
            raw = mc_send('get %s\r\n' % key, read_until=b'END\r\n')   # ← 漏洞：key 原样拼接
            view = ('<div class="result"><div class="t">发送给 127.0.0.1:%d 的内容</div><pre>%s</pre>'
                    '<div class="t">memcached 原始回复</div><pre>%s</pre></div>'
                    % (MEM_PORT, esc('get %s\r\n' % key), esc(raw or '(无响应)')))
            body = (HOME_BODY
                    .replace('%MEMPORT%', str(MEM_PORT))
                    .replace('%ANN%', esc(mc_get('announcement') or '(未命中)'))
                    .replace('%ROLE%', '<code>' + esc(mc_get('role') or '') + '</code>')
                    .replace('%VIEWRESULT%', view))
            return self.send_html(page(body))

        if path == '/fixed/view':
            key = qs.get('key', '')
            if not re.fullmatch(r'[A-Za-z0-9_.\-]{1,64}', key):
                body = ('<section class="card"><h2>已拒绝（修复版）</h2>'
                        '<div class="errbox">key 含非法字符（白名单 [A-Za-z0-9_.-]）：<code>' + esc(key) + '</code></div>'
                        '<p><a href="/">返回</a></p></section>')
                return self.send_html(page(body), 400)
            raw = mc_send('get %s\r\n' % key)
            body = ('<section class="card"><h2>修复版查询结果</h2><pre>' + esc(raw) + '</pre></section>')
            return self.send_html(page(body))

        if path == '/admin':
            role = mc_get('role')
            if role == 'admin':
                body = ('<section class="card"><h2>控制室</h2>'
                        '<div class="okbox">✅ role=admin（缓存污染成功）。控制室口令：<b>' + FLAG + '</b></div></section>')
            else:
                body = ('<section class="card"><h2>控制室</h2>'
                        '<div class="nobox">⛔ role=' + esc(role or '(空)') + '，需要 admin。缓存是服务端状态——改浏览器东西没用。</div></section>')
            return self.send_html(page(body))

        if path == '/reset':
            MEM['role'] = 'guest'
            MEM['announcement'] = '系统维护公告：周四 02:00-04:00 停机'
            body = ('<section class="card"><h2>缓存已重置</h2>'
                    '<div class="okbox">role=guest 已恢复。</div><p><a href="/">返回</a></p></section>')
            return self.send_html(page(body))

        self.send_html(page('<section class="card"><h2>404</h2></section>'), 404)


def main():
    threading.Thread(target=memcached, daemon=True).start()
    srv = http.server.ThreadingHTTPServer(('0.0.0.0', HTTP_PORT), Handler)
    print('[crlf2] web on http://0.0.0.0:%d/   (deploy behind /crlf2/)' % HTTP_PORT)
    srv.serve_forever()


if __name__ == '__main__':
    main()
