<?php
declare(strict_types=1);
// ============================================================
// graphql2 · graphql.php —— 迷你 GraphQL 端点（内省已关闭 + 会话上下文）
// 上一关的教训："生产环境要关闭内省"。本关照做了（引擎拒绝 __schema/__type），
// 但 member(id) 的 resolver 只做了"是否登录"检查，没做"能否读这个人"的
// 属主校验 → 登录后可查任意会员的 secretNote（GraphQL 版 IDOR）。
// 实验账号：bob / bob123（页面告知）；admin(id=1) 的 secretNote 是 flag。
// ============================================================
session_start();
require __DIR__ . '/gql.php';
require __DIR__ . '/members.php';

header('Content-Type: application/json; charset=utf-8');

function gql_out(array $o): void
{
    if (isset($o['data']) && is_array($o['data']) && $o['data'] === []) {
        $o['data'] = new stdClass();
    }
    echo json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$BOOKS = gql2_books();
$MEMBERS = gql2_members();
$member = gql2_current_member();   // 会话里的当前会员（null = 未登录）

// ---------------- Schema ----------------
$TYPES = [
    'Query' => ['kind' => 'OBJECT', 'fields' => [
        'books' => ['type' => '[Book]'],
        'me' => ['type' => 'Member', 'desc' => '当前登录会员（需会话）'],
        'member' => ['type' => 'Member', 'desc' => '按 id 查询会员（需会话）', 'args' => ['id' => 'ID']],
    ]],
    'Book' => ['kind' => 'OBJECT', 'fields' => [
        'id' => ['type' => 'Int'],
        'title' => ['type' => 'String'],
        'year' => ['type' => 'Int'],
    ]],
    'Member' => ['kind' => 'OBJECT', 'fields' => [
        'id' => ['type' => 'ID'],
        'username' => ['type' => 'String'],
        'email' => ['type' => 'String'],
        'borrowed' => ['type' => '[Book]',
            'resolve' => static function ($obj, array $args) use ($BOOKS): array {
                $out = [];
                foreach (($obj['borrowed'] ?? []) as $bid) {
                    if (isset($BOOKS[$bid])) {
                        $out[] = $BOOKS[$bid];
                    }
                }
                return $out;
            }],
        'secretNote' => ['type' => 'String', 'desc' => '会员私密备注（仅本人可见……理论上）'],
    ]],
    'Int' => ['kind' => 'SCALAR'],
    'String' => ['kind' => 'SCALAR'],
    'Boolean' => ['kind' => 'SCALAR'],
    'ID' => ['kind' => 'SCALAR'],
];

$RESOLVERS = [
    'books' => static function (array $args) use ($BOOKS): array {
        return array_values($BOOKS);
    },
    'me' => static function (array $args) use ($member): array {
        if ($member === null) {
            throw new GqlError('未登录：请先通过 /graphql2/login.php 登录（实验账号 bob/bob123）');
        }
        return $member;
    },
    'member' => static function (array $args) use ($MEMBERS): ?array {
        // ↓ 只检查了"登录与否"（me 里做的），这里连检查都没做——属主校验缺失 = IDOR（教学故意）
        if (!array_key_exists('id', $args) || $args['id'] === null) {
            return null;
        }
        $id = (string)$args['id'];
        return $MEMBERS[$id] ?? null;
    },
];

$engine = new GqlEngine($TYPES, $RESOLVERS, false);   // ← 内省已关闭

// ---------------- HTTP 接口 ----------------
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$query = '';
if ($method === 'POST') {
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($payload) && isset($payload['query']) && is_string($payload['query'])) {
        $query = $payload['query'];
    }
} else {
    $q = $_GET['query'] ?? '';
    if (is_string($q)) {
        $query = $q;
    }
}
if ($query === '') {
    gql_out(['errors' => [['message' => '缺少 query：POST {"query":"..."}（JSON）或 GET ?query=...']]]);
}

try {
    gql_out($engine->execute($query));
} catch (Throwable $e) {
    gql_out(['errors' => [['message' => '服务器内部错误：' . $e->getMessage()]]]);
}
