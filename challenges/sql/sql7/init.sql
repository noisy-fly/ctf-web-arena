-- sql7 二次注入 初始化数据（SQLite，可安全重放）
CREATE TABLE IF NOT EXISTS users (
    id       INTEGER PRIMARY KEY,
    username TEXT UNIQUE,
    password TEXT,
    note     TEXT DEFAULT ''
);
INSERT OR IGNORE INTO users(id, username, password, note) VALUES
 (1, 'admin', '$2y$12$uQBMTslRxMZJe4rP1Me8EOHVoo1yu7bXqmz0DroEEgmztzXOZFjIS', 'flag{sql7_37ba4d81}');
-- admin 真实密码（writeup 保密需要）：DeepBlue_Admin_2024
