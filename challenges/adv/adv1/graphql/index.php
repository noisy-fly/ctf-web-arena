<?php
/**
 * adv1 · POST /adv1/graphql —— 手写迷你 GraphQL 端点（PHP 8.2 教学实现）
 *
 * 复刻自原 Python 版（_original/app.py）：
 *   1. 生产环境未关闭内省（introspection）→ {__schema{types{fields{name}}}}
 *      发现文档里没有的隐藏字段 systemEnv(key)；
 *   2. 直接查 systemEnv(key:"FLAG") 被 WAF 拦（WAF 只扫「查询文本」中的 flag 关键字）；
 *   3. 用 GraphQL「变量」把参数放到 variables JSON 里（或字符串 \u 转义）绕过文本 WAF。
 *
 * 解析器只需覆盖题目 schema 用到的语法：查询、别名、参数、变量、内省，无需完整实现 GraphQL。
 */

if (!function_exists('gql_out')) {
    function gql_out(array $o, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

class GQLError extends Exception
{
}

/* ---------------- 词法 ---------------- */
function gql_utf8($cp)
{
    // 码点 → UTF-8 字节串（不依赖 mbstring）
    if ($cp >= 0 && $cp < 0x80) {
        return chr($cp);
    }
    if ($cp >= 0x80 && $cp < 0x800) {
        return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
    }
    if ($cp >= 0x800 && $cp < 0x10000) {
        return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }
    if ($cp >= 0x10000 && $cp <= 0x10FFFF) {
        return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F))
             . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }
    return false;
}

function gql_lex($src)
{
    $toks = array();
    $i = 0;
    $n = strlen($src);
    while ($i < $n) {
        $c = $src[$i];
        if (ctype_space($c)) {
            $i++;
        } elseif ($c === '#') { // 注释
            while ($i < $n && $src[$i] !== "\n") {
                $i++;
            }
        } elseif (strpos('{}(),:$!', $c) !== false) {
            $toks[] = array($c, $c);
            $i++;
        } elseif ($c === '"') {
            $i++;
            $buf = '';
            while ($i < $n && $src[$i] !== '"') {
                $ch = $src[$i];
                if ($ch === '\\') {
                    if ($i + 1 >= $n) {
                        throw new GQLError('字符串转义不完整');
                    }
                    $esc = $src[$i + 1];
                    if ($esc === 'u') {
                        if ($i + 5 >= $n) {
                            throw new GQLError('unicode 转义不完整');
                        }
                        $hex = substr($src, $i + 2, 4);
                        if (!ctype_xdigit($hex)) {
                            throw new GQLError('unicode 转义非法：' . $hex);
                        }
                        $cp = (int)hexdec($hex);
                        $chr = gql_utf8($cp);
                        if ($chr === false || $chr === '') {
                            throw new GQLError('unicode 转义非法：' . $hex);
                        }
                        $buf .= $chr;
                        $i += 6;
                    } else {
                        $map = array('n' => "\n", 't' => "\t", '"' => '"', '\\' => '\\', '/' => '/');
                        $buf .= isset($map[$esc]) ? $map[$esc] : $esc;
                        $i += 2;
                    }
                } else {
                    $buf .= $ch;
                    $i++;
                }
            }
            if ($i >= $n) {
                throw new GQLError('字符串未闭合');
            }
            $i++;
            $toks[] = array('STRING', $buf);
        } elseif (ctype_alpha($c) || $c === '_') {
            $j = $i;
            while ($j < $n && (ctype_alnum($src[$j]) || $src[$j] === '_')) {
                $j++;
            }
            $toks[] = array('IDENT', substr($src, $i, $j - $i));
            $i = $j;
        } elseif ($c === '.' && substr($src, $i, 3) === '...') {
            $toks[] = array('SPREAD', '...');
            $i += 3;
        } else {
            throw new GQLError('无法识别的字符 ' . $c);
        }
    }
    $toks[] = array('EOF', null);
    return $toks;
}

/* ---------------- 语法 ---------------- */
class GqlParser
{
    public $toks;
    public $pos = 0;

    public function __construct(array $toks)
    {
        $this->toks = $toks;
    }

    public function peek()
    {
        return $this->toks[$this->pos];
    }

    public function eat($expect = null)
    {
        $t = $this->toks[$this->pos];
        if ($expect !== null && $t[0] !== $expect) {
            throw new GQLError('期望 ' . $expect . '，收到 ' . gql_repr($t[1]));
        }
        $this->pos++;
        return $t;
    }

