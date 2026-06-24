#!/bin/bash
# AHPL Boot Script - Termux:Boot
# Letakkan di: ~/.termux/boot/

# Start PHP-FPM
php-fpm

# Start Nginx
nginx

# Start SSH (opsional, uncomment jika perlu)
# sshd

# Start Cloudflare Tunnel (opsional, uncomment jika perlu)
# cloudflared tunnel --url http://localhost:8080 > /storage/server/logs/tunnel.log 2>&1 &
