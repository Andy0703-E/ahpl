#!/bin/bash
# AHPL Boot Script - Termux:Boot
# Letakkan di: ~/.termux/boot/
# Akan otomatis start PHP-FPM dan Nginx saat HP dinyalakan

export HOME=/data/data/com.termux/files/home
AHPL_HOME="$HOME/ahpl-server"
mkdir -p "$AHPL_HOME/database" "$AHPL_HOME/logs"

# Kill existing instances (biar clean start)
pkill php-fpm 2>/dev/null
pkill nginx 2>/dev/null
pkill cloudflared 2>/dev/null
sleep 1

# Start PHP-FPM
php-fpm -R && echo "[AHPL] PHP-FPM started" || echo "[AHPL] PHP-FPM FAILED"

# Start Nginx
nginx && echo "[AHPL] Nginx started" || echo "[AHPL] Nginx FAILED"

echo "[AHPL] All services started"
