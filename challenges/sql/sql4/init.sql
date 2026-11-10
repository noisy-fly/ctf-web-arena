-- sql4 堆叠注入 初始化数据（SQLite，可安全重放）
CREATE TABLE IF NOT EXISTS goods (
    id    INTEGER PRIMARY KEY,
    title TEXT,
    price REAL
);
CREATE TABLE IF NOT EXISTS secret_flag_storage (
    id   INTEGER PRIMARY KEY,
    flag TEXT
);
INSERT OR IGNORE INTO goods(id, title, price) VALUES
 (1, 'Web安全入门指南', 59.00),
 (2, 'SQL注入攻防修炼', 79.50),
 (3, '从零开始的CTF生活', 45.00);
INSERT OR IGNORE INTO secret_flag_storage(id, flag) VALUES (1, 'flag{sql4_14263169}');
