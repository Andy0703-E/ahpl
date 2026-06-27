#!/bin/bash
# AHPL - MariaDB Setup for Termux
# Jalankan setelah setup-termux.sh

set -e
echo "========================================="
echo "  AHPL - MariaDB Setup"
echo "========================================="
echo ""

echo "[1/4] Install MariaDB..."
pkg install mariadb -y

echo "[2/4] Inisialisasi database directory..."
mysql_install_db --datadir=$PREFIX/var/lib/mysql 2>/dev/null || true

echo "[3/4] Setup database & user..."
# Hapus socket/lock dari run sebelumnya jika ada
rm -f $PREFIX/var/run/mysqld.sock $PREFIX/var/run/mysqld.sock.lock 2>/dev/null || true

# Jalankan MariaDB di background
mysqld_safe --skip-grant-tables &
SOCKET="$PREFIX/var/run/mysqld.sock"
echo "  Menunggu socket..."
for i in $(seq 1 15); do
    [ -S "$SOCKET" ] && break
    sleep 1
done
if [ ! -S "$SOCKET" ]; then
    echo "  ERROR: Socket tidak muncul setelah 15 detik. Cek log: $PREFIX/var/lib/mysql/localhost.err"
    exit 1
fi

# Buat database ahpl dan user
mysql -u root << SQL
FLUSH PRIVILEGES;
CREATE DATABASE IF NOT EXISTS ahpl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ahpl'@'localhost' IDENTIFIED BY 'ahpl123';
GRANT ALL PRIVILEGES ON ahpl.* TO 'ahpl'@'localhost';
FLUSH PRIVILEGES;
SQL

# Matikan server
mysqladmin -u root shutdown 2>/dev/null || true

echo "[4/4] Konfigurasi selesai."
echo ""
echo "========================================="
echo "  MariaDB siap digunakan!"
echo "========================================="
echo ""
echo "  Host     : 127.0.0.1"
echo "  Port     : 3306"
echo "  Database : ahpl"
echo "  User     : ahpl"
echo "  Password : ahpl123"
echo ""
echo "  Menjalankan MariaDB:"
echo "    mysqld_safe &"
echo ""
echo "  Atau tambahkan ke boot.sh:"
echo '    echo "mysqld_safe &" >> ~/.termux/boot/boot.sh'
echo ""
