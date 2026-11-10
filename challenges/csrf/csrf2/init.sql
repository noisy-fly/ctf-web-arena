CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    vip INTEGER DEFAULT 0,
    token TEXT UNIQUE
);
INSERT OR IGNORE INTO users(username, vip, token) VALUES
 ('dave', 1, 'dave-victim-token-m3n8r6t2');

CREATE TABLE IF NOT EXISTS attacks (
    name TEXT PRIMARY KEY,
    html TEXT,
    ts TEXT
);
