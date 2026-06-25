# AHPL - Android Hosting Panel Lite

> Panel hosting ringan yang berjalan di Android menggunakan Termux. Kelola website langsung dari HP Anda tanpa VPS.

![PHP](https://img.shields.io/badge/PHP-8-777BB4?style=flat&logo=php)
![SQLite](https://img.shields.io/badge/SQLite-3-003B57?style=flat&logo=sqlite)
![Nginx](https://img.shields.io/badge/Nginx-1.25-009639?style=flat&logo=nginx)
![Android](https://img.shields.io/badge/Android-8+-3DDC84?style=flat&logo=android)
![Version](https://img.shields.io/badge/Version-1.1.0-blue)
![License](https://img.shields.io/badge/License-MIT-blue)

---

## Daftar Isi

- [Fitur](#fitur)
- [Spesifikasi Minimum](#spesifikasi-minimum)
- [Screenshot](#screenshot)
- [Persiapan Android](#persiapan-android)
- [Instalasi](#instalasi)
- [Konfigurasi](#konfigurasi)
- [Cara Pakai](#cara-pakai)
- [Keamanan](#keamanan)
- [Testing](#testing)
- [Struktur Folder](#struktur-folder)
- [API Reference](#api-reference)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Roadmap](#roadmap)
- [License](#license)

---

## Fitur

| Fitur | Deskripsi |
|-------|-----------|
| **Login** | Autentikasi admin dengan CSRF protection + rate limiting |
| **Dashboard** | Status server, website, storage, RAM, activity log (AJAX refresh) |
| **Website Manager** | Buat dan hapus website, dukung AI generate (Cerebras Z-AI GLM-4.7) |
| **File Manager** | Upload (drag-drop, multi-file), rename, delete, download, buat folder |
| **Code Editor** | Edit file HTML/CSS/JS/PHP langsung di browser (CodeMirror) |
| **Cloudflare Tunnel** | Akses panel dari mana saja via internet (via terminal) |
| **Force Password Change** | Paksa ganti password default admin saat login pertama |
| **AJAX Dashboard** | Auto-refresh activity log tanpa reload halaman penuh |
| **Mobile Support** | Responsive sidebar dengan hamburger toggle |

---

## Spesifikasi Minimum

| Komponen | Minimum | Disarankan |
|----------|---------|------------|
| **RAM** | 2 GB | 3 GB+ |
| **Storage** | 2 GB kosong | 5 GB+ |
| **Android** | 8.0 (Oreo) | 10+ |
| **Termux** | Latest | Latest |
| **Koneksi** | WiFi | WiFi atau Data |

**Contoh device yang bisa jalan:**
- Vivo Y91C, Y91i, Y12, Y15
- Samsung A10, A20, A30
- Redmi 7, 8, 9, Note 8, Note 9
- Realme C1, C2, C3, 5, 5i

---

## Screenshot

```
┌─────────────────────────────────┐
│         AHPL - Login            │
│  ┌───────────────────────────┐  │
│  │  Username: admin          │  │
│  │  Password: ●●●●●●●●      │  │
│  │                           │  │
│  │     [ Login ]             │  │
│  └───────────────────────────┘  │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│  AHPL │ Dashboard              │
│────────┼────────────────────────│
│ Home   │ ┌──────┐ ┌──────┐     │
│ Website│ │Online│ │  3   │     │
│ Files  │ │Server│ │Sites │     │
│ Editor │ └──────┘ └──────┘     │
│        │ ┌──────┐ ┌──────┐     │
│        │ │4.2GB │ │   4  │     │
│        │ │Store │ │Files │     │
│────────│ └──────┘ └──────┘     │
└─────────────────────────────────┘
```

---

## Persiapan Android

### 1. Install Termux

Download dan install Termux dari sumber resmi:

> **PENTING:** Jangan install dari Play Store (versinya sudah tua). Gunakan F-Droid atau GitHub.

| Sumber | Link |
|--------|------|
| **F-Droid (Recommended)** | https://f-droid.org/en/packages/com.termux/ |
| **GitHub Release** | https://github.com/termux/termux-app/releases |

### 2. Install Termux:Boot (Opsional)

Agar otomatis start saat HP dinyalakan:

| Sumber | Link |
|--------|------|
| **F-Droid** | https://f-droid.org/en/packages/com.termux.boot/ |

### 3. Buka Termux dan Berikan Izin Storage

```bash
termux-setup-storage
```

Klik **Allow** saat muncul popup izin akses storage.

### 4. Update Termux

```bash
pkg update -y && pkg upgrade -y
```

---

## Instalasi

### Cara 1: Clone dari GitHub

```bash
# Install Git
pkg install git -y

# Clone project
git clone https://github.com/username/ahpl.git

# Masuk ke folder
cd ahpl

# Jalankan setup
bash scripts/setup-termux.sh
```

### Cara 2: Download Manual

```bash
# Install tools download
pkg install curl wget -y

# Download file
curl -L https://github.com/username/ahpl/archive/main.zip -o ahpl.zip

# Extract
unzip ahpl.zip

# Masuk ke folder
cd ahpl-main

# Jalankan setup
bash scripts/setup-termux.sh
```

### Cara 3: Upload dari PC ke HP

1. Copy folder `AHPL` ke HP Anda (via USB, Bluetooth, atau ShareMe)
2. Buka Termux
3. Jalankan:

```bash
# Berikan izin akses storage
termux-setup-storage

# Masuk ke folder project (sesuaikan lokasi)
cd /storage/emulated/0/AHPL

# Jalankan setup
bash scripts/setup-termux.sh
```

---

## Konfigurasi

### 1. Nginx Config

File config sudah otomatis disalin saat setup. Jika ingin edit manual:

```bash
nano /data/data/com.termux/files/usr/etc/nginx/nginx.conf
```

Isi config:

```nginx
server {
    listen 8080;
    server_name localhost;
    
    root /storage/server/panel;
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
```

### 2. PHP Config (Opsional)

```bash
nano /data/data/com.termux/files/usr/etc/php-fpm.d/www.conf
```

Pastikan listen address:

```
listen = 127.0.0.1:9000
```

### 3. Cloudflare Tunnel

```bash
# Install cloudflared (sudah di-setup otomatis)
# atau manual:
pkg install cloudflared -y

# Jalankan tunnel
cloudflared tunnel --url http://localhost:8080
```

Anda akan dapat URL seperti:
```
https://abc123.trycloudflare.com
```

Buka URL tersebut di browser untuk akses panel dari internet.

---

## Cara Pakai

### 1. Start Services

```bash
# Start PHP-FPM
php-fpm

# Start Nginx
nginx
```

### 2. Akses Panel

Buka browser di HP Anda:

```
http://localhost:8080
```

### 3. Login

| Field | Value |
|-------|-------|
| **Username** | `admin` |
| **Password** | `admin` |

> **PENTING:** Anda akan diminta mengganti password saat login pertama!

### 4. Buat Website

1. Buka **Website Manager**
2. Klik **Create Website**
3. Isi nama website dan nama folder
4. Klik **Create**

### 5. Upload File

1. Buka **File Manager**
2. Masuk ke folder website
3. Klik **Upload**
4. Pilih file (drag-drop atau klik)

### 6. Edit File

1. Buka **File Manager**
2. Klik icon edit pada file HTML/CSS/JS/PHP
3. Edit dengan CodeMirror dan klik **Save**

### 7. Akses dari Internet

Jalankan Cloudflare Tunnel di Termux:

```bash
cloudflared tunnel --url http://localhost:8080
```

---

## Keamanan

AHPL v1.1.0 menerapkan beberapa lapisan keamanan:

| Fitur | Keterangan |
|-------|------------|
| **CSRF Protection** | Setiap form dan API call memerlukan CSRF token valid |
| **Rate Limiting** | Maksimal 5 percobaan login dalam 5 menit |
| **Prepared Statements** | Semua query SQL menggunakan parameter binding |
| **SSL Verification** | API call ke Cerebras menggunakan SSL verification |
| **Force Password Change** | Password default (admin/admin) harus diganti saat login pertama |
| **Path Traversal Protection** | Semua file operation divalidasi dengan `realpath()` |
| **ZIP Extraction Safety** | Maksimal 500MB extract size dan depth 5 level |
| **Security Headers** | X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy |
| **Error Logging** | Error dari API dicatat ke file log (bukan di-display) |

---

## Testing

AHPL memiliki test suite sederhana yang bisa dijalankan tanpa dependensi eksternal:

```bash
php tests/run.php
```

Test mencakup:
- **test_helpers.php**: Fungsi utility (sanitize, formatSize, resolvePath, CSRF token, dll)
- **test_database.php**: Operasi database (create table, insert, query)
- **test_security.php**: CSRF token verification, rate limiting

> Test database dan security membutuhkan ekstensi SQLite3 (tersedia di Termux).

---

## Struktur Folder

```
/storage/server/
├── panel/                 # File panel admin
│   ├── login.php
│   ├── dashboard.php
│   ├── websites.php
│   ├── files.php
│   ├── editor.php
│   ├── settings.php
│   ├── api/               # API endpoints
│   ├── assets/            # CSS & JS
│   └── includes/          # Header & footer
├── websites/              # Folder website
│   ├── portfolio/
│   │   └── index.html
│   └── blog/
│       └── index.html
├── uploads/               # File upload
├── backups/               # File backup
├── database/              # SQLite database
│   └── ahpl.db
└── logs/                  # Log files
```

---

## API Reference

### Upload File

```bash
POST /panel/api/upload.php
Content-Type: multipart/form-data

Parameters:
  - file: File yang akan diupload
  - dir: Folder tujuan (default: /)
```

### Delete File

```bash
DELETE /panel/api/file.php?path=/websites/portfolio/old-file.html
Headers: X-CSRF-TOKEN: <token>
```

### Rename File

```bash
PUT /panel/api/file.php
Content-Type: application/json
Headers: X-CSRF-TOKEN: <token>

{
  "action": "rename",
  "path": "/websites/portfolio/old-name.html",
  "newName": "new-name.html"
}
```

### Create Folder

```bash
POST /panel/api/file.php
Content-Type: application/json
Headers: X-CSRF-TOKEN: <token>

{
  "action": "mkdir",
  "dir": "/websites/portfolio",
  "name": "images"
}
```

### Create Website

```bash
POST /panel/api/websites.php
Content-Type: application/json
Headers: X-CSRF-TOKEN: <token>

{
  "action": "create",
  "name": "My Portfolio",
  "folder": "portfolio"
}
```

### Delete Website

```bash
DELETE /panel/api/websites.php?id=1
Headers: X-CSRF-TOKEN: <token>
```

### AI Generate Website

```bash
POST /panel/api/cerebras.php
Content-Type: application/json
Headers: X-CSRF-TOKEN: <token>

{
  "prompt": "Buat website portofolio dengan tema gelam"
}
```

### Settings (API Key)

```bash
POST /panel/api/settings.php
Content-Type: application/json
Headers: X-CSRF-TOKEN: <token>

{
  "action": "save_key",
  "key": "cerebras-api-key-anda"
}
```

### Change Password

```bash
POST /panel/api/settings.php
Content-Type: application/json
Headers: X-CSRF-TOKEN: <token>

{
  "action": "change_password",
  "password": "password-baru"
}
```

### Dashboard (Activity Log)

```bash
GET /panel/api/dashboard.php
```

---

## Troubleshooting

### Nginx tidak mau start

```bash
# Cek error log
cat /data/data/com.termux/files/usr/var/log/nginx/error.log

# Cek port sudah dipakai
netstat -tlnp | grep 8080

# Kill process yang pakai port
fuser -k 8080/tcp
```

### PHP-FPM error

```bash
# Cek error log
cat /data/data/com.termux/files/usr/var/log/php-fpm.log

# Restart php-fpm
pkill php-fpm
php-fpm
```

### Cloudflared tidak bisa connect

```bash
# Cek versi
cloudflared --version

# Update ke versi terbaru
pkg install cloudflared -y

# Test manual
cloudflared tunnel --url http://localhost:8080
```

### Panel tidak bisa diakses

```bash
# Cek nginx running
ps aux | grep nginx

# Cek port
netstat -tlnp | grep 8080

# Restart services
nginx -s stop
pkill php-fpm
php-fpm
nginx
```

### Database corrupt

```bash
# Backup database lama
cp /storage/server/database/ahpl.db /storage/server/database/ahpl.db.bak

# Hapus database (akan dibuat ulang)
rm /storage/server/database/ahpl.db

# Refresh panel di browser
```

### Storage permission denied

```bash
# Berikan izin storage
termux-setup-storage

# Cek izin
ls -la /storage/server/
```

### Upload gagal

```bash
# Cek ukuran file (max 100MB)
ls -lh file.zip

# Cek disk space
df -h
```

---

## FAQ

### Berapa RAM yang dipakai?

AHPL sangat ringan, hanya butuh **100-250 MB RAM** saat aktif.

### Berapa storage yang dibutuhkan?

Minimal **500 MB** untuk panel + beberapa website. Disarankan **2 GB+**.

### Bisa diakses dari HP lain?

Ya, menggunakan Cloudflare Tunnel. Buka URL tunnel di browser HP lain.

### Bisa host domain sendiri?

Ya, Anda bisa arahkan domain sendiri ke Cloudflare Tunnel atau gunakan Nginx langsung.

### Database apa yang dipakai?

SQLite, sangat ringan dan tidak butuh service terpisah.

### Bisa multi-user?

Belum support. Akan ada di versi mendatang.

### Auto-start saat HP nyala?

Install Termux:Boot, lalu copy `boot.sh` ke `~/.termux/boot/`:

```bash
mkdir -p ~/.termux/boot
cp scripts/boot.sh ~/.termux/boot/
chmod +x ~/.termux/boot/boot.sh
```

### Cara stop services?

```bash
nginx -s stop
pkill php-fpm
```

### Cara update?

```bash
cd ahpl
git pull
```

---

## Roadmap

### V1 (Saat Ini)
- [x] Login + CSRF protection
- [x] Dashboard dengan AJAX auto-refresh
- [x] File Manager (upload, rename, delete, download)
- [x] Website Manager (CRUD + AI Generate)
- [x] Code Editor (CodeMirror)
- [x] Security: rate limiting, prepared statements, force password change
- [x] Mobile responsive sidebar
- [x] Test suite

### V2
- [ ] ZIP Deploy (upload ZIP langsung extract ke folder website)
- [ ] Restore Backup
- [ ] Multi-website management
- [ ] Database backup & restore

### V3
- [ ] Theme Customizer
- [ ] PWA Support
- [ ] Multi-user support
- [ ] Subdomain management

---

## Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Backend | PHP 8 |
| Database | SQLite |
| Web Server | Nginx |
| Tunnel | Cloudflare Tunnel |
| Frontend | HTML, CSS, JavaScript (vanilla) |
| Code Editor | CodeMirror 5 |
| AI Integration | Cerebras Z-AI GLM-4.7 |
| Icons | Font Awesome 6 |
| Font | Inter |
| Platform | Android 8+ (Termux) |

---

## Resource Usage

| Resource | Usage |
|----------|-------|
| RAM | 100 - 250 MB |
| Storage | 500 MB - 2 GB |
| CPU | Sangat Rendah |
| Battery | Minimal (saat idle) |

---

## License

MIT License

```
MIT License

Copyright (c) 2026

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## Credits

Dibuat untuk proyek portofolio mahasiswa Ilmu Komputer.

**Android Hosting Panel Lite** - Menjadikan Android sebagai mini hosting server pribadi.
