<?php
// ============================================================
// ssti6 · Go text/template 模板注入（SSTI 第六课 · PHP 复刻版）
// ============================================================
// 背景：工单系统的公告渲染器。原题是 Go text/template 实现（完整源码与
//       二进制保留在 _original/ 子目录）；本文件用 PHP 复刻了一个
//       「迷你 Go template」渲染引擎：支持 {{.}}、{{.Field}}、
//       {{.Method "arg"}}、{{printf "%+v" .}} 与 {{... | Func}} 管道等
//       语法子集，解析/执行报错也按 Go 的格式输出（unexpected "*" in
//       operand、function "x" not defined、can't evaluate field ... 等）。
// 漏洞点等价平移：用户输入的模板片段与 Site 数据对象一起交给引擎渲染
//       （tplRender($_GET['tpl'], $site)），而方法集里留着一个「能读
//       任意文件」的 ReadFile —— {{.ReadFile "flag_ssti6.txt"}} 即可
//       读出 flag。
// 环境：PHP 8.2、无外部依赖；flag 在本目录 flag_ssti6.txt（值与
//       meta.json 一致）。任何输入都不会 Fatal Error。
// ============================================================

// —— 异常类型（注意：不能叫 ParseError，那是 PHP 内置类） ——
final class TplParseError extends Exception {}
final class TplExecError extends Exception
{
    public function __construct(public string $at, string $reason)
    {
        parent::__construct($reason);   // at = 报错动作原文（如 ".name"），用于 Go 风格定位
    }
}

// —— 数据对象：渲染公告时传给模板的东西（对应 Go: *main.Site） ——
final class Site
{
    /** 导出字段（Go: struct 字段，模板里 {{.Name}} 可读） */
    public function fields(): array
    {
        return array('Name' => '运维工单中心', 'Slogan' => '响应更快一点');
    }

    /** 对应 Go: func (s *Site) Banner() string —— 普通导出方法 */
    public function banner(): string
    {
        $f = $this->fields();
        return '== ' . $f['Name'] . ' · ' . $f['Slogan'] . ' ==';
    }

    /** 对应 Go: func (s *Site) ReadFile(path string) string
     *  本意是给模板 include 常用文案片段的“便利函数”，没有做任何路径限制（危险！） */
    public function readFile(string $path): string
    {
        $real = (string)$path;
        if ($real !== '' && $real[0] !== '/' && !preg_match('#^[A-Za-z]:[/\\\\]#', $real)) {
            $real = __DIR__ . '/' . $real;   // 相对路径以题目目录为基准（Go 版以进程 CWD 为基准）
        }
        if (!is_file($real)) {
            return '读取失败: open ' . $path . ': no such file or directory';
        }
        $size = (int)@filesize($real);
        if ($size > 131072) {
            $chunk = @file_get_contents($real, false, null, 0, 131072);
            return (string)$chunk . "\n... (文件过大，已截断)";
        }
        $data = @file_get_contents($real);
        if ($data === false) {
            return '读取失败: open ' . $path . ': permission denied';
        }
        return $data;
    }

    /** 模板可调用的导出方法表：名字 => [内部方法名, 参数个数] */
    public static function templateMethods(): array
    {
        return array(
            'Banner'   => array('banner', 0),
            'ReadFile' => array('readFile', 1),
        );
    }
}

/** 迷你引擎内置函数（Go text/template 的 FuncMap 子集） */
function tplBuiltins(): array
{
    return array('printf', 'len', 'print');
}

