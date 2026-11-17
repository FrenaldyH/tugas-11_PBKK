# PromptLab API Hub — Tugas 11 PBKK: Secure LLM Chat API Hub

Backend API terproteksi untuk **PromptLab**, final project platform Agentic AI **Kelompok 1** ("Local-first Agentic
Prompt, Context & Memory Optimization"). Laravel menerima prompt, meneruskannya ke LLM
**Ollama lokal** (dengan fallback otomatis ke **Senopati AI ITS** bila Ollama mati), mencatat riwayatnya ke database, dan mengembalikan JSON
yang dibungkus API Resource. Semua endpoint chat dikunci token **Laravel Sanctum**. Antarmuka Livewire menyusul di
Pertemuan 12; untuk sekarang tersedia halaman **playground** di `/` untuk demo (desain: [`notes/DESIGN.md`](notes/DESIGN.md)).

## Ketentuan tugas dan implementasinya

| Ketentuan | Implementasi |
|---|---|
| `POST /api/chat-ai` dikunci `auth:sanctum` | [`routes/api.php`](routes/api.php) |
| FormRequest: prompt string, maks 1000 karakter | [`ChatAiRequest`](app/Http/Requests/ChatAiRequest.php) |
| Log DB: user, prompt, jawaban, model, token | tabel `chat_logs` ([migrasi](database/migrations/2026_11_10_210214_create_chat_logs_table.php), [model](app/Models/ChatLog.php)) |
| Respons JSON dibungkus API Resource | [`ChatLogResource`](app/Http/Resources/ChatLogResource.php) |
| Terhubung ke Senopati AI ITS | [`LlmClient`](app/Services/LlmClient.php), konfigurasi di [`config/llm.php`](config/llm.php) |
| Challenge 1: streaming SSE | `POST /api/chat-ai/stream` |
| Challenge 2: system prompt per tema | `theme`: `default`, `prompt_engineer`, `context_engineer`, `evaluator` (lihat [Tema agent](#tema-agent-promptlab)) |
| Challenge 3: fallback otomatis | Ollama lokal → Senopati AI ITS (`LLM_PROVIDER_ORDER`); jalur percobaan tampil di respons (`attempts`) dan di playground (lihat [Alur fallback](#alur-fallback-challenge-3)) |
| `.env` & `vendor/` tidak ikut Git | `.gitignore` bawaan Laravel |

## Instalasi

**Sekali saja (pertama kali clone):**

```bash
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"   # hanya menyalin bila .env belum ada
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed        # membuat akun demo
```

Lalu isi `.env` (token Senopati, `OLLAMA_MODEL`; lihat di bawah).

**Setiap kali menjalankan:**

```bash
php artisan serve                 # http://127.0.0.1:8000
```

> **Jangan** menjalankan `cp .env.example .env` setelah `.env` diisi. `.env.example` sengaja berisi
> nilai kosong karena ikut Git, jadi perintah itu menimpa token dan `OLLAMA_MODEL` Anda. Setelah `git pull`, cukup
> `composer install` (bila ada paket baru) dan `php artisan migrate`. Variabel baru di `.env.example` disalin manual.

**Provider utama: Ollama lokal (Challenge 3).** Jalankan Ollama dan isi `OLLAMA_MODEL` dengan model dari `ollama list`
(model 7B kuantisasi Q4 muat di GPU 6 GB):

```bash
sudo systemctl start ollama        # bila Ollama terpasang sebagai service; atau: ollama serve
ollama list                        # pilih nama model untuk OLLAMA_MODEL
ollama run NAMA_MODEL "halo"       # "pemanasan": memuat model ke GPU sebelum demo
```

Pemanasan penting: model yang belum dimuat butuh ±40 detik (*cold start*) pada jawaban pertama, dan Ollama melepas
model dari GPU setelah 5 menit tidak dipakai.

**Cadangan: Senopati AI ITS.** Dipakai otomatis bila Ollama mati. Isi token milik Anda di `.env` (file ini tidak pernah
ikut Git):

```dotenv
SENOPATI_API_KEY=token-dari-its
```

Cek token langsung ke gateway, tanpa lewat Laravel. Ganti `TOKEN_ANDA`; balasan JSON berisi `choices` berarti
token valid:

```bash
curl https://senopati.its.ac.id/llm-gateway/v1/chat/completions \
  -H "Authorization: Bearer TOKEN_ANDA" -H "Content-Type: application/json" \
  -d '{"model":"qwen3:14b","messages":[{"role":"user","content":"halo"}]}'
```

Akun demo: `demo@promptlab.test` / `password`.

### Variabel `.env`

| Variabel | Default | Arti |
|---|---|---|
| `LLM_PROVIDER_ORDER` | `ollama,senopati` | urutan provider; yang berikutnya = fallback |
| `LLM_TIMEOUT` | `60` | detik per request ke LLM |
| `LLM_RETRIES` | `3` | percobaan per provider (hanya untuk koneksi putus / HTTP 5xx; timeout tidak diulang) |
| `LLM_TEMPERATURE` | `0.7` | kreativitas jawaban |
| `LLM_THINK` | `false` | mode berpikir `qwen3`; `false` = jawaban jauh lebih cepat dan kata pertama streaming langsung muncul |
| `SENOPATI_BASE_URL` | `https://senopati.its.ac.id/llm-gateway/v1` | gateway LLM kampus, format Chat Completion OpenAI |
| `SENOPATI_MODEL` | `qwen3:14b` | model di gateway Senopati |
| `SENOPATI_API_KEY` | kosong | token ITS untuk fallback; dikirim sebagai `Authorization: Bearer` |
| `OLLAMA_BASE_URL` | `http://localhost:11434/v1` | endpoint OpenAI-compatible bawaan Ollama |
| `OLLAMA_MODEL` | `qwen3:14b` | **harus** model yang ada di `ollama list` (mis. `deephat:latest`) |
| `SANCTUM_EXPIRATION` | `1440` | umur token (menit) |

**Kalau `/api/chat-ai` menjawab 503**, berarti Ollama **dan** Senopati sama-sama gagal. Lihat `attempts` di respons
(atau "Jalur provider" di playground) dan `storage/logs/laravel.log`:
- `ollama gagal: tidak dapat dihubungi`: Ollama belum menyala (`sudo systemctl start ollama`).
- `senopati gagal: HTTP 401` / `Authentication Error`: `SENOPATI_API_KEY` kosong atau salah (tidak di-retry).
- `timeout 60 s` / `cURL error 6/7/28`: server tidak terjangkau atau terlalu lama menjawab.

## API Contract

Base URL: `http://127.0.0.1:8000/api`. Semua request mengirim `Accept: application/json` dan
`Content-Type: application/json`. Endpoint bertanda 🔒 wajib `Authorization: Bearer <token>`; tanpa token (atau token
kedaluwarsa/dicabut) responsnya **401** `{"message": "Unauthenticated."}`.

### `POST /login`
Tukar email + password dengan token. Dibatasi 6 request/menit.

```json
// Request
{ "email": "demo@promptlab.test", "password": "password", "device_name": "postman" }

// 200 OK (token hanya tampil sekali; server menyimpan hash SHA-256-nya)
{
  "status": "success",
  "access_token": "1|abc123...",
  "token_type": "Bearer",
  "expires_at": "2026-11-12 09:30:00",
  "user": { "id": 1, "name": "Demo Kelompok 1", "email": "demo@promptlab.test" }
}

// 401
{ "status": "error", "message": "Kredensial salah." }
```

### 🔒 `POST /chat-ai`
Kirim prompt, tunggu jawaban lengkap. Dibatasi 20 request/menit per user.

| Field | Tipe | Aturan |
|---|---|---|
| `prompt` | string | wajib, maks 1000 karakter |
| `theme` | string | opsional: `default` (bawaan), `prompt_engineer`, `context_engineer`, `evaluator` |
| `think` | boolean | opsional: mode berpikir `qwen3` untuk request ini; bila tidak dikirim, ikut `LLM_THINK` |
| `simulate_primary_down` | boolean | opsional, **hanya berlaku bila `APP_DEBUG=true`**: provider pertama dibuat gagal untuk menguji fallback |

```json
// Request
{ "prompt": "Jelaskan middleware!", "theme": "default" }

// 201 Created
{
  "status": "success",
  "data": {
    "session_id": 12,
    "status": "success",
    "provider": "ollama",
    "model_used": "deephat:latest",
    "think": false,
    "theme": "default",
    "prompt": "Jelaskan middleware!",
    "ai_response": "Middleware adalah...",
    "tokens": { "prompt": 30, "completion": 120, "total": 150 },
    "timestamp": "2026-08-28 09:30:00"
  },
  "attempts": [
    { "provider": "ollama", "ok": true, "reason": null }
  ]
}

// 201 Created setelah fallback: data.provider = "senopati", data.model_used = "qwen3:14b", dan
"attempts": [
  { "provider": "ollama", "ok": false, "reason": "tidak dapat dihubungi" },
  { "provider": "senopati", "ok": true, "reason": null }
]

// 422 Unprocessable Content
{ "message": "Prompt maksimal 1000 karakter.", "errors": { "prompt": ["Prompt maksimal 1000 karakter."] } }

// 503 Service Unavailable (semua provider gagal; tetap dicatat dengan status "failed")
{ "status": "error", "message": "Semua server AI tidak dapat dihubungi. Coba lagi nanti." }
```

### 🔒 `POST /chat-ai/stream`
Body sama dengan `/chat-ai`. Respons `Content-Type: text/event-stream` berisi event berikut:

```text
event: attempt
data: {"provider":"ollama","ok":true,"reason":null}

event: thinking
data: {"thinking":true}

event: token
data: {"delta":"Middleware "}

event: token
data: {"delta":"adalah "}

event: done
data: {"status":"success","data":{ ...sama dengan data /chat-ai... }}
```

`thinking` hanya dikirim **sekali**, dan hanya bila `think: true`, saat model mulai menalar. Isi penalarannya
(`reasoning_content` dari gateway) **tidak pernah** diteruskan ke klien maupun disimpan.
Bila gagal: `event: error` `data: {"status":"error","message":"Streaming gagal. Coba lagi nanti."}`.
Fallback (bila diaktifkan) hanya terjadi **sebelum** token pertama keluar; teks yang sudah terkirim tidak bisa ditarik.
Validasi (422) dan auth (401) tetap dijawab JSON biasa sebelum stream dibuka.

### 🔒 `GET /chat-ai/history`
Riwayat milik user yang login saja, terbaru dulu, 10 per halaman (`?page=2`).

```json
{
  "data": [ { "session_id": 12, "status": "success", "model_used": "qwen3:14b", "...": "..." } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 10, "total": 1 },
  "status": "success"
}
```

### 🔒 `GET /user` · 🔒 `POST /logout`
`/user` mengembalikan profil pemilik token. `/logout` mencabut **hanya token yang sedang dipakai**
(perangkat lain tetap login) → `{"status":"success","message":"Token dicabut."}`.

## Tema agent PromptLab

System prompt dipilih lewat field `theme` dan disisipkan sebagai pesan `system` pertama
([`config/llm.php`](config/llm.php)). Keempat tema mengikuti alur PromptLab *Ask → Context → Act → Evaluate*.

| `theme` | Peran di PromptLab | Perilaku yang diminta dari model |
|---|---|---|
| `default` | Workspace | Menjawab ringkas, tidak mengarang endpoint/file, menyebut info yang kurang |
| `prompt_engineer` | Prompt Engineering | Menandai bagian ambigu dan constraint yang hilang; saran prompt **hanya bila perlu** |
| `context_engineer` | Context Engineering | Memilih informasi/file yang relevan untuk model beserta alasannya |
| `evaluator` | Evaluation | Membandingkan baseline A vs kandidat B; benar dulu, baru hemat |

Menambah tema cukup dengan menambah satu entri di `themes`. Validasi `ChatAiRequest` dan dropdown playground membaca
daftar yang sama, jadi ikut berubah otomatis.

## Skema `chat_logs`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint | tampil sebagai `session_id` |
| `user_id` | FK → `users` | `cascadeOnDelete` |
| `theme` | string(50) | tema system prompt |
| `provider` / `model` | string, nullable | null bila semua provider gagal |
| `think` | boolean | mode berpikir yang dipakai (migrasi `add_think_to_chat_logs_table`) |
| `prompt` / `response` | text / longText | `response` null bila gagal total |
| `status` | string(10) | `success` / `failed` |
| `prompt_tokens` / `completion_tokens` / `total_tokens` | unsigned int, nullable | dari field `usage` LLM |
| `created_at` / `updated_at` | timestamp | indeks `(user_id, created_at)` untuk riwayat |

## Alur fallback (Challenge 3)

1. Coba provider pertama (`ollama`, lokal). Koneksi putus atau HTTP 5xx → ulangi sampai `LLM_RETRIES` kali (jeda 200 ms).
   - Error 4xx (mis. token salah) **tidak** diulang karena hasilnya akan sama.
   - Timeout **tidak** diulang: satu percobaan sudah memakai seluruh `LLM_TIMEOUT`, jadi mengulang hanya melipatgandakan
     waktu tunggu. Langsung pindah ke provider berikutnya.
2. Masih gagal → catat pesan lengkap di `storage/logs/laravel.log`, lanjut ke provider berikutnya (`senopati`).
3. Semua gagal → HTTP 503 + baris log `status = failed`.

Setiap percobaan dilaporkan sebagai `attempts` (`/chat-ai`) atau event `attempt` (`/chat-ai/stream`, terkirim sebelum
jawaban mulai mengalir). Alasan yang dikirim ke klien sengaja singkat: `HTTP 401`, `timeout 60 s`,
`tidak dapat dihubungi`, `disimulasikan mati`. Pesan error mentah hanya ada di log.

**Menguji di playground** (`APP_DEBUG=true`): pilih *Uji fallback → Simulasikan Ollama mati*. Provider pertama dibuat
gagal **sebelum** request dikirim, lalu jalur fallback yang sebenarnya berjalan. Di bawah jawaban tampil
`JALUR PROVIDER  OLLAMA GAGAL: DISIMULASIKAN MATI → SENOPATI BERHASIL`, dan kolom Provider di riwayat berisi
`senopati`. Versi nyata tanpa simulasi: `sudo systemctl stop ollama`, kirim prompt, lalu nyalakan lagi.
Saat `APP_DEBUG=false` tombol ini tidak tampil dan field `simulate_primary_down` diabaikan API.

**Bukti gangguan nyata (2026-11-17):** mekanisme ini sudah menyelamatkan request sungguhan. Saat itu urutannya masih
terbalik (Senopati → Ollama): gateway Senopati menggantung, dan setelah 60 detik Ollama mengambil alih
(`SENOPATI GAGAL: TIMEOUT 60 S → OLLAMA BERHASIL`). Kode fallback tidak bergantung pada urutan provider, jadi perilaku
yang sama berlaku untuk urutan Ollama → Senopati sekarang.

## Test dan standar kode

```bash
php artisan test          # 18 test: auth, validasi, log DB, tema, think, fallback + simulasi, timeout/401 tidak di-retry, 503, SSE, riwayat
./vendor/bin/pint --test  # PSR-12 (pint.json)
```

LLM dipalsukan dengan `Http::fake()`, jadi test tidak butuh token Senopati maupun internet.

## Pemetaan rubrik

| Komponen (bobot) | Bukti |
|---|---|
| Fungsionalitas & koneksi LLM (40%) | `/api/chat-ai`, `LlmClient`, test `chat_returns_resource_and_logs_to_database` |
| Autentikasi & validasi Sanctum (20%) | `auth:sanctum`, `ChatAiRequest`, test `chat_requires_token...`, `prompt_is_validated` |
| Logging & riwayat DB (20%) | `chat_logs`, `/api/chat-ai/history`, sukses dan gagal sama-sama dicatat |
| Challenge A+ (10%) | streaming SSE, 4 tema system prompt, fallback otomatis Ollama lokal → Senopati ITS (sesuai soal, teruji) |
| Kerapian kode (10%) | Pint PSR-12, satu service untuk semua provider, komentar per fungsi |

## Demo Postman (5 menit)

1. `POST /api/chat-ai` **tanpa** token → 401.
2. `POST /api/login` → salin `access_token` ke tab *Authorization → Bearer Token*.
3. `POST /api/chat-ai` dengan prompt 1001 karakter → 422.
4. `POST /api/chat-ai` dengan `theme: prompt_engineer` dan prompt yang sengaja ambigu
   (mis. "Dokumentasikan API login") → 201 + JSON berisi analisis dan saran prompt.
5. `POST /api/chat-ai/stream` → event mengalir satu per satu.
6. Fallback: kirim `"simulate_primary_down": true` (atau di playground pilih *Simulasikan Ollama mati*) →
   `data.provider: senopati` dan `attempts` menunjukkan Ollama gagal → Senopati berhasil. Versi nyata:
   `sudo systemctl stop ollama` (alasan `tidak dapat dihubungi`). Kosongkan juga `SENOPATI_API_KEY` → 503 + log `failed`.
7. `GET /api/chat-ai/history` → semua percobaan tercatat.
