#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
adv2 — 跨站 WebSocket 劫持（CSWSH）：服务端不校验 Origin

纯 Python 标准库实现（http.server + 手写 WebSocket 握手与帧编解码），零第三方依赖。

场景：管理台的实时控制通道 ws://HOST/ws 用 Cookie 里的 SessionAdmin 凭证认证，
但握手时**不校验 Origin**。攻击者在自己的网页里写一行
    new WebSocket("ws://HOST/ws")
受害者（已登录管理员）访问攻击页时，浏览器会自动带上 Cookie 完成跨站连接，
攻击者的 JS 就能以管理员身份收发消息——这就是跨站 WebSocket 劫持。

本题用「受害者浏览器模拟器」/simulate 完整复现该攻击：
模拟器携带管理员 Cookie、以攻击者指定的 Origin 发起连接并执行攻击脚本。
对比端点 /ws-strict 演示修复（校验 Origin 后同样的攻击被拒）。
"""
import base64
import hashlib
import json
import os
import secrets
import socket
import struct
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

BASE = os.path.dirname(os.path.abspath(__file__))
FLAG = os.environ.get("FLAG") or open(
    os.path.join(BASE, "flag.txt"), encoding="utf-8"
).read().strip()

ADMIN_TOKEN = secrets.token_hex(16)   # 每次启动随机生成，等价于管理员浏览器里的会话 Cookie
WS_GUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11"
PORT = 8315
ALLOWED_ORIGINS = ("http://localhost:%d" % PORT, "http://127.0.0.1:%d" % PORT)


# ---------------- WebSocket 帧编解码 ----------------
def accept_key(client_key):
    return base64.b64encode(hashlib.sha1((client_key + WS_GUID).encode()).digest()).decode()


def _frame_head(n, masked):
    head = bytearray([0x81])
    flag = 0x80 if masked else 0x00
    if n < 126:
        head.append(flag | n)
    elif n < (1 << 16):
        head += bytes([flag | 126]) + struct.pack(">H", n)
    else:
        head += bytes([flag | 127]) + struct.pack(">Q", n)
    return bytes(head)


def server_text_frame(text):
    payload = text.encode("utf-8")
    return _frame_head(len(payload), masked=False) + payload


def client_text_frame(text):
    payload = bytearray(text.encode("utf-8"))
    key = os.urandom(4)
    for i in range(len(payload)):
        payload[i] ^= key[i % 4]
    return _frame_head(len(payload), masked=True) + bytes(key) + bytes(payload)


def masked_close_frame():
    return bytes([0x88, 0x80]) + os.urandom(4)


def read_frame(read_exact):
    """read_exact(n) 返回恰好 n 字节；返回 (opcode, bytes) 或 None(连接结束)"""
    b = read_exact(2)
    if b is None:
        return None
    b1, b2 = b[0], b[1]
    opcode = b1 & 0x0F
    masked = bool(b2 & 0x80)
    length = b2 & 0x7F
    if length == 126:
        ext = read_exact(2)
        if ext is None:
            return None
        length = struct.unpack(">H", ext)[0]
    elif length == 127:
        ext = read_exact(8)
        if ext is None:
            return None
        length = struct.unpack(">Q", ext)[0]
    if length > (1 << 20):
        raise ValueError("帧过大")
    mask = read_exact(4) if masked else None
    data = read_exact(length)
    if data is None:
        return None
    if mask:
        data = bytes(x ^ mask[i % 4] for i, x in enumerate(data))
    return opcode, data


# ---------------- WebSocket 会话（服务端） ----------------
def ws_session(handler, strict_origin):
    origin = handler.headers.get("Origin")
    if strict_origin and origin not in ALLOWED_ORIGINS:
        body = ("Origin 校验失败：%r 不在白名单内（修复生效）" % (origin or "")).encode("utf-8")
        handler.send_response(403)
        handler.send_header("Content-Type", "text/plain; charset=utf-8")
        handler.send_header("Content-Length", str(len(body)))
        handler.end_headers()
        handler.wfile.write(body)
        return

    key = handler.headers.get("Sec-WebSocket-Key", "")
    handler.connection.sendall(
        ("HTTP/1.1 101 Switching Protocols\r\n"
         "Upgrade: websocket\r\n"
         "Connection: Upgrade\r\n"
         "Sec-WebSocket-Accept: %s\r\n\r\n" % accept_key(key)).encode("utf-8"))

    cookie = handler.headers.get("Cookie", "")
    role = "admin" if ("SessionAdmin=" + ADMIN_TOKEN) in cookie else "guest"

    def send(obj):
        handler.connection.sendall(server_text_frame(json.dumps(obj, ensure_ascii=False)))

    send({"type": "welcome", "role": role, "origin": origin or "(无)"})

    def read_exact(n):
        try:
            data = handler.rfile.read(n)
        except (socket.timeout, OSError):
            return None
        return data if (data and len(data) == n) else None

    handler.connection.settimeout(60)
    try:
        while True:
            frame = read_frame(read_exact)
            if frame is None:
                break
            opcode, data = frame
            if opcode == 0x8:  # close
                try:
                    handler.connection.sendall(bytes([0x88, 0x00]))
                except OSError:
                    pass
                break
            if opcode == 0x9:  # ping
                handler.connection.sendall(bytes([0x8A, len(data)]) + data)
                continue
            if opcode != 0x1:
                continue
            try:
                msg = json.loads(data.decode("utf-8"))
            except Exception:
                send({"type": "error", "msg": "仅接受 JSON 文本帧"})
                continue
            t = msg.get("type")
            if t == "hello":
                send({"type": "hi", "who": msg.get("who", "?"), "yourRole": role})
            elif t == "ping":
                send({"type": "pong"})
            elif t == "get_flag":
                if role == "admin":
                    send({"type": "flag", "flag": FLAG})
                else:
                    send({"type": "error", "msg": "仅管理员可执行 get_flag"})
            else:
                send({"type": "error", "msg": "未知指令 %r（可用：hello/ping/get_flag）" % t})
    except (socket.timeout, OSError, ValueError):
        pass
    finally:
        try:
            handler.connection.close()
        except OSError:
            pass


# ---------------- 受害者浏览器模拟器（WS 客户端） ----------------
def victim_simulate(origin, messages, strict):
    """以受害者（管理员）身份、从攻击者的 Origin 发起 WebSocket 连接并执行攻击脚本"""
    endpoint = "/ws-strict" if strict else "/ws"
    transcript = [{"dir": "info", "data":
                   "受害者浏览器模拟器：连接 ws://127.0.0.1:%d%s | Origin=%s | Cookie=SessionAdmin=<管理员令牌>"
                   % (PORT, endpoint, origin)}]
    try:
        sock = socket.create_connection(("127.0.0.1", PORT), timeout=5)
    except OSError as e:
        return {"ok": False, "transcript": transcript + [{"dir": "info", "data": "连接失败：%s" % e}]}

    rbuf = bytearray()

    def recv_more():
        chunk = sock.recv(4096)
        if not chunk:
            return False
        rbuf.extend(chunk)
        return True

    def read_exact(n):
        while len(rbuf) < n:
            if not recv_more():
                return None
        data = bytes(rbuf[:n])
        del rbuf[:n]
        return data

    req = ("GET %s HTTP/1.1\r\n"
           "Host: 127.0.0.1:%d\r\n"
           "Upgrade: websocket\r\n"
           "Connection: Upgrade\r\n"
           "Sec-WebSocket-Key: %s\r\n"
           "Sec-WebSocket-Version: 13\r\n"
           "Origin: %s\r\n"
           "Cookie: SessionAdmin=%s\r\n\r\n") % (
        endpoint, PORT, base64.b64encode(os.urandom(16)).decode(), origin, ADMIN_TOKEN)
    sock.sendall(req.encode("utf-8"))

    while b"\r\n\r\n" not in rbuf:
        if not recv_more():
            break
    head_end = rbuf.find(b"\r\n\r\n")
    raw_head = bytes(rbuf[:head_end + 4]) if head_end >= 0 else bytes(rbuf)
    del rbuf[:len(raw_head)]
    status = raw_head.split(b"\r\n", 1)[0].decode("utf-8", "replace")
    transcript.append({"dir": "info", "data": "握手响应：" + status})
    if "101" not in status:
        leftover = bytes(rbuf).decode("utf-8", "replace").strip()
        transcript.append({"dir": "info",
                           "data": "服务器拒绝连接（响应体：%s）——攻击失败，这就是修复后的样子" % leftover})
        sock.close()
        return {"ok": False, "transcript": transcript}

    sock.settimeout(1.5)
    ok = True
    try:
        # welcome（可能已在缓冲区）
        try:
            f = read_frame(read_exact)
            if f and f[0] == 0x1:
                transcript.append({"dir": "server→victim", "data": f[1].decode("utf-8", "replace")})
        except socket.timeout:
            pass

        for m in messages:
            sock.sendall(client_text_frame(m))
            transcript.append({"dir": "victim→server", "data": m})
            for _ in range(4):  # 每条消息最多收 4 个响应帧
                try:
                    f = read_frame(read_exact)
                except socket.timeout:
                    break
                if f is None or f[0] != 0x1:
                    break
                transcript.append({"dir": "server→victim", "data": f[1].decode("utf-8", "replace")})
        sock.sendall(masked_close_frame())
    except (socket.timeout, OSError):
        pass
    finally:
        sock.close()
    return {"ok": ok, "transcript": transcript}


# ---------------- HTTP 服务 ----------------
class Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def _json(self, obj, status=200):
        body = json.dumps(obj, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path == "/ws":
            ws_session(self, strict_origin=False)     # ← 漏洞端点：不校验 Origin
            return
        if self.path == "/ws-strict":
            ws_session(self, strict_origin=True)      # ← 修复演示：校验 Origin
            return
        if self.path == "/":
            body = PAGE_HTML.encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return
        self._json({"error": "not found"}, 404)

    def do_POST(self):
        if self.path != "/simulate":
            self._json({"error": "not found"}, 404)
            return
        try:
            length = int(self.headers.get("Content-Length", 0))
            data = json.loads(self.rfile.read(length).decode("utf-8"))
        except Exception:
            self._json({"error": "请求体应为 JSON"}, 400)
            return
        origin = str(data.get("origin", "http://evil.attacker.example"))
        messages = data.get("messages") or ['{"type":"hello"}', '{"type":"get_flag"}']
        if not isinstance(messages, list):
            messages = [str(messages)]
        messages = [str(m) for m in messages]
        strict = bool(data.get("fixed"))
        self._json(victim_simulate(origin, messages, strict))

    def log_message(self, fmt, *args):
        pass


PAGE_HTML = """<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>adv2 · 跨站 WebSocket 劫持</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin:0; font-family:"Segoe UI","Microsoft YaHei",sans-serif; background:#0d1117; color:#e6edf3; line-height:1.65; }
  .wrap { max-width: 900px; margin: 0 auto; padding: 24px 20px 60px; }
  header { border-bottom:1px solid #30363d; padding-bottom:14px; margin-bottom:20px; }
  .cat { color:#58a6ff; font-size:14px; letter-spacing:1px; }
  h1 { font-size:26px; margin:6px 0 10px; }
  .badges { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .badge { font-size:12px; padding:2px 10px; border-radius:10px; border:1px solid currentColor; }
  h2 { font-size:17px; margin:28px 0 10px; color:#58a6ff; border-left:3px solid #58a6ff; padding-left:10px; }
  .desc { color:#c9d1d9; } .desc code { background:#0a0d12; border:1px solid #30363d; border-radius:4px; padding:0 5px; color:#79c0ff; }
  .chips { display:flex; flex-wrap:wrap; gap:8px; }
  .chip { background:#161b22; border:1px solid #30363d; border-radius:14px; padding:2px 12px; font-size:13px; color:#79c0ff; }
  .lab { background:#161b22; border:1px solid #30363d; border-radius:8px; padding:16px; }
  details.hint { background:#161b22; border:1px solid #30363d; border-radius:8px; padding:10px 14px; margin:8px 0; }
  details.hint summary { cursor:pointer; color:#d29922; }
  .hint-body { color:#c9d1d9; margin-top:6px; }
  .srcbox { background:#0a0d12; border:1px solid #30363d; border-radius:8px; padding:12px 14px; overflow:auto; }
  .srcbox pre { margin:0; } .srcbox code { font-family:Consolas,monospace; font-size:13px; }
  input[type=text] { background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 10px; font-family:Consolas,monospace; width:100%; }
  textarea { background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 10px; font-family:Consolas,monospace; width:100%; min-height:80px; }
  button { background:#1f6feb; border:none; color:#fff; border-radius:6px; padding:8px 18px; cursor:pointer; font-size:14px; margin-top:8px; }
  button.ghost { background:#30363d; }
  pre.out { background:#0a0d12; border:1px dashed #30363d; border-radius:6px; padding:10px; overflow:auto; font-size:13px; white-space:pre-wrap; }
  form.flagform { display:flex; gap:10px; margin-top:10px; flex-wrap:wrap; }
  form.flagform input[type=text] { flex:1; min-width:240px; }
  form.flagform button { background:#238636; margin-top:0; }
  .nav { display:flex; justify-content:space-between; margin-top:40px; border-top:1px solid #30363d; padding-top:14px; font-size:14px; }
  .nav a { color:#58a6ff; text-decoration:none; }
</style></head><body><div class="wrap">
<header>
  <div class="cat">CTF Web 教学靶场 · 进阶攻击面 · adv2</div>
  <h1>WebSocket 的门禁 —— 跨站 WebSocket 劫持（CSWSH）</h1>
  <div class="badges">
    <span class="badge" style="color:#f85149">难度：高阶</span>
    <span class="badge" style="color:#79c0ff">进阶攻击面</span>
  </div>
</header>

<h2>📖 题目描述</h2>
<div class="desc">
管理台的实时控制通道是 <code>ws://HOST/ws</code>：浏览器握手时自动携带 Cookie，
服务端用 Cookie 中的 <code>SessionAdmin</code> 判定管理员——但它<b>不校验 Origin</b>。
攻击者只需在自己的页面里写 <code>new WebSocket("ws://HOST/ws")</code>，
管理员访问攻击页时，浏览器会带着他的 Cookie 跨站完成握手，
攻击者的脚本就能以管理员身份执行 <code>{"type":"get_flag"}</code>。
用「受害者浏览器模拟器」复现这起劫持，拿到 flag。
</div>

<h2>🧪 动手实验室</h2>
<div class="lab">
  <b>攻击者页面的 Origin</b>
  <input type="text" id="origin" value="http://evil.attacker.example">
  <b>攻击脚本（每行一个要发送的 JSON 消息）</b>
  <textarea id="msgs">{"type": "hello"}
{"type": "get_flag"}</textarea>
  <button onclick="attack(false)">🚀 发起 CSWSH 攻击</button>
  <button class="ghost" onclick="attack(true)">🛡 在修复版端点(/ws-strict)重放（对比）</button>
  <button class="ghost" onclick="guestDemo()">👤 以游客身份亲自连 /ws（浏览器原生）</button>
  <pre class="out" id="out" style="margin-top:14px;">等待操作……</pre>
</div>

<h2>🔍 关键源码</h2>
<div class="srcbox"><pre><code># /ws 握手处理（漏洞：无 Origin 校验）
def ws_session(handler, strict_origin):
    origin = handler.headers.get("Origin")
    if strict_origin and origin not in ALLOWED_ORIGINS:   # 仅修复版 /ws-strict 生效
        return 403
    send_101_switching_protocols()
    cookie = handler.headers.get("Cookie", "")
    role = "admin" if "SessionAdmin=" + ADMIN_TOKEN in cookie else "guest"
    ...
    elif t == "get_flag":
        if role == "admin": send({"type": "flag", "flag": FLAG})
        else: send({"type": "error"})

# 攻击者页面唯一需要的代码：
new WebSocket("ws://HOST/ws")   // 受害者浏览器自动带上 Cookie</code></pre></div>

<h2>🎓 知识点</h2>
<div class="chips">
  <span class="chip">跨站 WebSocket 劫持 CSWSH</span><span class="chip">Origin 校验缺失</span>
  <span class="chip">Cookie 随 WS 握手自动携带</span><span class="chip">Origin 白名单修复</span>
</div>

<h2>💡 渐进提示（按顺序展开）</h2>
<details class="hint"><summary>💡 提示 1</summary><div class="hint-body">WebSocket 不受同源策略限制：任何页面都能连任何 ws:// 地址，浏览器只负责带上 Cookie。防御靠服务端校验 Origin。</div></details>
<details class="hint"><summary>💡 提示 2</summary><div class="hint-body">点击「发起 CSWSH 攻击」：模拟器会替受害者（带管理员 Cookie、Origin 是攻击者站点）连接并执行你的脚本。</div></details>
<details class="hint"><summary>💡 提示 3</summary><div class="hint-body">curl -X POST http://HOST/adv2/simulate -H 'Content-Type: application/json' -d '{"origin":"http://evil.attacker.example","messages":["{\\"type\\":\\"get_flag\\"}"]}'</div></details>

<h2>🚩 提交 Flag</h2>
<form class="flagform" method="post" action="/api/verify">
  <input type="hidden" name="challenge_id" value="adv2">
  <input type="text" name="flag" placeholder="flag{adv2_xxxxxxxx}" required>
  <button type="submit">提交 Flag</button>
</form>

<div class="nav"><span></span><a href="/adv3">下一关：让缓存替你说谎 →</a></div>
</div>
<script>
async function attack(fixed){
  const origin = document.getElementById('origin').value;
  const messages = document.getElementById('msgs').value.split('\\n').map(s => s.trim()).filter(Boolean);
  const out = document.getElementById('out');
  out.textContent = '攻击运行中……';
  const r = await fetch('/simulate', {method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({origin, messages, fixed})});
  const j = await r.json();
  out.textContent = j.transcript.map(e => '[' + e.dir + '] ' + e.data).join('\\n');
}
function guestDemo(){
  const out = document.getElementById('out');
  out.textContent = '以（无 Cookie 的）游客身份连接 /ws ……';
  const ws = new WebSocket((location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + '/ws');
  const log = [];
  ws.onmessage = e => { log.push('[server→me] ' + e.data); out.textContent = log.join('\\n'); };
  ws.onopen = () => { log.push('[me→server] {"type":"get_flag"}'); ws.send('{"type":"get_flag"}'); };
  ws.onclose = () => { log.push('[info] 连接关闭'); out.textContent = log.join('\\n'); };
}
</script>
</body></html>"""


if __name__ == "__main__":
    print("adv2 listening on 0.0.0.0:%d  (admin token 本次随机生成)" % PORT)
    ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()
