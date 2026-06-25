#!/bin/bash
# AHPL Boot Script - Termux:Boot
# Letakkan di: ~/.termux/boot/
# Akan otomatis start PHP-FPM, Nginx, dan Cloudflare Tunnel saat HP dinyalakan

export HOME=/data/data/com.termux/files/home
AHPL_HOME="$HOME/ahpl-server"
TUNNEL_LOG=$(php -r 'echo sys_get_temp_dir() . "/cloudflared.log";' 2>/dev/null || echo "/data/data/com.termux/files/usr/tmp/cloudflared.log")
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

# Start Cloudflare Tunnel
nohup cloudflared tunnel --url http://localhost:8080 > "$TUNNEL_LOG" 2>&1 &
echo "[AHPL] Cloudflare Tunnel started"

# Tunggu tunnel URL muncul di log (max 10 detik)
for i in $(seq 1 10); do
    sleep 1
    URL=$(grep -oP 'https://[a-zA-Z0-9-]+\.trycloudflare\.com' "$TUNNEL_LOG" 2>/dev/null | tail -1)
    if [ -n "$URL" ]; then
        # Save ke database via sqlite3
        sqlite3 "$AHPL_HOME/database/ahpl.db" \
            "INSERT OR REPLACE INTO settings (key, value) VALUES ('cloudflare_url', '$URL');" 2>/dev/null
        echo "[AHPL] Tunnel URL: $URL"
        break
    fi
done

echo "[AHPL] All services started"
