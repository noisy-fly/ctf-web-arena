-- sql9 过滤绕过 初始化数据（SQLite，可安全重放）
CREATE TABLE IF NOT EXISTS items (
    id   INTEGER PRIMARY KEY,
    name TEXT,
    info TEXT
);
CREATE TABLE IF NOT EXISTS s3cret_vault (
    id   INTEGER PRIMARY KEY,
    flag TEXT
);
INSERT OR IGNORE INTO items(id, name, info) VALUES
 (1, 'Web安全入门指南', '第3版'),
 (2, 'CTF从零到一',     '畅销'),
 (3, '密码学基础',       '含习题');
INSERT OR IGNORE INTO s3cret_vault(id, flag) VALUES (1, 'flag{sql9_98da9c42}');
