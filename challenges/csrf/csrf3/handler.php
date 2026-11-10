<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';

/** 隐私设置接口(真实入口 api.php 与模拟受害者 sim.php 共用)。
 *  漏洞1(JSON CSRF):对请求体做 json_decode,却不校验 Content-Type,
 *  于是 enctype=text/plain 的表单(简单请求,可跨站发送)也能提交 JSON。 */
function csrf3_api(?array $me, string $method, string $rawBody): array {
    if (!$me) { return [401, '未登录']; }
    $j = json_decode($rawBody, true);
    if (!is_array($j)) { return [400, '请求体不是合法 JSON']; }
    $p = (string)($j['protection'] ?? '');
    if (!in_array($p, ['low', 'high'], true)) { return [400, '不支持的保护级别']; }
    db()->prepare('UPDATE users SET protection = ? WHERE id = ?')->execute([$p, $me['id']]);
    return [200, '保护级别已更新为 ' . $p];
}

/** 私密资料接口(真实入口 profile.php 与模拟受害者 sim.php 共用)。
 *  高防护:要求 X-Protection 自定义头(跨站"简单请求"带不了自定义头 → 传统 CSRF 读不到)。
 *  低防护(移动端调试模式):不要求自定义头,并且 CORS 反射任意 Origin + 允许携带凭据 → 跨站可读。 */
function csrf3_profile(?array $me, string $origin, bool $hasXProtection): array {
    if (!$me) { return [403, '{"error":"forbidden"}', '']; }
    if ($me['protection'] === 'high' && !$hasXProtection) {
        return [403, '{"error":"高防护模式要求 X-Protection 自定义头"}', ''];
    }
    $body = json_encode(['user' => $me['username'], 'note' => $me['note']], JSON_UNESCAPED_UNICODE);
    $cors = $origin !== ''
        ? "Access-Control-Allow-Origin: $origin; Access-Control-Allow-Credentials: true"   // ← 漏洞2:反射任意 Origin
        : '';
    return [200, (string)$body, $cors];
}
