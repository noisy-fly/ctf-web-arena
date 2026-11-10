#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ============================================================
# crlf1 · HTTP 响应头注入（CRLF 注入 Set-Cookie）
# 为什么用原生 socket：PHP header()、Python http.server（Werkzeug）、
# Node setHeader 现代版本都会拒绝值中含 \r\n 的头——框架层已把"应用内
# CRLF"堵死；现实中的 CRLF 漏洞存活于自研网关/代理/上游协议拼接处。
# 本题用纯 socket 手写 HTTP 响应，忠实还原漏洞形态（E 组 miscw2 同思路）。
#
# 路由：
#   GET /                    主页（展示当前身份；服务端真正下发过 role=admin 才给 flag）
#   GET /setlang?lang=X      302 跳回 / ，Set-Cookie: lang=<X 原样拼入，未过滤 CRLF> ← 注入点
#   GET /fixed/setlang?lang= X  同功能但过滤了 \r\n（修复对照）
#
# 判定（防手改 Cookie 走捷径）：服务端维护 issued_cookies——只有真正
# 通过 Set-Cookie 头下发过的 name=value 才算数。
# 运行：py -3 server.py [port]   默认 8311
# ============================================================
import socket
import threading
import urllib.parse
from datetime import datetime

FLAG = 'flag{crlf1_f6199eaa}'
PORT = int(__import__('sys').argv[1]) if len(__import__('sys').argv) > 1 else 8311

issued_cookies = set()   # 服务端真实下发过的 cookie 串，如 "lang=zh-CN"

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
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
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
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=crlf1&flag='+encodeURIComponent(v)})
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

def html_home(role, lang):
    if role == 'admin':
        status = ('<div class="okbox">👑 管理员身份确认（服务端确实下发过 role=admin）。'
                  '仓库管理员口令：<b>' + FLAG + '</b></div>')
    else:
        status = ('<div class="nobox">👤 当前身份：访客（lang=' + (lang or '-') + '，role=guest）。'
                  '管理员页面只认服务端<b>真正下发过</b>的 role=admin——手改 Cookie 无效。</div>')
    return """<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>crlf1 · 响应头注入 | CTF 教学靶场</title><style>""" + CSS + """</style></head><body><div class="wrap">
<header><span class="badge">CRLF · 第 1 关</span>
<h1>星语小铺 · 语言偏好设置</h1>
<div class="meta">难度：★★☆☆☆ 基础 | 关卡 ID：<code>crlf1</code> · 运行时：Python（原生 socket HTTP）</div></header>

<section class="card"><h2>题目描述</h2>
<p>星语小铺的"偏好设置"会把语言值写进 Cookie 再跳回首页：<code>/setlang?lang=zh-CN</code>。</p>
<p>仓库管理页就在首页里——只有当<b>服务端真正下发过</b> <code>role=admin</code> Cookie 时才会亮出管理员口令。
正常流程里没有任何入口能设置 role。但是……那个 <code>lang</code>，服务端是<b>原样</b>拼进 Set-Cookie 响应头的。</p>
<p>HTTP 头与头之间靠 <code>\\r\\n</code> 分隔（CRLF）。如果用户输入能带进 CRLF，一个"值"就能裂变成"任意多的头"。</p>
""" + status + """
<h2 style="margin-top:18px">实验区</h2>
<form method="get" action="/setlang">
<label>语言偏好：<input type="text" name="lang" value="zh-CN" size="30"></label>
<button type="submit">保存偏好</button>
</form>
<p style="color:var(--dim);font-size:13px">curl：<code>curl -si "http://&lt;host&gt;/crlf1/setlang?lang=zh-CN%0d%0aSet-Cookie:%20role=admin"</code></p>
</section>

<section class="card"><h2>知识点讲解</h2>
<p>本关服务端拼响应头的方式（伪码）：</p>
<pre>resp = "HTTP/1.1 302 Found\\r\\n"
resp += "Set-Cookie: lang=" + lang + "\\r\\n"     # lang 未过滤，\\r\\n 可混入
resp += "Location: /\\r\\n\\r\\n"</pre>
<p>当 <code>lang=guest\\r\\nSet-Cookie: role=admin</code> 时，浏览器实际收到：</p>
<pre>HTTP/1.1 302 Found
Set-Cookie: lang=guest
Set-Cookie: role=admin        ← 注入出来的"第二个头"
Location: /</pre>
<p><b>CRLF 注入</b>（HTTP 响应头注入/响应拆分）的根因：把不可信输入拼进"按 CRLF 分隔"的协议文本（HTTP 头、memcached 文本协议、日志头行……）。危害包括：注入 Set-Cookie 冒充身份、伪造 Location、注入 CSP/X-Frame-Options 破坏防护、缓存投毒（下一关）。</p>
<p><b>为什么现在少见又仍要学</b>：PHP <code>header()</code>、Python/Werkzeug、Node 的 <code>setHeader</code> 都已拒绝含 <code>\\r\\n</code> 的头值——应用层被框架堵死；但自研网关、Nginx/Lua 拼上游、代理链、以及<b>头值之外的拼装场景</b>（重定向 Location 拼接、日志注入、缓存键拼接、邮件头）里，CRLF 依旧真实存在。本服务用纯 socket 手写响应，正是模拟这类"框架之外"的位置。</p>
</section>

<section class="card"><h2>渐进提示</h2>
<details><summary>提示 1：观察正常响应头</summary><p><code>curl -si "/setlang?lang=zh-CN"</code>——Set-Cookie 的值就是你的输入。试试输入里能不能塞进"另起一行"。</p></details>
<details><summary>提示 2：CRLF 怎么进 URL</summary><p>URL 里 <code>%0d%0a</code> 会被服务端 URL 解码成 <code>\\r\\n</code>。空格用 <code>%20</code>（或 +）。</p></details>
<details><summary>提示 3：payload</summary><p><code>/setlang?lang=guest%0d%0aSet-Cookie:%20role=admin</code>，用浏览器访问（或 curl -i 看头），然后回到首页——管理员口令出现。对照修复版：<code>/fixed/setlang?lang=…</code>（过滤了 CRLF）。</p></details>
</section>

<section class="card"><h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button></form><div id="flagResult"></div></section>

<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer></div>
<script>""" + VERIFY_JS + """</script></body></html>"""


