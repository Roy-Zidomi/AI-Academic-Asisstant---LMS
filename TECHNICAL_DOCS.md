#  AI-AA LMS — Dokumentasi Teknis Detail

> Dokumen ini berisi deskripsi teknis mendalam dari proyek **AI Academic Assistant Learning Management System (AI-AA LMS)** untuk keperluan pengembangan bersama tim.

---

## 1. Latar Belakang & Tujuan Proyek

### 1.1 Permasalahan yang Diselesaikan

Sistem LMS konvensional (termasuk Moodle vanilla) tidak menyediakan kemampuan AI interaktif yang membantu mahasiswa memahami materi secara personal dan mandiri. Mahasiswa seringkali harus menunggu respons dosen untuk pertanyaan-pertanyaan yang sebenarnya bisa dijawab oleh sistem AI.

### 1.2 Solusi yang Dibangun

**AI-AA LMS** menggabungkan kekuatan Moodle sebagai LMS yang mapan dengan kecerdasan buatan berbasis LLM (Large Language Model) untuk memberikan:

- **Asisten belajar personal** yang bisa diakses 24/7
- **Peringkasan materi otomatis** untuk memudahkan belajar
- **Pembuatan soal latihan** secara otomatis dari konten kursus

### 1.3 Identitas Proyek

| Atribut | Nilai |
|---------|-------|
| **Nama Proyek** | AI Academic Assistant LMS (AI-AA LMS) |
| **Institusi** | Universitas Telkom (Tel-U) |
| **Tipe** | Tugas Kelompok / Academic Project |
| **Status** | In Development |
| **Lisensi** | MIT |

---

## 2. Arsitektur Teknis

### 2.1 Overview Arsitektur (Microservice-based)

Proyek ini mengikuti **pola arsitektur hybrid** antara monolitik (Moodle) dan microservice (AI Service). Dua komponen utama berkomunikasi melalui REST API:

```
[Browser] ──► [Nginx] ──► [Moodle/PHP] ──REST──► [AI Service/Python] ──► [Ollama/LLM]
                                │                          │
                                ▼                          ▼
                          [PostgreSQL]               [Redis Cache]
```

### 2.2 Service-by-Service Breakdown

####  Nginx (Reverse Proxy)
- **Image**: `nginx:1.25-alpine`
- **Port**: `80` (HTTP), `443` (HTTPS)
- **Fungsi**: Menjadi pintu masuk utama semua request. Meneruskan request ke Moodle (port 8080) atau membatasi akses ke service internal.
- **File Konfigurasi**: `docker/nginx/conf.d/default.conf`

####  Moodle (Core LMS)
- **Image**: `bitnamilegacy/moodle:4` (PHP 8.x + Apache)
- **Port**: `8080`
- **Fungsi**: Inti sistem LMS. Mengelola pengguna, kursus, materi, tugas, nilai, dan semua fitur akademik standar.
- **Customisasi**:
  - **Plugin `local_aiacademic`**: Menambahkan fitur AI ke dalam halaman kursus Moodle
  - **Tema `theme_teluai`**: Tampilan UI/UX kustom dengan brand Telkom University

####  AI Service (FastAPI)
- **Image**: Custom (di-build dari `ai-service/Dockerfile`)
- **Port**: `8000`
- **Fungsi**: Middleware AI. Menerima request dari Moodle, memproses dengan Ollama, mengembalikan respons AI.
- **Framework**: FastAPI (Python 3.11+)
- **Entry Point**: `ai-service/app/main.py`

#### ⚫ Ollama (LLM Runtime)
- **Image**: `ollama/ollama:latest`
- **Port**: `11434`
- **Fungsi**: Menjalankan model bahasa besar (LLM) secara lokal tanpa memerlukan koneksi ke API eksternal berbayar.
- **Model Default**: `llama3` (4.7GB)

#### 🔵 PostgreSQL (Database)
- **Image**: `postgres:17-alpine`
- **Port**: `5432`
- **Fungsi**: Database relasional utama untuk menyimpan seluruh data Moodle (pengguna, kursus, nilai, sesi, dll.)

