<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * sql6 宽字节注入（连接字符集 GBK + addslashes → %df 吃掉反斜杠）
 * 运行依赖：MySQL 服务 + php_mysqli 扩展（见 meta.json / NOTES）
 * 连接参数可用环境变量覆盖：SQL6_DB_HOST / SQL6_DB_PORT / SQL6_DB_USER / SQL6_DB_PASS / SQL6_DB_NAME
 * 教学靶场 · 仅用于授权学习环境
 * ------------------------------------------------------------------ */

$META = json_decode(file_get_contents(__DIR__ . '/meta.json'), true);

$DB_HOST = getenv('SQL6_DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('SQL6_DB_PORT') ?: '3306';
$DB_USER = getenv('SQL6_DB_USER') ?: 'root';
$DB_PASS = getenv('SQL6_DB_PASS') ?: 'ctf_sql6_pass';
$DB_NAME = getenv('SQL6_DB_NAME') ?: 'sql6db';

/* ---------- 连接（GBK 字符集——本题漏洞的前提） ---------- */
$err = '';
$db = mysqli_init();
try {
    $db->real_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
    $db->set_charset('gbk');          // ← 连接字符集被设为 GBK（历史遗留配置）
} catch (Throwable $e) {
    $err = '数据库连接失败：' . $e->getMessage();
}

/* ---------- 首次访问自动初始化表（init.sql 的等价逻辑） ---------- */
if ($db && !$err) {
    try {
        $db->query("SELECT 1 FROM users LIMIT 1");
    } catch (Throwable $t) {
        // 表不存在 → 按 init.sql 逐句建表（init.sql 是 UTF-8 文件，需转成连接字符集 GBK 再发送）
        $init = (string)file_get_contents(__DIR__ . '/init.sql');
        $init = iconv('UTF-8', 'GBK//IGNORE', $init);
        foreach (array_filter(array_map('trim', explode(';', $init))) as $stmt) {
            $clean = preg_replace('/^--.*$/m', '', $stmt);
            if (trim($clean) !== '') $db->query(trim($clean));
        }
    }
}

/* ---------- 题目逻辑：用户查询 ---------- */
$rows = []; $sql = '';
if ($db && !$err) {
    $u   = isset($_GET['u']) ? (string)$_GET['u'] : 'admin';
    $esc = addslashes($u);                                  // 转义了 '，但 GBK 下不够
    $sql = "SELECT id, username, memo FROM users WHERE username = '$esc'";   // 漏洞：转义后拼接
    try {
        $res = $db->query($sql);
        // 连接是 GBK，取回的数据是 GBK 字节；页面是 UTF-8，输出前转换（老系统的日常）
        if ($res) while ($r = $res->fetch_assoc()) {
            foreach ($r as $k => $v) $r[$k] = iconv('GBK', 'UTF-8//IGNORE', (string)$v);
            $rows[] = $r;
        }
    } catch (Throwable $e) {
        $err = 'SQL 错误：' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($META['title']) ?> · <?= htmlspecialchars($META['id']) ?></title>
<style>
:root{--bg:#0d1117;--card:#161b22;--bd:#30363d;--fg:#c9d1d9;--dim:#8b949e;--acc:#58a6ff;--ok:#3fb950;--warn:#d29922;--err:#f85149;--mono:ui-monospace,SFMono-Regular,Consolas,monospace}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);font:15px/1.7 "Segoe UI","Microsoft YaHei",sans-serif;padding:0 0 60px}
.wrap{max-width:900px;margin:0 auto;padding:0 20px}
header{border-bottom:1px solid var(--bd);background:linear-gradient(180deg,#161b22,#0d1117);padding:28px 0 20px;margin-bottom:24px}
h1{font-size:24px;margin:8px 0 4px}
.badge{display:inline-block;font-size:12px;padding:2px 10px;border:1px solid var(--bd);border-radius:12px;color:var(--acc);margin-right:6px}
.badge.diff-easy{color:var(--ok)} .badge.diff-medium{color:var(--warn)} .badge.diff-hard{color:var(--err)}
.sub{color:var(--dim);font-size:13px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:8px;padding:18px 20px;margin-bottom:18px}
.card h2{font-size:16px;color:var(--acc);margin-bottom:10px;border-left:3px solid var(--acc);padding-left:8px}
.card p{margin-bottom:8px}
code,.mono{font-family:var(--mono);background:#0b0f14;border:1px solid var(--bd);border-radius:4px;padding:1px 6px;font-size:13px;color:#79c0ff}
pre{background:#0b0f14;border:1px solid var(--bd);border-radius:6px;padding:12px;overflow:auto;font-family:var(--mono);font-size:13px;margin:8px 0;color:#a5d6ff}
table{border-collapse:collapse;width:100%;margin:10px 0}
th,td{border:1px solid var(--bd);padding:6px 12px;text-align:left;font-size:14px}
th{color:var(--dim);background:#10151c}
details{border:1px solid var(--bd);border-radius:6px;padding:10px 14px;margin:8px 0;background:#10151c}
summary{cursor:pointer;color:var(--warn);font-size:14px;user-select:none}
details[open] summary{border-bottom:1px dashed var(--bd);padding-bottom:6px;margin-bottom:8px}
.hint-lv{color:var(--dim);font-size:12px}
input[type=text]{background:#0b0f14;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 12px;font-family:var(--mono);font-size:14px;width:70%}
button{background:#21262d;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 18px;cursor:pointer;font-size:14px}
button:hover{border-color:var(--acc);color:var(--acc)}
#flagResult{margin-top:10px;font-size:14px;min-height:22px}
.ok{color:var(--ok)} .bad{color:var(--err)}
footer{border-top:1px solid var(--bd);margin-top:30px;padding-top:16px;color:var(--dim);font-size:13px;display:flex;justify-content:space-between}
footer a{color:var(--dim);text-decoration:none} footer a:hover{color:var(--acc)}
.form-row{margin:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sqlerr{color:var(--err);font-size:13px}
</style>
</head>
<body>
<header><div class="wrap">
  <div><span class="badge"><?= htmlspecialchars($META['category']) ?></span><span class="badge diff-<?= htmlspecialchars($META['difficulty']) ?>">难度: <?= htmlspecialchars($META['difficulty']) ?></span></div>
  <h1><?= htmlspecialchars($META['title']) ?> <span style="color:var(--dim);font-size:14px;font-weight:400"><?= htmlspecialchars($META['id']) ?></span></h1>
  <div class="sub">CTF Web 全题型教学式靶场 · 本题仅用于授权安全学习</div>
</div></header>

<div class="wrap">

<div class="card">
  <h2>题目描述</h2>
  <p>“深蓝笔记”的会员查询系统。为了防注入，开发对所有输入都加了 <code>addslashes()</code>——单引号会被转义成 <code>\'</code>，看起来引号永远逃不出去。</p>
  <p>但这是 2010 年的老系统：为了迁就旧数据，<b>数据库连接字符集被设成了 GBK</b>。</p>
  <p>输入一个普通的 <code>'</code> 会被拦住；输入 <code>%df'</code> 试试？flag 在表 <code>secret_vault</code> 的 <code>flag</code> 列（内部资料泄露了表结构）。</p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>宽字节注入原理</b>——转义函数与数据库字符集的“错配”：</p>
  <pre>攻击者输入：        %df'            (字节 0xdf 0x27)
addslashes 转义后：  %df\'           (字节 0xdf 0x5c 0x27)
MySQL(GBK) 解析：    [0xdf 0x5c]  +  '
                     └── 一个合法 GBK 汉字（0x5c 被吃掉）
                                  └── 单引号逃逸成功！</pre>
  <p>关键条件：</p>
  <pre>1. 连接字符集是 GBK/BIG5 等宽字符集（第二字节覆盖 0x5c 的那些编码）
2. 转义只加反斜杠（addslashes / 不带字符集意识的转义）
3. 转义发生在【不知道目标字符集】的层面</pre>
  <p>正确的两味药：</p>
  <pre>mysqli_set_charset($db,'utf8mb4') + 预编译/参数绑定（转义和解析在同一个字符集语境下）
或 mysql_real_escape_string()（它感知连接字符集，GBK 下会给 0xdf 补全宽字符——但参数化仍是首选）</pre>
</div>

<div class="card">
  <h2>会员查询</h2>
  <form method="GET" class="form-row">
    <label>用户名：</label>
    <input type="text" name="u" value="<?= htmlspecialchars((string)($_GET['u'] ?? 'admin')) ?>" style="width:320px">
    <button type="submit">查询</button>
  </form>
  <?php if ($sql): ?><p class="sub">后端执行的 SQL：<code class="mono"><?= htmlspecialchars($sql) ?></code></p><?php endif; ?>
  <?php if ($err): ?><p class="sqlerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <?php if ($rows): ?>
  <table><tr><th>id</th><th>username</th><th>memo</th></tr>
    <?php foreach ($rows as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= htmlspecialchars((string)$r['username']) ?></td><td><?= htmlspecialchars((string)$r['memo']) ?></td></tr><?php endforeach; ?>
  </table>
  <?php elseif ($sql): ?><p class="sub">（没有匹配的用户）</p><?php endif; ?>
</div>

<div class="card">
  <h2>渐进提示（先自己想，再一层层打开）</h2>
  <?php foreach ($META['hints'] as $i => $h): ?>
  <details><summary>提示 <?= $i + 1 ?> <span class="hint-lv">（第 <?= $i + 1 ?>/<?= count($META['hints']) ?> 层）</span></summary>
    <p><?= nl2br(htmlspecialchars($h)) ?></p>
  </details>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>提交 Flag</h2>
  <div class="form-row">
    <input type="text" id="flagInput" placeholder="flag{sql6_xxxxxxxx}">
    <button onclick="submitFlag()">提交</button>
  </div>
  <div id="flagResult"></div>
</div>

<footer class="wrap">
  <span><?php if ($META['prev']): ?><a href="/<?= htmlspecialchars($META['prev']) ?>">← 上一题 <?= htmlspecialchars($META['prev']) ?></a><?php endif; ?></span>
  <span>难度递进 · 教学式靶场</span>
  <span><?php if ($META['next']): ?><a href="/<?= htmlspecialchars($META['next']) ?>">下一题 <?= htmlspecialchars($META['next']) ?> →</a><?php endif; ?></span>
</footer>
</div>

<script>
async function submitFlag(){
  const box = document.getElementById('flagResult');
  const flag = document.getElementById('flagInput').value.trim();
  if(!flag) { box.textContent = '请输入 flag'; box.className='bad'; return; }
  box.textContent = '校验中...'; box.className='';
  try{
    const r = await fetch('/api/verify', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: 'challenge_id=<?= urlencode($META['id']) ?>&flag=' + encodeURIComponent(flag)
    });
    const t = await r.text();
    let msg = t; try{ const j = JSON.parse(t); msg = j.message || j.msg || (j.correct!==undefined ? (j.correct?'正确 ✓':'错误 ✗') : t); }catch(e){}
    box.textContent = msg; box.className = /正确|correct|true|✓/.test(String(msg)) ? 'ok' : 'bad';
  }catch(e){
    box.textContent = '无法连接校验接口 /api/verify（本地预览属正常，联调后可用）';
    box.className = 'bad';
  }
}
</script>
</body>
</html>
