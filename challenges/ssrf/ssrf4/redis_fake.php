<?php
// ============================================================
// ssrf4 · 迷你 Redis（模拟"内网未授权 Redis"）
// 用 php CLI 常驻 127.0.0.1:6379，支持内联命令（与真 Redis 一致）
// 启动：php redis_fake.php   （建议 supervisor/entrypoint 常驻）
// 命令：PING / SET k v / GET k / KEYS * / DEL k / INFO / HELP
//       —— 宽松解析：每行扫描 token，容忍 dict 协议带来的前缀词（如 DEFINE/CLIENT）
// flag：从 /flag_ssrf4.txt 读取，预置为键 flag
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

$FLAG_FILE = '/flag_ssrf4.txt';
$flag = @file_exists($FLAG_FILE) ? trim((string)file_get_contents($FLAG_FILE)) : 'flag{ssrf4_not_deployed}';
$store = array('flag' => $flag);                       // 预置 flag 键
$debug_log = __DIR__ . '/gopher_debug.log';            // 记录原始收包，便于排障（gitignore）

$server = @stream_socket_server('tcp://127.0.0.1:6379', $errno, $errstr);
if (!$server) { fwrite(STDERR, "bind 127.0.0.1:6379 failed: $errstr\n"); exit(1); }
fwrite(STDOUT, "mini-redis up on 127.0.0.1:6379, flag key ready\n");

function resp_bulk($v) { return $v === null ? "\$-1\r\n" : '$' . strlen($v) . "\r\n" . $v . "\r\n"; }

while ($conn = @stream_socket_accept($server, null)) {
    stream_set_timeout($conn, 2);
    $buf = '';
    // 读一段（gopher/dict 通常一次把命令发完再等回包）
    while (($chunk = fread($conn, 4096)) !== false && $chunk !== '') {
        $buf .= $chunk;
        $meta = stream_get_meta_data($conn);
        if (strpos($buf, "\n") !== false && (feof($conn) || $meta['unread_bytes'] == 0)) break;
    }
    @file_put_contents($debug_log, '[' . date('H:i:s') . "] recv: " . bin2hex($buf) . " | raw: $buf\n", FILE_APPEND);

    $lines = preg_split('/\r?\n/', $buf);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        // 宽松分词：空格或冒号都算分隔（dict:// 的 get:flag / DEFINE get flag 都能识别）
        $tok = preg_split('/[\s:]+/', $line);
        $tok = array_values(array_filter($tok, function ($t) { return $t !== ''; }));
        $n = count($tok);
        $handled = false;
        for ($i = 0; $i < $n; $i++) {
            $c = strtolower($tok[$i]);
            if ($c === 'ping') { fwrite($conn, "+PONG\r\n"); $handled = true; break; }
            if ($c === 'get' && isset($tok[$i + 1])) {
                global $store;
                $k = $tok[$i + 1];
                fwrite($conn, resp_bulk(isset($store[$k]) ? $store[$k] : null));
                $handled = true; break;
            }
            if ($c === 'set' && isset($tok[$i + 2])) {
                global $store;
                $store[$tok[$i + 1]] = $tok[$i + 2];
                fwrite($conn, "+OK\r\n");
                $handled = true; break;
            }
            if ($c === 'keys') {
                global $store;
                $ks = array_keys($store);
                fwrite($conn, '*' . count($ks) . "\r\n");
                foreach ($ks as $k) fwrite($conn, resp_bulk($k));
                $handled = true; break;
            }
            if ($c === 'del' && isset($tok[$i + 1])) {
                global $store;
                unset($store[$tok[$i + 1]]);
                fwrite($conn, ":1\r\n");
                $handled = true; break;
            }
            if ($c === 'info') {
                fwrite($conn, "# mini-redis (ssrf4 teaching service)\r\nkeys:" . count($store) . "\r\n");
                $handled = true; break;
            }
            if ($c === 'help') {
                fwrite($conn, "commands: PING | SET k v | GET k | KEYS * | DEL k | INFO | HELP\r\n");
                $handled = true; break;
            }
        }
        if (!$handled) { fwrite($conn, "-ERR unknown command line\r\n"); }
    }
    fclose($conn);
}
