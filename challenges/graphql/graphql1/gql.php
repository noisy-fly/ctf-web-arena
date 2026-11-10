<?php
declare(strict_types=1);
// ============================================================
// gql.php —— 手写迷你 GraphQL 引擎（教学实现，PHP 8.2，零依赖）
// 只覆盖题目 schema 用到的语法：查询文档、字段选择集（嵌套/别名）、
// 字面量参数（String/Int/Boolean/null）、__typename、__schema/__type
// 内省、字段级错误聚合、"Did you mean …" 拼写建议（levenshtein）。
// 不支持 mutation / 片段 / 变量（题目用不到，遇到给出明确报错）。
// ============================================================

class GqlError extends Exception
{
}

function gql_repr($v): string
{
    if ($v === null) {
        return 'EOF';
    }
    if (is_bool($v)) {
        return $v ? 'true' : 'false';
    }
    return "'" . (string)$v . "'";
}

/** 码点 → UTF-8 字节串（\uXXXX 转义用，不依赖 mbstring） */
function gql_utf8(int $cp): string
{
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
    return "\xEF\xBF\xBD";
}

/* ---------------- 词法 ---------------- */
function gql_lex(string $src): array
{
    $toks = [];
    $i = 0;
    $n = strlen($src);
    while ($i < $n) {
        $c = $src[$i];
        if (ctype_space($c)) {
            $i++;
        } elseif ($c === '#') {                    // 注释
            while ($i < $n && $src[$i] !== "\n") {
                $i++;
            }
        } elseif (strpos('{}():,!', $c) !== false) {
            $toks[] = [$c, $c];
            $i++;
        } elseif ($c === '$') {
            throw new GqlError('本教学实现不支持变量（$variables）');
        } elseif ($c === '"') {                    // 字符串字面量
            $i++;
            $buf = '';
            while ($i < $n && $src[$i] !== '"') {
                $ch = $src[$i];
                if ($ch === '\\') {
                    if ($i + 1 >= $n) {
                        throw new GqlError('字符串转义不完整');
                    }
                    $esc = $src[$i + 1];
                    if ($esc === 'u') {
                        if ($i + 5 >= $n) {
                            throw new GqlError('\\u 转义不完整');
                        }
                        $hex = substr($src, $i + 2, 4);
                        if (!ctype_xdigit($hex)) {
                            throw new GqlError('\\u 转义非法：' . $hex);
                        }
                        $buf .= gql_utf8((int)hexdec($hex));
                        $i += 6;
                    } else {
                        $map = ['n' => "\n", 't' => "\t", 'r' => "\r", 'b' => "\x08", 'f' => "\x0C",
                                '"' => '"', '\\' => '\\', '/' => '/'];
                        $buf .= array_key_exists($esc, $map) ? $map[$esc] : $esc;
                        $i += 2;
                    }
                } else {
                    $buf .= $ch;
                    $i++;
                }
            }
            if ($i >= $n) {
                throw new GqlError('字符串未闭合');
            }
            $i++;
            $toks[] = ['STRING', $buf];
        } elseif ($c === '-' || ctype_digit($c)) { // 整数字面量
            $j = $i + 1;
            while ($j < $n && ctype_digit($src[$j])) {
                $j++;
            }
            $toks[] = ['INT', (int)substr($src, $i, $j - $i)];
            $i = $j;
        } elseif (ctype_alpha($c) || $c === '_') { // 名称
            $j = $i;
            while ($j < $n && (ctype_alnum($src[$j]) || $src[$j] === '_')) {
                $j++;
            }
            $toks[] = ['IDENT', substr($src, $i, $j - $i)];
            $i = $j;
        } elseif (substr($src, $i, 3) === '...') {
            throw new GqlError('片段（...）暂不支持');
        } else {
            throw new GqlError('无法识别的字符：' . $c);
        }
    }
    $toks[] = ['EOF', null];
    return $toks;
}

/* ---------------- 语法 ---------------- */
class GqlParser
{
    private array $toks;
    private int $pos = 0;

    public function __construct(array $toks)
    {
        $this->toks = $toks;
    }

    private function peek(): array
    {
        return $this->toks[$this->pos] ?? ['EOF', null];
    }

    private function eat(?string $expect = null): array
    {
        $t = $this->peek();
        if ($expect !== null && $t[0] !== $expect) {
            throw new GqlError('期望 ' . $expect . '，收到 ' . gql_repr($t[1]));
        }
        $this->pos++;
        return $t;
    }

