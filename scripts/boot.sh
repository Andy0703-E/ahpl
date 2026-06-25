#!/bin/bash
# AHPL Boot Script - Termux:Boot
# Letakkan di: ~/.termux/boot/
# Akan otomatis start PHP-FPM dan Nginx saat HP dinyalakan

export HOME=/data/data/com.termux/files/home
AHPL_HOME="$HOME/ahpl-server"

# Kill existing instances (biar clean start)
pkill php-fpm 2>/dev/null
pkill nginx 2>/dev/null
sleep 1

# Start PHP-FPM
php-fpm -R && echo "[AHPL] PHP-FPM started" || echo "[AHPL] PHP-FPM FAILED"

# Start Nginx
nginx && echo "[AHPL] Nginx started" || echo "[AHPL] Nginx FAILED"

# Start Cloudflare Tunnel (opsional)
# Uncomment baris di bawah jika ingin tunnel otomatis start:
# if ! pgrep -x cloudflared > /dev/null 2>&1; then
#     nohup cloudflared tunnel --url http://localhost:8080 > "$AHPL_HOME/storage/server/logs/tunnel.log" 2>&1 &
#     echo "[AHPL] Cloudflare Tunnel started"
# fi

echo "[AHPL] All services started"
