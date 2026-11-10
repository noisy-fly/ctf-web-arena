# -*- coding: utf-8 -*-
"""
ssti1 · Jinja2 模板注入基础（SSTI 第一课）
=========================================
漏洞点：render_template_string('... ' + name + ' ...') —— 用户输入被当作
        模板【源码】的一部分传给 Jinja2 渲染，{{ }} 里的表达式会被服务端执行。
考点：  ① 用 {{7*7}} 探测 SSTI；② 用 {{config}} / {{config['FLAG']}} 读敏感配置。
运行：  python app.py            （默认 http://127.0.0.1:5001/ssti1/）
集成：  由 runtime/flask_challenges.py 以 Blueprint 挂到 /ssti1（gunicorn 单进程）。
"""
import html
import os

from flask import Blueprint, Flask, request, render_template_string

FLAG = os.environ.get("SSTI1_FLAG", "flag{ssti1_4a3c9c99}")

bp = Blueprint("ssti1", __name__)


@bp.route("/", methods=["GET"])
def index():
    name = request.args.get("name", "")
    rendered = ""
    if name:
        # ---------------------- 漏洞点 ----------------------
        # 用户输入直接拼进模板源码：{{7*7}} 会被 Jinja2 当表达式执行
        try:
            rendered = render_template_string(
                "欢迎光临 <b>__(" + name + ")__</b>，祝您购物愉快！"
            )
        except Exception as e:
            rendered = "模板渲染出错：%s（这也说明你的输入确实进了模板引擎）" % html.escape(str(e))
    page = PAGE.replace("__NAME__", html.escape(name, quote=True))
    page = page.replace("__RENDERED__", rendered)  # rendered 已由模板引擎产出，仅展示
    return page


PAGE = """<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ssti1 · Jinja2 模板注入基础 | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#0ea5e9;color:#04121f;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
input[type=text]{width:65%;min-width:220px;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
button:hover{filter:brightness(1.15)}
.result{background:#0b1220;border:1px solid #27364d;border-radius:8px;margin-top:12px}
.result .t{padding:6px 12px;border-bottom:1px solid #27364d;color:var(--dim);font-size:12px}
.result pre{border:0;margin:0}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">SSTI · 模板注入 · 第 1 关</span>
<h1>会员欢迎语生成器 —— Jinja2 模板注入基础</h1>
<div class="meta">难度：★☆☆☆☆ 入门 · 关卡 ID：<code>ssti1</code> · 运行时：Python / Flask + Jinja2</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>商城上线了「个性化欢迎语」功能：输入会员昵称，首页横幅就会显示一段欢迎语。开发同学图省事，把你的昵称直接拼进了<b>模板字符串</b>里渲染。</p>
<p>flag 就藏在 Flask 应用的配置里，想办法让模板引擎把它「说出来」。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>会员昵称：<input type="text" name="name" placeholder="先试试输入 {{7*7}}" value="__NAME__"></label>
<button type="submit">生成欢迎语</button>
</form>
<div class="result"><div class="t">渲染结果（注意看你的输入发生了什么变化）</div>
<pre>__RENDERED__</pre></div>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>服务端模板注入（SSTI, Server-Side Template Injection）</b>：用户输入被拼进模板<b>源码</b>，模板引擎在服务端解析执行。攻击者插入模板语法（如 <code>{{ 表达式 }}</code>），表达式会被服务端求值并把结果拼进页面。</p>
<p>本关核心代码（已给出，黑白盒结合学习）：</p>
<pre>from flask import render_template_string

name = request.args.get("name", "")
rendered = render_template_string(
    "欢迎光临 &lt;b&gt;" + name + "&lt;/b&gt;，祝您购物愉快！"   # ← 漏洞：输入成为模板源码
)</pre>
<p>它与安全写法的区别：</p>
<table>
<tr><th>写法</th><th>含义</th><th><code>{{7*7}}</code> 的结果</th></tr>
<tr><td><code>render_template_string("hi " + name)</code></td><td>输入是<b>模板的一部分</b>，表达式会被执行</td><td><code>hi 49</code>（被计算了！）</td></tr>
<tr><td><code>render_template("hi.html", name=name)</code></td><td>输入只是<b>数据</b>，模板文件固定</td><td><code>hi {{7*7}}</code>（原样显示）</td></tr>
</table>
<p>常用探测与利用（Jinja2）：</p>
<table>
<tr><th>载荷</th><th>作用</th></tr>
<tr><td><code>{{7*7}}</code></td><td>探测：回显 49 说明存在 SSTI</td></tr>
<tr><td><code>{{7*'7'}}</code></td><td>指纹：Jinja2 回显 <code>7777777</code>，Twig 回显 <code>49</code></td></tr>
<tr><td><code>{{config}}</code></td><td>打印 Flask 全部配置对象（含自定义配置）</td></tr>
<tr><td><code>{{config['FLAG']}}</code> / <code>{{config.FLAG}}</code></td><td>精确取出某一项配置</td></tr>
</table>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：先证明输入被当成了「模板」</summary><p>输入 <code>{{7*7}}</code> 提交。如果欢迎语里出现的是 <b>49</b> 而不是 <code>{{7*7}}</code> 这串字符，说明花括号里的内容被服务端<b>计算</b>了——这就是 SSTI。</p></details>
<details><summary>提示 2：模板渲染时能「看到」什么？</summary><p>Jinja2 为 Flask 渲染时默认注入了一些全局对象：<code>config</code>（应用配置）、<code>request</code>、<code>session</code>、<code>url_for</code>……它们在 <code>{{ }}</code> 里都能直接访问。试着打印整个配置对象看看。</p></details>
<details><summary>提示 3：取出 flag</summary><p>flag 存在应用配置 <code>FLAG</code> 里：<code>?name={{config}}</code> 在一大串配置里找到 FLAG 字段；精确写法是 <code>?name={{config['FLAG']}}</code>（点号写法 <code>{{config.FLAG}}</code> 也行）。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><span></span><a href="/ssti2">下一关：ssti2 · 过滤绕过 →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssti1&flag='+encodeURIComponent(v)})
   .then(function(r){return r.text().then(function(t){
      var msg='HTTP '+r.status+': '+t;
      try{var j=JSON.parse(t);
        if(typeof j.correct==='boolean')msg=j.correct?'✅ 回答正确！':'❌ flag 不对，再试试';
        else if(j.message!==undefined)msg=j.message;
      }catch(e){}
      el.textContent=msg;})})
   .catch(function(e){el.textContent='提交失败('+e.message+')：请从靶场门户页提交'});
}
</script>
</body>
</html>
"""


def create_app(url_prefix="/ssti1"):
    app = Flask(__name__)
    app.config["FLAG"] = FLAG
    app.register_blueprint(bp, url_prefix=url_prefix)
    return app


if __name__ == "__main__":
    create_app().run(host="127.0.0.1", port=5001, debug=False)
