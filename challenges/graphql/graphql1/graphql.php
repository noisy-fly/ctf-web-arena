<?php
declare(strict_types=1);
// ============================================================
// graphql1 · graphql.php —— 迷你 GraphQL 端点（内省开放）
// POST {"query":"..."}（Content-Type: application/json）或 GET ?query=...
// 教学漏洞：生产环境开放内省（__schema/__type 全量可查）
//   → 文档里"不存在"的 Query.systemConfig 被揪出来 → adminNote 即 flag
// ============================================================
require __DIR__ . '/gql.php';

header('Content-Type: application/json; charset=utf-8');

function gql_out(array $o): void
{
    if (isset($o['data']) && is_array($o['data']) && $o['data'] === []) {
        $o['data'] = new stdClass();
    }
    echo json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

const GQL1_FLAG = 'flag{graphql1_c07aa601}';

// ---------------- 数据 ----------------
$BOOKS = [
    ['id' => 1, 'title' => '三体·黑暗森林', 'author' => '刘慈欣', 'year' => 2008],
    ['id' => 2, 'title' => '宇宙尽头的餐馆', 'author' => 'Douglas Adams', 'year' => 1980],
    ['id' => 3, 'title' => '银河系搭车客指南', 'author' => 'Douglas Adams', 'year' => 1979],
    ['id' => 4, 'title' => '沙丘', 'author' => 'Frank Herbert', 'year' => 1965],
];

// ---------------- Schema（类型注册表） ----------------
$TYPES = [
    'Query' => ['kind' => 'OBJECT', 'fields' => [
        'books' => ['type' => '[Book]', 'desc' => '馆藏图书列表（公开）'],
        'book' => ['type' => 'Book', 'desc' => '按 id 查询图书（公开）', 'args' => ['id' => 'Int']],
        // ↓ 未写入任何文档、页面菜单也没有——但内省能看见
        'systemConfig' => ['type' => 'SystemConfig', 'desc' => '[内部] 系统配置（请勿对外暴露）'],
    ]],
    'Book' => ['kind' => 'OBJECT', 'fields' => [
        'id' => ['type' => 'Int'],
        'title' => ['type' => 'String'],
        'author' => ['type' => 'String'],
        'year' => ['type' => 'Int'],
    ]],
    // "内部"类型：文档与页面均未提及
    'SystemConfig' => ['kind' => 'OBJECT', 'fields' => [
        'maintenanceMode' => ['type' => 'Boolean', 'desc' => '维护模式开关'],
        'adminNote' => ['type' => 'String', 'desc' => '管理员备注（内部）'],
    ]],
    'Int' => ['kind' => 'SCALAR'],
    'String' => ['kind' => 'SCALAR'],
    'Boolean' => ['kind' => 'SCALAR'],
    'ID' => ['kind' => 'SCALAR'],
];

$RESOLVERS = [
    'books' => static function (array $args) use ($BOOKS): array {
        return $BOOKS;
    },
    'book' => static function (array $args) use ($BOOKS): ?array {
        if (!array_key_exists('id', $args) || !is_int($args['id'])) {
            throw new GqlError('book 需要整数参数 id，例如 book(id: 1)');
        }
        foreach ($BOOKS as $b) {
            if ($b['id'] === $args['id']) {
                return $b;
            }
        }
        return null;
    },
    'systemConfig' => static function (array $args): array {
        return ['maintenanceMode' => false, 'adminNote' => GQL1_FLAG];
    },
];

$engine = new GqlEngine($TYPES, $RESOLVERS, true);   // ← 内省开放（漏洞点）

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
