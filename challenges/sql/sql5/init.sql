-- sql5 时间盲注 初始化数据（SQLite，可安全重放）
CREATE TABLE IF NOT EXISTS cards (
    id      INTEGER PRIMARY KEY,
    cardno  TEXT,
    balance REAL
);
CREATE TABLE IF NOT EXISTS flag_secret (
    id   INTEGER PRIMARY KEY,
    flag TEXT
);
INSERT OR IGNORE INTO cards(id, cardno, balance) VALUES
 (1, 'WISH-2024-0001', 100.00),
 (2, 'WISH-2024-0002', 0.50),
 (3, 'WISH-2024-0003', 880.00);
INSERT OR IGNORE INTO flag_secret(id, flag) VALUES (1, 'flag{sql5_159dae0d}');
