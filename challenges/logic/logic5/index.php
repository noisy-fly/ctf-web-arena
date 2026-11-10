<?php
declare(strict_types=1);
// logic5 星云钱包 —— 条件竞争(并发提现)
// 部署: PHP-FPM 多 worker(每个请求由独立 worker 处理,天然并发)。
// 注意: 本题刻意不使用 session —— PHP 的会话文件锁会把同一会话的并发请求串行化,竞态就演不出来了。
require __DIR__ . '/init.php';

function fmt(float $n): string {
    $s = number_format($n, 4, '.', '');
    $t = rtrim(rtrim($s, '0'), '.');
    return $t === '' ? '0' : $t;
}

$msg = '';
$ok = false;
$db = db();

// 教学环境: 所有玩家共用一个钱包;每次访问都确保初始行存在(表被清空也能自愈)
$db->exec("INSERT OR IGNORE INTO wallet(id, owner, balance, init_balance) VALUES(1,'you',50,50)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    $rawAmount = $_POST['amount'] ?? '0';
    if (is_array($do)) { $do = ''; }
    if (is_array($rawAmount)) { $rawAmount = '0'; }
    if ($do === 'withdraw') {
        $amount = (float)$rawAmount;   // 任意非法输入经 float 转换后都不会等于 50.0,走"金额固定"分支,无 Fatal
        if ($amount !== 50.0) {
            $msg = '单笔提现金额固定为 50 星币';
        } else {
            try {
                // ===== 漏洞代码: 典型的 check-then-act 竞态 =====
                // 1) 先读余额(检查) —— PHP-FPM 下每个请求由独立 worker 处理,各自读到各自快照
                $row = $db->query('SELECT balance FROM wallet WHERE id=1')->fetch();
                $bal = (float)($row['balance'] ?? 0);
                if ($bal >= $amount) {
                    // 2) 模拟"风控/银行通道校验"耗时 1.2 秒 —— 并发窗口被撑大
                    usleep(1200000);
                    // 3) 用 1) 读到的旧余额直接回写(动作),期间其他 worker 也能通过同样的检查
                    $db->beginTransaction();
                    $db->prepare('INSERT INTO withdrawals(amount, ts) VALUES(?, datetime("now","localtime"))')->execute([$amount]);
                    $db->prepare('UPDATE wallet SET balance=? WHERE id=1')->execute([$bal - $amount]);
                    $db->commit();
                    $msg = '提现成功，到账 50 星币';
                    $ok = true;
                } else {
                    $msg = '余额不足（当前 ' . fmt($bal) . '）';
                }
            } catch (PDOException $e) {
                if ($db->inTransaction()) { try { $db->rollBack(); } catch (Throwable) {} }
                $msg = '通道繁忙，本笔未入账（并发写锁冲突），请重试';
            }
        }
    }
}

$w = $db->query('SELECT * FROM wallet WHERE id=1')->fetch();
$agg = $db->query('SELECT coalesce(sum(amount),0) AS s, count(*) AS c FROM withdrawals')->fetch();
$rows = $db->query('SELECT * FROM withdrawals ORDER BY id DESC LIMIT 15')->fetchAll();
$bal = (float)($w['balance'] ?? 0);
$init = (float)($w['init_balance'] ?? 0);
$sum = (float)($agg['s'] ?? 0);
$cnt = (int)($agg['c'] ?? 0);

page_head('逻辑漏洞 5/5 · hard', '星云钱包 —— 一次点击，多次到账', '★★★☆☆',
 '钱包里有 <b>50</b> 星币，每笔可提现 <b>50</b>。规则上你只能提一次。但“检查余额→转账”之间存在一个<b>时间窗口</b>：' .
 '如果在窗口内同时发出很多个提现请求，每个请求看到的都还是“没被扣掉的 50”…… 试着<b>并发</b>提现，让累计提现金额超过初始余额（≥2 笔成功），本页就会出现 flag。' .
 '环境玩坏后可访问 <a href="reset.php">reset.php</a> 重置。',
 ['<b>条件竞争（Race Condition）</b>：“检查-再执行”两步之间没有加锁，并发请求互不感知，同一条件被多次通过。经典案例：余额提现、优惠券限领一张、投票刷票、秒杀超卖。',
  '<b>并发窗口</b>：本题在检查与执行之间留了 1.2 秒“风控延迟”，现实中哪怕几毫秒（一次数据库往返）也足以被打满。服务端是 PHP-FPM 多 worker：每个请求由独立进程处理，同时各自“查→睡→写”，互不可见。',
  '修复思路：把“检查+扣减”变成<b>原子的条件更新</b>（数据库行锁/事务），或加互斥锁 + 幂等令牌。'],
 ['单次点击提现，第二次会提示“余额不足”——顺序请求是安全的，问题出在“同时”。',
  '用 curl 并行发起 10~20 个 POST（do=withdraw&amp;amount=50）：shell 里 for 循环加 &amp; 最后 wait（或 xargs -P 16），或用 Burp Intruder / Turbo Intruder。',
  '只要有两个请求落在同一个 1.2 秒风控窗口内，累计提现就会 ≥100 &gt; 50，flag 出现。']);
?>
<div class="card"><h2>星云钱包</h2>
<div class="msg ok">当前余额：<b><?php echo fmt($bal); ?></b> 星币（初始 <?php echo fmt($init); ?>）&nbsp;|&nbsp;
累计已提现：<b><?php echo fmt($sum); ?></b> 星币（<?php echo $cnt; ?> 笔）</div>
<?php if ($sum > $init): ?>
<div class="msg err">⚠ 检测到挤兑：累计提现 <?php echo fmt($sum); ?> &gt; 初始余额 <?php echo fmt($init); ?>。
银行赔付通道已开启，补偿码：<code>flag{logic5_0fe0c9fe}</code></div>
<?php endif; ?>
<?php if ($msg): ?><div class="msg <?php echo $ok ? 'ok' : 'err'; ?>"><?php echo h($msg); ?></div><?php endif; ?>
<form method="post" style="display:flex;gap:8px;align-items:center">
<input type="hidden" name="do" value="withdraw">
<input name="amount" value="50" style="width:90px"> 星币
<button class="btn" type="submit">提现</button></form>
<p class="muted">顺序点击是安全的；要“双花”，请用脚本/工具并发开火（见渐进提示与 writeup）。</p>
<h2 style="margin-top:16px">提现记录</h2>
<table>
<tr><th>#</th><th>金额</th><th>时间</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?php echo (int)$r['id']; ?></td><td><?php echo fmt((float)$r['amount']); ?></td><td><?php echo h((string)$r['ts']); ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="3" class="muted">暂无记录</td></tr><?php endif; ?>
</table>
</div>
<?php page_foot('logic5');