/** 把一个动作（{{ }} 之间的源码）切成记号；非法字符抛 Go 风格解析错误 */
function tplLex(string $s): array
{
    $toks = array();
    $i = 0;
    $n = strlen($s);
    $escMap = array('n' => "\n", 't' => "\t", 'r' => "\r", '\\' => '\\', '"' => '"', "'" => "'");
    while ($i < $n) {
        $c = $s[$i];
        if (ctype_space($c)) { $i++; continue; }
        if ($c === '"') {                                   // Go 双引号字符串
            $i++;
            $buf = '';
            $closed = false;
            while ($i < $n) {
                if ($s[$i] === '"') { $closed = true; $i++; break; }
                if ($s[$i] === '\\' && $i + 1 < $n) {
                    $e = $s[$i + 1];
                    $buf .= isset($escMap[$e]) ? $escMap[$e] : $e;
                    $i += 2;
                } else {
                    $buf .= $s[$i];
                    $i++;
                }
            }
            if (!$closed) { throw new TplParseError('unterminated quoted string'); }
            $toks[] = array('str', $buf);
            continue;
        }
        if ($c === '`') {                                   // Go 反引号原始字符串
            $j = strpos($s, '`', $i + 1);
            if ($j === false) { throw new TplParseError('unterminated raw quoted string'); }
            $toks[] = array('str', substr($s, $i + 1, $j - $i - 1));
            $i = $j + 1;
            continue;
        }
        if ($c === '.') {                                   // "." 或 ".Field" / ".A.B"
            $j = $i + 1;
            while ($j < $n && (ctype_alnum($s[$j]) || $s[$j] === '_' || $s[$j] === '.')) { $j++; }
            if ($j === $i + 1) {
                $toks[] = array('dot', null);               // 裸 "."（根数据对象）
            } else {
                $toks[] = array('field', explode('.', substr($s, $i + 1, $j - $i - 1)));
            }
            $i = $j;
            continue;
        }
        if (ctype_digit($c)) {                              // 无符号数字字面量
            $j = $i;
            while ($j < $n && (ctype_digit($s[$j]) || $s[$j] === '.')) { $j++; }
            $raw = substr($s, $i, $j - $i);
            if (substr_count($raw, '.') > 1) { throw new TplParseError('unexpected "." in operand'); }
            $toks[] = array(substr_count($raw, '.') === 1 ? 'float' : 'int', $raw);
            $i = $j;
            continue;
        }
        if (ctype_alpha($c) || $c === '_') {                // 标识符（函数名）
            $j = $i;
            while ($j < $n && (ctype_alnum($s[$j]) || $s[$j] === '_')) { $j++; }
            $toks[] = array('ident', substr($s, $i, $j - $i));
            $i = $j;
            continue;
        }
        if ($c === '|') {                                   // 管道分隔符
            $toks[] = array('pipe', null);
            $i++;
            continue;
        }
        // 其余字符（算术运算符、括号、比较符、非 ASCII……）→ Go 风格“无法识别”报错。
        // 这正是 {{7*7}} 得到 unexpected "*" 的原因：Go 模板语言没有算术运算符。
        $ch = $c;
        if (ord($c) >= 128 && preg_match('/./us', substr($s, $i), $m)) {
            $ch = $m[0];                                    // 取完整 UTF-8 字符，报错信息可读
        }
        throw new TplParseError('unexpected "' . $ch . '" in operand');
    }
    return $toks;
}

/** 记号的显示文本（用于报错） */
function tplTokText(array $t): string
{
    switch ($t[0]) {
        case 'str':   return $t[1];
        case 'int':
        case 'float': return $t[1];
        case 'ident': return $t[1];
        case 'dot':   return '.';
        case 'field': return '.' . implode('.', $t[1]);
    }
    return '?';
}

/** 把记号流解析成管道（若干条命令，用 | 分隔） */
function tplParsePipeline(array $toks): array
{
    $cmds = array();
    $cur = null;
    foreach ($toks as $t) {
        if ($t[0] === 'pipe') {
            if ($cur === null) { throw new TplParseError('missing value for command'); }
            $cmds[] = $cur;
            $cur = null;
            continue;
        }
        if ($cur === null) {
            if ($t[0] === 'ident' && !in_array($t[1], tplBuiltins(), true)) {
                throw new TplParseError('function "' . $t[1] . '" not defined');
            }
            if (!in_array($t[0], array('dot', 'field', 'ident', 'str', 'int', 'float'), true)) {
                throw new TplParseError('unexpected "' . tplTokText($t) . '" in operand');
            }
            $cur = array('op' => $t, 'args' => array());
        } else {
            if (!in_array($t[0], array('str', 'int', 'float', 'dot', 'field'), true)) {
                throw new TplParseError('unexpected "' . tplTokText($t) . '" in operand');
            }
            $cur['args'][] = $t;
        }
    }
    if ($cur === null) { throw new TplParseError('missing value for command'); }
    $cmds[] = $cur;
    return $cmds;
}

