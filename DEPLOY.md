# 🚀 Panduan Deploy ke Server Kantor

## Persiapan di Server

### 1. Pastikan Docker & Docker Compose sudah terpasang
```bash
docker --version
docker compose version
```
Kalau belum, install Docker Engine:
```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
newgrp docker   # Refresh grup tanpa logout
```

---

### 2. Clone / Copy project ke server
```bash
# Via SCP dari laptop Windows
scp -r "C:\Users\Guest-Firzi\Documents\Meeting" user@<IP-SERVER>:/opt/meeting

# Atau via git
git clone <repo-url> /opt/meeting
cd /opt/meeting
```

---

### 3. Buat file `.env` dari template
```bash
cd /opt/meeting
cp .env.example .env
nano .env
```

Isi dengan kredensial yang kuat:
```env
POSTGRES_DB=meeting_db
POSTGRES_USER=meetinguser
POSTGRES_PASSWORD=P@ssw0rdKuatBanget!
```

> ⚠️ **WAJIB** ganti password sebelum deploy. Jangan pakai contoh di atas!

---

### 4. Jalankan dengan Docker Compose

```bash
docker compose -f docker-compose.prod.yml --env-file .env up -d --build
```

Cek status:
```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f
```

---

## ✅ Setelah Running

- **Akses App**: `http://<IP-SERVER>:8080`
- Login awal: `admin` / `password` (segera ganti!)
- Database otomatis terbuat dari `db_meeting_dump.sql`

---

## 🗄️ Koneksi ke Database PostgreSQL

Database berjalan di dalam Docker dan **tidak bisa diakses dari luar** secara langsung.
Gunakan salah satu cara berikut:

### Cara 1 — Masuk via Docker exec (paling mudah)
```bash
# Masuk ke shell PostgreSQL langsung
docker exec -it meeting_db psql -U meetinguser -d meeting_db

# Contoh query di dalam psql:
SELECT id, name, username, role FROM users;
\q   # Keluar
```

### Cara 2 — Koneksi dari aplikasi database GUI (DBeaver, TablePlus, dll)

Karena port DB tidak dibuka ke luar, gunakan **SSH Tunnel**:

| Setting          | Nilai                        |
|------------------|------------------------------|
| SSH Host         | `<IP-SERVER>`                |
| SSH User         | `user` (user Linux server)   |
| SSH Port         | `22`                         |
| DB Host          | `localhost` (lewat tunnel)   |
| DB Port          | `5432`                       |
| Database         | `meeting_db`                 |
| Username         | `meetinguser`                |
| Password         | *(sesuai isi `.env`)*        |

**Langkah di DBeaver:**
1. New Connection → PostgreSQL
2. Tab **SSH** → Enable SSH Tunnel
3. Isi SSH Host, User, dan pilih auth method (password atau key)
4. Tab **Main** → isi DB settings seperti tabel di atas
5. Test Connection → OK

### Cara 3 — Buka port sementara (hanya untuk debugging)

> ⚠️ Hanya untuk keperluan debugging! Tutup kembali setelah selesai.

Edit `docker-compose.prod.yml`, uncomment bagian ports di service `db`:
```yaml
ports:
  - "5432:5432"
```
Lalu restart:
```bash
docker compose -f docker-compose.prod.yml up -d db
```

Setelah selesai, comment kembali dan restart.

---

## 🔐 Migrasi Password (Jalankan sekali setelah deploy pertama)

Password di database dump sudah bcrypt. Tapi jika ada user lama dengan password plain text, jalankan script migrasi:

```bash
docker exec meeting_app php migrate_passwords.php
```

Setelah berhasil, **hapus script migrasi**:
```bash
docker exec meeting_app rm migrate_passwords.php
```

---

## 🔧 Perintah Berguna

| Perintah | Fungsi |
|---|---|
| `docker compose -f docker-compose.prod.yml logs app` | Lihat log aplikasi |
| `docker compose -f docker-compose.prod.yml logs db` | Lihat log database |
| `docker compose -f docker-compose.prod.yml restart app` | Restart app saja |
| `docker compose -f docker-compose.prod.yml down` | Matikan semua |
| `docker compose -f docker-compose.prod.yml down -v` | Matikan + hapus data |
| `docker exec -it meeting_db psql -U meetinguser -d meeting_db` | Masuk ke DB shell |

---

## 🗄️ Backup & Restore Database

**Backup:**
```bash
docker exec meeting_db pg_dump -U meetinguser meeting_db > backup_$(date +%Y%m%d_%H%M).sql
```

**Restore:**
```bash
docker exec -i meeting_db psql -U meetinguser meeting_db < backup_20260701_1400.sql
```

---

## ⚠️ Catatan Penting
- Port `5432` (PostgreSQL) **tidak** dibuka ke publik — hanya bisa diakses dari dalam container atau via SSH Tunnel.
- Folder `uploads/` disimpan di Docker Volume `uploads_data` — aman saat container di-rebuild.
- Untuk update aplikasi: `docker compose -f docker-compose.prod.yml up -d --build` (data DB tetap aman).
- Password semua user default adalah `password` — **wajib diganti** setelah login pertama.
