<?php
// ssti1 · 应用配置（等价于 Flask 的 app.config，也是模板上下文里 config 变量的来源）。
// 直接访问本文件只会 return 一个数组、不输出任何内容（空白页）。
// 恭喜通关 ssti1！flag{ssti1_4a3c9c99} —— {{config['FLAG']}}
return [
    'DEBUG'          => false,
    'APP_NAME'       => '会员商城 MemberMall',
    'ENV'            => 'production',
    'SECRET_KEY'     => 'dev-only-fake-key-请不要在生产环境学它',
    'SESSION_COOKIE' => 'mm_session',
    'MAX_NAME_LEN'   => 20,
    'FLAG'           => 'flag{ssti1_4a3c9c99}',
];
