#!/bin/bash
# AHPL Boot Script - Termux:Boot
# Letakkan di: ~/.termux/boot/

export HOME=/data/data/com.termux/files/home
AHPL_HOME="$HOME/ahpl-server"

# Start PHP-FPM
php-fpm -R

# Start Nginx  
nginx

# Start SSH (opsional)
# sshd

# Start Cloudflare Tunnel (opsional)
# cloudflared tunnel --url http://localhost:8080 > "$AHPL_HOME/logs/tunnel.log" 2>&1 &