/** 值的 Go 风格类型名 */
function tplTypeName($v): string
{
    if (is_string($v)) { return 'string'; }
    if (is_int($v)) { return 'int'; }
    if (is_float($v)) { return 'float64'; }
    if (is_bool($v)) { return 'bool'; }
    if ($v instanceof Site) { return '*main.Site'; }
    return 'unknown';
}

/** 值的 Go 风格 %v / %+v 打印 */
function tplGoValue($v, bool $plus): string
{
    if ($v === null) { return '<no value>'; }
    if (is_bool($v)) { return $v ? 'true' : 'false'; }
    if (is_int($v)) { return (string)$v; }
    if (is_float($v)) { return (string)$v; }
    if (is_string($v)) { return $v; }
    if ($v instanceof Site) {
        $parts = array();
        foreach ($v->fields() as $k => $x) {
            $parts[] = ($plus ? $k . ':' : '') . tplGoValue($x, false);
        }
        return '{' . implode(' ', $parts) . '}';
    }
    return '{?}';
}

/** Go 风格 %q（加引号并转义） */
function tplQuote(string $s): string
{
    return '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), $s) . '"';
}

/** 内置 printf：迷你版 fmt.Sprintf（动词子集 v/+v/s/d/q/t/f/%%） */
function tplSprintf(array $args): string
{
    if (count($args) === 0) {
        throw new TplExecError('printf', 'wrong number of args for printf: want at least 1 got 0');
    }
    if (!is_string($args[0])) {
        throw new TplExecError('printf', 'wrong type for value; expected string; got ' . tplTypeName($args[0]));
    }
    $fmt = array_shift($args);
    $out = '';
    $ai = 0;
    $i = 0;
    $n = strlen($fmt);
    while ($i < $n) {
        $c = $fmt[$i];
        if ($c !== '%') { $out .= $c; $i++; continue; }
        if ($i + 1 < $n && $fmt[$i + 1] === '%') { $out .= '%'; $i += 2; continue; }
        $j = $i + 1;
        $plus = false;
        while ($j < $n && $fmt[$j] === '+') { $plus = true; $j++; }
        $verb = ($j < $n) ? $fmt[$j] : '';
        $has = $ai < count($args);
        $val = $has ? $args[$ai] : null;
        switch ($verb) {
            case 'v':
                $out .= $has ? tplGoValue($val, $plus) : '%!v(MISSING)';
                if ($has) { $ai++; }
                break;
            case 's':
                $out .= ($has && is_string($val)) ? $val : (($has ? '%!s(' . tplTypeName($val) . '=' . tplGoValue($val, false) . ')' : '%!s(MISSING)'));
                if ($has) { $ai++; }
                break;
            case 'd':
                $out .= ($has && is_int($val)) ? (string)$val : (($has ? '%!d(' . tplTypeName($val) . '=' . tplGoValue($val, false) . ')' : '%!d(MISSING)'));
                if ($has) { $ai++; }
                break;
            case 'q':
                $out .= $has ? tplQuote(is_string($val) ? $val : tplGoValue($val, false)) : '%!q(MISSING)';
                if ($has) { $ai++; }
                break;
            case 't':
                $out .= !$has ? '%!t(MISSING)' : (is_bool($val) ? ($val ? 'true' : 'false') : '%!t(' . tplTypeName($val) . '=' . tplGoValue($val, false) . ')');
                if ($has) { $ai++; }
                break;
            case 'f':
                $out .= ($has && (is_int($val) || is_float($val))) ? sprintf('%f', (float)$val) : (($has ? '%!f(' . tplTypeName($val) . '=' . tplGoValue($val, false) . ')' : '%!f(MISSING)'));
                if ($has) { $ai++; }
                break;
            default:
                $out .= '%' . ($plus ? '+' : '') . $verb;
                break;
        }
        $i = $j + 1;
    }
    return $out;
}

