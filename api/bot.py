# -*- coding: utf-8 -*-
"""XSS 虚拟 Victim（教学模拟，ADR-6）。

内存预算不允许 headless Chromium，因此用 Python 解析 HTML 判定“用户输入是否构成了
会被浏览器执行的解释器向量”，并按题目页面里真实的过滤逻辑做镜像模拟：
  - <script> 元素的 JS 文本
  - on* 事件属性（onerror/onload/...）
  - javascript: 伪协议 URL
三种向量与真实浏览器执行语义一致；页面上已明确标注“教学模拟”。
"""
import re
from html.parser import HTMLParser
from urllib.parse import parse_qs, urlsplit

import requests

URL_ATTRS = {"href", "src", "action", "formaction", "data", "xlink:href", "poster", "background"}


class _Scanner(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.vectors = []
        self._in_script = False
        self._script = []

    def _attrs(self, tag, attrs):
        for k, v in attrs:
            v = v or ""
            if k.startswith("on") and v.strip():
                self.vectors.append({"kind": "事件属性", "desc": f"<{tag} {k}=...>", "code": v.strip()})
            elif k in URL_ATTRS and v.strip().lower().startswith("javascript:"):
                self.vectors.append({"kind": "javascript: 伪协议", "desc": f"<{tag} {k}=...>", "code": v.strip()})

    def handle_starttag(self, tag, attrs):
        if tag == "script":
            self._in_script = True
            self._script = []
        self._attrs(tag, attrs)

    def handle_startendtag(self, tag, attrs):
        self._attrs(tag, attrs)

    def handle_endtag(self, tag):
        if tag == "script" and self._in_script:
            self._in_script = False
            text = "".join(self._script).strip()
            if text:
                self.vectors.append({"kind": "<script> 标签", "desc": "<script>…</script>", "code": text})

    def handle_data(self, data):
        if self._in_script:
            self._script.append(data)


def scan(html):
    p = _Scanner()
    try:
        p.feed(html)
        p.close()
    except Exception:  # noqa: BLE001
        pass
    return p.vectors


def from_input(vec, user_input):
    code = vec["code"].strip()
    u = (user_input or "").strip()
    return bool(u) and (code in u or u in code)


def _param(url, name):
    q = parse_qs(urlsplit(url).query)
    return (q.get(name) or [""])[0]


def visit(chal_id, url, token):
    if chal_id == "xss1":
        return _xss1(url, token)
    if chal_id == "xss2":
        return _xss2(token)
    if chal_id == "xss3":
        return _xss3(url)
    return {"executed": False, "reason": "未知关卡"}


def _xss1(url, token):
    q = _param(url, "q")
    if not q:
        return {"executed": False, "reason": "链接里没有 q 参数。先在搜索框里输入 payload 并提交。"}
    # 真实请求题目页面（带会话 cookie 以通过 nginx 门禁）
    html = requests.get("http://127.0.0.1" + url,
                        headers={"Cookie": "ctf_sess=" + token}, timeout=6).text
    hits = [v for v in scan(html) if from_input(v, q)]
    if hits:
        return {"executed": True, "vector": hits[0]["desc"]}
    return {
        "executed": False,
        "reason": "页面上没有发现可执行的向量。检查 payload 是否出现在回显里、"
                  "是否被转义成了纯文本。可尝试 <img src=x onerror=alert(1)> 或 <script>alert(1)</script>",
    }


def _xss2(token):
    import pymysql

    conn = pymysql.connect(host="127.0.0.1", user="web", password="webpass_2026", database="xssboard")
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT content FROM msgs WHERE poster=%s ORDER BY id DESC LIMIT 20", (token,))
            rows = cur.fetchall()
    finally:
        conn.close()
    if not rows:
        return {"executed": False, "reason": "你还没有发布任何留言。先发一条带 payload 的留言。"}
    for (content,) in rows:
        # 留言板模板把 content 原样塞进 <div class="msg">…</div>（与 index.php 一致）
        hits = scan('<div class="msg">' + content + "</div>")
        if hits:
            return {"executed": True, "vector": hits[0]["desc"]}
    return {
        "executed": False,
        "reason": "你的留言都只是纯文本。试试发一条 <img src=x onerror=alert(1)> 留言。",
    }


def _xss3(url):
    name = _param(url, "name")
    if not name:
        return {"executed": False, "reason": "链接里没有 name 参数。"}
    # 镜像题目页面的过滤：删除 <script ...> 开标签（正则与页面 JS 保持一致）
    filtered = re.sub(r"<script[\s\S]*?>", "", name, flags=re.I)
    # 镜像 sink：el.innerHTML = '你好, ' + name + '!'
    html = '<div id="greet">你好, ' + filtered + "!</div>"
    hits = scan(html)
    if hits:
        return {"executed": True, "vector": hits[0]["desc"]}
    if "<script" in name.lower():
        return {
            "executed": False,
            "reason": "页面的过滤删掉了 <script> 开标签；而且 innerHTML 插入的 <script> 本来也不会执行。"
                      "换事件属性试试：<img src=x onerror=alert(1)>",
        }
    return {
        "executed": False,
        "reason": "没有可执行的向量。提示：目标是 innerHTML 这个 sink，事件属性（onerror/onload）可行。",
    }
