// ssti6 · Go text/template 模板注入（SSTI 第六课）
// ============================================================
// 背景：工单系统的公告渲染器，Go 实现。用户模板片段与 *Site 数据
//       一起交给 text/template 渲染；开发者把一个"能读任意文件"的
//       诊断方法 ReadFile 留在了模板可触达的方法集里。
// 考点：① Go 模板没有算术、没有 __class__ 反射链 —— 探测/利用方式
//         与 Jinja2/Twig 完全不同；② SSTI 破坏力 = 暴露给模板的
//         数据与方法面（{{.}} 枚举字段 → 源码里找方法名 → 调用）。
// 运行：FLAG_PATH 指向 flag 文件（默认 ./flag_ssti6.txt，容器内建议
//       /flag_ssti6.txt）；PORT 监听端口（默认 8096）；BasePath 自动
//       兼容 /ssti6 前缀（nginx 反代两种写法都能工作）。
// 编译：go build -o ssti6 .      （Linux: GOOS=linux GOARCH=amd64）
// ============================================================
package main

import (
	"bytes"
	"html"
	"io"
	"net/http"
	"os"
	"strings"
	"text/template"
)

var (
	flagPath = envOr("FLAG_PATH", "flag_ssti6.txt")
	port     = envOr("PORT", "8096")
)

func envOr(k, def string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return def
}

// Site 是渲染公告时传给模板的数据对象。
// 危险点：ReadFile 是导出方法 —— Go 模板里 {{.ReadFile "路径"}} 就能调用它。
type Site struct {
	Name   string
	Slogan string
}

func (s *Site) Banner() string { return "== " + s.Name + " · " + s.Slogan + " ==" }

// ReadFile：本意是给模板 include 常用文案片段的"便利函数"，没有做任何路径限制。
func (s *Site) ReadFile(path string) string {
	b, err := os.ReadFile(path)
	if err != nil {
		return "读取失败: " + err.Error()
	}
	return string(b)
}

var data = &Site{Name: "运维工单中心", Slogan: "响应更快一点"}

func handler(w http.ResponseWriter, r *http.Request) {
	if strings.HasSuffix(r.URL.Path, "/health") {
		w.WriteHeader(200)
		io.WriteString(w, "ok")
		return
	}
	_ = r.ParseForm()
	tpl := r.FormValue("tpl")
	out := ""
	if tpl != "" {
		t, err := template.New("user").Parse(tpl) // ← 漏洞：用户输入直接成为模板源码
		if err != nil {
			out = "模板解析出错: " + err.Error()
		} else {
			var buf bytes.Buffer
			if err := t.Execute(&buf, data); err != nil {
				out = "模板执行出错: " + err.Error()
			} else {
				out = buf.String()
			}
		}
	}
	page := strings.ReplaceAll(PAGE, "__TPL__", html.EscapeString(tpl))
	page = strings.ReplaceAll(page, "__OUT__", html.EscapeString(out))
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	io.WriteString(w, page)
}

func main() {
	http.HandleFunc("/", handler)
	http.HandleFunc("/ssti6/", handler)
	http.HandleFunc("/ssti6", handler)
	http.ListenAndServe("127.0.0.1:"+port, nil)
}

const PAGE = `<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ssti6 · Go template 模板注入 | CTF 教学靶场</title>
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
<span class="badge">SSTI · 模板注入 · 第 6 关</span>
<h1>工单公告渲染器 —— Go template 模板注入</h1>
<div class="meta">难度：★★★★☆ 高阶 · 关卡 ID：<code>ssti6</code> · 运行时：Go 单二进制（text/template）</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>工单中心的后端用 <b>Go</b> 写的，公告预览功能把运营输入的「模板片段」连同站点数据对象一起交给 <code>text/template</code> 渲染。</p>
<p>Go 模板和前五关都不一样：没有算术运算、没有下划线反射链，能做的事取决于<b>传给模板的数据对象长什么样</b>。flag 在服务器的一个文件里，想办法让模板把它读出来。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>模板片段：<input type="text" name="tpl" placeholder="试试 {{.}} 或 {{.Banner}}" value="__TPL__"></label>
<button type="submit">预览公告</button>
</form>
<div class="result"><div class="t">渲染结果（Go 的解析/执行报错也属于输出，信息量很大）</div>
<pre>__OUT__</pre></div>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关核心代码（已给出）：</p>
<pre>type Site struct {
    Name, Slogan string          // 字段：模板里 {{.Name}} 可读
}
func (s *Site) Banner() string { ... }                  // 导出方法：{{.Banner}} 可调用
func (s *Site) ReadFile(path string) string { ... }     // ← 危险方法：无路径限制的读文件

t, _ := template.New("user").Parse(用户输入)    // ← 漏洞：输入就是模板源码
t.Execute(w, data)                              // data 是 *Site</pre>
<p><b>Go 模板的能力边界</b>（和 Jinja2/Twig/Smarty 对照着记）：</p>
<table>
<tr><th>能力</th><th>Jinja2/Twig/Smarty</th><th>Go text/template</th></tr>
<tr><td>算术探测 <code>{{7*7}}</code></td><td>49</td><td>❌ 解析直接报错（模板语言没有算术运算符）</td></tr>
<tr><td>反射链 <code>.__class__</code> / <code>__globals__</code></td><td>可达任意对象</td><td>❌ 不存在——只能访问传入数据的<b>导出字段/方法</b></td></tr>
<tr><td>枚举数据</td><td><code>{{config}}</code> 等</td><td>✅ <code>{{.}}</code> / <code>{{printf "%+v" .}}</code></td></tr>
<tr><td>调用方法</td><td>依引擎</td><td>✅ <code>{{.方法 参数}}</code>（仅限导出方法，参数类型还得对得上）</td></tr>
</table>
<p>所以 Go 系 SSTI 的利用思路是：<b>① <code>{{.}}</code> 摸清字段 → ② 看源码/报错找导出方法 → ③ 调用最危险的那个</b>。本题就有一个不该暴露的 <code>ReadFile</code>。</p>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：先摸清模板能看到什么</summary><p>输入 <code>{{.}}</code> 会把数据对象按字段打印出来（Go 的 %v 格式，字段值都看得见）；<code>{{.Name}}</code>、<code>{{.Banner}}</code> 也都行。但 <code>{{7*7}}</code> 会报「unexpected *」——Go 模板没有算术，这不是 Jinja2。</p></details>
<details><summary>提示 2：字段里没有 flag，那方法呢？</summary><p>Go 模板还能调用数据对象的<b>导出方法</b>。翻翻「知识点讲解」里的源码：除了 Banner，还有一个为"包含文案片段"而写的便利方法，签名是 <code>ReadFile(path string) string</code>，而且没做任何路径限制。</p></details>
<details><summary>提示 3：完整 payload</summary><p><code>?tpl={{.ReadFile "/flag_ssti6.txt"}}</code> —— 把服务器上那个 flag 文件读出来。（方法调用用空格传参，字符串参数带引号。）</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="/ssti5">← 上一关：ssti5 · FreeMarker</a><a href="/unser1">下一分类：unser1 · 反序列化 →</a></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssti6&flag='+encodeURIComponent(v)})
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
`