####  Redis (Cache & Session)
- **Image**: `redis:7-alpine`
- **Port**: `6379`
- **Fungsi**: Menyimpan cache sementara dan data sesi untuk meningkatkan performa. Digunakan oleh AI Service untuk rate limiting dan caching respons.

---

## 3. Plugin Moodle: `local_aiacademic`

### 3.1 Struktur Plugin

```
moodle/local/aiacademic/
├── version.php          # Metadata plugin (versi, dependensi)
├── lib.php              # Hooks Moodle (navigation, events)
├── settings.php         # Halaman pengaturan admin plugin
├── chat.php             # Halaman: AI Academic Chat
├── summarizer.php       # Halaman: AI Material Summarizer
├── quiz_generator.php   # Halaman: AI Quiz Generator
├── styles.css           # Stylesheet plugin (UI komponen AI)
│
├── db/
│   ├── access.php       # Definisi capabilities (izin akses)
│   ├── install.xml      # Skema tabel database plugin
│   └── services.php     # Definisi Web Service API Moodle
│
├── classes/
│   └── external/        # PHP classes untuk Web Service
│
├── amd/src/             # JavaScript modules (AMD format)
│   ├── chat.js          # Logic UI AI Chat
│   ├── summarizer.js    # Logic UI Summarizer
│   └── quiz.js          # Logic UI Quiz Generator
│
├── templates/           # Mustache HTML templates
│   ├── chat.mustache
│   ├── summarizer.mustache
│   └── quiz_generator.mustache
│
└── lang/en/             # String bahasa Inggris
    └── local_aiacademic.php
```

### 3.2 Cara Plugin Bekerja

1. **Plugin memanggil AI Service** melalui AJAX request (via AMD JavaScript modules) langsung ke `http://localhost:8000/api/v1/*`
2. **AI Service memproses request** dengan mengirimkan prompt ke Ollama
3. **Ollama mengeksekusi model LLM** (llama3) dan menghasilkan respons teks
4. **Respons dikembalikan** ke browser pengguna melalui chain yang sama

### 3.3 Izin Akses (Capabilities)

| Capability | Deskripsi | Default |
|------------|-----------|---------|
| `local/aiacademic:use_chat` | Menggunakan fitur AI Chat | Student |
| `local/aiacademic:use_summarizer` | Menggunakan Summarizer | Student |
| `local/aiacademic:use_quiz_generator` | Menggunakan Quiz Generator | Teacher |
| `local/aiacademic:manage` | Mengelola pengaturan plugin | Admin |

---

## 4. Tema Moodle: `theme_teluai`

### 4.1 Gambaran Umum

`theme_teluai` adalah **Boost child theme** untuk Moodle yang mengganti tampilan default Moodle dengan desain premium bergaya SaaS modern dengan brand Universitas Telkom.

### 4.2 Desain System (Tokens)

```scss
// Brand Colors
$primary:        #ED1C24;  // Tel-U Red
$primary-dark:   #B01116;  // Dark Red (hover state)
$primary-soft:   #FDE7E8;  // Soft Red (background)

// Neutral Palette  
$body-bg:        #F8FAFC;  // Slate white
$body-color:     #1F2937;  // Slate gray
$card-bg:        #FFFFFF;  // White card

// Typography
$font-family:    "Inter", system-ui, sans-serif;

// Border Radius
$card-border-radius: 1.5rem;   // 24px
$btn-border-radius:  0.75rem;  // 12px
$input-border-radius: 0.75rem; // 12px
```

### 4.3 Fitur Desain Login Page

Login page menggunakan layout **split-screen 55/45**:
- **Panel Kiri (55%)**: Hero branding dengan gradasi merah Tel-U, animated floating orbs, glassmorphism feature cards
- **Panel Kanan (45%)**: Form login dalam card modern dengan shadow lembut

### 4.4 File SCSS Modular

