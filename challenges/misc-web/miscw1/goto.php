<?php
declare(strict_types=1);
// 漏洞:跳转目标完全由客户端参数决定,没有白名单校验
$url = (string)($_GET['url'] ?? '');
if ($url === '') { http_response_code(400); exit('缺少 url 参数'); }
header('Location: ' . $url);   // 任意地址,照跳不误
exit;
