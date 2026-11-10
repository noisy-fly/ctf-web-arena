# -*- coding: utf-8 -*-
"""CTF Web Arena — 校验/会话/解锁 API（gunicorn 运行于容器内 127.0.0.1:8000）。

端点：
  GET  /api/state          会话进度+关卡地图数据（门户首屏）
  POST /api/verify         flag 校验（前置题已解 + sha256 比对），成功即解锁下一题
  GET  /api/meta/<id>      题目面板元数据（未解锁返回 403）
  GET  /api/gate?next=     无 cookie/未解锁的引导页（nginx auth_request 403 的落点）
  GET  /_auth              nginx auth_request 子请求（读 X-Original-URI），绝不创建会话
  POST /api/xssbot         XSS 虚拟 Victim（教学模拟）
  POST /api/reset          清空当前会话进度
  GET  /api/health         健康检查
"""
import hashlib
import json
import os
import re
import secrets
import sqlite3
import time

from flask import Flask, g, jsonify, redirect, request

import bot as xssbot
from oob import oob_bp

BASE = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE, "data")
DB_PATH = os.path.join(DATA_DIR, "ctf.db")
COOKIE = "ctf_sess"
SESS_DAYS = 30

with open(os.path.join(BASE, "challenges.json"), encoding="utf-8") as f:
    SEED = json.load(f)

CATS = SEED["categories"]
CHALS = sorted(SEED["challenges"], key=lambda c: (c["category"], c["order"]))
BY_ID = {c["id"]: c for c in CHALS}
# 仅"非 public"题目做页面门禁（recon 类产物保持公开，见 AGENTS.md ADR-5）
BY_PATH = {c["path"]: c for c in CHALS if not c.get("public")}
PREV = {}
NEXT = {}
for c in CHALS:
    p = next((x for x in CHALS if x["category"] == c["category"] and x["order"] == c["order"] - 1), None)
    n = next((x for x in CHALS if x["category"] == c["category"] and x["order"] == c["order"] + 1), None)
    PREV[c["id"]] = p
    NEXT[c["id"]] = n

TOKEN_RE = re.compile(r"^[0-9a-f]{16,64}$")
app = Flask(__name__)
app.register_blueprint(oob_bp)


# ---------- 数据库 ----------
def sha(s):
    return hashlib.sha256(s.encode("utf-8")).hexdigest()


DDL = """
        CREATE TABLE IF NOT EXISTS sessions(token TEXT PRIMARY KEY, created INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS solves(sess TEXT NOT NULL, chal TEXT NOT NULL, ts INTEGER NOT NULL,
                                         PRIMARY KEY(sess, chal));
        CREATE TABLE IF NOT EXISTS challenges(id TEXT PRIMARY KEY, flag_sha TEXT NOT NULL);
        """


def init_db():
    """gunicorn 多 worker 同时 import 也会走到这里，sqlite 并发初始化会短暂锁库，
    必须带 busy timeout 并整体重试，否则 worker 随机起不来。"""
    os.makedirs(DATA_DIR, exist_ok=True)
    last = None
    for _ in range(60):
        try:
            con = sqlite3.connect(DB_PATH, timeout=15)
            con.execute("PRAGMA journal_mode=WAL")
            con.executescript(DDL)
            for c in CHALS:
                con.execute(
                    "INSERT INTO challenges(id, flag_sha) VALUES(?,?) "
                    "ON CONFLICT(id) DO UPDATE SET flag_sha=excluded.flag_sha",
                    (c["id"], sha(c["flag"])),
                )
            con.commit()
            con.close()
            return
        except sqlite3.OperationalError as e:
            last = e
            time.sleep(0.5)
    raise last


def db():
    if "db" not in g:
        g.db = sqlite3.connect(DB_PATH, timeout=10)
        g.db.row_factory = sqlite3.Row
    return g.db


@app.teardown_appcontext
def _close_db(exc):
    conn = g.pop("db", None)
    if conn is not None:
        conn.close()


init_db()


# ---------- 会话 ----------
def solved_set(token):
    rows = db().execute("SELECT chal FROM solves WHERE sess=?", (token,)).fetchall()
    return {r["chal"] for r in rows}


def is_unlocked(chal, solved):
    if chal["order"] == 1:
        return True
    prev = PREV[chal["id"]]
    return prev is not None and prev["id"] in solved


def flag_sha(cid):
    row = db().execute("SELECT flag_sha FROM challenges WHERE id=?", (cid,)).fetchone()
    return row["flag_sha"] if row else None