| File | Fungsi |
|------|--------|
| `variables.scss` | Design tokens (warna, font, shadow, radius) |
| `preset/teluai.scss` | Import Boost default preset (Bootstrap + Moodle) |
| `login.scss` | Styling khusus halaman login |
| `teluai.scss` | Global component overrides |

---

## 5. AI Service (FastAPI)

### 5.1 Struktur Kode

```
ai-service/app/
├── main.py              # FastAPI app initialization
├── config.py            # Settings dari environment variables
│
├── api/v1/
│   ├── router.py        # Menggabungkan semua route
│   ├── chat.py          # Route: POST /api/v1/chat
│   ├── summarizer.py    # Route: POST /api/v1/summarize
│   └── quiz.py          # Route: POST /api/v1/quiz/generate
│
├── services/
│   ├── ollama.py        # Wrapper client untuk Ollama API
│   ├── chat.py          # Logika bisnis chat (prompt engineering)
│   ├── summarizer.py    # Logika ringkasan materi
│   └── quiz.py          # Logika pembuatan soal
│
├── models/
│   ├── chat.py          # Pydantic models: ChatRequest, ChatResponse
│   ├── summarizer.py    # Pydantic models: SummarizeRequest, dll
│   └── quiz.py          # Pydantic models: QuizRequest, dll
│
├── middleware/
│   ├── error_handler.py # Global exception handler
│   └── logging.py       # Request logging middleware
│
└── db/
    └── queries.py       # Database queries (asyncpg)
```

### 5.2 Endpoint Detail

#### POST `/api/v1/chat`
Mengirim pesan ke AI Academic Assistant.

**Request Body:**
```json
{
  "message": "Apa itu enkripsi simetris?",
  "course_id": 2,
  "user_id": 5,
  "history": [
    {"role": "user", "content": "Halo"},
    {"role": "assistant", "content": "Halo! Ada yang bisa saya bantu?"}
  ]
}
```

**Response:**
```json
{
  "response": "Enkripsi simetris adalah metode enkripsi...",
  "model": "llama3",
  "tokens_used": 245
}
```

#### POST `/api/v1/summarize`
Meringkas teks materi kuliah.

**Request Body:**
```json
{
  "content": "Teks materi yang panjang...",
  "course_id": 2,
  "language": "id"
}
```

#### POST `/api/v1/quiz/generate`
Membuat soal quiz dari materi.

**Request Body:**
```json
{
  "content": "Teks materi...",
  "num_questions": 5,
  "difficulty": "medium",
  "question_type": "multiple_choice",
  "course_id": 2
}
```

### 5.3 Authentication

AI Service menggunakan **API Key authentication** via HTTP header:

```
X-API-Key: <nilai dari AI_API_KEY di .env>
```

Untuk development, nilai defaultnya adalah `ai_api_key_change_me_in_production`.

### 5.4 Rate Limiting

Rate limit per pengguna dikonfigurasi via variabel environment:

| Feature | Default Limit |
|---------|--------------|
| Chat | 20 request/menit |
| Summarize | 10 request/menit |
| Quiz Generate | 5 request/menit |

---

## 6. Database Schema

### 6.1 Tabel Custom Plugin (local_aiacademic)

Plugin `local_aiacademic` menambahkan tabel berikut ke database Moodle:

```sql
-- Riwayat percakapan AI Chat
CREATE TABLE mdl_local_aiacademic_chats (
    id          BIGINT PRIMARY KEY,
    userid      BIGINT NOT NULL,     -- FK ke mdl_user
    courseid    BIGINT NOT NULL,     -- FK ke mdl_course
    message     TEXT NOT NULL,       -- Pesan pengguna
    response    TEXT NOT NULL,       -- Respons AI
    timecreated BIGINT NOT NULL      -- Unix timestamp
);
```

### 6.2 Relasi dengan Tabel Moodle

Plugin menggunakan tabel Moodle standar berikut (READ ONLY):
- `mdl_user` — Data pengguna
- `mdl_course` — Data kursus
- `mdl_course_modules` — Modul dalam kursus
- `mdl_resource` — Resource/materi kursus

---

## 7. Konfigurasi Docker

### 7.1 Network

