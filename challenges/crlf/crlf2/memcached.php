<?php
declare(strict_types=1);
// ============================================================
// crlf2 · 迷你 memcached（文本协议子集）—— PHP 进程内引擎 + JSON 文件持久化
// 真实部署里 memcached 是独立 TCP 服务（文本协议）；共享路由的 PHP 运行时
// 无法监听第二个端口，这里用“文本协议引擎 + 文件存储”等价模拟：
//   - 命令流按 \r\n 分隔逐条解析执行（支持管道化：一条流里多条命令）；
//   - get / set（含定长数据块）/ delete / flush_all，回复格式与真实
//     memcached 一致（VALUE..END / STORED / DELETED / NOT_FOUND / OK / END）；
//   - 所有访客共享同一份缓存（全局服务端状态，重启后仍在——文件持久化）。
// 教学点不变：应用把用户输入拼进“一行一命令（\r\n 边界）”的协议文本，
// get 就能裂变出 set —— 与打真实 memcached 的注入完全同构。
// ============================================================

class MiniMemcached
{
    private string $file;

    public function __construct(string $dir)
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->file = $dir . '/cache.json';
    }

    /** 初始缓存内容（reset 后亦恢复为此） */
    public static function defaults(): array
    {
        return [
            'role' => 'guest',                                     // 权限角色（本关目标）
            'announcement' => '系统维护公告：周四 02:00-04:00 停机',  // 首页展示
            'motto' => 'Cache me if you can.',
        ];
    }

    private function load(): array
    {
        $raw = @file_get_contents($this->file);
        $m = $raw === false ? null : json_decode($raw, true);
        return is_array($m) ? $m : self::defaults();
    }

    private function save(array $m): void
    {
        @file_put_contents($this->file, json_encode($m, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * 执行一段原始文本协议命令流（多条命令以 \r\n 分隔），返回服务的全部回复。
     * 这是“缓存服务”的全部行为：命令边界就是 \r\n —— 用户输入一旦能跨过
     * 边界，“一次查询”就变成了“任意命令序列”。
     */
    public function execute(string $stream): string
    {
        $map = $this->load();
        $out = $this->run($stream, $map);
        $this->save($map);
        return $out;
    }

    private function run(string $stream, array &$map): string
    {
        $out = '';
        $pos = 0;
        $len = strlen($stream);
        while ($pos < $len) {
            $nl = strpos($stream, "\r\n", $pos);
            if ($nl === false) {
                $line = substr($stream, $pos);
                $pos = $len;
            } else {
                $line = substr($stream, $pos, $nl - $pos);
                $pos = $nl + 2;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode(' ', $line);
            $cmd = strtolower($parts[0]);
            if ($cmd === 'quit' || $cmd === 'exit') {
                break;
            }
            if ($cmd === 'get' || $cmd === 'gets') {
                $count = count($parts);
                for ($i = 1; $i < $count; $i++) {
                    $k = $parts[$i];
                    if ($k !== '' && array_key_exists($k, $map) && is_string($map[$k])) {
                        $out .= 'VALUE ' . $k . ' 0 ' . strlen($map[$k]) . "\r\n" . $map[$k] . "\r\n";
                    }
                }
                $out .= "END\r\n";
            } elseif ($cmd === 'set' || $cmd === 'add' || $cmd === 'replace') {
                // set <key> <flags> <exptime> <bytes>\r\n<数据块(定长)>\r\n
                if (count($parts) < 5 || !preg_match('/^\d+$/', (string)$parts[4])) {
                    $out .= "CLIENT_ERROR bad command line format\r\n";
                    continue;
                }
                $key = (string)$parts[1];
                $n = (int)$parts[4];
                $data = substr($stream, $pos, $n);            // 定长数据块
                $pos = min($len, $pos + $n + 2);              // 跳过数据块 + 尾随 \r\n
                if ($key === '') {
                    $out .= "CLIENT_ERROR bad command line format\r\n";
                } elseif ($cmd === 'add' && array_key_exists($key, $map)) {
                    $out .= "NOT_STORED\r\n";
                } elseif ($cmd === 'replace' && !array_key_exists($key, $map)) {
                    $out .= "NOT_STORED\r\n";
                } else {
                    $map[$key] = $data;                       // ← 污染点：写入任意 key/value
                    $out .= "STORED\r\n";
                }
            } elseif ($cmd === 'delete') {
                $k = $parts[1] ?? '';
                if ($k !== '' && array_key_exists($k, $map)) {
                    unset($map[$k]);
                    $out .= "DELETED\r\n";
                } else {
                    $out .= "NOT_FOUND\r\n";
                }
            } elseif ($cmd === 'flush_all') {
                $map = [];
                $out .= "OK\r\n";
            } else {
                $out .= "ERROR\r\n";
            }
        }
        return $out;
    }

    /** 应用正常的 get 封装（安全用法：key 由程序给出，不经用户输入）。未命中返回 null。 */
    public function get(string $key): ?string
    {
        $r = $this->execute('get ' . $key . "\r\n");
        if (preg_match('/VALUE \S+ 0 \d+\r\n(.*?)\r\nEND/s', $r, $m)) {
            return $m[1];
        }
        return null;
    }

    /** 重置缓存为初始状态（方便重复练习） */
    public function reset(): void
    {
        $this->save(self::defaults());
    }
}