    public function document()
    {
        $t = $this->peek();
        if ($t[0] === 'IDENT' && ($t[1] === 'query' || $t[1] === 'mutation')) {
            $this->eat();
            if ($t[1] === 'mutation') {
                throw new GQLError('本服务只读：不支持 mutation');
            }
            if ($this->peek()[0] === 'IDENT') {
                $this->eat(); // 操作名
            }
            if ($this->peek()[0] === '(') {
                $this->varDefs();
            }
            return $this->selectionSet();
        }
        if ($t[0] === '{') {
            return $this->selectionSet();
        }
        throw new GQLError('文档应以 query 或选择集 { 开头');
    }

    public function varDefs()
    {
        $this->eat('(');
        while ($this->peek()[0] !== ')') {
            $this->eat('$');
            $this->eat('IDENT');
            $this->eat(':');
            $this->eat('IDENT'); // 类型名
            if ($this->peek()[0] === '!') {
                $this->eat();
            }
            if ($this->peek()[0] === ',') {
                $this->eat();
            }
        }
        $this->eat(')');
    }

    public function selectionSet()
    {
        $this->eat('{');
        $fields = array();
        while ($this->peek()[0] !== '}') {
            $fields[] = $this->field();
        }
        $this->eat('}');
        return $fields;
    }

    public function field()
    {
        if ($this->peek()[0] === 'SPREAD') {
            throw new GQLError('片段暂不支持');
        }
        $name = $this->eat('IDENT')[1];
        $alias = $name;
        if ($this->peek()[0] === ':') {
            $this->eat();
            $name = $this->eat('IDENT')[1];
        }
        $args = array();
        if ($this->peek()[0] === '(') {
            $this->eat('(');
            while ($this->peek()[0] !== ')') {
                $k = $this->eat('IDENT')[1];
                $this->eat(':');
                $args[$k] = $this->valueNode();
                if ($this->peek()[0] === ',') {
                    $this->eat();
                }
            }
            $this->eat(')');
        }
        $subsel = null;
        if ($this->peek()[0] === '{') {
            $subsel = $this->selectionSet();
        }
        return array('alias' => $alias, 'name' => $name, 'args' => $args, 'subsel' => $subsel);
    }

    public function valueNode()
    {
        $t = $this->peek();
        if ($t[0] === 'STRING') {
            $this->eat();
            return array('lit', $t[1]);
        }
        if ($t[0] === '$') {
            $this->eat();
            return array('var', $this->eat('IDENT')[1]);
        }
        if ($t[0] === 'IDENT') {
            $this->eat();
            $lits = array('true' => true, 'false' => false, 'null' => null);
            return array('lit', isset($lits[$t[1]]) ? $lits[$t[1]] : $t[1]);
        }
        throw new GQLError('参数值仅支持字符串/布尔/null/变量');
    }
}

function gql_repr($v)
{
    if ($v === null) {
        return 'EOF';
    }
    if (is_bool($v)) {
        return $v ? 'true' : 'false';
    }
    return "'" . (string)$v . "'";
}

/* ---------------- 执行 ---------------- */
class MiniGql
{
    public $users;
    public $flag;

    const SCHEMA_META = array(
        'types' => array(
            array('name' => 'Query', 'kind' => 'OBJECT', 'fields' => array(
                array('name' => 'hello'), array('name' => 'version'),
                array('name' => 'users'), array('name' => 'systemEnv'),
            )),
            array('name' => 'User', 'kind' => 'OBJECT', 'fields' => array(
                array('name' => 'username'), array('name' => 'role'), array('name' => 'email'),
            )),
            array('name' => 'String', 'kind' => 'SCALAR', 'fields' => null),
            array('name' => 'Boolean', 'kind' => 'SCALAR', 'fields' => null),
            array('name' => 'ID', 'kind' => 'SCALAR', 'fields' => null),
        ),
    );

    public function __construct(array $users, $flag)
    {
        $this->users = $users;
        $this->flag = $flag;
    }

    public function resolveValue($v, array $variables)
    {
        if (is_array($v) && count($v) === 2 && $v[0] === 'var') {
            if (!array_key_exists($v[1], $variables)) {
                throw new GQLError('变量 $' . $v[1] . ' 未提供');
            }
            return $variables[$v[1]];
        }
        if (is_array($v) && count($v) === 2 && $v[0] === 'lit') {
            return $v[1];
        }
        return $v;
    }