Semua container terhubung dalam satu Docker bridge network bernama `ai-aa-lms-network`. Container bisa saling mengakses satu sama lain menggunakan nama container sebagai hostname (misalnya: `postgres`, `redis`, `ollama`).

### 7.2 Volumes

| Volume | Container | Isi |
|--------|-----------|-----|
| `aaalms-pgdata` | postgres | File database PostgreSQL |
| `aaalms-moodledata` | moodle | Instalasi Moodle (kode PHP) |
| `aaalms-moodledata-data` | moodle | Data Moodle (upload file, dll) |
| `aaalms-redisdata` | redis | Data Redis (AOF persistence) |
| `aaalms-ollama-models` | ollama | Model LLM yang sudah diunduh |
| `aaalms-pgadmin-data` | pgadmin | Konfigurasi pgAdmin |

### 7.3 Resource Limits

| Container | Memory Limit |
|-----------|-------------|
| Moodle | 1 GB |
| PostgreSQL | 1 GB |
| Redis | 512 MB |
| AI Service | 512 MB |
| Ollama | 8 GB |
| Nginx | 128 MB |
| pgAdmin | 512 MB |

>  **Ollama butuh RAM besar** karena model llama3 memerlukan ~6-8 GB RAM saat berjalan. Pastikan komputer Anda memiliki minimal 16 GB RAM untuk pengembangan.

---

## 8. Environment Variables Reference

Semua konfigurasi diatur melalui file `.env`. Lihat [`.env.example`](.env.example) untuk daftar lengkap.

### Variabel Kritis

| Variable | Default | Keterangan |
|----------|---------|------------|
| `POSTGRES_PASSWORD` | `moodle_secret_change_me` | **GANTI di production!** |
| `REDIS_PASSWORD` | `redis_secret_change_me` | **GANTI di production!** |
| `MOODLE_PASSWORD` | `Admin@12345` | Password admin Moodle |
| `AI_API_KEY` | `ai_api_key_change_me_in_production` | **GANTI di production!** |
| `OLLAMA_MODELS` | `llama3` | Model yang digunakan |
| `ENVIRONMENT` | `development` | `development` atau `production` |

---

## 9. Deployment Checklist

Sebelum mendeploy ke server produksi, pastikan semua item berikut selesai:

- [ ] Ganti semua password default di `.env`
- [ ] Ganti `AI_API_KEY` dengan nilai yang aman dan panjang
- [ ] Set `ENVIRONMENT=production` di `.env`
- [ ] Konfigurasi domain dan SSL certificate di Nginx
- [ ] Hapus expose port `5432` dan `6379` (hapus bagian `ports:` di docker-compose untuk kedua service tersebut)
- [ ] Pastikan backup strategy sudah ada untuk PostgreSQL volume
- [ ] Atur `MOODLE_SKIP_BOOTSTRAP=yes` setelah instalasi pertama

---

## 10. Kontribusi & Pembagian Tugas

### Panduan Umum

1. **Jangan langsung push ke branch `main`**. Selalu buat branch baru untuk setiap fitur/bugfix.
2. Gunakan pull request (PR) untuk menggabungkan perubahan ke branch utama.
3. Minta review dari minimal 1 anggota tim sebelum merge.

### Saran Pembagian Komponen

| Komponen | Lokasi | Teknologi |
|----------|--------|-----------|
| AI Service Logic | `ai-service/app/services/` | Python |
| AI API Endpoints | `ai-service/app/api/` | Python (FastAPI) |
| Moodle Plugin PHP | `moodle/local/aiacademic/` | PHP |
| Plugin JavaScript | `moodle/local/aiacademic/amd/src/` | JavaScript |
| Plugin Templates | `moodle/local/aiacademic/templates/` | Mustache |
| Tema UI/UX | `moodle/theme/teluai/scss/` | SCSS |
| Infrastruktur | `docker/`, `docker-compose.yml` | Docker/YAML |

---

*Dokumen ini dibuat untuk keperluan pengembangan internal tim. Terakhir diperbarui: Juli 2026.*