@app.before_request
def _session():
    g.sess = None
    g.sess_new = False
    raw = request.cookies.get(COOKIE, "")
    if TOKEN_RE.match(raw):
        row = db().execute("SELECT token FROM sessions WHERE token=?", (raw,)).fetchone()
        if row:
            g.sess = raw
    # /_auth 只读不建；/api/health 无状态，避免 healthcheck 塞满会话表
    if g.sess is None and request.path not in ("/_auth", "/api/health"):
        token = secrets.token_hex(16)
        db().execute("INSERT OR IGNORE INTO sessions(token, created) VALUES(?,?)", (token, int(time.time())))
        db().commit()
        g.sess = token
        g.sess_new = True


@app.after_request
def _cookie(resp):
    if getattr(g, "sess_new", False) and g.get("sess"):
        resp.set_cookie(COOKIE, g.sess, max_age=SESS_DAYS * 86400,
                        httponly=True, samesite="Lax", path="/")
    return resp


def brief(c, solved):
    prev = PREV[c["id"]]
    return {
        "id": c["id"], "title": c["title"], "path": c["path"],
        "difficulty": c["difficulty"],
        "solved": c["id"] in solved,
        "unlocked": is_unlocked(c, solved),
        "prereq_title": prev["title"] if prev else None,
        "prereq_path": prev["path"] if prev else None,
    }


# ---------- 端点 ----------
@app.get("/api/health")
def health():
    return jsonify(ok=True)


@app.get("/api/state")
def state():
    solved = solved_set(g.sess)
    cats = []
    for cid, meta in CATS.items():
        items = [brief(c, solved) for c in CHALS if c["category"] == cid]
        if items:
            cats.append({
                "id": cid, "title": meta["title"], "emoji": meta["emoji"],
                "desc": meta["desc"], "challenges": items,
            })
    return jsonify(
        categories=cats,
        progress={"solved": len(solved & set(BY_ID)), "total": len(CHALS)},
    )


@app.post("/api/verify")
def verify():
    # 规范格式：JSON {"id","flag"}。兼容垫片：页内表单/旧版页面以 urlencoded
    # 提交 challenge_id 字段（多会话模板复制的历史形态），同样接受并映射到 id。
    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        data = request.form.to_dict() or {}
    cid = (data.get("id") or data.get("challenge_id") or "").strip()
    flag = (data.get("flag") or "").strip()
    chal = BY_ID.get(cid)
    if not chal:
        return jsonify(ok=False, msg="题目不存在", correct=False, message="题目不存在"), 404
    solved = solved_set(g.sess)
    if cid in solved:
        nxt = NEXT.get(cid)
        return jsonify(ok=True, msg="已解出过", correct=True, message="已解出过",
                       next={"id": nxt["id"], "title": nxt["title"], "path": nxt["path"]} if nxt else None)
    if not is_unlocked(chal, solved):
        prev = PREV[cid]
        if prev is None:
            return jsonify(ok=False, msg="前置题尚未上线，本题暂时无法解锁",
                           correct=False, message="前置题尚未上线，本题暂时无法解锁"), 403
        m = f"题目未解锁：请先完成前置题「{prev['title']}」"
        return jsonify(ok=False, msg=m, correct=False, message=m), 403
    if flag_sha(cid) != sha(flag):
        return jsonify(ok=False, msg="flag 不正确，再想想？",
                       correct=False, message="flag 不正确，再想想？")
    db().execute("INSERT OR IGNORE INTO solves(sess, chal, ts) VALUES(?,?,?)", (g.sess, cid, int(time.time())))
    db().commit()
    nxt = NEXT.get(cid)
    return jsonify(
        ok=True, msg="正确！", correct=True, message="回答正确！",
        next={"id": nxt["id"], "title": nxt["title"], "path": nxt["path"]} if nxt else None,
    )


@app.get("/api/meta/<cid>")
def meta(cid):
    chal = BY_ID.get(cid)
    if not chal:
        return jsonify(ok=False, msg="题目不存在"), 404
    solved = solved_set(g.sess)
    if not (cid in solved or is_unlocked(chal, solved)):
        prev = PREV[cid]
        if prev is None:
            return jsonify(ok=False, msg="前置题尚未上线，本题暂时无法解锁"), 403
        return jsonify(ok=False, msg=f"题目未解锁：请先完成前置题「{prev['title']}」",
                       prereq_title=prev["title"], prereq_path=prev["path"]), 403
    nxt = NEXT.get(cid)
    prev = PREV[cid]
    return jsonify(
        id=cid, title=chal["title"], order=chal["order"], difficulty=chal["difficulty"],
        category=chal["category"], category_title=CATS[chal["category"]]["title"],
        description_html=chal["description_html"], hints=chal.get("hints", []),
        knowledge=chal.get("knowledge", []), bot=bool(chal.get("bot")),
        solved=cid in solved,
        next={"id": nxt["id"], "title": nxt["title"], "path": nxt["path"]} if nxt else None,
        prereq_title=prev["title"] if prev else None,
        prereq_path=prev["path"] if prev else None,
    )


