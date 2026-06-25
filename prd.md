PRD dan README yang Anda buat sudah cukup matang untuk V1. Namun jika targetnya adalah **"cPanel Android" yang benar-benar berguna untuk penggunaan sehari-hari**, saya melihat ada beberapa fitur bernilai tinggi yang masih belum ada dan relatif ringan untuk Vivo Y91C.

## Prioritas Tinggi (Saya Sarankan Masuk V1.2)

### 1. Auto Start Server

Saat HP restart:

```text
HP Nyala
↓
Termux:Boot
↓
PHP-FPM Start
↓
Nginx Start
↓
Cloudflared Start
```

Manfaat:

* Server langsung online.
* Tidak perlu buka Termux manual.

---

### 2. Service Manager

Menu:

```text
Services

[Start Nginx]
[Stop Nginx]

[Start PHP]
[Stop PHP]

[Start Tunnel]
[Stop Tunnel]
```

Saat ini user harus pakai terminal.

Lebih baik semua bisa dari panel.

---

### 3. File Upload Progress

Menampilkan:

```text
Uploading...
75%
████████░░
```

Karena upload file besar tanpa progress bar terasa rusak.

---

### 4. Activity Log

Contoh:

```text
[10:22] Upload index.html
[10:23] Delete old.css
[10:25] Create Website Portfolio
```

Sangat membantu debugging.

---

### 5. Website Status

Dashboard:

```text
Portfolio
● Online

Blog
● Online

Company
● Offline
```

Bisa cek otomatis apakah:

```text
index.html
```

atau

```text
index.php
```

ada di root website.

---

## Prioritas Menengah (V2)

### 6. Template Website

Create Website:

```text
○ Blank HTML
○ Portfolio
○ Landing Page
○ Blog
```

Otomatis generate file awal.

---

### 7. One Click Deploy

Upload:

```text
portfolio.zip
```

Klik:

```text
Deploy
```

Otomatis:

```text
Extract
↓
Publish
↓
Done
```

Ini fitur yang akan paling sering dipakai.

---

### 8. Backup Scheduler

Misal:

```text
Backup setiap:
○ Harian
○ Mingguan
○ Bulanan
```

Menggunakan cron.

---

### 9. Storage Analyzer

Menampilkan:

```text
Portfolio
  50 MB

Blog
  120 MB

Uploads
  700 MB
```

Mudah mengetahui penyebab storage penuh.

---

### 10. QR Code Tunnel

Saat Cloudflare aktif:

```text
https://abc.trycloudflare.com
```

langsung muncul QR Code.

Buka dari laptop tinggal scan.

---

## Prioritas Tinggi untuk Keamanan

### 11. Session Timeout

Misal:

```text
Logout otomatis setelah 30 menit
```

Jika HP hilang atau dipinjam orang.

---

### 12. IP Login History

Contoh:

```text
192.168.1.10
2026-06-25 08:22

103.xxx.xxx.xxx
2026-06-25 09:14
```

---

### 13. Backup Sebelum Delete

Saat hapus:

```text
Delete Website?
```

Otomatis:

```text
Backup
↓
Delete
```

Mengurangi risiko kehilangan data.

---

## Fitur yang Sebaiknya Dibatalkan

Untuk Vivo Y91C saya akan menghapus:

```text
❌ AI Generate Website
❌ Cerebras Integration
❌ AI Chat
❌ Multi User
❌ Web Terminal
```

Alasannya:

* Menambah kompleksitas.
* Tidak terlalu dipakai sehari-hari.
* Menambah maintenance.
* Fokus utama panel hosting adalah manajemen file dan website.

---

## Fitur "Killer Feature"

Kalau ingin proyek ini terlihat unik di GitHub:

### GitHub Deploy

Input:

```text
https://github.com/user/project
```

Klik:

```text
Deploy
```

Sistem:

```bash
git clone
↓
Publish
↓
Online
```

Ini jauh lebih menarik daripada AI Generate Website karena benar-benar berguna untuk developer.

Jika saya yang menyusun roadmap, urutannya:

```text
V1.2
✓ Service Manager
✓ Activity Log
✓ Upload Progress
✓ Website Status
✓ Auto Start

V2
✓ ZIP Deploy
✓ Backup Scheduler
✓ Template Website
✓ QR Tunnel

V3
✓ GitHub Deploy
✓ PWA
✓ Custom Theme
```

Dengan roadmap tersebut, AHPL akan terasa seperti mini-cPanel sungguhan tetapi tetap ringan untuk Vivo Y91C RAM 2 GB.
