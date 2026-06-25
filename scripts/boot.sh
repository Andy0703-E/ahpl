#!/bin/bash
# AHPL Boot Script - Termux:Boot
# Letakkan di: ~/.termux/boot/
# Akan otomatis start PHP-FPM dan Nginx saat HP dinyalakan

export HOME=/data/data/com.termux/files/home
AHPL_HOME="$HOME/ahpl-server"

# Start PHP-FPM
php-fpm -R
echo "[AHPL] PHP-FPM started"

# Start Nginx
nginx
echo "[AHPL] Nginx started"

# Start Cloudflare Tunnel (opsional)
# Uncomment baris di bawah jika ingin tunnel otomatis start:
# nohup cloudflared tunnel --url http://localhost:8080 > "$AHPL_HOME/storage/server/logs/tunnel.log" 2>&1 &

echo "[AHPL] All services started"
