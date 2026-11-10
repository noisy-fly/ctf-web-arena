<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';

/** VIP 转让接口实现(真实入口 api.php 与模拟受害者 sim.php 共用)。
 *  这次开发"吸取了教训":只收 POST,还加了 Referer 校验——但校验写成了"包含即可" */
function csrf2_api(?array $me, string $method, array $get, array $post, string $referer): array {
    if (!$me) { return [401, '未登录']; }
    if ((($get['do'] ?? '') !== 'transfer_vip') && (($post['do'] ?? '') !== 'transfer_vip')) {
        return [404, '未知操作'];
    }
    if ($method !== 'POST') { return [405, '仅支持 POST（GET 做状态变更太危险,已禁用）']; }
    // Referer "包含"校验:只要字符串里出现 csrf2.lab 就放行
    if (strpos($referer, 'csrf2.lab') === false) {
        return [403, 'Referer 校验未通过:必须是本站(csrf2.lab)发起的请求'];
    }
    $to = (string)($post['to'] ?? '');
    $target = user_by_name($to);
    if (!$target) { return [404, '目标用户不存在']; }
    if (!$me['vip']) { return [403, '你不是 VIP,无权转让']; }
    db()->prepare('UPDATE users SET vip = 0 WHERE id = ?')->execute([$me['id']]);
    db()->prepare('UPDATE users SET vip = 1 WHERE id = ?')->execute([$target['id']]);
    return [200, '转让成功:' . $me['username'] . ' -> ' . $to];
}