def parse_request(data):
    try:
        head = data.split(b'\r\n\r\n', 1)[0].decode('utf-8', 'replace')
        lines = head.split('\r\n')
        method, path, _ = lines[0].split(' ', 2)
        headers = {}
        for ln in lines[1:]:
            if ':' in ln:
                k, v = ln.split(':', 1)
                headers[k.strip().lower()] = v.strip()
        return method, path, headers
    except Exception:
        return 'GET', '/', {}


def get_cookie(headers):
    jar = {}
    for pair in headers.get('cookie', '').split(';'):
        if '=' in pair:
            k, v = pair.split('=', 1)
            jar[k.strip()] = v.strip()
    return jar


def response(status, headers_list, body):
    out = ('HTTP/1.1 ' + status + '\r\n').encode('utf-8')
    for h in headers_list:
        out += (h + '\r\n').encode('utf-8')
    out += b'Content-Length: ' + str(len(body)).encode() + b'\r\n'
    out += b'Connection: close\r\n\r\n'
    out += body
    return out


def handle(conn):
    try:
        data = conn.recv(8192)
        if not data:
            return
        method, rawpath, headers = parse_request(data)
        parsed = urllib.parse.urlsplit(rawpath)
        path = parsed.path
        qs = dict(urllib.parse.parse_qsl(parsed.query, keep_blank_values=True))

        if path == '/setlang' or path == '/crlf1/setlang':
            lang = qs.get('lang', '')
            # —— 漏洞：lang 原样拼进响应头，CRLF 可注入新的头 ——
            sc = 'Set-Cookie: lang=' + lang
            issued_cookies.add('lang=' + lang.split('\r\n')[0].split(';')[0].strip())
            for line in lang.split('\r\n')[1:]:
                if line.lower().startswith('set-cookie:'):
                    cv = line[len('set-cookie:'):].strip()
                    issued_cookies.add(cv.split(';')[0].strip())
            body = b''
            conn.sendall(response('302 Found', [sc, 'Location: /', 'Content-Type: text/html'], body))
            return

        if path == '/fixed/setlang' or path == '/crlf1/fixed/setlang':
            lang = qs.get('lang', '')
            if '\r' in lang or '\n' in lang:
                body = '已拒绝：lang 含换行符（CRLF 已过滤）'.encode('utf-8')
                conn.sendall(response('400 Bad Request', ['Content-Type: text/html; charset=utf-8'], body))
                return
            issued_cookies.add('lang=' + lang.split(';')[0].strip())
            conn.sendall(response('302 Found',
                                  ['Set-Cookie: lang=' + lang, 'Location: /', 'Content-Type: text/html'], b''))
            return

        # GET / —— 主页
        jar = get_cookie(headers)
        role = 'admin' if (jar.get('role') == 'admin' and 'role=admin' in issued_cookies) else 'guest'
        body = html_home(role, jar.get('lang', '')).encode('utf-8')
        conn.sendall(response('200 OK', ['Content-Type: text/html; charset=utf-8'], body))
    except Exception as e:
        try:
            conn.sendall(response('500 Internal Server Error', ['Content-Type: text/plain'],
                                  ('error: %s' % e).encode('utf-8')))
        except Exception:
            pass
    finally:
        try:
            conn.close()
        except Exception:
            pass


def main():
    srv = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    srv.bind(('0.0.0.0', PORT))
    srv.listen(16)
    print('[crlf1] listening on http://0.0.0.0:%d/   (deploy behind /crlf1/)' % PORT)
    while True:
        conn, addr = srv.accept()
        threading.Thread(target=handle, args=(conn,), daemon=True).start()


if __name__ == '__main__':
    main()