    /** @return array 根字段列表 */
    public function document(): array
    {
        $t = $this->peek();
        if ($t[0] === 'IDENT' && ($t[1] === 'query' || $t[1] === 'mutation')) {
            $this->eat();
            if ($t[1] === 'mutation') {
                throw new GqlError('本服务只读：不支持 mutation');
            }
            if ($this->peek()[0] === 'IDENT') {
                $this->eat();                          // 操作名
            }
            if ($this->peek()[0] === '(') {
                throw new GqlError('本教学实现不支持变量定义');
            }
        }
        $fields = $this->selectionSet();
        if ($this->peek()[0] !== 'EOF') {
            throw new GqlError('文档末尾存在多余内容：' . gql_repr($this->peek()[1]));
        }
        return $fields;
    }

    public function selectionSet(): array
    {
        $this->eat('{');
        $fields = [];
        while ($this->peek()[0] !== '}') {
            if ($this->peek()[0] === 'EOF') {
                throw new GqlError('选择集未闭合（缺少 }）');
            }
            $fields[] = $this->field();
        }
        $this->eat('}');
        return $fields;
    }

    public function field(): array
    {
        $alias = $this->eat('IDENT')[1];
        $name = $alias;
        if ($this->peek()[0] === ':') {
            $this->eat();
            $name = $this->eat('IDENT')[1];
        }
        $args = [];
        if ($this->peek()[0] === '(') {
            $this->eat('(');
            while ($this->peek()[0] !== ')') {
                if ($this->peek()[0] === 'EOF') {
                    throw new GqlError('参数列表未闭合（缺少 )）');
                }
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
        return ['alias' => $alias, 'name' => $name, 'args' => $args, 'subsel' => $subsel];
    }

    private function valueNode(): array
    {
        $t = $this->peek();
        if ($t[0] === 'STRING' || $t[0] === 'INT') {
            $this->eat();
            return ['lit', $t[1]];
        }
        if ($t[0] === 'IDENT') {
            $this->eat();
            $lits = ['true' => true, 'false' => false, 'null' => null];
            return ['lit', array_key_exists($t[1], $lits) ? $lits[$t[1]] : $t[1]];
        }
        throw new GqlError('参数值仅支持字符串/整数/布尔/null 字面量，收到 ' . gql_repr($t[1]));
    }
}

/* ---------------- 执行 ---------------- */
class GqlEngine
{
    private array $types;            // 类型注册表：name => ['kind'=>'OBJECT'|'SCALAR', 'fields'=>[fname=>def]]
    private array $rootResolvers;    // Query 字段 => callable(array $args): mixed
    private bool $allowIntrospection;
    private array $execErrors = [];

    /**
     * @param array $types            类型注册表（OBJECT 的 fields 里每项：type/desc/args/resolve 可选）
     * @param array $rootResolvers    Query 根字段 resolver 列表
     * @param bool $allowIntrospection 是否开放 __schema/__type 内省
     */
    public function __construct(array $types, array $rootResolvers, bool $allowIntrospection = true)
    {
        $this->types = $types;
        $this->rootResolvers = $rootResolvers;
        $this->allowIntrospection = $allowIntrospection;
    }

    public function execute(string $query): array
    {
        $this->execErrors = [];
        try {
            $ast = (new GqlParser(gql_lex($query)))->document();
        } catch (GqlError $e) {
            return ['errors' => [['message' => $e->getMessage()]]];
        }
        if (!$this->allowIntrospection && $this->usesIntrospection($ast)) {
            return ['errors' => [['message' => 'GraphQL introspection is disabled on this server.']]];
        }
        $errs = [];
        $this->validateFields($ast, 'Query', $errs, true);
        if ($errs) {
            return ['errors' => $errs];
        }
        $data = [];
        foreach ($ast as $f) {
            try {
                $data[$f['alias']] = $this->resolveRoot($f);
            } catch (GqlError $e) {
                $this->execErrors[] = ['message' => $e->getMessage()];
                $data[$f['alias']] = null;
            }
        }
        return $this->execErrors ? ['data' => $data, 'errors' => $this->execErrors] : ['data' => $data];
    }

    /* ---------- 内省开关检查 ---------- */
    private function usesIntrospection(array $fields): bool
    {
        foreach ($fields as $f) {
            if ($f['name'] === '__schema' || $f['name'] === '__type') {
                return true;
            }
            if (is_array($f['subsel']) && $this->usesIntrospection($f['subsel'])) {
                return true;
            }
        }
        return false;
    }

    /* ---------- 校验（未知字段/未知参数/子选择规则），带 Did-you-mean 建议 ---------- */
    private function validateFields(array $fields, string $typeName, array &$errs, bool $root): void
    {
        foreach ($fields as $f) {
            $name = (string)$f['name'];
            if ($name === '__typename') {
                if ($f['subsel'] !== null) {
                    $errs[] = ['message' => "Field '__typename' must not have a selection."];
                }
                continue;
            }
            if ($name === '__schema' || $name === '__type') {
                if (!$root) {
                    $errs[] = ['message' => "Cannot query field '" . $name . "' on type '" . $typeName . "'."];
                }
                continue;
            }
            $def = $this->types[$typeName]['fields'][$name] ?? null;
            if ($def === null) {
                $errs[] = ['message' => $this->unknownFieldMessage($name, $typeName)];
                continue;
            }
            foreach (array_keys($f['args']) as $an) {
                if (!array_key_exists($an, $def['args'] ?? [])) {
                    $errs[] = ['message' => "Unknown argument '" . $an . "' on field '" . $typeName . '.' . $name . "'."];
                }
            }
            $base = str_replace(['[', ']', '!'], '', (string)$def['type']);
            $isObj = (($this->types[$base]['kind'] ?? '') === 'OBJECT');
            if ($f['subsel'] === null) {
                if ($isObj) {
                    $errs[] = ['message' => "Field '" . $name . "' of type '" . $def['type']
                        . "' must have a selection of subfields. Did you mean '" . $name . " { ... }'?"];
                }
            } else {
                if (!$isObj) {
                    $errs[] = ['message' => "Field '" . $name . "' must not have a selection since type '"
                        . $base . "' has no subfields."];
                } else {
                    $this->validateFields($f['subsel'], $base, $errs, false);
                }
            }
        }
    }

    private function unknownFieldMessage(string $name, string $typeName): string
    {
        $msg = "Cannot query field '" . $name . "' on type '" . $typeName . "'.";
        $cands = [];
        $limit = max(1, intdiv(strlen($name), 2) + 1);
        foreach (array_keys($this->types[$typeName]['fields'] ?? []) as $cand) {
            $d = levenshtein($name, (string)$cand);
            if ($d <= $limit) {
                $cands[] = ['d' => $d, 'n' => (string)$cand];
            }
        }
        usort($cands, static fn(array $a, array $b): int => $a['d'] <=> $b['d']);
        $names = [];
        foreach ($cands as $c) {
            $names[] = "'" . $c['n'] . "'";
        }
        $cnt = count($names);
        if ($cnt === 1) {
            $msg .= ' Did you mean ' . $names[0] . '?';
        } elseif ($cnt === 2) {
            $msg .= ' Did you mean ' . $names[0] . ' or ' . $names[1] . '?';
        } elseif ($cnt > 2) {
            $msg .= ' Did you mean ' . implode(', ', array_slice($names, 0, -1)) . ', or ' . $names[$cnt - 1] . '?';
        }
        return $msg;
    }

    /* ---------- 解析执行 ---------- */
    private function resolveRoot(array $f)
    {
        $name = (string)$f['name'];
        $args = $this->litArgs($f['args']);
        if ($name === '__typename') {
            return 'Query';
        }
        if ($name === '__schema') {
            return $this->rawSelection($f['subsel'] ?? [], $this->schemaMeta());
        }
        if ($name === '__type') {
            $tn = array_key_exists('name', $args) ? (string)$args['name'] : '';
            foreach ($this->schemaMeta()['types'] as $t) {
                if (($t['name'] ?? '') === $tn) {
                    return $this->rawSelection($f['subsel'] ?? [], $t);
                }
            }
            return null;
        }
        $def = $this->types['Query']['fields'][$name] ?? null;
        if ($def === null) {
            throw new GqlError($this->unknownFieldMessage($name, 'Query'));
        }
        if (!isset($this->rootResolvers[$name]) || !is_callable($this->rootResolvers[$name])) {
            throw new GqlError('内部错误：字段 ' . $name . ' 缺少 resolver');
        }
        $value = call_user_func($this->rootResolvers[$name], $args);
        return $this->completeValue($f, $value, $def, $name);
    }

    private function completeValue(array $f, $value, array $def, string $fieldName)
    {
        if ($f['subsel'] === null) {
            $base = str_replace(['[', ']', '!'], '', (string)$def['type']);
            if (($this->types[$base]['kind'] ?? '') === 'OBJECT') {
                throw new GqlError("Field '" . $fieldName . "' of type '" . $def['type']
                    . "' must have a selection of subfields. Did you mean '" . $fieldName . " { ... }'?");
            }
            return $value;
        }
        return $this->applyTyped($f['subsel'], $value, (string)$def['type'], $fieldName);
    }

    private function applyTyped(array $fields, $value, string $typeStr, string $fieldName)
    {
        if ($value === null) {
            return null;
        }
        $base = str_replace(['[', ']', '!'], '', $typeStr);
        if (str_starts_with($typeStr, '[')) {       // 列表：逐项套用同一选择集
            if (!is_array($value)) {
                throw new GqlError("字段 '" . $fieldName . "' 期望列表值");
            }
            $out = [];
            foreach ($value as $v) {
                $out[] = $this->applyTyped($fields, $v, $base, $fieldName);
            }
            return $out;
        }
        if (($this->types[$base]['kind'] ?? '') === 'OBJECT') {
            return $this->applyObject($fields, $value, $base);
        }
        throw new GqlError("Field '" . $fieldName . "' must not have a selection since type '"
            . $base . "' has no subfields.");
    }

    private function applyObject(array $fields, $obj, string $typeName): array
    {
        if (!is_array($obj) || array_is_list($obj)) {
            throw new GqlError("类型 '" . $typeName . "' 的解析值不是对象（引擎内部错误）");
        }
        $out = [];
        foreach ($fields as $f) {
            $fname = (string)$f['name'];
            if ($fname === '__typename') {
                $out[$f['alias']] = $typeName;
                continue;
            }
            $def = $this->types[$typeName]['fields'][$fname] ?? null;
            if ($def === null) {
                throw new GqlError($this->unknownFieldMessage($fname, $typeName));
            }
            $args = $this->litArgs($f['args']);
            $inner = null;
            try {
                if (isset($def['resolve']) && is_callable($def['resolve'])) {
                    $inner = call_user_func($def['resolve'], $obj, $args);
                } elseif (array_key_exists($fname, $obj)) {
                    $inner = $obj[$fname];
                }
            } catch (GqlError $e) {
                $this->execErrors[] = ['message' => $e->getMessage()];
                $inner = null;
            }
            $out[$f['alias']] = $this->completeValue($f, $inner, $def, $fname);
        }
        return $out;
    }

    private function litArgs(array $args): array
    {
        $out = [];
        foreach ($args as $k => $v) {
            $out[$k] = (is_array($v) && isset($v[0]) && $v[0] === 'lit') ? $v[1] : null;
        }
        return $out;
    }

    /* ---------- 内省元数据（供 __schema/__type 用，rawSelection 直接按键取值） ---------- */
    private function schemaMeta(): array
    {
        $types = [];
        $queryFields = null;
        foreach ($this->types as $name => $def) {
            $fields = null;
            if (($def['kind'] ?? '') === 'OBJECT') {
                $fields = [];
                foreach ($def['fields'] as $fname => $fdef) {
                    $args = [];
                    foreach (($fdef['args'] ?? []) as $aname => $atype) {
                        $args[] = ['name' => $aname];
                    }
                    $fields[] = [
                        'name' => $fname,
                        'description' => $fdef['desc'] ?? null,
                        'args' => $args,
                    ];
                }
            }
            $types[] = [
                'name' => $name,
                'kind' => $def['kind'] ?? 'OBJECT',
                'description' => $def['desc'] ?? null,
                'fields' => $fields,
            ];
            if ($name === 'Query') {
                $queryFields = $fields;
            }
        }
        return ['types' => $types, 'queryType' => ['name' => 'Query', 'fields' => $queryFields]];
    }

    /** 对内省元数据按选择集取值（不做 schema 校验——元数据即数据） */
    private function rawSelection(array $fields, $value)
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                $out = [];
                foreach ($value as $v) {
                    $out[] = $this->rawSelection($fields, $v);
                }
                return $out;
            }
            $out = [];
            foreach ($fields as $f) {
                $inner = array_key_exists($f['name'], $value) ? $value[$f['name']] : null;
                $out[$f['alias']] = ($f['subsel'] !== null) ? $this->rawSelection($f['subsel'], $inner) : $inner;
            }
            return $out;
        }
        return $value;
    }
}
