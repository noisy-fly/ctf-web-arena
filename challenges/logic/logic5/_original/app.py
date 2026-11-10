#!/usr/bin/env python3
"""logic5 星云钱包 —— 条件竞争(并发提现)。
运行: python app.py [port]   (默认 8105;Flask 多线程,竞态窗口 1.2 秒)
"""
import os
import sqlite3
import sys
import time

from flask import Flask, redirect, request

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(BASE_DIR, 'app.db')
FLAG = 'flag{logic5_0fe0c9fe}'
PORT = int(sys.argv[1]) if len(sys.argv) > 1 else int(os.environ.get('PORT', 8105))

app = Flask(__name__)


def get_db() -> sqlite3.Connection:
    conn = sqlite3.connect(DB_PATH, timeout=10)
    conn.row_factory = sqlite3.Row
    conn.execute('PRAGMA busy_timeout=5000')
    conn.execute('PRAGMA journal_mode=WAL')
    return conn


def init_db() -> None:
    conn = get_db()
    conn.executescript(open(os.path.join(BASE_DIR, 'init.sql'), encoding='utf-8').read())
    conn.commit()
    conn.close()


PAGE_HEAD = """<!doctype html><html lang=zh><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>logic5 星云钱包 · 条件竞争</title>
<style>
:root{--pri:#2563eb;--bg:#f5f7fb;--ink:#1f2937;--mut:#6b7280;--ok:#059669;--warn:#d97706;--err:#dc2626}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--ink);line-height:1.65}
.wrap{max-width:880px;margin:0 auto;padding:24px 16px 64px}
header{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin-bottom:6px}
.tag{background:var(--pri);color:#fff;border-radius:6px;padding:2px 10px;font-size:13px}
h1{font-size:22px;margin:0}.stars{color:#f59e0b;font-size:14px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 20px;margin:14px 0}
.card h2{font-size:16px;margin:0 0 8px;border-left:4px solid var(--pri);padding-left:8px}
details{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px 14px;margin:8px 0}
summary{cursor:pointer;font-weight:600;color:var(--mut)}
code{font-family:Consolas,Menlo,monospace;font-size:13px;background:#f1f5f9;border-radius:4px;padding:1px 5px}
table{border-collapse:collapse;width:100%}th,td{border:1px solid #e5e7eb;padding:6px 10px;font-size:14px;text-align:left}th{background:#f8fafc}
.btn{display:inline-block;background:var(--pri);color:#fff;border:0;border-radius:6px;padding:7px 16px;font-size:14px;cursor:pointer}
input{padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px}
.msg{padding:8px 12px;border-radius:6px;margin:8px 0}.err{background:#fef2f2;color:var(--err)}.ok{background:#ecfdf5;color:var(--ok)}
.flagbox{border:2px dashed var(--warn)}.muted{color:var(--mut);font-size:13px}
</style></head><body><div class=wrap>
<header><span class=tag>逻辑漏洞 5/5 · hard</span><h1>星云钱包 —— 一次点击，多次到账</h1><span class=stars>★★★☆☆</span></header>
<div class=card><h2>场景</h2><p>钱包里有 <b>50</b> 星币，每笔可提现 <b>50</b>。规则上你只能提一次。但"检查余额→转账"之间存在一个<b>时间窗口</b>：
如果在窗口内同时发出很多个提现请求，每个请求看到的都还是"没被扣掉的 50"…… 试着<b>并发</b>提现，让累计提现金额超过初始余额（≥2 笔成功），本页就会出现 flag。
环境玩坏后可访问 <a href="reset">reset</a> 重置。</p></div>
<div class=card><h2>知识点</h2><ul>
<li><b>条件竞争（Race Condition）</b>："检查-再执行"两步之间没有加锁，并发请求互不感知，同一条件被多次通过。经典案例：余额提现、优惠券限领一张、投票刷票、秒杀超卖。</li>
<li><b>并发窗口</b>：本题在检查与执行之间留了 1.2 秒"风控延迟"，现实中哪怕几毫秒（一次数据库往返）也足以被打满。</li>
<li>修复思路：把"检查+扣减"变成<b>原子的条件更新</b>（数据库行锁/事务），或加互斥锁 + 幂等令牌。</li>
</ul></div>
<div class=card><h2>渐进提示（点开前先自己想想）</h2>
<details><summary>提示 1</summary><p>单次点击提现，第二次会提示"余额不足"——顺序请求是安全的，问题出在"同时"。</p></details>
<details><summary>提示 2</summary><p>写脚本用多线程同时发 10~20 个 POST（do=withdraw&amp;amount=50），或用 Burp Intruder / Turbo Intruder。</p></details>
<details><summary>提示 3</summary><p>只要有两个请求都落在同一个 1.2 秒窗口内，累计提现就会 ≥100 &gt; 50，flag 出现。</p></details>
</div>
"""

