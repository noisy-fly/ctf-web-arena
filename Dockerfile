FROM debian:bookworm-slim
ENV DEBIAN_FRONTEND=noninteractive TZ=Asia/Shanghai
RUN apt-get update && apt-get install -y --no-install-recommends nginx mariadb-server mariadb-client php-fpm php-cli php-mysql php-mbstring php-sqlite3 php-curl php-xml php-gd curl ca-certificates python3 python3-pip git tini && rm -rf /var/lib/apt/lists/*
WORKDIR /srv/api
COPY api/requirements.txt ./
RUN pip3 install --no-cache-dir --break-system-packages -r requirements.txt
COPY api/ /srv/api/
COPY portal/ /var/www/portal/
COPY challenges/ /var/www/challenges/
COPY artifacts/ /var/www/artifacts/
COPY recon3-git.tar /tmp/recon3-git.tar
RUN tar -xf /tmp/recon3-git.tar -C /var/www/challenges/recon/recon3 && rm /tmp/recon3-git.tar && for f in /var/www/challenges/*/*/deploy/flag*; do [ ! -f "$f" ] || cp "$f" /; done && chown -R www-data:www-data /var/www/challenges && rm -f /etc/nginx/sites-enabled/default && ln -s /etc/nginx/sites-available/ctf /etc/nginx/sites-enabled/ctf
COPY nginx.conf /etc/nginx/sites-available/ctf
COPY php-www.conf /etc/php/8.2/fpm/pool.d/www.conf
COPY php.ini /etc/php/8.2/fpm/conf.d/99-ctf.ini
COPY mysql.cnf /etc/mysql/mariadb.conf.d/99-ctf.cnf
COPY ch-init.sql /srv/init/ch-init.sql
COPY entry.sh /entry.sh
RUN chmod +x /entry.sh && mkdir -p /srv/api/data /run/php /var/log/nginx && chown -R www-data:www-data /run/php
EXPOSE 80 8090 8307
ENTRYPOINT ["/usr/bin/tini", "--", "/entry.sh"]
