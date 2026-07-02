# Meeting & Feedback Management System (Multi-Branch Edition)

Aplikasi berbasis web ini dirancang untuk memudahkan manajemen jadwal meeting korporat, memonitor absensi karyawan secara pintar, serta mengumpulkan feedback kepuasan peserta secara terpusat. Aplikasi ini mendukung arsitektur **Multi-Branch (Isolasi Data Cabang)**.

---

## 🚀 Technology Stack

- **Backend**: PHP 8.2 (Procedural & OOP Hybrid)
- **Database**: PostgreSQL 15
- **Frontend**: Vanilla HTML5, CSS3, JavaScript, FullCalendar, Select2, SweetAlert2
- **Arsitektur Data**: Row-Level Multi-Tenancy (Data terisolasi per `branch_id`)
- **Deployment**: Docker & Docker Compose

---

## 🏢 Alur Kerja Sistem (System Workflow) & Detail Logika

Aplikasi ini dirancang dengan alur bisnis yang sangat detail dan terstruktur. Berikut adalah perjalanan data dan fitur dari awal hingga akhir:

### 1. Multi-Branch & Tingkatan Hak Akses
- **Isolasi Data Cabang**: Seluruh data Karyawan, Divisi, Ruangan, dan Meeting dipisahkan berdasarkan lokasi cabang (misal: Jakarta, Surabaya) melalui field `branch_id`.
- **Super Admin**: Memiliki *Dropdown Branch Switcher* di Topbar. Admin pusat bisa berpindah-pindah cabang untuk melihat dan mengelola data spesifik dari tiap cabang.
- **Karyawan / HR Cabang**: Otomatis terisolasi pada cabang masing-masing saat login (tidak ada switcher cabang).
- **Dashboard Access User (`can_dashboard = true`)**: Karyawan non-admin yang diberikan akses dashboard akan mendapatkan hak penuh layaknya admin untuk mengelola Data Master (Cabang, Ruangan, Karyawan, Divisi, Template), mengelola Hak Akses (Grant Access), membuat meeting, menyetujui meeting, dan mengelola Laporan, **tetapi tidak dapat berpindah cabang (switch branch)**. Mereka tetap terkunci pada cabang asalnya.
- **User Biasa (`can_dashboard = false`)**: Pengguna biasa tidak memiliki akses ke halaman admin/dashboard. 
  - Menu **Dashboard** disembunyikan.
  - Menu **Riwayat Absen** diganti namanya menjadi **Presensi**.
  - Jika mereka mencoba mengakses halaman dashboard secara manual (`index.php`), sistem akan otomatis me-redirect ke halaman `my_schedule.php` (Presensi).
  - Tampilan menu utama (Dashboard, Kalender, Presensi) dikelompokkan rapi dalam sub-menu **"Utama"** di sidebar.

### 2. Booking Jadwal & Conflict Checking (Anti-Bentrok)
- Pengguna yang memiliki akses pembuatan meeting dapat menjadwalkan rapat baru dengan menentukan Judul, Ruangan, Waktu (Mulai & Selesai), Toleransi Keterlambatan, Peserta, Fasilitas (Snack/Kopi), dan PIC.
- **Validasi Cerdas Ruangan**: Jika ada user yang mencoba memesan ruangan yang sama di rentang waktu yang beririsan dengan meeting yang sudah disetujui (Approved), sistem akan menolak pengajuan tersebut secara otomatis.

### 3. Pelaksanaan Meeting & Absensi Pintar
Peserta dapat melakukan absensi dengan memindai (scan) QR Code ruangan, atau mengklik tombol **Akses** pada tabel jadwal presensi mereka.

#### ⏱ Logika Penentuan Status Kehadiran
Saat peserta melakukan absen (check-in), sistem membandingkan waktu saat ini dengan jam mulai meeting ditambah batas toleransi keterlambatan (`late_tolerance` dalam menit):
- **Tepat Waktu**: Jika absen di bawah batas toleransi keterlambatan.
- **Telat**: Jika absen melewati batas toleransi keterlambatan (pengguna wajib mengisi alasan keterlambatan pada form yang disediakan).
- **Owner Privilege**: User yang ditandai memiliki akses `is_owner` di database akan otomatis selalu tercatat hadir dengan status **"Tepat Waktu"** walau terlambat atau tidak memindai absen.

#### 👥 Logika Peserta Non-Undangan (Bukan Peserta Terdaftar)
Apabila ada karyawan yang **tidak terdaftar dalam daftar peserta undangan** memindai QR Code absen atau mengakses link absensi meeting tersebut:
1. Sistem akan mendeteksi bahwa user tersebut tidak ada di tabel `meeting_participants`.
2. Sistem **tidak menolak** akses, melainkan meloloskan proses check-in.
3. Karyawan tersebut secara otomatis akan didaftarkan ke dalam list peserta (`meeting_participants`).
4. Kehadirannya akan dicatat pada tabel `attendances` dengan status **"Dadakan"** dan alasan keterlambatan otomatis terisi **"Peserta Dadakan"**.

