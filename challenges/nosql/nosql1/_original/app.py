#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
nosql1 — MongoDB 风格操作符注入（纯 Python 模拟，零外部数据源依赖）

模拟一个用 NoSQL（MongoDB 风格查询）做登录认证的站点：
    db.users.findOne({ user: <用户输入>, pass: <用户输入> })
若客户端提交 JSON 对象/表单数组语法，"密码"就成了一个**条件对象**而非字符串，
$ne / $nin / $regex 等操作符即可注入查询逻辑。

WAF 对「原始请求体」做黑名单字符串匹配（$ne、$gt）——
可被 JSON unicode 转义（\\u0024ne）或黑名单遗漏（$nin/$regex）绕过。
"""
import html
import json
import os
import re

from flask import Flask, jsonify, request

BASE = os.path.dirname(os.path.abspath(__file__))
FLAG = os.environ.get("FLAG") or open(
    os.path.join(BASE, "flag.txt"), encoding="utf-8"
).read().strip()

USERS = {
    "guest": {"password": "guest12345", "role": "guest"},
    "alice": {"password": "aliceW0nder", "role": "user"},
    "bob": {"password": "bobTheBu1lder", "role": "user"},
    "admin": {"password": "Adm1n_Sup3r$ecret_9f3e", "role": "admin"},
}

app = Flask(__name__)


# ---------- 简化版 Mongo 匹配器（支持常见操作符） ----------
def mongo_match(stored, cond):
    if isinstance(cond, dict):
        for op, arg in cond.items():
            if op == "$ne":
                if stored == arg:
                    return False
            elif op == "$eq":
                if stored != arg:
                    return False
            elif op in ("$gt", "$gte", "$lt", "$lte"):
                try:
                    a, b = float(stored), float(arg)
                except (TypeError, ValueError):
                    a, b = str(stored), str(arg)
                if not {"$gt": a > b, "$gte": a >= b, "$lt": a < b, "$lte": a <= b}[op]:
                    return False
            elif op == "$regex":
                if not re.search(str(arg), str(stored)):
                    return False
            elif op == "$in":
                if stored not in (arg if isinstance(arg, list) else [arg]):
                    return False
            elif op == "$nin":
                if stored in (arg if isinstance(arg, list) else [arg]):
                    return False
            elif op == "$exists":
                if (stored is not None) != bool(arg):
                    return False
            else:
                return False  # 未知操作符：不匹配
        return True
    return stored == cond  # 普通等值匹配


def find_user(query):
    for name, rec in USERS.items():
        try:
            if mongo_match(name, query.get("user")) and mongo_match(
                rec["password"], query.get("pass")
            ):
                return name, rec
        except re.error:
            continue
    return None


# ---------- 登录接口 ----------
@app.route("/login", methods=["POST"])
def login():
    raw = request.get_data(as_text=True)

    # WAF：对「原始请求体」做黑名单匹配
    for bad in ("$ne", "$gt"):
        if bad in raw:
            return jsonify({"ok": False, "msg": "WAF 拦截：请求体中发现危险操作符 %s" % bad}), 403

    ctype = (request.content_type or "").lower()
    query = {}
    try:
        if "json" in ctype:
            data = json.loads(raw) if raw.strip() else {}
            if not isinstance(data, dict):
                raise ValueError("body must be a JSON object")
            query = {"user": data.get("user"), "pass": data.get("pass")}
        else:
            # 表单/URL 参数：支持 pass[$ne]=x 这类数组语法
            for k, v in request.values.items():
                if k in ("user", "pass"):
                    query[k] = v
                else:
                    m = re.fullmatch(r"(user|pass)\[(\$[A-Za-z]+)\]", k)
                    if m:
                        query[m.group(1)] = {m.group(2): v}
    except (ValueError, json.JSONDecodeError) as e:
        return jsonify({"ok": False, "msg": "参数解析失败: %s" % e}), 400

    if not query or "user" not in query or "pass" not in query:
        return jsonify({"ok": False, "msg": "请同时提交 user 与 pass"}), 400

    hit = find_user(query)
    if hit:
        name, rec = hit
        resp = {"ok": True, "user": name, "role": rec["role"]}
        if rec["role"] == "admin":
            resp["msg"] = "以 admin 身份登录成功，拿到一次性令牌"
            resp["flag"] = FLAG
        else:
            resp["msg"] = "以 %s（%s）登录成功——但这不是 admin，没有 flag" % (name, rec["role"])
        return jsonify(resp)
    return jsonify({"ok": False, "msg": "用户名或密码错误"}), 401


# ---------- 教学页面 ----------
def esc(s):
    return html.escape(str(s), quote=True)


PAGE_CSS = """
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
  .desc, .desc code { color:#c9d1d9; } .desc code { background:#0a0d12; border:1px solid #30363d; border-radius:4px; padding:0 5px; color:#79c0ff; }
  .chips { display:flex; flex-wrap:wrap; gap:8px; }
  .chip { background:#161b22; border:1px solid #30363d; border-radius:14px; padding:2px 12px; font-size:13px; color:#79c0ff; }
  .lab { background:#161b22; border:1px solid #30363d; border-radius:8px; padding:16px; }
  details.hint { background:#161b22; border:1px solid #30363d; border-radius:8px; padding:10px 14px; margin:8px 0; }
  details.hint summary { cursor:pointer; color:#d29922; }
  .hint-body { color:#c9d1d9; margin-top:6px; }
  .srcbox { background:#0a0d12; border:1px solid #30363d; border-radius:8px; padding:12px 14px; overflow:auto; }
  .srcbox pre { margin:0; } .srcbox code { font-family:Consolas,monospace; font-size:13px; }
  textarea, input[type=text] { background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 10px; font-family:Consolas,monospace; width:100%; }
  textarea { min-height:110px; }
  button { background:#1f6feb; border:none; color:#fff; border-radius:6px; padding:8px 18px; cursor:pointer; font-size:14px; margin-top:8px; }
  pre.out { background:#0a0d12; border:1px dashed #30363d; border-radius:6px; padding:10px; overflow:auto; font-size:13px; }
  form.flagform { display:flex; gap:10px; margin-top:10px; flex-wrap:wrap; }
  form.flagform input[type=text] { flex:1; min-width:240px; }
  form.flagform button { background:#238636; margin-top:0; }
  .nav { display:flex; justify-content:space-between; margin-top:40px; border-top:1px solid #30363d; padding-top:14px; font-size:14px; }
  .nav a { color:#58a6ff; text-decoration:none; }
"""


def render_page(o):
    colors = {"easy": ("入门", "#3fb950"), "medium": ("进阶", "#d29922"), "hard": ("高阶", "#f85149")}
    dname, dcolor = colors.get(o["difficulty"], ("?", "#999"))
    chips = "".join('<span class="chip">%s</span>' % esc(k) for k in o["knowledge"])
    hints = "".join(
        '<details class="hint"><summary>💡 提示 %d</summary><div class="hint-body">%s</div></details>'
        % (i + 1, esc(h))
        for i, h in enumerate(o["hints"])
    )
    src_html = '<div class="srcbox"><pre><code>%s</code></pre></div>' % esc(o["src"]) if o.get("src") else ""
    prev, nxt = o.get("prev"), o.get("next")
    nav = "<div class='nav'>%s%s</div>" % (
        "<a href='%s'>← 上一关：%s</a>" % (prev[0], esc(prev[1])) if prev else "<span></span>",
        "<a href='%s'>下一关：%s →</a>" % (nxt[0], esc(nxt[1])) if nxt else "<span></span>",
    )
    return """<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{id} · {title}</title><style>{css}</style></head><body><div class="wrap">
<header>
  <div class="cat">CTF Web 教学靶场 · {catname} · {id}</div>
  <h1>{title}</h1>
  <div class="badges">
    <span class="badge" style="color:{dcolor}">难度：{dname}</span>
    <span class="badge" style="color:#79c0ff">{catname}</span>
  </div>
</header>
<h2>📖 题目描述</h2><div class="desc">{desc}</div>
<h2>🧪 动手实验室</h2><div class="lab">{body}</div>
<h2>🔍 关键源码</h2>{src}
<h2>🎓 知识点</h2><div class="chips">{chips}</div>
<h2>💡 渐进提示（按顺序展开）</h2>{hints}
<h2>🚩 提交 Flag</h2>
<form class="flagform" method="post" action="/api/verify">
  <input type="hidden" name="challenge_id" value="{id}">
  <input type="text" name="flag" placeholder="flag{{{id}_xxxxxxxx" required>
  <button type="submit">提交 Flag</button>
</form>
{nav}
</div></body></html>""".format(
        id=o["id"], title=esc(o["title"]), catname=esc(o["catname"]), css=PAGE_CSS,
        dname=dname, dcolor=dcolor, desc=o["desc"], body=o["body"], src=src_html,
        chips=chips, hints=hints, nav=nav,
    )


SRC_SNIPPET = """# 登录处理（Node/Express + mongodb 原型）
const q = { user: req.body.user, pass: req.body.pass };
db.collection('users').findOne(q, (err, u) => { ... });

# 本题的纯 Python 等价实现
query = {"user": data.get("user"), "pass": data.get("pass")}
hit = find_user(query)   # pass 若是 {"$ne": "x"} 这样的对象 → 操作符注入

# WAF（对原始请求体做黑名单字符串匹配）
for bad in ("$ne", "$gt"):
    if bad in raw: return 403
"""


@app.route("/")
def index():
    body = """
<div>
  <b>方式一 · JSON 登录</b>
  <textarea id="jsonbody">{"user": "admin", "pass": "123456"}</textarea>
  <button onclick="sendJson()">以 JSON 登录</button>
</div>
<div style="margin-top:18px;">
  <b>方式二 · 表单登录</b>
  <div style="display:flex;gap:10px;">
    <input type="text" id="f_user" placeholder="user" value="admin">
    <input type="text" id="f_pass" placeholder="pass" value="123456">
  </div>
  <button onclick="sendForm()">以表单登录</button>
</div>
<p style="color:#8b949e;font-size:13px;">数组/操作符语法（pass[$ne]=x）请用 curl 等工具直接构造请求体。</p>
<pre class="out" id="out" style="margin-top:14px;">等待请求……</pre>
<script>
async function show(promise){
  const r = await promise;
  document.getElementById('out').textContent = JSON.stringify(r, null, 2);
}
function sendJson(){
  show(fetch('/login', {method:'POST', headers:{'Content-Type':'application/json'},
    body: document.getElementById('jsonbody').value})
    .then(async r => ({status: r.status, body: await r.json()})));
}
function sendForm(){
  const u = document.getElementById('f_user').value, p = document.getElementById('f_pass').value;
  show(fetch('/login', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: 'user=' + encodeURIComponent(u) + '&pass=' + encodeURIComponent(p)})
    .then(async r => ({status: r.status, body: await r.json()})));
}
</script>
"""
    return render_page({
        "id": "nosql1", "catname": "NoSQL 与注入变体",
        "title": "不是只有 SQL 才能注入 —— Mongo 操作符注入",
        "difficulty": "medium",
        "desc": "新项目用了 NoSQL：登录查询是 <code>findOne({user: 输入, pass: 输入})</code>。"
                "如果客户端提交的不是字符串而是 <b>JSON 对象</b>（或表单数组语法），"
                "密码条件就变成了 <code>{'$ne': 'xxx'}</code> 这样的<b>操作符</b>——"
                "「密码 ≠ xxx」几乎永远为真。WAF 对请求体做了 $ne/$gt 黑名单，但它扫的是<b>原始文本</b>。",
        "knowledge": ["NoSQL 操作符注入（$ne/$nin/$gt/$regex）", "JSON 体 / 表单数组语法", "WAF 黑名单绕过（unicode 转义）", "$regex 盲注提取口令"],
        "hints": [
            "先正常登录一次看看响应结构；user=admin&pass=wrong 会怎样？",
            "表单数组语法：pass[$ne]=wrongpw 表示条件 {pass: {'$ne': 'wrongpw'}}。WAF 拦 $ne——那 $nin 呢？",
            "curl -d 'user=admin&pass[$nin]=wrongpw' 直接登录；或 JSON 体用 \\u0024ne 绕过 WAF；admin 的口令还可用 pass[$regex]=^A 一类条件逐字符盲注。",
        ],
        "body": body, "src": SRC_SNIPPET,
        "prev": ("/read3", "flag 不在磁盘上"), "next": ("/nosql2", "XML 里的注入"),
    })


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8312, debug=False)
