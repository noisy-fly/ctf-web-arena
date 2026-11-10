<?php
// Twig 3.x 免 composer 引导（题库自 vendor，见 lib/Twig 与 lib/Twig.LICENSE）
// 等价于 twigphp/Twig v3.21.1 composer.json 里的 autoload 配置
spl_autoload_register(function ($class) {
    if (strncmp($class, 'Twig\\', 5) === 0) {
        $path = __DIR__ . '/lib/Twig/' . str_replace('\\', '/', substr($class, 5)) . '.php';
        if (is_file($path)) require $path;
    }
});

// symfony/deprecation-contracts shim（服务端与本题逻辑不触发弃用路径，保底即可）
if (!function_exists('trigger_deprecation')) {
    function trigger_deprecation(string $package, string $version, string $message, ...$args): void {}
}

require __DIR__ . '/lib/Twig/Resources/core.php';
require __DIR__ . '/lib/Twig/Resources/debug.php';
require __DIR__ . '/lib/Twig/Resources/escaper.php';
require __DIR__ . '/lib/Twig/Resources/string_loader.php';