PAGE_FOOT = """<div class="card flagbox"><h2>拿到 Flag 了吗？</h2>
<form method="post" action="/api/verify" target="_blank">
<input type="hidden" name="challenge_id" value="logic5">
<input name="flag" placeholder="flag{logic5_xxxxxxxx}" size="34" required>
<button class="btn" type="submit">提交 Flag</button></form>
<p class=muted>提交接口由靶场门户提供（POST /api/verify，字段 challenge_id、flag）。解出后建议阅读 writeup.md 复盘修复方案。</p></div>
<p class=muted>CTF Web 教学靶场 · 逻辑漏洞篇</p></div></body></html>"""


def render_index(msg: str = '') -> str:
    conn = get_db()
    w = conn.execute('select * from wallet where id=1').fetchone()
    agg = conn.execute('select coalesce(sum(amount),0) s, count(*) c from withdrawals').fetchone()
    rows = conn.execute('select * from withdrawals order by id desc limit 15').fetchall()
    conn.close()

    body = ['<div class=card><h2>星云钱包</h2>',
            f'<div class="msg ok">当前余额：<b>{w["balance"]:g}</b> 星币（初始 {w["init_balance"]:g}）&nbsp;|&nbsp; '
            f'累计已提现：<b>{agg["s"]:g}</b> 星币（{agg["c"]} 笔）</div>']
    if agg['s'] > w['init_balance']:
        body.append(f'<div class="msg err">⚠ 检测到挤兑：累计提现 {agg["s"]:g} &gt; 初始余额 {w["init_balance"]:g}。'
                    f'银行赔付通道已开启，补偿码：<code>{FLAG}</code></div>')
    if msg:
        body.append(f'<div class="msg err">{msg}</div>')
    body.append('<form method=post style="display:flex;gap:8px;align-items:center">'
                '<input type=hidden name=do value=withdraw>'
                '<input name=amount value=50 style="width:90px"> 星币 '
                '<button class=btn type=submit>提现</button></form>'
                '<h2 style="margin-top:16px">提现记录</h2><table>'
                '<tr><th>#</th><th>金额</th><th>时间</th></tr>')
    for r in rows:
        body.append(f'<tr><td>{r["id"]}</td><td>{r["amount"]:g}</td><td>{r["ts"]}</td></tr>')
    if not rows:
        body.append('<tr><td colspan=3 class=muted>暂无记录</td></tr>')
    body.append('</table></div>')
    return PAGE_HEAD + ''.join(body) + PAGE_FOOT


@app.route('/')
def index():
    return render_index()


@app.route('/', methods=['POST'])
def withdraw():
    msg = ''
    try:
        amount = float(request.form.get('amount', '0'))
    except ValueError:
        amount = 0.0
    if amount != 50.0:
        msg = '单笔提现金额固定为 50 星币'
    else:
        conn = get_db()
        # ===== 漏洞代码：典型的 check-then-act 竞态 =====
        # 1) 先读余额（检查）
        bal = conn.execute('select balance from wallet where id=1').fetchone()[0]
        if bal >= amount:
            # 2) 模拟"风控/银行通道校验"耗时 1.2 秒 —— 并发窗口被撑大
            time.sleep(1.2)
            # 3) 用 1) 读到的旧余额直接回写（动作），期间其他请求也能通过同样的检查
            with conn:
                conn.execute('insert into withdrawals(amount, ts) values(?, datetime("now","localtime"))', (amount,))
                conn.execute('update wallet set balance=? where id=1', (bal - amount,))
            msg = '提现成功，到账 50 星币'
        else:
            msg = f'余额不足（当前 {bal:g}）'
        conn.close()
    return render_index(msg)


@app.route('/reset')
def reset():
    for suffix in ('', '-wal', '-shm'):
        try:
            os.unlink(DB_PATH + suffix)
        except OSError:
            pass
    init_db()
    return redirect('/')


if __name__ == '__main__':
    init_db()
    app.run(host='0.0.0.0', port=PORT, threaded=True, debug=False)