/** 在已有值上继续取字段（对应 .A.B 的 B 及之后段） */
function tplFieldOn($val, string $name)
{
    if ($val instanceof Site) {
        $fields = $val->fields();
        if (array_key_exists($name, $fields)) { return $fields[$name]; }
        throw new TplExecError('.' . $name, "can't evaluate field " . $name . ' in type *main.Site');
    }
    throw new TplExecError('.' . $name, "can't evaluate field " . $name . ' in type ' . tplTypeName($val));
}

/** 解析管道首段：字段 / 方法调用 / 根数据 */
function tplResolveFirst(string $name, array $args, Site $data)
{
    $methods = Site::templateMethods();
    if (isset($methods[$name])) {
        list($m, $want) = $methods[$name];
        if (count($args) !== $want) {
            throw new TplExecError('.' . $name, 'wrong number of args for ' . $name . ': want ' . $want . ' got ' . count($args));
        }
        foreach ($args as $a) {
            if (!is_string($a)) {
                throw new TplExecError('.' . $name, 'wrong type for value; expected string; got ' . tplTypeName($a));
            }
        }
        return $data->$m(...$args);
    }
    $fields = $data->fields();
    if (array_key_exists($name, $fields)) {
        if (count($args) !== 0) {
            throw new TplExecError('.' . $name, 'wrong number of args for ' . $name . ': want 0 got ' . count($args));
        }
        return $fields[$name];
    }
    throw new TplExecError('.' . $name, "can't evaluate field " . $name . ' in type *main.Site');
}

/** 求值一条命令（操作数 + 参数） */
function tplEvalCmd(array $op, array $args, Site $data)
{
    if ($op[0] === 'ident') {
        switch ($op[1]) {
            case 'printf':
                return tplSprintf($args);
            case 'len':
                if (count($args) !== 1) {
                    throw new TplExecError('len', 'wrong number of args for len: want 1 got ' . count($args));
                }
                if (is_string($args[0])) { return strlen($args[0]); }   // Go 的 len(字符串) 按字节计
                throw new TplExecError('len', 'len of type ' . tplTypeName($args[0]));
            case 'print':
                $parts = array();
                foreach ($args as $a) { $parts[] = tplGoValue($a, false); }
                return implode(' ', $parts);
        }
        throw new TplParseError('function "' . $op[1] . '" not defined');
    }
    if ($op[0] === 'field') {
        if (in_array('', $op[1], true)) {
            throw new TplParseError('unexpected "." in operand');
        }
        $val = tplResolveFirst($op[1][0], $args, $data);
        for ($k = 1; $k < count($op[1]); $k++) {
            $val = tplFieldOn($val, $op[1][$k]);
        }
        return $val;
    }
    if ($op[0] === 'dot') {
        if (count($args) !== 0) {
            throw new TplExecError('.', 'wrong number of args: want 0 got ' . count($args));
        }
        return $data;
    }
    // 常量命令：{{"abc"}} / {{7}}
    if (count($args) !== 0) {
        throw new TplExecError(tplTokText($op), 'wrong number of args: want 0 got ' . count($args));
    }
    switch ($op[0]) {
        case 'str':   return $op[1];
        case 'int':   return (int)$op[1];
        case 'float': return (float)$op[1];
    }
    throw new TplExecError(tplTokText($op), 'unexpected operand');
}

/** 求值参数记号（常量 / . / .Field 链） */
function tplEvalArg(array $t, Site $data)
{
    switch ($t[0]) {
        case 'str':   return $t[1];
        case 'int':   return (int)$t[1];
        case 'float': return (float)$t[1];
        case 'dot':   return $data;
        case 'field':
            if (in_array('', $t[1], true)) {
                throw new TplParseError('unexpected "." in operand');
            }
            $val = tplResolveFirst($t[1][0], array(), $data);
            for ($k = 1; $k < count($t[1]); $k++) {
                $val = tplFieldOn($val, $t[1][$k]);
            }
            return $val;
    }
    throw new TplParseError('unexpected "' . tplTokText($t) . '" in operand');
}

