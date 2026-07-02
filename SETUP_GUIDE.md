#  Panduan Setup Lokal — AI Academic Assistant LMS

Panduan ini menjelaskan cara menjalankan proyek **AI-AA LMS** di komputer lokal Anda dari awal hingga selesai. Pastikan Anda membaca setiap langkah dengan seksama.

---

##  Prasyarat Wajib

Sebelum memulai, pastikan semua software berikut sudah terinstal di komputer Anda:

| Software | Versi Minimum | Link Download |
|----------|--------------|---------------|
| **Git** | 2.x | [git-scm.com](https://git-scm.com/downloads) |
| **Docker Desktop** | 4.x | [docker.com/products/docker-desktop](https://www.docker.com/products/docker-desktop/) |
| **WSL2** (Windows) | - | Otomatis via Docker Desktop |

>  **Tips untuk Windows**: Pastikan **WSL2 Backend** sudah aktif di Docker Desktop.  
> Buka Docker Desktop → Settings → General → centang "Use the WSL 2 based engine"

### Cek Instalasi

Buka terminal (PowerShell / Command Prompt / bash) dan jalankan:

```bash
git --version       # Harus muncul: git version 2.x.x
docker --version    # Harus muncul: Docker version 24.x.x atau lebih baru
docker compose version  # Harus muncul: Docker Compose version v2.x.x
```

---

##  Langkah 1 — Clone Repository

```bash
# Clone proyek ke komputer Anda
git clone <URL_REPOSITORY_GITHUB> "AI Academic Assistant LMS"

# Masuk ke direktori proyek
cd "AI Academic Assistant LMS"
```

>  Ganti `<URL_REPOSITORY_GITHUB>` dengan URL repo yang dibagikan oleh ketua tim.

---

##  Langkah 2 — Konfigurasi Environment

Proyek ini menggunakan file `.env` untuk menyimpan konfigurasi sensitif (password, API key, dll.). File ini **tidak di-commit ke Git** untuk alasan keamanan.

```bash
# Salin template konfigurasi
cp .env.example .env
```

Untuk pengembangan lokal, Anda **tidak perlu mengubah** isi file `.env` — nilai default sudah dikonfigurasi agar bisa langsung berjalan.

>  **Jangan pernah commit file `.env`** yang sudah diisi ke GitHub!

---

##  Langkah 3 — Jalankan Docker

Pastikan **Docker Desktop sedang berjalan** (tampilannya harus hijau / "Running"), lalu jalankan:

```bash
# Build image custom dan jalankan semua container
docker compose up -d --build
```

Perintah ini akan:
1. **Mengunduh** semua Docker images yang dibutuhkan (Moodle, PostgreSQL, Redis, Nginx, dll.)
2. **Membangun** image AI Service dari `Dockerfile` lokal
3. **Menjalankan** semua 7 container secara bersamaan

>  **Pertama kali butuh 5–15 menit** tergantung kecepatan internet dan spesifikasi komputer Anda.

### Cek Status Container

```bash
docker compose ps
```

Tunggu hingga semua container berstatus **`healthy`** atau **`running`**:

```
NAME                IMAGE                   STATUS          PORTS
aaalms-nginx        nginx:1.25-alpine       Up (healthy)    0.0.0.0:80->80/tcp
aaalms-moodle       bitnamilegacy/moodle:4  Up (healthy)    0.0.0.0:8080->8080/tcp
aaalms-postgres     postgres:17-alpine      Up (healthy)    0.0.0.0:5432->5432/tcp
aaalms-redis        redis:7-alpine          Up (healthy)    0.0.0.0:6379->6379/tcp
aaalms-ollama       ollama/ollama:latest    Up (healthy)    0.0.0.0:11434->11434/tcp
aaalms-ai-service   ai-aa-lms-ai-service    Up (healthy)    0.0.0.0:8000->8000/tcp
aaalms-pgadmin      dpage/pgadmin4:latest   Up              0.0.0.0:8081->5050/tcp
```

>  Container **Moodle** biasanya membutuhkan **3–5 menit** untuk selesai inisialisasi database pertama kali.

---

##  Langkah 4 — Download Model AI (Ollama)

Setelah semua container berjalan, Anda perlu mengunduh model LLM ke dalam Ollama. Secara default, proyek ini menggunakan model **`llama3`** (±4GB).

```bash
# Download model llama3 ke dalam container Ollama
docker exec aaalms-ollama ollama pull llama3
```

> Proses ini membutuhkan **5–15 menit** tergantung kecepatan internet.  
> Model akan disimpan di Docker volume `aaalms-ollama-models` sehingga tidak perlu diunduh ulang.

### Verifikasi Model Berhasil Diunduh

```bash
docker exec aaalms-ollama ollama list
```

Output yang diharapkan:
```
NAME            ID              SIZE    MODIFIED
llama3:latest   365c0bd3c000    4.7 GB  ...
```

---

## Langkah 5 — Deploy Custom Theme Moodle

Tema kustom `theme_teluai` perlu di-copy ke dalam container Moodle secara manual setelah container berjalan.

```bash
# Hapus versi lama (jika ada)
docker exec aaalms-moodle rm -rf /opt/bitnami/moodle/theme/teluai

# Buat direktori baru
docker exec aaalms-moodle mkdir -p /opt/bitnami/moodle/theme/teluai

# Copy file tema
docker cp moodle/theme/teluai/. aaalms-moodle:/opt/bitnami/moodle/theme/teluai/

# Perbaiki kepemilikan file (PENTING!)
docker exec aaalms-moodle chown -R daemon:daemon /opt/bitnami/moodle/theme/teluai
docker exec aaalms-moodle chown -R daemon:daemon /bitnami/moodledata
```

Kemudian jalankan upgrade database Moodle dan bersihkan cache:

```bash
# Upgrade database untuk mendaftarkan versi tema baru
docker exec aaalms-moodle gosu daemon php /opt/bitnami/moodle/admin/cli/upgrade.php --non-interactive

# Bersihkan semua cache Moodle
docker exec aaalms-moodle gosu daemon php /opt/bitnami/moodle/admin/cli/purge_caches.php
```

---

##  Langkah 6 — Aktivasi Tema di Moodle

1. Buka browser dan akses **http://localhost:8080**
2. Login dengan username `admin` dan password `Admin@12345`
3. Masuk ke **Site Administration** → **Appearance** → **Themes** → **Theme Selector**
4. Klik **"Change theme"** dan pilih **"Tel-U AI LMS Theme"** (`teluai`)
5. Klik **"Use Theme"** untuk mengaktifkan

---

##  Verifikasi Sistem Berjalan

Setelah semua langkah selesai, cek semua layanan berjalan normal:

| URL | Yang Diharapkan |
|-----|----------------|
| http://localhost:8080/login | Halaman login Moodle |
| http://localhost:8000/health | `{"status":"healthy",...}` |
| http://localhost:8000/docs | Swagger UI AI Service |
| http://localhost:8081 | pgAdmin login |

---

## Menghentikan dan Memulai Ulang

```bash
# Hentikan semua container (data tetap tersimpan)
docker compose down

# Jalankan kembali
docker compose up -d

# Hentikan DAN hapus semua data (HATI-HATI!)
docker compose down -v
```

---

##  Troubleshooting Umum

###  Container Moodle Tidak Bisa Start

**Gejala**: Container `aaalms-moodle` berstatus `Exit` atau `Error`.

```bash
# Cek log container
docker logs aaalms-moodle --tail 50
```

Jika ada error koneksi ke database, pastikan container PostgreSQL sudah `healthy` terlebih dahulu.

---

###  Error "Invalid permissions detected when trying to create a directory"

**Penyebab**: File di dalam container dimiliki oleh user `root`, bukan `daemon`.

**Solusi**:
```bash
docker exec aaalms-moodle chown -R daemon:daemon /bitnami/moodledata
docker exec aaalms-moodle chown -R daemon:daemon /opt/bitnami/moodle/theme/teluai
docker exec aaalms-moodle gosu daemon php /opt/bitnami/moodle/admin/cli/purge_caches.php
```

---

###  Error "Error reading from database" (dmlreadexception)

**Penyebab**: Container Docker Desktop crash/freeze, mengakibatkan koneksi database terputus.

**Solusi**:
```bash
# Restart WSL2 engine (lebih cepat dari restart Docker Desktop)
wsl --shutdown

# Tunggu 10 detik, lalu jalankan ulang container
docker compose up -d
```

---

###  Plugin Upgrades Check Muncul di Moodle

**Gejala**: Moodle menampilkan halaman "Plugins check" dengan status "To be upgraded".

**Solusi**: Ini normal terjadi ketika versi tema di-update. Klik saja tombol **"Upgrade Moodle database now"** di browser, atau jalankan via CLI:

```bash
docker exec aaalms-moodle gosu daemon php /opt/bitnami/moodle/admin/cli/upgrade.php --non-interactive
```

---

###  AI Chat/Summarizer Tidak Berfungsi

**Langkah Debugging**:

```bash
# 1. Cek apakah AI Service berjalan
curl http://localhost:8000/health

# 2. Cek apakah model Ollama sudah terunduh
docker exec aaalms-ollama ollama list

# 3. Jika belum, unduh modelnya
docker exec aaalms-ollama ollama pull llama3

# 4. Cek log AI Service
docker logs aaalms-ai-service --tail 50
```

---

###  Port Sudah Digunakan (Port Already in Use)

Jika ada error seperti `bind: address already in use`, cari proses yang menggunakan port tersebut:

```bash
# Windows PowerShell
netstat -ano | findstr :8080
# Kemudian kill PID yang ditemukan
taskkill /PID <nomor_PID> /F

# Linux/macOS
lsof -i :8080
kill -9 <PID>
```

---

##  Alur Pengembangan (Workflow)

### Mengubah Kode AI Service (Python)

```bash
# Setelah mengubah file di ai-service/
docker compose build ai-service
docker compose up -d ai-service
docker logs aaalms-ai-service -f  # Pantau log
```

### Mengubah Kode Plugin Moodle (PHP/JS)

```bash
# Setelah mengubah file di moodle/local/aiacademic/
# Plugin di-mount sebagai volume read-only di /tmp/aiacademic
# Restart container Moodle untuk menyalin ulang file terbaru
docker compose restart moodle
docker exec aaalms-moodle gosu daemon php /opt/bitnami/moodle/admin/cli/purge_caches.php
```

### Mengubah Kode Tema Moodle (SCSS/Templates)

```bash
# Setelah mengubah file di moodle/theme/teluai/
docker cp moodle/theme/teluai/. aaalms-moodle:/opt/bitnami/moodle/theme/teluai/
docker exec aaalms-moodle chown -R daemon:daemon /opt/bitnami/moodle/theme/teluai
docker exec aaalms-moodle gosu daemon php /opt/bitnami/moodle/admin/cli/purge_caches.php
```

---

##  Konvensi Git

Gunakan konvensi berikut agar commit history mudah dibaca oleh semua anggota tim:

```
feat: menambahkan fitur baru
fix: memperbaiki bug
style: perubahan tampilan/CSS tanpa mengubah logika
refactor: refactoring kode tanpa mengubah fungsi
docs: update dokumentasi
chore: update konfigurasi, dependency, dll.
```

**Contoh:**
```
git commit -m "feat: tambahkan endpoint AI quiz generator dengan format JSON"
git commit -m "fix: perbaiki permission error pada theme deployment"
git commit -m "style: update warna tombol login sesuai brand Tel-U"
```

---

## Struktur API AI Service

Dokumentasi lengkap API tersedia di **http://localhost:8000/docs** (Swagger UI) saat mode development.

### Endpoint Utama

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| `GET` | `/health` | Cek status semua layanan |
| `POST` | `/api/v1/chat` | Kirim pesan ke AI Academic Assistant |
| `POST` | `/api/v1/summarize` | Minta ringkasan materi |
| `POST` | `/api/v1/quiz/generate` | Buat soal quiz dari teks/materi |

### Contoh Request Chat

```bash
curl -X POST http://localhost:8000/api/v1/chat \
  -H "Content-Type: application/json" \
  -H "X-API-Key: ai_api_key_change_me_in_production" \
  -d '{
    "message": "Apa itu keamanan sistem?",
    "course_id": 2,
    "user_id": 5,
    "history": []
  }'
```

---

## Butuh Bantuan?

Jika mengalami masalah yang tidak tercantum di sini, silakan:

1. **Buat issue** di repository GitHub proyek ini
2. **Hubungi** ketua tim atau anggota yang bertanggung jawab di bagian tersebut
3. **Cek log** container yang bermasalah dengan `docker logs <nama-container> --tail 100`

---

*Panduan ini terakhir diperbarui: Juli 2026*
