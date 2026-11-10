#!/usr/bin/env python3
"""miscw2 星云留言板 —— HTTP 响应头注入 / CRLF(Set-Cookie 注入)

为什么用纯标准库手写响应?因为 PHP header()、Flask/Werkzeug、Node res.setHeader()
都会拦截包含 \\r\\n 的头值——这是框架的"补救"。要还原"开发自己拼响应头"的经典漏洞,
必须亲手写响应。

运行: python server.py [port]   (默认 8116)
"""
import re
import secrets
import sys
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, unquote, urlparse

FLAG = 'flag{miscw2_e688c64d}'
PORT = int(sys.argv[1]) if len(sys.argv) > 1 else int(__import__('os').environ.get('PORT', 8116))

_lock = threading.Lock()
SESSIONS = {}   # sess_token -> {'issued': ['role=admin', ...]}  由服务端响应实际下发过的 Cookie


def sess_of(handler):
    m = re.search(r'(?:^|;\s*)sess=([A-Za-z0-9]+)', handler.headers.get('Cookie', '') or '')
    with _lock:
        return SESSIONS.get(m.group(1)) if m else None


PAGE = """<!doctype html><html lang=zh><head><meta charset=utf-8><title>miscw2 星云留言板 · CRLF</title>
<style>
:root{--pri:#0f766e;--bg:#f5f7fb;--ink:#1f2937;--mut:#6b7280;--ok:#059669;--warn:#d97706;--err:#dc2626}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--ink);line-height:1.65}
.wrap{max-width:880px;margin:0 auto;padding:24px 16px 64px}
header{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin-bottom:6px}
.tag{background:var(--pri);color:#fff;border-radius:6px;padding:2px 10px;font-size:13px}
h1{font-size:22px;margin:0}.stars{color:#f59e0b;font-size:14px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 20px;margin:14px 0}
.card h2{font-size:16px;margin:0 0 8px;border-left:4px solid var(--pri);padding-left:8px}
details{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px 14px;margin:8px 0}
summary{cursor:pointer;font-weight:600;color:var(--mut)}
code,pre{font-family:Consolas,Menlo,monospace;font-size:13px;background:#f1f5f9;border-radius:4px}
code{padding:1px 5px}pre{padding:10px 12px;overflow:auto}
.btn{display:inline-block;background:var(--pri);color:#fff;border:0;border-radius:6px;padding:7px 16px;font-size:14px;cursor:pointer;text-decoration:none}
.msg{padding:8px 12px;border-radius:6px;margin:8px 0}.err{background:#fef2f2;color:var(--err)}.ok{background:#ecfdf5;color:var(--ok)}
.flagbox{border:2px dashed var(--warn)}.muted{color:var(--mut);font-size:13px}
</style></head><body><div class=wrap>
<header><span class=tag>杂项 Web 2/2 · medium</span><h1>星云留言板 —— 会拐弯的响应头</h1><span class=stars>★★☆☆☆</span></header>
<div class=card><h2>场景</h2><p>留言板的"记住我的称呼"功能调用了 <code>/setname?name=…</code>：
服务端把<b>你提交的 name 参数原样拼进响应头</b> <code>Set-Cookie: visitor=…</code> 后返回 302。
管理入口 <a href="admin"><b>/admin</b></a> 只认可<b>由本站响应真正下发过</b>的 <code>role=admin</code> Cookie
（浏览器只会保存服务端下发的 Cookie——自己用开发者工具手改的可不算）。想办法让<b>服务器亲手</b>给你发一份管理员 Cookie。</p></div>
<div class=card><h2>知识点</h2><ul>
<li><b>CRLF 注入 / HTTP 响应拆分</b>：HTTP 头以 <code>\\r\\n</code> 分隔。用户输入未过滤就进入响应头时，<code>%0d%0a</code> 可以"折行"注入任意头（这里是第二条 Set-Cookie），甚至注入完整响应体。</li>
<li><b>现实危害</b>：Set-Cookie 注入 → 会话固定/Cookie tossing（子域覆盖主域 Cookie）；配合代理缓存 → 缓存投毒；老版本还能拆分出第二个完整响应。</li>
<li><b>为什么现在少见</b>：现代框架（PHP header()、Werkzeug、Node）在 API 层拒绝含换行的头值。但"自研网关/手写响应/旧式 CGI"依旧会踩坑——所以输入校验永远是第一道防线。</li>
<li>修复：头值只允许白名单字符（如 <code>^[A-Za-z0-9._-]+$</code>），或对输入做 URL 编码后再放入 Set-Cookie。</li>
</ul></div>
<div class=card><h2>渐进提示</h2>
<details><summary>提示 1</summary><p>先 <code>curl -i "/setname?name=Bob"</code> 观察响应头长什么样，name 出现在哪一行。</p></details>
<details><summary>提示 2</summary><p>URL 编码的回车换行是 <code>%0d%0a</code>。在 name 后面接上 <code>%0d%0aSet-Cookie:%20role=admin</code>，再用 <code>curl -i</code> 看响应头发生了什么。</p></details>
<details><summary>提示 3</summary><p>注入成功后（同一会话）访问 <code>/admin</code> —— 服务端记录过它"下发过 role=admin"，管理入口即对你开放。</p></details>
</div>
<div class="card flagbox"><h2>拿到 Flag 了吗？</h2>
<form method="post" action="/api/verify" target="_blank">
<input type="hidden" name="challenge_id" value="miscw2">
<input name="flag" placeholder="flag{miscw2_xxxxxxxx}" size="34" required>
<button class="btn" type="submit">提交 Flag</button></form>
<p class=muted>提交接口由靶场门户提供（POST /api/verify，字段 challenge_id、flag）。</p></div>
<p class="muted">CTF Web 教学靶场 · 杂项 Web 篇</p></div></body></html>"""


