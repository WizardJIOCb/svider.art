#!/usr/bin/env bash
set -euo pipefail

DOMAIN="sweder.ru"
WWW_DOMAIN="www.sweder.ru"
WEBROOT="/var/www/svider.art"
CONF="/etc/nginx/sites-available/svider.art"
ENABLED_CONF="/etc/nginx/sites-enabled/svider.art"
EMAIL="admin@sweder.ru"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root."
  exit 1
fi

if [[ ! -d "$WEBROOT" ]]; then
  echo "Webroot not found: $WEBROOT"
  exit 1
fi

backup_path="${CONF}.bak.$(date +%Y%m%d%H%M%S)"
cp "$CONF" "$backup_path"
echo "Backup created: $backup_path"

echo "Requesting/renewing certificate for $DOMAIN and $WWW_DOMAIN ..."
certbot certonly \
  --webroot -w "$WEBROOT" \
  -d "$DOMAIN" -d "$WWW_DOMAIN" \
  --non-interactive \
  --agree-tos \
  --email "$EMAIL" \
  --keep-until-expiring

cat > "$CONF" <<'NGINX'
server {
    listen 80;
    server_name www.svider.art;
    return 301 https://svider.art$request_uri;
}

server {
    listen 80;
    server_name svider.art;
    return 301 https://svider.art$request_uri;
}

server {
    listen 80;
    server_name sweder.ru www.sweder.ru;
    return 301 https://sweder.ru$request_uri;
}

server {
    listen 443 ssl;
    server_name www.svider.art;

    ssl_certificate /etc/letsencrypt/live/svider.art/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/svider.art/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    return 301 https://svider.art$request_uri;
}

server {
    listen 443 ssl;
    server_name svider.art;

    root /var/www/svider.art;
    index index.html;

    access_log /var/log/nginx/svider.art.access.log;
    error_log /var/log/nginx/svider.art.error.log;

    ssl_certificate /etc/letsencrypt/live/svider.art/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/svider.art/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location /admin/ {
        auth_basic "svider.art admin";
        auth_basic_user_file /etc/nginx/.htpasswd-svider-admin;
        index index.php;
        try_files $uri /admin/index.php?$query_string;
    }

    location ~ ^/admin/.+\.php$ {
        auth_basic "svider.art admin";
        auth_basic_user_file /etc/nginx/.htpasswd-svider-admin;
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120s;
    }

    location = /request.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/svider.art/request.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120s;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /assets/ {
        try_files $uri =404;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location /content/ {
        try_files $uri =404;
        expires 1h;
        add_header Cache-Control "public";
    }
}

server {
    listen 443 ssl;
    server_name www.sweder.ru;

    ssl_certificate /etc/letsencrypt/live/sweder.ru/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sweder.ru/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    return 301 https://sweder.ru$request_uri;
}

server {
    listen 443 ssl;
    server_name sweder.ru;

    root /var/www/svider.art;
    index index.html;

    access_log /var/log/nginx/sweder.ru.access.log;
    error_log /var/log/nginx/sweder.ru.error.log;

    ssl_certificate /etc/letsencrypt/live/sweder.ru/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sweder.ru/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location /admin/ {
        return 301 https://svider.art$request_uri;
    }

    location = /request.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/svider.art/request.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120s;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /assets/ {
        try_files $uri =404;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location /content/ {
        try_files $uri =404;
        expires 1h;
        add_header Cache-Control "public";
    }
}
NGINX

cp "$CONF" "$ENABLED_CONF"
nginx -t
systemctl reload nginx
systemctl is-active nginx >/dev/null

echo "Done. HTTPS for $DOMAIN is active."
