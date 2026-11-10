<?php
/**
 * adv1 — GraphQL 内省 + 变量绕过 WAF（PHP 8.2 复刻版）
 * 端点：POST /adv1/graphql（graphql/index.php，手写迷你 GraphQL）
 */
require __DIR__ . '/common.php';

$body = <<<'LAB'
<b>Query</b>
<textarea id="q" style="min-height:110px;">{ __schema { types { name fields { name } } } }</textarea>
<b>Variables（JSON，可选）</b>
<textarea id="v" style="min-height:60px;">{}</textarea>
<button onclick="run()">▶ 运行</button>
<button class="ghost" onclick="setQ('{ hello }')">hello</button>
<button class="ghost" onclick="setQ('{ users { username role email } }')">users</button>
<button class="ghost" onclick="setQ('{ systemEnv(key: &quot;PATH&quot;) }')">systemEnv(PATH)</button>
<button class="ghost" onclick="setVarQuery()">构造变量查询</button>
<pre class="out" id="out" style="margin-top:14px;">等待请求……</pre>
<p style="color:#8b949e;font-size:13px;margin-bottom:0;">等价 curl：<code>curl -s -X POST http://HOST/adv1/graphql -H 'Content-Type: application/json' -d '{"query":"{ hello }"}'</code></p>
<script>
var BASE = location.pathname.replace(/\/+$/, '');
function setQ(q){ document.getElementById('q').value = q; }
function setVarQuery(){
  setQ('query($k: String!) { systemEnv(key: $k) }');
  document.getElementById('v').value = '{\n  "k": "FLAG"\n}';
}
async function run(){
  let vars = {};
  try { vars = JSON.parse(document.getElementById('v').value || '{}'); }
  catch(e){ document.getElementById('out').textContent = 'Variables 不是合法 JSON：' + e; return; }
  const r = await fetch(BASE + '/graphql/', {method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({query: document.getElementById('q').value, variables: vars})});
  let t;
  try { t = JSON.stringify(await r.json(), null, 2); } catch(e){ t = '<非 JSON 响应>'; }
  document.getElementById('out').textContent = 'HTTP ' + r.status + '\n' + t;
}
</script>
LAB;

$src = <<<'PHPSRC'
<?php
// graphql/index.php —— /graphql 端点（关键部分）
$query     = $payload['query'] ?? '';
$variables = $payload['variables'] ?? [];

if (str_contains(strtolower($query), 'flag')) {   // WAF：只扫「查询文本」
    gql_out(['errors' => [['message' => 'WAF：查询文本包含敏感关键字 flag，已拦截']]], 422);
}

$ast = (new GqlParser(gql_lex($query)))->document();   // 解析（支持变量/内省）
$out = $engine->applyRoot($ast, $variables, 'Query');

// 隐藏字段：文档未公开，只能靠内省发现
if ($name === 'systemEnv') {
    $key = $engine->resolveValue($args['key'], $variables);
    return $key === 'FLAG' ? $FLAG : getenv($key);     // 参数 key 来自查询或「变量」
}
PHPSRC;

render_page(array(
    'id' => 'adv1',
    'cat' => 'adv',
    'catname' => '进阶攻击面',
    'title' => '查询语言的内省 —— GraphQL 信息泄露',
    'difficulty' => 'hard',
    'desc' => '团队把 REST 迁到了 GraphQL，公开文档只写了 hello / version / users 三个字段。'
            . '但服务在生产环境<b>没有关闭内省</b>（introspection）——GraphQL 自带一套'
            . '「查询自己结构」的元字段。先内省发现隐藏字段，再取回环境变量里的 FLAG。'
            . 'WAF 会拦截查询文本中出现 flag 关键字的请求——注意它<b>只扫查询文本</b>。',
    'knowledge' => array('GraphQL 内省 __schema', '隐藏字段发现', 'GraphQL 变量（variables）', 'WAF 文本匹配的盲区'),
    'hints' => array(
        '先运行默认的内省查询：{__schema{types{name fields{name}}}}，看看 Query 上到底有哪些字段。',
        '发现 systemEnv(key) 了吗？直接 {systemEnv(key:"FLAG")} 会被 WAF 拦——错误信息会告诉你 WAF 扫的是什么。',
        '把参数挪到 variables：query($k:String!){systemEnv(key:$k)} + {"k":"FLAG"}；或用字符串 unicode 转义 "\u0046LAG" 绕过文本匹配。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/nosql2', 'XML 里的注入'),
    'next' => array('/adv2', 'WebSocket 的门禁'),
));