class Handler(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, fmt, *args):
        sys.stderr.write('%s - %s\n' % (self.address_string(), fmt % args))

    def _route(self):
        p = urlparse(self.path)
        path, q = p.path, parse_qs(p.query)
        for r in ('/setname', '/admin', '/'):
            if path == r or path.endswith(r):
                return r, q
        return None, q

    def _html(self, body, status=200, headers=None):
        data = body.encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Content-Length', str(len(data)))
        for k, v in (headers or []):
            self.send_header(k, v)
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self):
        path, q = self._route()

        if path is None:
            self._html('<h1>404</h1>', 404)
            return

        if path == '/':
            # 无会话则发一个
            tok = None
            m = re.search(r'(?:^|;\s*)sess=([A-Za-z0-9]+)', self.headers.get('Cookie', '') or '')
            if m:
                with _lock:
                    if m.group(1) in SESSIONS:
                        tok = m.group(1)
            if tok is None:
                tok = secrets.token_hex(16)
                with _lock:
                    SESSIONS[tok] = {'issued': []}
            self._html(PAGE, headers=[('Set-Cookie', 'sess=' + tok + '; Path=/')])
            return

        if path == '/setname':
            name = unquote(q.get('name', [''])[0])
            # ===== 漏洞代码:用户输入未过滤,直接拼进响应头 =====
            head = (
                'HTTP/1.1 302 Found\r\n'
                'Set-Cookie: visitor=' + name + '\r\n'
                'Location: /?saved=1\r\n'
                'Content-Length: 0\r\n'
                'Connection: close\r\n'
                '\r\n'
            )
            # 模拟"浏览器 Cookie jar 只收服务端下发过的 Cookie":
            # 解析我们真正发出去的头,记录本会话被服务端下发过的 Cookie
            sess = sess_of(self)
            if sess is not None:
                for line in head.split('\r\n'):
                    mm = re.match(r'Set-Cookie:\s*([^=;\s]+)=([^;\s]*)', line)
                    if mm:
                        with _lock:
                            sess['issued'].append(mm.group(1) + '=' + mm.group(2))
            self.close_connection = True
            self.wfile.write(head.encode('latin-1', 'replace'))   # 手写原始响应:换行原样通过
            return

        if path == '/admin':
            sess = sess_of(self)
            issued = sess['issued'] if sess else []
            if 'role=admin' in issued:
                self._html('<div class="card"><h2>管理入口</h2>'
                           '<div class="msg ok">检测到本站确实向你下发过 role=admin（CRLF 注入成功）。'
                           '管理员初始化口令：<code>' + FLAG + '</code></div></div>')
            elif sess is None:
                self._html('<div class="card"><h2>请先访问 <a href="/">首页</a> 获得会话</h2></div>', 403)
            else:
                self._html('<div class="card"><h2>403</h2><div class="msg err">'
                           '你手改的 Cookie 可骗不了人——只认本站响应真正下发过的 role=admin。</div></div>', 403)
            return


if __name__ == '__main__':
    srv = ThreadingHTTPServer(('0.0.0.0', PORT), Handler)
    print('miscw2 serving on 0.0.0.0:%d' % PORT)
    srv.serve_forever()
