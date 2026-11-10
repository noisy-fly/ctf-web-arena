#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ============================================================
# graphql1 · GraphQL 内省泄露
# 漏洞：生产环境开放内省（__schema/__type 全量可查）
#   → 文档里"不存在"的 Query.systemConfig 被揪出来 → adminNote 即 flag
# 运行：py -3 server.py [port]   默认 8318（依赖 graphql-core，pip install graphql-core）
# ============================================================
import json
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlsplit

from graphql import (GraphQLArgument, GraphQLBoolean, GraphQLField, GraphQLList,
                     GraphQLInt, GraphQLObjectType, GraphQLSchema,
                     GraphQLString, graphql_sync)

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8318
FLAG = 'flag{graphql1_c07aa601}'

# ---------------- 数据 ----------------
BOOKS = [
    {'id': 1, 'title': '三体·黑暗森林', 'author': '刘慈欣', 'year': 2008},
    {'id': 2, 'title': '宇宙尽头的餐馆', 'author': 'Douglas Adams', 'year': 1980},
    {'id': 3, 'title': '银河系搭车客指南', 'author': 'Douglas Adams', 'year': 1979},
    {'id': 4, 'title': '沙丘', 'author': 'Frank Herbert', 'year': 1965},
]

# ---------------- Schema ----------------
book_type = GraphQLObjectType('Book', lambda: {
    'id': GraphQLField(GraphQLInt),
    'title': GraphQLField(GraphQLString),
    'author': GraphQLField(GraphQLString),
    'year': GraphQLField(GraphQLInt),
})

# "内部"类型：文档与页面均未提及
system_config_type = GraphQLObjectType('SystemConfig', lambda: {
    'maintenanceMode': GraphQLField(GraphQLBoolean, description='维护模式开关'),
    'adminNote': GraphQLField(GraphQLString, description='管理员备注（内部）'),
})

query_type = GraphQLObjectType('Query', lambda: {
    'books': GraphQLField(GraphQLList(book_type), description='馆藏图书列表（公开）',
                          resolve=lambda src, info: BOOKS),
    'book': GraphQLField(book_type, description='按 id 查询图书（公开）',
                         args={'id': GraphQLArgument(GraphQLInt)},
                         resolve=lambda src, info, id: next((b for b in BOOKS if b['id'] == id), None)),
    # ↓ 未写入任何文档、页面菜单也没有——但内省能看见
    'systemConfig': GraphQLField(system_config_type, description='[内部] 系统配置（请勿对外暴露）',
                                  resolve=lambda src, info: {'maintenanceMode': False, 'adminNote': FLAG}),
})

SCHEMA = GraphQLSchema(query=query_type)

# ---------------- HTTP ----------------
CSS = """
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:940px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#f472b6;color:#3b0a22;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
textarea{width:100%;min-height:110px;padding:10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink);font-family:Consolas,monospace;font-size:13px}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer;margin:2px 4px 2px 0}
button:hover{filter:brightness(1.15)}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
#out{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px 14px;margin-top:10px;font-size:13px;white-space:pre-wrap;word-break:break-all;min-height:24px;max-height:420px;overflow:auto}
a{color:var(--acc)}
"""

