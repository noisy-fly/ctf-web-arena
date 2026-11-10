CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    username TEXT UNIQUE,
    password TEXT,
    intro TEXT
);
INSERT OR IGNORE INTO users(id, username, password, intro) VALUES
 (1, 'captain', '1937', '运维值班长'),
 (2, 'intern',  'intern888', '实习同学');

CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ts TEXT,
    ok INTEGER
);
