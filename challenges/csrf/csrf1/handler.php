<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';

/** 订阅接口的业务实现:真实入口 api.php 与模拟受害者浏览器(sim.php)都调用这里。
 *  $me      当前会话对应的用户(由调用方决定——这正是 Cookie 的语义)
 *  $method  请求方法
 *  $get     查询参数
 *  漏洞:GET 做状态变更 + 无 CSRF token + 不校验 Referer/Origin */
function csrf1_api(?array $me, string $method, array $get): array {
    if (!$me) { return [401, '未登录']; }
    if (($get['do'] ?? '') === 'subscribe') {
        db()->prepare('UPDATE users SET subscribed = 1 WHERE id = ?')->execute([$me['id']]);
        return [200, '订阅成功！接收邮箱：' . (string)($get['email'] ?? '')];
    }
    return [404, '未知操作'];
}
