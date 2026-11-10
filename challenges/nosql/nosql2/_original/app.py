#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
nosql2 — XPath 注入（XML 数据源）

模拟一个用 XML 存用户、用 XPath 做认证的老系统：
    //users/user[username='$user' and password='$pass']
用户输入被直接字符串拼接进 XPath 表达式 → 闭合引号 + or 恒真 → 绕过认证。

为保证零依赖与行为可控，本题内置一个 XPath 1.0 子集求值器
（支持 =、!=、and、or、字符串字面量、节点取值），语义与真实 XPath 一致。
"""
import html
import os
import xml.etree.ElementTree as ET

from flask import Flask, jsonify, request

BASE = os.path.dirname(os.path.abspath(__file__))
FLAG = os.environ.get("FLAG") or open(
    os.path.join(BASE, "flag.txt"), encoding="utf-8"
).read().strip()

TREE = ET.parse(os.path.join(BASE, "users.xml"))
ROOT = TREE.getroot()
# 部署时允许用环境变量 FLAG 覆盖 admin 口令（保持与门户统一注入）
for u in ROOT.findall("user"):
    if (u.findtext("role") or "") == "admin":
        u.find("password").text = FLAG

app = Flask(__name__)


# ---------- XPath 子集：词法 + 语法 + 求值 ----------
class XPathParseError(Exception):
    pass


def tokenize(s):
    toks, i = [], 0
    while i < len(s):
        c = s[i]
        if c.isspace():
            i += 1
        elif c == "'":
            j, buf = i + 1, []
            closed = False
            while j < len(s):
                if s[j] == "'":
                    closed = True
                    break
                buf.append(s[j])
                j += 1
            if not closed:
                raise XPathParseError("字符串字面量未闭合")
            toks.append(("STR", "".join(buf)))
            i = j + 1
        elif c.isalpha() or c == "_":
            j = i
            while j < len(s) and (s[j].isalnum() or s[j] in "_-"):
                j += 1
            toks.append(("IDENT", s[i:j]))
            i = j
        elif s.startswith("!=", i) or s.startswith("==", i):
            toks.append(("OP", s[i : i + 2]))
            i += 2
        elif c == "=":
            toks.append(("OP", "="))
            i += 1
        else:
            raise XPathParseError("无法识别的字符 %r" % c)
    toks.append(("EOF", None))
    return toks


def parse(toks):
    pos = [0]

    def peek():
        return toks[pos[0]]

    def eat():
        t = toks[pos[0]]
        pos[0] += 1
        return t

    def operand():
        kind, val = eat()
        if kind == "STR":
            return ("lit", val)
        if kind == "IDENT":
            if val.lower() in ("and", "or"):
                raise XPathParseError("缺少操作数：%s" % val)
            return ("field", val)
        raise XPathParseError("此处应为字符串或节点名，收到 %r" % (val,))

    def comparison():
        node = operand()
        kind, val = peek()
        if kind == "OP":
            eat()
            return ("cmp", val, node, operand())
        raise XPathParseError("缺少比较运算符（= / !=）")

    def and_expr():
        node = comparison()
        while peek()[0] == "IDENT" and peek()[1].lower() == "and":
            eat()
            node = ("and", node, comparison())
        return node

    def or_expr():
        node = and_expr()
        while peek()[0] == "IDENT" and peek()[1].lower() == "or":
            eat()
            node = ("or", node, and_expr())
        return node

    ast = or_expr()
    if peek()[0] != "EOF":
        raise XPathParseError("表达式末尾有多余内容：%r" % (peek()[1],))
    return ast


def evaluate(node, env):
    t = node[0]
    if t == "lit":
        return node[1]
    if t == "field":
        return env.get(node[1], "")
    if t == "cmp":
        op, l, r = node[1], evaluate(node[2], env), evaluate(node[3], env)
        return (l == r) if op in ("=", "==") else (l != r)
    if t == "and":
        return bool(evaluate(node[1], env)) and bool(evaluate(node[2], env))
    if t == "or":
        return bool(evaluate(node[1], env)) or bool(evaluate(node[2], env))
    raise XPathParseError("未知节点 %r" % (t,))


# ---------- 登录接口 ----------
@app.route("/login", methods=["POST"])
def login():
    user = request.form.get("user", "")
    passwd = request.form.get("pass", "")

    # 服务端拼接 XPath（漏洞所在）；求值只针对方括号内的条件部分
    cond = "username='%s' and password='%s'" % (user, passwd)
    expr = "//users/user[%s]" % cond

    try:
        ast = parse(tokenize(cond))
    except XPathParseError as e:
        # 报错回显拼接后的查询 —— 经典错误信息泄露
        return jsonify({"ok": False, "msg": "XPath 解析错误：%s" % e, "query": expr}), 400

    for u in ROOT.findall("user"):
        env = {c.tag: (c.text or "") for c in u}
        if evaluate(ast, env):
            if env.get("role") == "admin":
                return jsonify({
                    "ok": True, "user": env.get("username"), "role": "admin",
                    "msg": "以 admin 登录成功，口令即通关令牌",
                    "flag": env.get("password"),
                })
            return jsonify({"ok": True, "user": env.get("username"), "role": env.get("role", "user"),
                            "msg": "以 %s 登录成功（普通用户没有令牌）" % env.get("username")})
    return jsonify({"ok": False, "msg": "认证失败：用户不存在或口令错误"}), 401


# ---------- 教学页面（与 nosql1 同款样式） ----------
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
        % (i + 1, esc(h)) for i, h in enumerate(o["hints"]))
    src_html = '<div class="srcbox"><pre><code>%s</code></pre></div>' % esc(o["src"]) if o.get("src") else ""
    prev, nxt = o.get("prev"), o.get("next")
    nav = "<div class='nav'>%s%s</div>" % (
        "<a href='%s'>← 上一关：%s</a>" % (prev[0], esc(prev[1])) if prev else "<span></span>",
        "<a href='%s'>下一关：%s →</a>" % (nxt[0], esc(nxt[1])) if nxt else "<span></span>")
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
        chips=chips, hints=hints, nav=nav)


SRC_SNIPPET = """# 服务端认证逻辑（Python + lxml 等价场景）
user  = request.form['user']
passwd = request.form['pass']
expr = "//users/user[username='%s' and password='%s']" % (user, passwd)
hit = xpath_eval(expr)          # 用户输入直接拼进 XPath
if hit: login_as(hit)

