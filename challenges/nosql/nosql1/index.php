<?php
/**
 * nosql1 — MongoDB 风格操作符注入（PHP 8.2 复刻版）
 * 端点：POST /nosql1/login（login/index.php）
 */
require __DIR__ . '/common.php';

$body = <<<'LAB'
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
var BASE = location.pathname.replace(/\/+$/, '');
async function show(promise){
  const r = await promise;
  document.getElementById('out').textContent = JSON.stringify(r, null, 2);
}
function sendJson(){
  show(fetch(BASE + '/login/', {method:'POST', headers:{'Content-Type':'application/json'},
    body: document.getElementById('jsonbody').value})
    .then(async r => ({status: r.status, body: await r.json()})));
}
function sendForm(){
  const u = document.getElementById('f_user').value, p = document.getElementById('f_pass').value;
  show(fetch(BASE + '/login/', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: 'user=' + encodeURIComponent(u) + '&pass=' + encodeURIComponent(p)})
    .then(async r => ({status: r.status, body: await r.json()})));
}
</script>
LAB;

$src = <<<'PHPSRC'
<?php
// 登录处理（Node/Express + mongodb 原型）
// const q = { user: req.body.user, pass: req.body.pass };
// db.collection('users').findOne(q, (err, u) => { ... });

// 本题的纯 PHP 等价实现（login/index.php）
$raw = file_get_contents('php://input');

foreach (array('$ne', '$gt') as $bad) {        // WAF：对原始请求体做黑名单字符串匹配
    if (str_contains($raw, $bad)) { /* 403 */ }
}

// JSON 体；或 PHP 原生表单解析——pass[$ne]=x 自动变成 array('$ne' => 'x')！
$query = array('user' => $data['user'] ?? null, 'pass' => $data['pass'] ?? null);
$hit = find_user($query, $users);              // pass 若是数组 → 操作符注入

function mongo_match($stored, $cond) {         // 简化 Mongo 匹配器（$ne/$gt/$regex/$in/...）
    if (is_array($cond)) { /* 按操作符逐条判定 */ }
    return $stored === $cond;                  // 普通等值匹配
}
PHPSRC;

render_page(array(
    'id' => 'nosql1',
    'cat' => 'nosql',
    'catname' => 'NoSQL 与注入变体',
    'title' => '不是只有 SQL 才能注入 —— Mongo 操作符注入',
    'difficulty' => 'medium',
    'desc' => '新项目用了 NoSQL：登录查询是 <code>findOne({user: 输入, pass: 输入})</code>。'
            . '如果客户端提交的不是字符串而是 <b>JSON 对象</b>（或表单数组语法），'
            . '密码条件就变成了 <code>{\'$ne\': \'xxx\'}</code> 这样的<b>操作符</b>——'
            . '「密码 ≠ xxx」几乎永远为真。WAF 对请求体做了 $ne/$gt 黑名单，但它扫的是<b>原始文本</b>。',
    'knowledge' => array('NoSQL 操作符注入（$ne/$nin/$gt/$regex）', 'JSON 体 / 表单数组语法', 'WAF 黑名单绕过（unicode 转义）', '$regex 盲注提取口令'),
    'hints' => array(
        '先正常登录一次看看响应结构；user=admin&pass=wrong 会怎样？',
        '表单数组语法：pass[$ne]=wrongpw 表示条件 {pass: {\'$ne\': \'wrongpw\'}}。WAF 拦 $ne——那 $nin 呢？',
        'curl -d \'user=admin&pass[$nin]=wrongpw\' 直接登录；或 JSON 体用 \\u0024ne 绕过 WAF；admin 的口令还可用 pass[$regex]=^A 一类条件逐字符盲注。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/read3', 'flag 不在磁盘上'),
    'next' => array('/nosql2', 'XML 里的注入'),
));