PAGE = """<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>graphql1 · 内省泄露 | CTF 教学靶场</title><style>""" + CSS + """</style></head><body><div class="wrap">
<header><span class="badge">GraphQL · 第 1 关</span>
<h1>星际图书馆 · 开放 API</h1>
<div class="meta">难度：★★★☆☆ 基础 | 关卡 ID：<code>graphql1</code> · 运行时：Python + graphql-core</div></header>

<section class="card"><h2>题目描述</h2>
<p>星际图书馆把馆藏查询做成了 GraphQL API（<code>POST /graphql</code>）。官方文档只写了两个入口：</p>
<pre>query {{ books {{ title author }} }}
query {{ book(id: 1) {{ title }} }}</pre>
<p>图书目录平平无奇。但馆长室留了一条"内部备注"在 API 里——文档没写、菜单没有。
GraphQL 有个特色能力：<b>内省（Introspection）</b>，API 可以自己描述自己。你的目标：用内省把那条"看不见的入口"挖出来，读到备注（flag）。</p></section>

<section class="card"><h2>实验区 · GraphQL 控制台</h2>
<textarea id="q">{
  books {
    title
    author
  }
}</textarea><br>
<button onclick="run()">▶ 运行（POST /graphql）</button>
<button onclick="fill('intro')">🪞 填入内省查询</button>
<div id="out">（响应区）</div></section>

<section class="card"><h2>知识点讲解</h2>
<p><b>内省</b>是 GraphQL 的"自描述"机制：schema 里保留了一组以 <code>__</code> 开头的元字段，最常见的两个：</p>
<pre>{{ __schema {{ queryType {{ fields {{ name description }} }} }} }}   # 查询入口清单
{{ __type(name: "Book") {{ fields {{ name }} }} }}                  # 某类型的字段清单</pre>
<p>生产环境开放内省 = 把完整的 API 地图（包括没写进文档的内部字段、废弃字段、敏感参数）发给任何访问者。
攻击者省去逐个猜测：一份 <code>__schema</code> 全量导出（或 <code>get-graphql-schema</code>/Insomnia/GraphiQL 的文档页）即可看见 <code>Query.systemConfig</code> 这种"内部"入口。</p>
<p>防御上要么关闭内省（生产环境，下一关就是这么做的），要么接受"schema 必须当成公开信息"来设计——内部字段放鉴权后面，而不是靠"不写文档"来藏。</p>
<p>curl 等价操作：</p>
<pre>curl -s http://&lt;host&gt;/graphql1/graphql -H "Content-Type: application/json" \\
     -d '{{"query":"{ __schema { queryType { fields { name } } } }"}}</pre>
</section>

<section class="card"><h2>渐进提示</h2>
<details><summary>提示 1：先跑通控制台</summary><p>默认查询 books 正常返回。试试文档里的另一个入口 book(id:1)。</p></details>
<details><summary>提示 2：让 API 说出自己的全部门面</summary><p>点"🪞 填入内省查询"，运行——<code>__schema.queryType.fields</code> 会列出 Query 类型上的所有字段。和官方文档对比，多出来的是谁？</p></details>
<details><summary>提示 3：读取内部备注</summary><p><code>{{ systemConfig {{ maintenanceMode adminNote }} }}</code>——adminNote 就是 flag。</p></details>
</section>

<section class="card"><h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button></form><div id="flagResult"></div></section>

<nav class="nav"><a href="../pp2">← 上一关：pp2</a><a href="../graphql2">下一关：graphql2 · 越权 →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer></div>
<script>
var out=document.getElementById('out');
function show(t){out.textContent=t}
function run(){
  var q=document.getElementById('q').value;
  fetch('/graphql',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({query:q})})
    .then(r=>r.text()).then(show).catch(e=>show('ERR '+e));
}
function fill(kind){
  document.getElementById('q').value =
'{\\n  __schema {\\n    queryType {\\n      fields {\\n        name\\n        description\\n      }\\n    }\\n  }\\n}';
}
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=graphql1&flag='+encodeURIComponent(v)})
   .then(function(r){return r.text().then(function(t){
      var msg='HTTP '+r.status+': '+t;
      try{var j=JSON.parse(t);
        if(typeof j.correct==='boolean')msg=j.correct?'✅ 回答正确！':'❌ flag 不对，再试试';
        else if(j.message!==undefined)msg=j.message;
      }catch(e){}
      el.textContent=msg;})})
   .catch(function(e){el.textContent='提交失败('+e.message+')：请从靶场门户页提交'});
}
</script></body></html>"""


class Handler(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, fmt, *args):
        pass

    def _html(self, html, status=200):
        b = html.encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Content-Length', str(len(b)))
        self.end_headers()
        self.wfile.write(b)

    def do_GET(self):
        path = urlsplit(self.path).path
        path = path.replace('/graphql1', '') or '/'
        if path == '/':
            return self._html(PAGE)
        if path == '/graphql':
            qs = parse_qs(urlsplit(self.path).query)
            query = (qs.get('query') or [''])[0]
            return self._graphql(query)
        self._html('not found', 404)

    def do_POST(self):
        path = urlsplit(self.path).path
        path = path.replace('/graphql1', '') or '/'
        if path == '/graphql':
            n = int(self.headers.get('Content-Length') or 0)
            try:
                body = json.loads(self.rfile.read(n).decode('utf-8') or '{}')
            except Exception:
                body = {}
            return self._graphql(body.get('query', ''))
        self._html('not found', 404)

    def _graphql(self, query):
        result = graphql_sync(SCHEMA, query)
        payload = json.dumps(result.formatted, ensure_ascii=False, indent=2)
        b = payload.encode('utf-8')
        self.send_response(200)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(b)))
        self.end_headers()
        self.wfile.write(b)


def main():
    srv = ThreadingHTTPServer(('0.0.0.0', PORT), Handler)
    print('[graphql1] listening on http://0.0.0.0:%d/   (deploy behind /graphql1/)' % PORT)
    srv.serve_forever()


if __name__ == '__main__':
    main()
