#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
adv1 — GraphQL 内省 + 变量绕过 WAF（手写简化 GraphQL，零外部依赖）

攻击链：
1. 生产环境未关闭内省（introspection）→ {__schema{types{fields{name}}}}
   发现文档里没有的隐藏字段 systemEnv(key)；
2. 直接查 systemEnv(key:"FLAG") 被 WAF 拦（WAF 只扫「查询文本」中的 flag 关键字）；
3. 用 GraphQL「变量」把参数放到 variables JSON 里（或字符串 \\u 转义）绕过文本 WAF。
"""
import html
import json
import os

from flask import Flask, jsonify, request

BASE = os.path.dirname(os.path.abspath(__file__))
FLAG = os.environ.get("FLAG") or open(
    os.path.join(BASE, "flag.txt"), encoding="utf-8"
).read().strip()
os.environ.setdefault("FLAG", FLAG)  # systemEnv 从真实环境变量读取

app = Flask(__name__)

USERS = [
    {"username": "alice", "role": "admin", "email": "alice@corp.internal"},
    {"username": "bob", "role": "user", "email": "bob@corp.internal"},
    {"username": "carol", "role": "user", "email": "carol@corp.internal"},
]

SCHEMA_META = {
    "types": [
        {"name": "Query", "kind": "OBJECT", "fields": [
            {"name": "hello"}, {"name": "version"}, {"name": "users"}, {"name": "systemEnv"},
        ]},
        {"name": "User", "kind": "OBJECT", "fields": [
            {"name": "username"}, {"name": "role"}, {"name": "email"},
        ]},
        {"name": "String", "kind": "SCALAR", "fields": None},
        {"name": "Boolean", "kind": "SCALAR", "fields": None},
        {"name": "ID", "kind": "SCALAR", "fields": None},
    ]
}


class GQLError(Exception):
    pass


# ---------------- 词法 ----------------
def lex(src):
    toks, i, n = [], 0, len(src)
    while i < n:
        c = src[i]
        if c.isspace():
            i += 1
        elif c == "#":  # 注释
            while i < n and src[i] != "\n":
                i += 1
        elif c in "{}(),:$!":
            toks.append((c, c)); i += 1
        elif c == '"':
            i += 1; buf = []
            while i < n and src[i] != '"':
                ch = src[i]
                if ch == "\\":
                    esc = src[i + 1]
                    if esc == "u":
                        buf.append(chr(int(src[i + 2:i + 6], 16))); i += 6
                    else:
                        buf.append({"n": "\n", "t": "\t", '"': '"', "\\": "\\", "/": "/"}.get(esc, esc))
                        i += 2
                else:
                    buf.append(ch); i += 1
            if i >= n:
                raise GQLError("字符串未闭合")
            i += 1
            toks.append(("STRING", "".join(buf)))
        elif c.isalpha() or c == "_":
            j = i
            while j < n and (src[j].isalnum() or src[j] == "_"):
                j += 1
            toks.append(("IDENT", src[i:j])); i = j
        elif c == "." and src.startswith("...", i):
            toks.append(("SPREAD", "...")); i += 3
        else:
            raise GQLError("无法识别的字符 %r" % c)
    toks.append(("EOF", None))
    return toks


# ---------------- 语法 ----------------
class Field:
    def __init__(self, alias, name, args, subsel):
        self.alias, self.name, self.args, self.subsel = alias, name, args, subsel


class Parser:
    def __init__(self, toks):
        self.toks, self.pos = toks, 0

    def peek(self):
        return self.toks[self.pos]

    def eat(self, expect=None):
        t = self.toks[self.pos]
        if expect and t[0] != expect:
            raise GQLError("期望 %s，收到 %r" % (expect, t[1]))
        self.pos += 1
        return t

    def document(self):
        t = self.peek()
        if t[0] == "IDENT" and t[1] in ("query", "mutation"):
            self.eat()
            if t[1] == "mutation":
                raise GQLError("本服务只读：不支持 mutation")
            if self.peek()[0] == "IDENT":
                self.eat()  # 操作名
            if self.peek()[0] == "(":
                self.var_defs()
            return self.selection_set()
        if t[0] == "{":
            return self.selection_set()
        raise GQLError("文档应以 query 或选择集 { 开头")

    def var_defs(self):
        self.eat("(")
        while self.peek()[0] != ")":
            self.eat("$"); self.eat("IDENT"); self.eat(":")
            self.eat("IDENT")  # 类型名
            if self.peek()[0] == "!":
                self.eat()
            if self.peek()[0] == ",":
                self.eat()
        self.eat(")")

    def selection_set(self):
        self.eat("{")
        fields = []
        while self.peek()[0] != "}":
            fields.append(self.field())
        self.eat("}")
        return fields

    def field(self):
        if self.peek()[0] == "SPREAD":
            raise GQLError("片段暂不支持")
        name = self.eat("IDENT")[1]
        alias = name
        if self.peek()[0] == ":":
            self.eat(); alias = name
            name = self.eat("IDENT")[1]
        args = {}
        if self.peek()[0] == "(":
            self.eat("(")
            while self.peek()[0] != ")":
                k = self.eat("IDENT")[1]; self.eat(":")
                args[k] = self.value()
                if self.peek()[0] == ",":
                    self.eat()
            self.eat(")")
        subsel = self.selection_set() if self.peek()[0] == "{" else None
        return Field(alias, name, args, subsel)

    def value(self):
        t = self.peek()
        if t[0] == "STRING":
            self.eat(); return t[1]
        if t[0] == "$":
            self.eat(); return ("VAR", self.eat("IDENT")[1])
        if t[0] == "IDENT":
            self.eat()
            return {"true": True, "false": False, "null": None}.get(t[1], t[1])
        raise GQLError("参数值仅支持字符串/布尔/null/变量")


# ---------------- 执行 ----------------
def resolve_value(v, variables):
    if isinstance(v, tuple) and v and v[0] == "VAR":
        if not isinstance(variables, dict) or v[1] not in variables:
            raise GQLError("变量 $%s 未提供" % v[1])
        return variables[v[1]]
    return v


def root_resolve(name, args, variables):
    if name == "hello":
        return "world"
    if name == "version":
        return "mini-graphql/1.0 (教学实现)"
    if name == "users":
        return USERS
    if name == "systemEnv":
        key = resolve_value(args.get("key"), variables) if "key" in args else None
        if not isinstance(key, str) or not key:
            raise GQLError('字段 systemEnv 需要字符串参数 key，例如 systemEnv(key:"PATH")')
        return os.environ.get(key)
    raise GQLError("Query 上不存在字段 %r" % name)


def apply_selection(fields, value, variables, typename):
    if value is None:
        return None
    if isinstance(value, list):
        return [apply_selection(fields, v, variables, typename) for v in value]
    out = {}
    for f in fields:
        if f.name == "__typename":
            out[f.alias] = typename
            continue
        if f.name == "__schema":
            sub = f.subsel or []
            out[f.alias] = apply_selection(sub, SCHEMA_META, variables, "__Schema")
            continue
            if f.subsel:
                if isinstance(value, dict) and f.name in value:
                    inner = value[f.name]
                    tname = "User" if isinstance(inner, dict) else typename
                else:
                    inner = root_resolve(f.name, f.args, variables)
                    tname = "User" if isinstance(inner, dict) else typename
                out[f.alias] = apply_selection(f.subsel, inner, variables, tname)
        else:
            if isinstance(value, dict) and f.name in value:
                out[f.alias] = value[f.name]
            else:
                out[f.alias] = root_resolve(f.name, f.args, variables)
    return out


# ---------------- 接口 ----------------
@app.route("/graphql", methods=["POST"])
def graphql():
    try:
        payload = request.get_json(force=True, silent=True) or {}
    except Exception:
        return jsonify({"errors": [{"message": "请求体应为 JSON"}]}), 400
    query = payload.get("query", "")
    variables = payload.get("variables") or {}

    # WAF：只扫查询文本里的关键字
    if "flag" in query.lower():
        return jsonify({"errors": [{"message": "WAF：查询文本包含敏感关键字 flag，已拦截"}]}), 422

    try:
        ast = Parser(lex(query)).document()
        data = apply_selection(ast, {}, variables, "Query")
        return jsonify({"data": data})
    except GQLError as e:
        return jsonify({"errors": [{"message": str(e)}]}), 400


# ---------------- 教学页面 ----------------
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
  textarea { background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 10px; font-family:Consolas,monospace; width:100%; min-height:90px; }
  button { background:#1f6feb; border:none; color:#fff; border-radius:6px; padding:8px 18px; cursor:pointer; font-size:14px; margin-top:8px; }
  button.ghost { background:#30363d; }
  pre.out { background:#0a0d12; border:1px dashed #30363d; border-radius:6px; padding:10px; overflow:auto; font-size:13px; }
  form.flagform { display:flex; gap:10px; margin-top:10px; flex-wrap:wrap; }
  form.flagform input[type=text] { flex:1; min-width:240px; background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 12px; font-family:Consolas,monospace; }
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


SRC_SNIPPET = """# /graphql 处理（关键部分）
query = payload.get("query", "")
variables = payload.get("variables") or {}