/** 求值整条管道；第 N 段命令把上一段结果作为最后一个参数（Go 管道语义） */
function tplEvalPipeline(array $cmds, Site $data)
{
    $val = null;
    $idx = 0;
    foreach ($cmds as $cmd) {
        $args = array();
        foreach ($cmd['args'] as $a) {
            $args[] = tplEvalArg($a, $data);
        }
        if ($idx > 0) { $args[] = $val; }
        $val = tplEvalCmd($cmd['op'], $args, $data);
        $idx++;
    }
    return $val;
}

/** 渲染入口：等价于 Go 的 template.New("user").Parse(tpl).Execute(w, data) */
function tplRender(string $tpl, Site $data): string
{
    $out = '';
    $i = 0;
    $n = strlen($tpl);
    $pendingTrimRight = false;
    while ($i < $n) {
        $left = strpos($tpl, '{{', $i);
        if ($left === false) {
            $seg = substr($tpl, $i);
            if ($pendingTrimRight) { $seg = ltrim($seg); $pendingTrimRight = false; }
            $out .= $seg;
            break;
        }
        $seg = substr($tpl, $i, $left - $i);
        if ($pendingTrimRight) { $seg = ltrim($seg); $pendingTrimRight = false; }
        $out .= $seg;
        $right = strpos($tpl, '}}', $left + 2);
        if ($right === false) { throw new TplParseError('unclosed action'); }
        $inner = substr($tpl, $left + 2, $right - $left - 2);

        // Go 的 {{- / -}} 去空白标记（子集实现：动作首/尾紧贴花括号的独立 “-”）
        $trimL = false;
        $trimR = false;
        $inner = ltrim($inner);
        if ($inner !== '' && $inner[0] === '-') { $trimL = true; $inner = substr($inner, 1); }
        $inner = rtrim($inner);
        if ($inner !== '' && substr($inner, -1) === '-') { $trimR = true; $inner = substr($inner, 0, -1); }
        if ($trimL) { $out = rtrim($out); }
        if ($trimR) { $pendingTrimRight = true; }

        try {
            $toks = tplLex($inner);
            if (count($toks) === 0) { throw new TplParseError('missing value for command'); }
            $cmds = tplParsePipeline($toks);
            $val = tplEvalPipeline($cmds, $data);
        } catch (TplExecError $e) {
            // 补上 Go 风格的位置信息：template: user:行:列: executing "user" at <...>
            $before = substr($tpl, 0, $left + 2);
            $nl = strrpos($before, "\n");
            $col = ($nl === false) ? $left + 3 : ($left + 2 - $nl);
            $line = substr_count($before, "\n") + 1;
            throw new TplExecError($e->at,
                'template: user:' . $line . ':' . $col . ': executing "user" at <' . $e->at . '>: ' . $e->getMessage());
        }
        $out .= tplGoValue($val, false);
        $i = $right + 2;
    }
    return $out;
}