    public function rootResolve($name, array $args, array $variables)
    {
        if ($name === 'hello') {
            return 'world';
        }
        if ($name === 'version') {
            return 'mini-graphql/1.0 (教学实现)';
        }
        if ($name === 'users') {
            return $this->users;
        }
        if ($name === 'systemEnv') {
            $key = null;
            if (array_key_exists('key', $args)) {
                $key = $this->resolveValue($args['key'], $variables);
            }
            if (!is_string($key) || $key === '') {
                throw new GQLError('字段 systemEnv 需要字符串参数 key，例如 systemEnv(key:"PATH")');
            }
            if ($key === 'FLAG') {
                // 容器启动时注入环境变量 FLAG；未注入时回退 flag.txt（与原版 os.environ.setdefault 行为一致）
                return $this->flag;
            }
            $v = getenv($key);
            return ($v === false || $v === '') ? null : $v;
        }
        throw new GQLError('Query 上不存在字段 ' . gql_repr($name));
    }

    public function resolveField(array $f, array $variables, $typename, $value)
    {
        if ($f['name'] === '__typename') {
            return $typename;
        }
        if ($f['name'] === '__schema') {
            $sub = $f['subsel'] === null ? array() : $f['subsel'];
            return $this->applySelection($sub, self::SCHEMA_META, $variables, '__Schema');
        }
        if ($f['name'] === '__type') {
            $nameArg = null;
            if (array_key_exists('name', $f['args'])) {
                $nameArg = $this->resolveValue($f['args']['name'], $variables);
            }
            $type = null;
            foreach (self::SCHEMA_META['types'] as $t) {
                if ($t['name'] === $nameArg) {
                    $type = $t;
                }
            }
            $sub = $f['subsel'] === null ? array() : $f['subsel'];
            return $this->applySelection($sub, $type, $variables, '__Type');
        }
        if (is_array($value) && array_key_exists($f['name'], $value)) {
            $inner = $value[$f['name']];
        } else {
            $inner = $this->rootResolve($f['name'], $f['args'], $variables);
        }
        if ($f['subsel'] !== null) {
            $tname = is_array($inner) ? 'User' : $typename;
            return $this->applySelection($f['subsel'], $inner, $variables, $tname);
        }
        return $inner;
    }

    public function applySelection(array $fields, $value, array $variables, $typename)
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value) && array_is_list($value)) {
            $out = array();
            foreach ($value as $v) {
                $out[] = $this->applySelection($fields, $v, $variables, $typename);
            }
            return $out;
        }
        $out = array();
        foreach ($fields as $f) {
            $out[$f['alias']] = $this->resolveField($f, $variables, $typename, $value);
        }
        return $out;
    }

    public function execute($query, array $variables)
    {
        $ast = (new GqlParser(gql_lex($query)))->document();
        $out = array();
        foreach ($ast as $f) {
            $out[$f['alias']] = $this->resolveField($f, $variables, 'Query', array());
        }
        return array('data' => $out);
    }
}

/* ---------------- 接口 ---------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gql_out(array('errors' => array(array('message' => '请使用 POST，请求体 {"query":"...","variables":{...}}'))), 405);
}

$FLAG = getenv('FLAG');
if ($FLAG === false || $FLAG === '') {
    $f = @file_get_contents(__DIR__ . '/../flag.txt');
    $FLAG = ($f === false) ? '' : trim($f);
}

$USERS = array(
    array('username' => 'alice', 'role' => 'admin', 'email' => 'alice@corp.internal'),
    array('username' => 'bob', 'role' => 'user', 'email' => 'bob@corp.internal'),
    array('username' => 'carol', 'role' => 'user', 'email' => 'carol@corp.internal'),
);

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    gql_out(array('errors' => array(array('message' => '请求体应为 JSON'))), 400);
}
$query = (isset($payload['query']) && is_string($payload['query'])) ? $payload['query'] : '';
$variables = (isset($payload['variables']) && is_array($payload['variables'])) ? $payload['variables'] : array();

// WAF：只扫查询文本里的关键字
if (str_contains(strtolower($query), 'flag')) {
    gql_out(array('errors' => array(array('message' => 'WAF：查询文本包含敏感关键字 flag，已拦截'))), 422);
}

try {
    $engine = new MiniGql($USERS, $FLAG);
    gql_out($engine->execute($query, $variables));
} catch (GQLError $e) {
    gql_out(array('errors' => array(array('message' => $e->getMessage()))), 400);
} catch (Throwable $e) {
    gql_out(array('errors' => array(array('message' => '服务器内部错误：' . $e->getMessage()))), 400);
}