@app.get("/_auth")
def nginx_auth():
    """nginx auth_request 子请求：2xx=放行，403=走 gate。绝不创建会话。

    门禁单位是「题目的整棵子树」：/<id> 与 /<id>/... 都按该题判定，防止
    /<id>/index.php 之类的子路径绕过解锁链。无会话时仅放行两类：
      1) 公开题 / 未登记路径（recon 产物等）；
      2) 来源为 127.0.0.1 的「题目子路径」请求——服务器内部回调（SSRF 系列
         以本机身份读 internal.php 时不会带 cookie），浏览器用户做不到。
    """
    path = (request.headers.get("X-Original-URI", "") or "/").split("?", 1)[0]
    seg = path.strip("/").split("/", 1)[0] if path.strip("/") else ""
    chal = BY_PATH.get("/" + seg) if seg else None
    if chal is None:
        return "", 200
    if g.sess and is_unlocked(chal, solved_set(g.sess)):
        return "", 200
    if not g.sess:
        # 首访无会话：天然解锁的题（第一题）直接放行——否则 gate 判「已解锁」又 302
        # 回原题，_auth 再次 403，形成 gate↔_auth 回环，浏览器直到放弃。
        if is_unlocked(chal, set()):
            return "", 200
        # 服务器内部回调（SSRF 场景）只放行题目子路径
        if (path.rstrip("/") != chal["path"]
                and request.headers.get("X-Original-Remote", "") == "127.0.0.1"):
            return "", 200
    return "", 403


LOCKED_PAGE = """<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>未解锁 · CTF Web Arena</title>
<script src="/assets/boot.js"></script><link rel="stylesheet" href="/assets/style.css"></head>
<body style="display:grid;place-items:center;min-height:100vh">
<div style="text-align:center;max-width:520px;padding:30px" class="cat-card">
<div style="font-size:3rem">🔒</div>
<h2 style="margin:10px 0">这一关还没解锁</h2>
<p style="color:var(--fg-soft);font-size:.92rem">%s<br>%s</p>
<p><a class="btn" href="/">← 回关卡地图</a></p>
</div></body></html>"""


@app.get("/api/gate")
def gate():
    nxt = request.args.get("next", "/")
    if not nxt.startswith("/") or ".." in nxt:
        nxt = "/"
    path = nxt.split("?", 1)[0]
    chal = BY_PATH.get(path)
    if not chal:
        return redirect("/")
    solved = solved_set(g.sess)
    if is_unlocked(chal, solved):
        return redirect(nxt)
    prev = PREV[chal["id"]]
    if prev is None:
        msg = f"「{chal['title']}」的前置题尚未上线。"
        sub = "该题暂时无法解锁，先去玩别的关卡轨道吧。"
    else:
        msg = f"「{chal['title']}」需要先通过前置题。"
        sub = f"先去解出 <b>{prev['title']}</b>（{prev['path']}）再来挑战吧。线性解锁是教学靶场的规则～"
    return LOCKED_PAGE % (msg, sub)


@app.post("/api/xssbot")
def xssbot_route():
    data = request.get_json(silent=True) or {}
    url = data.get("url") or ""
    if url.startswith("http://") or url.startswith("https://"):
        from urllib.parse import urlsplit
        u = urlsplit(url)
        url = u.path + (("?" + u.query) if u.query else "")
    if not url.startswith("/"):
        return jsonify(executed=False, reason="请提交站内链接（以 / 开头）"), 400
    path = url.split("?", 1)[0]
    chal = BY_PATH.get(path)
    if not chal or not chal.get("bot"):
        return jsonify(executed=False, reason="这个链接不是 XSS 关卡"), 400
    solved = solved_set(g.sess)
    if not is_unlocked(chal, solved):
        return jsonify(executed=False, reason="题目未解锁"), 403
    try:
        res = xssbot.visit(chal["id"], url, g.sess)
    except Exception as e:  # noqa: BLE001
        return jsonify(executed=False, reason="Victim 出错了：%s" % e), 500
    out = {"executed": bool(res.get("executed")), "reason": res.get("reason", ""),
           "vector": res.get("vector", "")}
    if res.get("executed"):
        out["cookie"] = chal["flag"]
    return jsonify(out)


@app.post("/api/reset")
def reset():
    if g.sess:
        db().execute("DELETE FROM solves WHERE sess=?", (g.sess,))
        db().commit()
    return jsonify(ok=True)


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=8000, threaded=True)
