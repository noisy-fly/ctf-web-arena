-- sql8 ORDER BY 注入 初始化数据（SQLite，可安全重放）
CREATE TABLE IF NOT EXISTS goods (
    id    INTEGER PRIMARY KEY,
    title TEXT,
    price REAL
);
CREATE TABLE IF NOT EXISTS flag_secret (
    id   INTEGER PRIMARY KEY,
    flag TEXT
);
INSERT OR IGNORE INTO goods(id, title, price) VALUES
 (1, 'Web安全入门指南', 59.00),
 (2, 'CTF从零到一',     45.00),
 (3, '密码学基础',       39.00),
 (4, '逆向工程实录',     69.00),
 (5, '堆栈攻防艺术',     89.00),
 (6, '移动安全实践',     55.00);
INSERT OR IGNORE INTO flag_secret(id, flag) VALUES (1, 'flag{sql8_9e497864}');
