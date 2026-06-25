#!/bin/bash
# AHPL - Fix PHP upload limits untuk Termux
# Jalankan: bash scripts/fix-php-upload.sh

PHP_INI=$(php -i | grep "Loaded Configuration File" | head -1 | awk '{print $NF}')

if [ -z "$PHP_INI" ] || [ "$PHP_INI" = "(none)" ]; then
    echo "Tidak dapat menemukan php.ini"
    echo "Coba cari manual: find /data/data/com.termux -name php.ini"
    exit 1
fi

echo "php.ini ditemukan di: $PHP_INI"

# Backup
cp "$PHP_INI" "$PHP_INI.bak.$(date +%s)"
echo "Backup dibuat"

# Update values
sed -i 's/^post_max_size.*/post_max_size = 105M/' "$PHP_INI" 2>/dev/null
sed -i 's/^upload_max_filesize.*/upload_max_filesize = 100M/' "$PHP_INI" 2>/dev/null
sed -i 's/^max_execution_time.*/max_execution_time = 120/' "$PHP_INI" 2>/dev/null

# Add if not exists
grep -q "^post_max_size" "$PHP_INI" || echo "post_max_size = 105M" >> "$PHP_INI"
grep -q "^upload_max_filesize" "$PHP_INI" || echo "upload_max_filesize = 100M" >> "$PHP_INI"
grep -q "^max_execution_time" "$PHP_INI" || echo "max_execution_time = 120" >> "$PHP_INI"

echo "Upload limits updated!"

# Restart PHP-FPM
echo "Restarting PHP-FPM..."
pkill php-fpm 2>/dev/null
sleep 1
php-fpm -R
echo "PHP-FPM restarted!"
