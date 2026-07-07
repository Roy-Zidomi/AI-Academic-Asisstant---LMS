# Troubleshooting Fitur AI Moodle

Dokumen ini menjelaskan penyebab dan solusi ketika fitur AI di `local_aiacademic` tidak bisa digunakan, terutama error:

```text
error_invalid_response
Received an invalid response format from the AI Service.
```

## Ringkasan Arsitektur

Fitur AI di Moodle tidak memanggil Ollama secara langsung. Alur yang benar adalah:

```text
Browser
  -> Moodle local_aiacademic
  -> AI Service FastAPI
  -> Ollama
  -> Model LLM, contoh: llama3
```

Jadi agar AI Chat, AI Summarizer, dan AI Quiz Generator berjalan, semua komponen berikut harus aktif:

- Moodle
- PostgreSQL
- AI Service
- Ollama
- Model LLM, misalnya `llama3`

## Penyebab Utama

### 1. AI Service belum berjalan

Plugin Moodle memanggil endpoint:

```text
/api/v1/chat
/api/v1/summarize
/api/v1/generate-quiz
```

Endpoint tersebut disediakan oleh container `ai-service`. Jika container ini mati, tidak healthy, atau gagal build, fitur AI tidak akan berjalan.

Cek:

```powershell
docker compose ps
```

Pastikan container berikut berjalan:

```text
aaalms-moodle
aaalms-ai-service
aaalms-ollama
aaalms-postgres
```

### 2. AI Service URL salah di Moodle

Jika Moodle berjalan di dalam Docker, jangan gunakan `localhost` untuk memanggil AI Service dari Moodle.

Gunakan:

```text
http://ai-service:8000
```

Jangan gunakan:

```text
http://localhost:8000
http://localhost:11434
http://ollama:11434
```

Penjelasan:

- `localhost` dari dalam container Moodle berarti container Moodle itu sendiri, bukan host laptop.
- `11434` adalah port Ollama, bukan AI Service.
- Moodle membutuhkan format response dari FastAPI AI Service, bukan response mentah dari Ollama.

Lokasi setting:

```text
Site administration
-> Plugins
-> Local plugins
-> AI Academic Assistant
-> AI Service URL
```

Isi:

```text
http://ai-service:8000
```

### 3. Model Ollama belum di-download

Default model yang dipakai plugin adalah:

```text
llama3
```

Cek model:

```powershell
docker exec aaalms-ollama ollama list
```

Jika `llama3` belum ada, jalankan:

```powershell
docker exec aaalms-ollama ollama pull llama3
```

Setelah selesai, ulangi:

```powershell
docker exec aaalms-ollama ollama list
```

### 4. API key Moodle dan AI Service tidak sama

Moodle mengirim API key melalui header:

```text
X-API-Key
```

Nilai ini harus sama dengan environment variable AI Service:

```text
AI_API_KEY
```

Default project:

```text
ai_api_key_change_me_in_production
```

Cek file `.env`:

```text
AI_API_KEY=ai_api_key_change_me_in_production
```

Lalu cek setting Moodle:

```text
Site administration
-> Plugins
-> Local plugins
-> AI Academic Assistant
-> AI Service API Key
```

Nilainya harus sama.

### 5. AI Service mengembalikan JSON yang tidak sesuai format plugin

Plugin Moodle mengharapkan response seperti ini:

```json
{
  "success": true,
  "data": {
    "response": "Jawaban AI",
    "model": "llama3",
    "tokens": {
      "input": 0,
      "output": 0,
      "total": 0
    },
    "response_time_seconds": 1.23
  }
}
```

Jika response bukan JSON, HTML error page, response Ollama mentah, atau error service, Moodle akan menampilkan:

```text
error_invalid_response
```

## Checklist Perbaikan Cepat

Jalankan dari root project:

```powershell
docker compose ps
```

Cek health AI Service dari host:

```powershell
curl http://localhost:8000/health
```

Cek log AI Service:

```powershell
docker logs aaalms-ai-service --tail 80
```

Cek Ollama:

```powershell
docker exec aaalms-ollama ollama list
```

Download model jika belum ada:

```powershell
docker exec aaalms-ollama ollama pull llama3
```

Restart AI Service:

```powershell
docker compose restart ai-service
```

Purge cache Moodle:

```powershell
docker compose exec -u daemon moodle bash -lc "cd /opt/bitnami/moodle && /opt/bitnami/php/bin/php admin/cli/purge_caches.php"
```

## Setting Moodle yang Direkomendasikan

Di halaman setting plugin `AI Academic Assistant`, gunakan:

```text
AI Service URL: http://ai-service:8000
AI Service API Key: ai_api_key_change_me_in_production
Default Chat Model: llama3
Default Summary Model: llama3
Default Quiz Model: llama3
Connection Timeout: 120
```

Untuk summary dan quiz, proses bisa lebih lama daripada chat. Jika sering timeout, naikkan timeout ke `180` atau `300`.

## Cara Tes Manual Endpoint Chat

Tes dari host:

```powershell
curl -X POST http://localhost:8000/api/v1/chat `
  -H "Content-Type: application/json" `
  -H "X-API-Key: ai_api_key_change_me_in_production" `
  -H "X-User-ID: 1" `
  -d "{\"user_id\":1,\"message\":\"Halo, jelaskan apa itu LMS secara singkat\",\"history\":[],\"course_context\":null,\"options\":{\"model\":\"llama3\"}}"
```

Response yang benar harus memiliki:

```json
{
  "success": true,
  "data": {}
}
```

Jika response error, cek `docker logs aaalms-ai-service --tail 80`.

## Kesimpulan

Jika fitur AI berjalan di komputer utama tetapi gagal di laptop teman, hampir pasti penyebabnya salah satu dari ini:

- Container `ai-service` belum jalan.
- Container `ollama` belum jalan.
- Model `llama3` belum di-download.
- Setting `AI Service URL` salah.
- API key Moodle dan AI Service tidak sama.
- Resource laptop tidak cukup untuk menjalankan model.

Perbaikan paling sering:

```powershell
docker compose up -d
docker exec aaalms-ollama ollama pull llama3
docker compose restart ai-service
```

Lalu pastikan Moodle plugin memakai:

```text
http://ai-service:8000
```
