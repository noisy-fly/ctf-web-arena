#!/bin/bash
# CTF Web Arena 单容器多进程编排（ADR-1）
# 顺序：mariadb(初始化+守护) → php-fpm → gunicorn(flask) → nginx(前台)
# 任何关键进程死亡 → 守护循环退出 → docker restart 自愈
set -u

log() { echo "[entry $(date +%H:%M:%S)] $*"; }

cleanup() {
    log "stopping all services..."
    pkill -f "mariadbd" 2>/dev/null
    pkill -f "php-fpm" 2>/dev/null
    pkill -f "gunicorn" 2>/dev/null
    pkill -x nginx 2>/dev/null
    exit 0
}
trap cleanup TERM INT

# ---------- 安全隔离：封禁云 metadata（SSRF 题防凭据窃取） ----------
# 100.100.100.200 = 阿里云 metadata（含 RAM STS 凭据）；169.254.0.0/16 = 通用 metadata/链路本地。
# 仅作用于本容器网络栈；题目只用 127.0.0.1 内网目标，不受影响。
if iptables -A OUTPUT -d 100.100.100.200/32 -j REJECT 2>/dev/null     && iptables -A OUTPUT -d 169.254.0.0/16 -j REJECT 2>/dev/null; then
    log "metadata egress blocked (100.100.100.200, 169.254.0.0/16)"
else
    log "WARN: iptables unavailable, metadata egress NOT blocked"
fi

mkdir -p /run/php /var/log/nginx
chown www-data:www-data /run/php

# ---------- MariaDB ----------
if [ ! -d /var/lib/mysql/mysql ]; then
    log "initializing mariadb datadir..."
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql --skip-test-db >/dev/null 2>&1
fi
chown -R mysql:mysql /var/lib/mysql
log "starting mariadbd..."
# 注：--skip-syslog 是 mariadbd-safe 包装器的选项，mariadbd 本体不认识；容器内 stderr 即日志
mariadbd --user=mysql &
for i in $(seq 1 120); do
    mariadb-admin --silent ping >/dev/null 2>&1 && break
    sleep 0.5
done
if ! mariadb-admin --silent ping >/dev/null 2>&1; then
    log "FATAL: mariadb not reachable"; exit 1
fi
if ! mariadb -e "USE sqlilab1" >/dev/null 2>&1; then
    log "initializing challenge databases..."
    mariadb < /srv/init/ch-init.sql && log "challenge db ready"
else
    log "challenge db already initialized"
fi

# ---------- PHP-FPM ----------
# read3 的 flag 在进程环境变量里（/proc/self/environ 读取），从 meta.json 注入；
# www.conf 已设 clear_env=no，worker 才能看到
FLAG="$(python3 -c 'import json;print(json.load(open("/var/www/challenges/read/read3/meta.json"))["flag"])' 2>/dev/null || true)"
export FLAG
mkdir -p /var/lib/php/sessions && chown -R www-data:www-data /var/lib/php/sessions
log "starting php-fpm..."
php-fpm8.2 -D

# ---------- Flask API ----------
log "starting gunicorn (flask api)..."
cd /srv/api
gunicorn --workers 2 --threads 2 --bind 127.0.0.1:8000 --timeout 30 \
         --access-logfile - --error-logfile - app:app &

# ---------- ssrf4 迷你 Redis（内网模拟服务，127.0.0.1:6379）----------
log "starting mini-redis (ssrf4)..."
(cd /var/www/challenges/ssrf/ssrf4 && nohup php redis_fake.php >>/var/log/mini-redis.log 2>&1 &)

# ---------- Nginx ----------
log "starting nginx..."
nginx -g 'daemon off;' &

log "ALL SERVICES UP"

# ---------- 守护循环 ----------
while sleep 10; do
    mariadb-admin --silent ping >/dev/null 2>&1 || { log "mariadb died"; exit 1; }
    curl -fsS http://127.0.0.1:8000/api/health >/dev/null || { log "api died"; exit 2; }
    pgrep -x nginx >/dev/null || { log "nginx died"; exit 3; }
    pgrep -f php-fpm >/dev/null || { log "php-fpm died"; exit 4; }
    pgrep -f redis_fake.php >/dev/null || { \
        log "mini-redis died, restarting"; \
        (cd /var/www/challenges/ssrf/ssrf4 && nohup php redis_fake.php >>/var/log/mini-redis.log 2>&1 &); }
done
