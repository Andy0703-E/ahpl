#!/bin/bash
# AHPL - Android Hosting Panel Lite
# Setup Script for Termux

set -e
echo "========================================="
echo "  AHPL - Android Hosting Panel Lite"
echo "========================================="
echo ""

# === PAKET WAJIB ===
echo "[1/8] Update sistem..."
pkg update -y && pkg upgrade -y

echo "[2/8] Install Git..."
pkg install git -y

echo "[3/8] Install PHP + FPM..."
pkg install php php-fpm php-sqlite3 -y

echo "[4/8] Install Nginx..."
pkg install nginx -y

echo "[5/8] Install SQLite..."
pkg install sqlite -y

echo "[6/8] Install Cloudflared..."
pkg install cloudflared -y

echo "[7/8] Install tools (zip, unzip, curl, wget)..."
pkg install zip unzip curl wget -y

# === PAKET DISARANKAN ===
echo "[8/8] Install OpenSSH (opsional)..."
pkg install openssh -y || true

# === DIRECTORIES ===
echo ""
echo "Membuat direktori..."
mkdir -p /storage/server/{panel,websites,uploads,backups,database,logs}
termux-setup-storage 2>/dev/null || true

# === COPY FILES ===
echo "Menyalin file..."
DIR="$(cd "$(dirname "$0")" && pwd)"
cp -r "$DIR"/../panel /storage/server/
cp -r "$DIR"/../config /storage/server/
cp -r "$DIR"/../includes /storage/server/
cp "$DIR"/../index.php /storage/server/

# === CLOUDFLARED ===
if ! command -v cloudflared &> /dev/null; then
    echo "Download cloudflared..."
    curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-arm -o "$PREFIX/bin/cloudflared"
    chmod +x "$PREFIX/bin/cloudflared"
fi

# === START SERVICES ===
echo "Menjalankan services..."
php-fpm 2>/dev/null || true
nginx 2>/dev/null || true

# === DONE ===
echo ""
echo "========================================="
echo "  Setup Selesai!"
echo "========================================="
echo ""
echo "  Panel  : http://localhost:8080/"
echo "  Login  : admin / admin"
echo ""
echo "  Tunnel : cloudflared tunnel --url http://localhost:8080"
echo "  SSH    : sshd (jalankan manual)"
echo ""
echo "  Auto-start on boot:"
echo "    cp scripts/boot.sh ~/.termux/boot/"
echo ""
