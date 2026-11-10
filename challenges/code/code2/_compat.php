<?php
// PHP 8 兼容层：create_function() 在 PHP 8.0 起被移除。
// 这里用 eval 还原它的原始语义——「把字符串拼进函数体再编译」，
// 这正是本题要教学的危险本质（模式 A 的注入点因此保持可用）。
// 返回值按闭包实现（题目代码直接 $fn() 调用）；编译失败返回 false。
if (!function_exists('create_function')) {
    function create_function($args, $code) {
        if (!is_string($args) || !is_string($code)) {
            return false;
        }
        try {
            $fn = eval('return function(' . $args . '){' . $code . '};');
            return $fn ?: false;
        } catch (\ParseError $e) {
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