# 拼接结果示例（user = admin' or '1'='1）
//users/user[username='admin' or '1'='1' and password='x']
"""


@app.route("/")
def index():
    body = """
<form onsubmit="doLogin(event)">
  <div style="display:flex;gap:10px;">
    <input type="text" id="user" placeholder="用户名（试试注入）" value="admin">
    <input type="text" id="pass" placeholder="密码" value="123456">
  </div>
  <button type="submit">登录</button>
</form>
<pre class="out" id="out" style="margin-top:14px;">等待请求……</pre>
<script>
async function doLogin(e){
  e.preventDefault();
  const body = 'user=' + encodeURIComponent(document.getElementById('user').value)
             + '&pass=' + encodeURIComponent(document.getElementById('pass').value);
  const r = await fetch('/login', {method:'POST',
     headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
  document.getElementById('out').textContent =
    'HTTP ' + r.status + '\\n' + JSON.stringify(await r.json(), null, 2);
}
</script>
"""
    return render_page({
        "id": "nosql2", "catname": "NoSQL 与注入变体",
        "title": "XML 里的注入 —— XPath 认证绕过",
        "difficulty": "medium",
        "desc": "老系统把用户存在 XML 里，认证用 XPath：<code>//users/user[username='$user' and password='$pass']</code>。"
                "用户输入被<b>直接拼接</b>进表达式——单引号可以提前闭合，再注入 <code>or</code> 恒真条件。"
                "admin 的口令就是通关令牌。解析出错时接口还会<b>回显拼接后的查询</b>，帮你调试。",
        "knowledge": ["XPath 注入（引号闭合）", "or 恒真绕过认证", "报错回显调试", "参数化 XPath 修复"],
        "hints": [
            "先提交 user=admin&pass=wrong 看看失败响应；再提交带单引号的 user=admin' 观察报错回显的 query。",
            "目标：让引号被「吐掉」之后整体条件恒真。注意 and 优先级高于 or。",
            "user 填 admin' or '1'='1，pass 随意（如 x）；或 user 与 pass 都填 ' or '1'='1。",
        ],
        "body": body, "src": SRC_SNIPPET,
        "prev": ("/nosql1", "不是只有 SQL 才能注入"), "next": ("/adv1", "查询语言的内省"),
    })


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8313, debug=False)