### 4. Berakhirnya Rapat & Link Summary Sena(DMS)
- Meeting akan otomatis diselesaikan ketika waktu selesai telah terlewati, atau secara instan jika Admin/PIC menekan tombol **"Akhiri"** di halaman daftar meeting.
- Setelah meeting berstatus **Finished**:
  - Pendaftaran absensi ditutup.
  - Portal ulasan **Feedback** dibuka untuk mengumpulkan rating (1-5 Bintang) beserta saran/komentar keefektifan meeting dari para peserta.
  - Di halaman Laporan (`report.php`), tombol **Summary** (berwarna biru dengan ikon dokumen) akan muncul di sebelah tombol Rekap. Admin/Dashboard user dapat mengklik tombol ini untuk memunculkan modal penyimpanan tautan/link PDF dokumen summary yang diintegrasikan langsung dari **Sena(DMS)** (`https://skycloud.indoarsip.co.id/dokmeeecm/...`). Tautan ini bersifat editable dan dapat langsung dibuka via tombol **Buka Link**.

---

## 🛠 Panduan Instalasi (Docker)

1. Pastikan **Docker** dan **Docker Compose** telah ter-install.
2. Buka terminal di folder proyek ini.
3. Jalankan container:
   ```bash
   docker compose up -d --build
   ```
4. Aplikasi dapat diakses di: **http://localhost:8080**

---

## 📱 Panduan Akses via Mobile (HP)

Jika Anda ingin mencoba fitur Scan QR menggunakan HP:
1. Pastikan HP dan PC/Laptop terhubung pada **WiFi yang sama**.
2. Buka `cmd` di Windows, ketik `ipconfig` untuk melihat IPv4 Address (Misal: `192.168.1.10`).
3. Buka browser di HP dan akses: `http://192.168.1.10:8080`.

---

## 👤 Kredensial Login Default

Sistem menyediakan akun awal berdasarkan dump database (`db_meeting_dump.sql`):

| Role | Username | Password | Cabang | Fitur Khusus |
| --- | --- | --- | --- | --- |
| **Admin Pusat** | `admin` | `admin` | Jakarta (Cabang 1) | Super Admin (Bisa Switch Cabang) |
| **User Biasa (Finance)** | `finance` | `password123` | Jakarta (Cabang 1) | Karyawan di Cabang Jakarta |
| **User Biasa (HR)** | `hr` | `password123` | Jakarta (Cabang 1) | Karyawan di Cabang Jakarta |
| **User Biasa (IT)** | `it` | `password123` | Jakarta (Cabang 1) | Karyawan di Cabang Jakarta |
| **User Khusus (Surabaya)** | `asri` | `asri` | Surabaya (Cabang 2) | Akses Pemesanan, Ekspor & Dashboard Cabang 2 |

---

## 🧪 Pengujian Otomatis (Cypress E2E Testing)

Untuk menjamin kualitas dan stabilitas seluruh alur kerja sistem, proyek ini telah dilengkapi dengan rangkaian pengujian otomatis **Cypress End-to-End (E2E)**.

### 🔌 Database Reset Helper
Terdapat berkas helper [cypress_reset_db.php](cypress_reset_db.php) di root proyek yang otomatis dipanggil oleh Cypress sebelum pengujian untuk mereset data basis data menggunakan `db_meeting_dump.sql` agar pengujian berjalan konsisten dan bersih. Fitur ini hanya dapat dijalankan dari localhost (`127.0.0.1` / `::1`) demi keamanan.

### 📂 Struktur Folder Pengujian
- **Custom Commands**: [cypress/support/commands.js](cypress/support/commands.js) (berisi perintah `cy.resetDb()`, `cy.login()`, dan `cy.select2()`).
- **Test Specs**:
  - `auth.cy.js`: Pengujian login (admin & user), pembatasan menu sidebar, serta logout.
  - `master_data.cy.js`: Pengujian CRUD (Cabang, Ruangan, Divisi, Karyawan, Template) dan Select2.
  - `branch_isolation.cy.js`: Pengujian row-level data isolation untuk multi-branch.
  - `meeting_booking.cy.js`: Pengujian pemesanan jadwal meeting, validasi anti-bentrok ruangan, fasilitas snack/kopi, serta autofill template.
  - `attendance_feedback.cy.js`: Pengujian absensi check-in (tepat waktu vs telat), toleransi telat, owner privilege, serta ulasan bintang (feedback).
  - `reports_adhoc.cy.js`: Pengujian penambahan peserta dadakan dan validasi menu ekspor laporan Excel.

### 🏃‍♂️ Cara Menjalankan Pengujian
Pastikan container Docker Anda sedang aktif (di `http://localhost:8080`), lalu pasang dependensi Node terlebih dahulu:
```bash
npm install
```

Jalankan pengujian secara interaktif (GUI):
```bash
npm run cypress:open
```

Jalankan pengujian secara otomatis di terminal (headless mode):
```bash
npm run cypress:run
```
