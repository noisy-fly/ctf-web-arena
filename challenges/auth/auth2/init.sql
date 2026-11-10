CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    username TEXT UNIQUE,
    password TEXT
);
INSERT OR IGNORE INTO users(id, username, password) VALUES
 (1, 'guest',         'guest123'),
 (2, 'administrator', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855');