if "flag" in query.lower():                       # WAF：只扫查询文本
    return 422 "WAF：查询文本包含敏感关键字 flag"

ast = Parser(lex(query)).document()               # 解析（支持变量/内省）
data = apply_selection(ast, {}, variables, "Query")

# 隐藏字段：文档未公开，只能靠内省发现
if name == "systemEnv":
    return os.environ.get(args["key"])            # 参数 key 来自查询或「变量」
"""


@app.route("/")
def index():
    body = """
<b>Query</b>
<textarea id="q">{ __schema { types { name fields { name } } } }</textarea>
<b>Variables（JSON，可选）</b>
<textarea id="v">{}</textarea>
<button onclick="run()">▶ 运行</button>
<button class="ghost" onclick="setQ('{ hello }')">hello</button>
<button class="ghost" onclick="setQ('{ users { username role email } }')">users</button>
<button class="ghost" onclick="setQ('{ systemEnv(key: \\"PATH\\") }')">systemEnv(PATH)</button>
<button class="ghost" onclick="setQ('query($k: String!) { systemEnv(key: $k) }'); document.getElementById('v').value='{\\n  \\"k\\": \\"FLAG\\"\\n}'">构造变量查询</button>
<pre class="out" id="out" style="margin-top:14px;">等待请求……</pre>
<script>
function setQ(q){ document.getElementById('q').value = q; }
async function run(){
  let vars = {};
  try { vars = JSON.parse(document.getElementById('v').value || '{}'); }
  catch(e){ document.getElementById('out').textContent = 'Variables 不是合法 JSON：' + e; return; }
  const r = await fetch('/graphql', {method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({query: document.getElementById('q').value, variables: vars})});
  document.getElementById('out').textContent = 'HTTP ' + r.status + '\\n' +
    JSON.stringify(await r.json(), null, 2);
}
</script>
"""
    return render_page({
        "id": "adv1", "catname": "进阶攻击面",
        "title": "查询语言的内省 —— GraphQL 信息泄露",
        "difficulty": "hard",
        "desc": "团队把 REST 迁到了 GraphQL，公开文档只写了 hello / version / users 三个字段。"
                "但服务在生产环境<b>没有关闭内省</b>（introspection）——GraphQL 自带一套"
                "「查询自己结构」的元字段。先内省发现隐藏字段，再取回环境变量里的 FLAG。"
                "WAF 会拦截查询文本中出现 flag 关键字的请求——注意它<b>只扫查询文本</b>。",
        "knowledge": ["GraphQL 内省 __schema", "隐藏字段发现", "GraphQL 变量（variables）", "WAF 文本匹配的盲区"],
        "hints": [
            "先运行默认的内省查询：{__schema{types{name fields{name}}}}，看看 Query 上到底有哪些字段。",
            "发现 systemEnv(key) 了吗？直接 {systemEnv(key:\"FLAG\")} 会被 WAF 拦——错误信息会告诉你 WAF 扫的是什么。",
            '把参数挪到 variables：query($k:String!){systemEnv(key:$k)} + {"k":"FLAG"}；或用字符串 unicode 转义 "\\u0046LAG" 绕过文本匹配。',
        ],
        "body": body, "src": SRC_SNIPPET,
        "prev": ("/nosql2", "XML 里的注入"), "next": ("/adv2", "WebSocket 的门禁"),
    })


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8314, debug=False)
