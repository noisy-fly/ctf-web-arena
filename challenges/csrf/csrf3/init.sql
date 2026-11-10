CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    protection TEXT DEFAULT 'high',
    note TEXT DEFAULT '',
    token TEXT UNIQUE
);
INSERT OR IGNORE INTO users(username, protection, note, token) VALUES
 ('emma', 'high', 'flag{csrf3_0ccc88a2}', 'emma-victim-token-q7w9e2r4');

CREATE TABLE IF NOT EXISTS attacks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    html TEXT,
    ts TEXT
);
