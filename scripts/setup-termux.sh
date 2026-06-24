#!/bin/bash
# AHPL - Android Hosting Panel Lite
# Setup Script for Termux

set -e
echo "========================================="
echo "  AHPL - Android Hosting Panel Lite"
echo "========================================="
echo ""

AHPL_HOME="$HOME/ahpl-server"
echo "Target install: $AHPL_HOME"

# === PAKET WAJIB ===
echo "[1/8] Update sistem..."
pkg update -y && pkg upgrade -y

echo "[2/8] Install Git..."
pkg install git -y

echo "[3/8] Install PHP + FPM..."
pkg install php php-fpm -y

echo "[4/8] Install Nginx..."
pkg install nginx -y

echo "[5/8] Install SQLite..."
pkg install sqlite -y

echo "[6/8] Install Cloudflared..."
pkg install cloudflared -y

echo "[7/8] Install tools (zip, unzip, curl, wget)..."
pkg install zip unzip curl wget -y

echo "[8/8] Install OpenSSH (opsional)..."
pkg install openssh -y || true

# === DIRECTORIES ===
echo ""
echo "Membuat direktori di $AHPL_HOME..."
mkdir -p "$AHPL_HOME"/{panel,websites,uploads,backups,database,logs}

# === COPY FILES ===
echo "Menyalin file..."
DIR="$(cd "$(dirname "$0")" && pwd)"
cp -r "$DIR"/../panel/* "$AHPL_HOME"/panel/
cp -r "$DIR"/../config "$AHPL_HOME"/
cp "$DIR"/../index.php "$AHPL_HOME"/

# === CREATE LOCAL CONFIG FOR TERMUX ===
echo "Membuat konfigurasi lokal..."
cat > "$AHPL_HOME"/config/local.php << 'EOF'
<?php
// Override paths untuk Termux
$home = getenv('HOME') ?: '/data/data/com.termux/files/home';
$base = $home . '/ahpl-server';
define('AHPL_HOME', $base);
define('SERVER_PATH', $base);
define('PANEL_PATH', $base . '/panel');
define('WEBSITES_PATH', $base . '/websites');
define('UPLOADS_PATH', $base . '/uploads');
define('BACKUPS_PATH', $base . '/backups');
define('DATABASE_PATH', $base . '/database');
define('LOGS_PATH', $base . '/logs');
define('DB_FILE', DATABASE_PATH . '/ahpl.db');
EOF

# === SETUP NGINX ===
echo "Setup Nginx..."
NGINX_CONF="$PREFIX/etc/nginx/conf.d/ahpl.conf"
cat > "$NGINX_CONF" << 'EOF'
server {
    listen 8080;
    server_name localhost;
    root __AHPL_HOME__/panel;
    index index.php index.html;
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. { deny all; }
    location ~* \.(db|sqlite|log)$ { deny all; }
}
EOF

sed "s|__AHPL_HOME__|$AHPL_HOME|g" "$NGINX_CONF" > "$NGINX_CONF.tmp"
mv "$NGINX_CONF.tmp" "$NGINX_CONF"

# === SETUP PHP-FPM ===
PHP_FPM_CONF="$PREFIX/etc/php-fpm.d/www.conf"
if [ -f "$PHP_FPM_CONF" ]; then
    sed -i 's/listen = \/data\/data\/com.termux\/files\/usr\/var\/run\/php-fpm.sock/listen = 127.0.0.1:9000/' "$PHP_FPM_CONF"
fi

# === CLOUDFLARED ===
if ! command -v cloudflared &> /dev/null; then
    curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-arm -o "$PREFIX/bin/cloudflared"
    chmod +x "$PREFIX/bin/cloudflared"
fi

# === START SERVICES ===
echo ""
echo "Menjalankan services..."
pkill php-fpm 2>/dev/null || true
php-fpm -R 2>/dev/null || true
nginx -s stop 2>/dev/null || true
nginx 2>/dev/null || true

# === DONE ===
echo ""
echo "========================================="
echo "  Setup Selesai!"
echo "========================================="
echo ""
echo "  Panel  : http://localhost:8080/"
echo "  Login  : admin / admin"
echo "  Folder : $AHPL_HOME"
echo ""
echo "  Tunnel : cloudflared tunnel --url http://localhost:8080"
echo "  SSH    : sshd (jalankan manual)"
echo ""
echo "  Auto-start on boot:"
echo "    cp scripts/boot.sh ~/.termux/boot/"
echo ""
