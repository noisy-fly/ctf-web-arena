<?php
/**
 * nosql2 — XPath 注入（PHP 8.2 复刻版）
 * 端点：POST /nosql2/login（login/index.php，真实 DOMXPath）
 */
require __DIR__ . '/common.php';

$body = <<<'LAB'
<form onsubmit="doLogin(event)">
  <div style="display:flex;gap:10px;">
    <input type="text" id="user" placeholder="用户名（试试注入）" value="admin">
    <input type="text" id="pass" placeholder="密码" value="123456">
  </div>
  <button type="submit">登录</button>
</form>
<pre class="out" id="out" style="margin-top:14px;">等待请求……</pre>
<script>
var BASE = location.pathname.replace(/\/+$/, '');
async function doLogin(e){
  e.preventDefault();
  const body = 'user=' + encodeURIComponent(document.getElementById('user').value)
             + '&pass=' + encodeURIComponent(document.getElementById('pass').value);
  const r = await fetch(BASE + '/login/', {method:'POST',
     headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
  document.getElementById('out').textContent =
    'HTTP ' + r.status + '\n' + JSON.stringify(await r.json(), null, 2);
}
</script>
LAB;

$src = <<<'PHPSRC'
<?php
// 服务端认证逻辑（login/index.php，真实 DOMXPath）
$user  = $_POST['user'] ?? '';
$pass  = $_POST['pass'] ?? '';
$cond  = "username='" . $user . "' and password='" . $pass . "'";
$expr  = '//users/user[' . $cond . ']';
$nodes = $xp->query($expr);        // 用户输入直接拼进 XPath（漏洞所在）
if ($nodes === false) {
    // 报错回显拼接后的查询 —— 经典错误信息泄露
    json_out(['ok' => false, 'msg' => 'XPath 解析错误：…', 'query' => $expr], 400);
}

// 拼接结果示例（user = admin' or '1'='1）
//   //users/user[username='admin' or '1'='1' and password='x']
PHPSRC;

render_page(array(
    'id' => 'nosql2',
    'cat' => 'nosql',
    'catname' => 'NoSQL 与注入变体',
    'title' => 'XML 里的注入 —— XPath 认证绕过',
    'difficulty' => 'medium',
    'desc' => '老系统把用户存在 XML 里，认证用 XPath：<code>//users/user[username=\'$user\' and password=\'$pass\']</code>。'
            . '用户输入被<b>直接拼接</b>进表达式——单引号可以提前闭合，再注入 <code>or</code> 恒真条件。'
            . 'admin 的口令就是通关令牌。解析出错时接口还会<b>回显拼接后的查询</b>，帮你调试。',
    'knowledge' => array('XPath 注入（引号闭合）', 'or 恒真绕过认证', '报错回显调试', '参数化 XPath 修复'),
    'hints' => array(
        '先提交 user=admin&pass=wrong 看看失败响应；再提交带单引号的 user=admin\' 观察报错回显的 query。',
        '目标：让引号被「吐掉」之后整体条件恒真。注意 and 优先级高于 or。',
        'user 填 admin\' or \'1\'=\'1，pass 随意（如 x）；或 user 与 pass 都填 \' or \'1\'=\'1。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/nosql1', '不是只有 SQL 才能注入'),
    'next' => array('/adv1', '查询语言的内省'),
));
