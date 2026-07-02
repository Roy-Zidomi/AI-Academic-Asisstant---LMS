<div align="center">

#  AI Academic Assistant LMS (AI-AA LMS)
### *Sistem Manajemen Pembelajaran Bertenaga AI berbasis Moodle*

[![Moodle](https://img.shields.io/badge/Moodle-4.x-orange?logo=moodle)](https://moodle.org)
[![FastAPI](https://img.shields.io/badge/FastAPI-0.110-green?logo=fastapi)](https://fastapi.tiangolo.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-blue?logo=postgresql)](https://postgresql.org)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker)](https://docker.com)
[![Ollama](https://img.shields.io/badge/Ollama-LLM-black)](https://ollama.ai)
[![License](https://img.shields.io/badge/License-MIT-yellow)](LICENSE)

**AI-AA LMS** adalah platform Learning Management System (LMS) berbasis **Moodle** yang terintegrasi dengan kecerdasan buatan (AI) melalui layanan middleware **FastAPI** yang memanggil model bahasa besar (LLM) secara lokal via **Ollama**.

Sistem ini dirancang untuk Universitas Telkom (Tel-U) dengan identitas visual yang modern, elegan, dan profesional menggunakan tema kustom **`theme_teluai`**.

</div>

---

##  Fitur Utama

| Fitur | Deskripsi |
|-------|-----------|
|  **AI Academic Chat** | Asisten AI yang bisa menjawab pertanyaan mahasiswa seputar materi perkuliahan secara real-time dalam konteks mata kuliah yang sedang diambil |
|  **AI Material Summarizer** | Meringkas konten materi kuliah (teks, dokumen) secara otomatis menggunakan LLM lokal |
|  **AI Quiz Generator** | Membuat soal-soal quiz secara otomatis berdasarkan materi yang di-upload dosen |
|  **Custom Theme (theme_teluai)** | Tampilan UI/UX premium dengan desain split-screen login, warna brand Tel-U Merah, dan Google Fonts Inter |
|  **Full Docker Stack** | Seluruh infrastruktur dapat dijalankan hanya dengan satu perintah `docker-compose up` |

---

##  Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────────┐
│                      BROWSER (Pengguna)                         │
└─────────────────────────────┬───────────────────────────────────┘
                              │ HTTP/HTTPS
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│              NGINX Reverse Proxy (Port 80 / 443)                │
│         Routing, Static File Serving, SSL Termination           │
└──────┬──────────────────────────────────────────────┬───────────┘
       │ /                                            │ /api/ai/*
       ▼                                              ▼
┌──────────────────┐                    ┌─────────────────────────┐
│  MOODLE 4.x      │                    │   AI SERVICE (FastAPI)  │
│  (PHP/Apache)    │◄──── REST API ────►│   Python 3.11+          │
│  Port 8080       │                    │   Port 8000             │
│                  │                    │                         │
│  Plugin:         │                    │  Endpoints:             │
│  local_aiacademic│                    │  POST /api/v1/chat      │
│                  │                    │  POST /api/v1/summarize │
│  Theme: teluai   │                    │  POST /api/v1/quiz      │
└──────┬───────────┘                    └──────────┬──────────────┘
       │                                           │
       ▼                                           ▼
┌──────────────────┐                    ┌─────────────────────────┐
│  PostgreSQL 17   │                    │   OLLAMA (LLM Runtime)  │
│  Port 5432       │                    │   Port 11434            │
│  (Database utama)│                    │   Model: llama3 / dll   │
└──────────────────┘                    └─────────────────────────┘
       │
       ▼
┌──────────────────┐
│  Redis 7         │
│  Port 6379       │
│  (Cache & Sesi)  │
└──────────────────┘
```

---

##  Struktur Direktori

```
AI Academic Assistant LMS/
├──  docker-compose.yml          # Orkestrasi seluruh container
├──  .env.example                # Template konfigurasi environment
├──  .gitignore                  # File dan folder yang diabaikan Git
│
├──  ai-service/                 # AI Middleware (FastAPI/Python)
│   ├──  Dockerfile
│   ├──  requirements.txt
│   └──  app/
│       ├──  main.py             # Entry point FastAPI
│       ├──  config.py           # Konfigurasi environment
│       ├──  api/v1/             # Endpoint REST API AI
│       ├──  services/           # Logika bisnis AI (chat, summarize, quiz)
│       ├──  models/             # Pydantic request/response models
│       ├──  core/               # Core utilities
│       ├──  middleware/         # Middleware (logging, error handler)
│       └──  db/                 # Database queries
│
├──  docker/                     # Konfigurasi infrastruktur Docker
│   ├──  nginx/                  # Reverse proxy config
│   │   ├──  nginx.conf
│   │   └──  conf.d/default.conf
│   ├──  postgres/
│   │   └──  init/01-init.sql    # Script inisialisasi DB
│   └──  pgadmin/
│       └──  servers.json        # Auto-register PostgreSQL server
│
├──  moodle/                     # Kustomisasi Moodle
│   ├──  local/aiacademic/       # Plugin AI kustom Moodle
│   │   ├──  version.php
│   │   ├──  lib.php             # Library utama plugin
│   │   ├──  chat.php            # Halaman AI Chat
│   │   ├──  summarizer.php      # Halaman AI Summarizer
│   │   ├──  quiz_generator.php  # Halaman AI Quiz Generator
│   │   ├──  styles.css          # Styling plugin
│   │   ├──  db/                 # Schema database & capabilities
│   │   ├──  classes/            # PHP Classes (API, services)
│   │   ├──  amd/src/            # JavaScript modules (AMD)
│   │   └──  templates/          # Mustache templates
│   │
│   └──  theme/teluai/           # Custom Moodle Theme
│       ├──  config.php          # Konfigurasi tema
│       ├──  lib.php             # SCSS callbacks
│       ├──  version.php
│       ├──  scss/               # File SCSS modular
│       │   ├── variables.scss     # Design tokens & color system
│       │   ├── login.scss         # Styling halaman login
│       │   ├── teluai.scss        # Global component styles
│       │   └── preset/teluai.scss # Bootstrap preset
│       ├──  layout/             # PHP layout templates
│       ├──  templates/          # Mustache templates override
│       └──  pix/                # Aset gambar & ikon
│
├──  docs/                       # Dokumentasi tambahan
└──  scripts/                    # Helper scripts
```

---

##  Tech Stack

| Komponen | Teknologi | Versi |
|----------|-----------|-------|
| **LMS Core** | Moodle | 4.x (Bitnami) |
| **AI Service** | FastAPI (Python) | 0.110+ |
| **LLM Runtime** | Ollama | Latest |
| **Database** | PostgreSQL | 17 (Alpine) |
| **Cache/Session** | Redis | 7 (Alpine) |
| **Reverse Proxy** | Nginx | 1.25 (Alpine) |
| **Container** | Docker + Docker Compose | Latest |
| **Tema UI** | Custom Boost Child Theme (SCSS) | 1.0 |
| **Font** | Google Fonts - Inter | Variable |
| **Icons** | Font Awesome | 6.x |

---

##  Cara Menjalankan (Ringkas)

Panduan lengkap tersedia di **[SETUP_GUIDE.md](SETUP_GUIDE.md)**

```bash
# 1. Clone repository
git clone <url-repo> && cd "AI Academic Assistant LMS"

# 2. Salin dan konfigurasi environment
cp .env.example .env

# 3. Jalankan semua container
docker-compose up -d --build

# 4. Tunggu ±3-5 menit hingga Moodle selesai inisialisasi
# Akses: http://localhost:8080
```

---

##  URL Akses Layanan

| Layanan | URL | Keterangan |
|---------|-----|------------|
| **Moodle LMS** | http://localhost:8080 | Aplikasi utama LMS |
| **AI Service API** | http://localhost:8000 | REST API AI middleware |
| **API Docs** | http://localhost:8000/docs | Swagger UI (dev only) |
| **Nginx Proxy** | http://localhost | Reverse proxy |
| **pgAdmin** | http://localhost:8081 | Database admin panel |
| **Ollama** | http://localhost:11434 | LLM runtime API |

---

##  Kredensial Default (Development)

>  **PENTING**: Semua password di bawah ini **HARUS diganti** sebelum digunakan di lingkungan produksi!

| Layanan | Username/Email | Password |
|---------|---------------|----------|
| Moodle Admin | `admin` | `Admin@12345` |
| pgAdmin | `admin@lms.com` | `pgadmin_secret_change_me` |
| PostgreSQL | `moodle` | `moodle_secret_change_me` |

---

##  Prasyarat Sistem

- **Docker Desktop** v4.x atau lebih baru
- **RAM**: Minimum 8 GB (Direkomendasikan 16 GB jika menjalankan LLM lokal)
- **Storage**: Minimum 20 GB ruang disk kosong
- **OS**: Windows 10/11 (WSL2), macOS, atau Linux
- **Internet**: Diperlukan saat pertama kali untuk mengunduh Docker images dan model Ollama

---

##  Tim Pengembang

Proyek ini dikembangkan sebagai tugas akhir bersama (tugas kelompok) dengan lisensi **MIT**.

---

##  Lisensi

Proyek ini dilisensikan di bawah **MIT License**. Lihat file [LICENSE](LICENSE) untuk detail.