// ---------------------- 请求处理 ----------------------
$site = new Site();
$tpl = isset($_GET['tpl']) ? (string)$_GET['tpl'] : '';
$output = null;
if ($tpl !== '') {
    try {
        $output = tplRender($tpl, $site);            // ← 漏洞：用户输入直接成为模板源码
    } catch (TplParseError $e) {
        $output = '模板解析出错: template: user:1: ' . $e->getMessage();
    } catch (TplExecError $e) {
        $output = '模板执行出错: ' . $e->getMessage();
    } catch (Throwable $e) {
        $output = '模板执行出错: template: user:1: ' . $e->getMessage();
    }
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
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
.badge2{display:inline-block;background:#fbbf24;color:#291800;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;margin-left:6px}
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
<span class="badge">SSTI · 模板注入 · 第 6 关</span><span class="badge2">PHP 复刻迷你引擎</span>
<h1>工单公告渲染器 —— Go template 模板注入</h1>
<div class="meta">难度：★★★★☆ 高阶 · 关卡 ID：<code>ssti6</code> · 运行时：PHP 8 复刻「迷你 Go template」引擎（原 Go 版见 <code>_original/main.go</code>）</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>工单中心后端的公告预览功能，会把运营输入的「模板片段」连同站点数据对象一起交给模板引擎渲染。本关的引擎用 <b>PHP 复刻了 Go <code>text/template</code> 的语法子集与报错指纹</b>（行为按 Go 模拟，原版 Go 实现完整保留在 <code>_original/</code> 目录）。</p>
<p>Go 模板和前几关都不一样：没有算术运算、没有下划线反射链，能做的事取决于<b>传给模板的数据对象长什么样</b>。flag 在服务器的一个文件里（<code>flag_ssti6.txt</code>），想办法让模板把它读出来。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>模板片段：<input type="text" name="tpl" placeholder="试试 {{.}} 或 {{.Banner}}" value="<?= e($tpl) ?>"></label>
<button type="submit">预览公告</button>
</form>
<?php if ($output !== null): ?>
<div class="result"><div class="t">渲染结果（引擎的解析/执行报错也属于输出，信息量很大）</div>
<pre><?= e($output) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关核心代码（已给出）——渲染入口与数据对象：</p>
<pre>// 渲染入口（等价于 Go 的 template.New("user").Parse(输入).Execute(w, data)）
$output = tplRender($_GET['tpl'], $site);     // ← 漏洞：用户输入直接成为模板源码

class Site {                                  // 传给模板的数据对象（Go: *main.Site）
    Name, Slogan                              // 字段：模板里 {{.Name}} 可读
    Banner()                                  // 导出方法：{{.Banner}} 可调用
    ReadFile($path)                           // ← 危险方法：无路径限制的读文件
}</pre>
<p>支持的动作子集：<code>{{.}}</code>、<code>{{.Field}}</code>、<code>{{.Method "参数"}}</code>（空格传参）、<code>{{printf "%+v" .}}</code>、<code>{{.Field | printf "%s"}}</code> 管道；算术、括号等一律按 Go 行为报解析错误。</p>
<p><b>Go 模板的能力边界</b>（和 Jinja2/Twig/Smarty 对照着记）：</p>
<table>
<tr><th>能力</th><th>Jinja2/Twig/Smarty</th><th>Go text/template（本关引擎同款行为）</th></tr>
<tr><td>算术探测 <code>{{7*7}}</code></td><td>49</td><td>❌ 解析直接报错（模板语言没有算术运算符）</td></tr>
<tr><td>反射链 <code>.__class__</code> / <code>__globals__</code></td><td>可达任意对象</td><td>❌ 不存在——只能访问传入数据的<b>导出字段/方法</b></td></tr>
<tr><td>枚举数据</td><td><code>{{config}}</code> 等</td><td>✅ <code>{{.}}</code> / <code>{{printf "%+v" .}}</code></td></tr>
<tr><td>调用方法</td><td>依引擎</td><td>✅ <code>{{.方法 参数}}</code>（仅限导出方法，参数类型还得对得上）</td></tr>
</table>
<p>所以 Go 系 SSTI 的利用思路是：<b>① <code>{{.}}</code> 摸清字段 → ② 看源码/报错找导出方法 → ③ 调用最危险的那个</b>。本题就有一个不该暴露的 <code>ReadFile</code>。顺带一提：<code>ReadFile</code> 没有路径限制，你甚至可以用 <code>{{.ReadFile "_original/main.go"}}</code> 把原版 Go 源码读出来对照——顺便体会“无路径限制”意味着什么。</p>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：先摸清模板能看到什么</summary><p>输入 <code>{{.}}</code> 会把数据对象按字段打印出来（Go 的 %v 格式，字段值都看得见）；<code>{{.Name}}</code>、<code>{{.Banner}}</code> 也都行。但 <code>{{7*7}}</code> 会报「unexpected "*"」——Go 模板没有算术，这不是 Jinja2。</p></details>
<details><summary>提示 2：字段里没有 flag，那方法呢？</summary><p>Go 模板还能调用数据对象的<b>导出方法</b>。翻翻「知识点讲解」里的源码：除了 Banner，还有一个为"包含文案片段"而写的便利方法，签名是 <code>ReadFile(path string) string</code>，而且没做任何路径限制。</p></details>
<details><summary>提示 3：完整 payload</summary><p><code>?tpl={{.ReadFile "flag_ssti6.txt"}}</code> —— 把题目目录里那个 flag 文件读出来。（方法调用用空格传参，字符串参数带引号。）</p></details>
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
