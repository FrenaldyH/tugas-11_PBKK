# Pembagian Bagian untuk Code Review — PromptLab API Hub (Tugas 11)

Kode dibagi menjadi **6 bagian sesuai tanggung jawab**, satu bagian per anggota. Setiap bagian berisi: file yang
dipegang, cara kerjanya, alasan desainnya, test yang membuktikannya, dan pertanyaan yang mungkin diajukan dosen beserta
jawabannya. Isi kolom nama sendiri.

| Bagian | Topik | Pemegang |
|---|---|---|
| 1 | Autentikasi token Sanctum | ............ |
| 2 | Validasi input (FormRequest) | ............ |
| 3 | Database & riwayat chat | ............ |
| 4 | API Resource, bentuk JSON & halaman playground | ............ |
| 5 | Integrasi LLM & fallback (Challenge 3) | ............ |
| 6 | Streaming SSE & tema system prompt (Challenge 1 & 2) | ............ |

**Persiapan semua anggota:** jalankan `php artisan test`. Untuk test bagian sendiri saja:
`php artisan test --filter=nama_test`.

---

## Alur satu request (wajib dipahami semua anggota)

Contoh: `POST /api/chat-ai` dengan `{"prompt": "Jelaskan middleware!", "theme": "prompt_engineer"}`.

1. **Rute** (`routes/api.php`) → middleware `auth:sanctum` memeriksa token → **Bagian 1**.
2. Middleware `throttle:20,1` membatasi 20 request per menit per user.
3. Laravel membuat `ChatAiRequest` dan memvalidasi input. Kalau gagal → 422, controller tidak dijalankan → **Bagian 2**.
4. `ChatAiController@store` meminta `LlmClient` menyusun pesan: system prompt tema + prompt user → **Bagian 6**.
5. `LlmClient::chat()` memanggil gateway Senopati AI ITS. Kalau gagal → retry → fallback ke Ollama lokal → **Bagian 5**.
6. Hasil dicatat ke tabel `chat_logs` → **Bagian 3**.
7. Baris log dibungkus `ChatLogResource` dan dikirim sebagai JSON 201 → **Bagian 4**.

---

## Bagian 1 — Autentikasi token Sanctum

**File:** `app/Http/Controllers/Api/AuthController.php` (`login`, `logout`) · `app/Models/User.php` (trait
`HasApiTokens`) · `config/sanctum.php` (`expiration`) · `routes/api.php` (rute login + grup `auth:sanctum`) ·
`bootstrap/app.php` (`redirectGuestsTo`, `shouldRenderJsonWhen`).

**Cara kerja**
- `login` mencari user berdasarkan email, lalu mencocokkan password dengan `Hash::check()` (bcrypt). Pesan gagalnya
  sama untuk email salah maupun password salah, jadi penyerang tidak bisa menebak email mana yang terdaftar.
- `createToken()` membuat token berformat `id|acak40karakter+checksum`. Teks token asli **hanya dikembalikan sekali**.
  Yang disimpan di tabel `personal_access_tokens` hanya **hash SHA-256**-nya (dicek di
  `vendor/laravel/sanctum/src/HasApiTokens.php`).
- Pada request berikutnya, Sanctum memecah token di tanda `|`, mencari baris berdasarkan `id`, lalu membandingkan hash
  dengan `hash_equals()` (perbandingan waktu-konstan, tahan *timing attack*).
- Kedaluwarsa: token ditolak bila `created_at` lebih lama dari `SANCTUM_EXPIRATION` menit (default 1440 = 24 jam).
  Nilai yang sama juga diisi ke kolom `expires_at` saat login, supaya klien tahu kapan harus login ulang.
- `logout` menghapus **hanya token yang sedang dipakai** (`currentAccessToken()->delete()`). Perangkat lain tetap login.

**Kenapa begini**
- `throttle:6,1` di login: maksimal 6 percobaan per menit, untuk menahan tebak password (*brute force*).
- `bootstrap/app.php`: tanpa pengaturan ini, request tanpa token dan tanpa header `Accept: application/json`
  menghasilkan **error 500**, bukan 401. Penyebabnya, middleware mencoba redirect ke rute `login` yang tidak ada di API
  ini. Bug ini ditemukan oleh test.

**Test:** `test_login_returns_token_and_logout_revokes_it`, `test_login_rejects_wrong_password`,
`test_chat_requires_token_even_without_accept_header`.

**Kemungkinan pertanyaan**
- *Kalau token bocor ke GitHub?* Hapus token itu: `$user->tokens()->where('id', $id)->delete()`. Karena server hanya
  menyimpan hash, kebocoran database tidak langsung membocorkan token yang bisa dipakai.
- *Kenapa Sanctum, bukan Passport?* Passport menerapkan OAuth2 penuh (client, grant, refresh token). Kita hanya butuh
  token per user, dan Sanctum sudah cukup serta lebih ringan.
- *Kenapa tidak ada endpoint register?* Tidak diminta spesifikasi. Akun dibuat lewat seeder (`demo@promptlab.test`).

---

## Bagian 2 — Validasi input (FormRequest)

**File:** `app/Http/Requests/ChatAiRequest.php` (`authorize`, `rules`, `messages`, `theme`, `think`).

**Cara kerja**
- Controller menerima `ChatAiRequest`, bukan `Request` biasa. Laravel memvalidasi input **sebelum** method controller
  dijalankan. Kalau gagal, Laravel melempar `ValidationException` dan menjawab **422** dengan daftar `errors`.
  Controller tidak dijalankan sama sekali, jadi server LLM juga tidak dipanggil (dibuktikan oleh
  `Http::assertNothingSent()` di test).
- Aturan: `prompt` wajib, berupa string, maksimal 1000 **karakter** (Laravel menghitung string dengan `mb_strlen`,
  jadi huruf beraksen dihitung 1). `theme` opsional dan harus salah satu kunci di `config('llm.themes')`.
- `messages()` berisi pesan error berbahasa Indonesia. `theme()` mengembalikan `default` bila tema tidak dikirim.
- `think` opsional dan harus boolean (`true`/`false`/`1`/`0`). `think()` memakai `$this->boolean('think', ...)`, dengan
  nilai bawaan dari `config('llm.think')` (`LLM_THINK`). Jadi klien yang tidak mengirim `think` tetap mendapat perilaku
  yang ditentukan server.

**Kenapa begini**
- Satu FormRequest dipakai **dua endpoint** (`/chat-ai` dan `/chat-ai/stream`). Aturannya cukup ditulis sekali dan
  controller tetap ringkas.
- Daftar tema yang boleh dibaca langsung dari config. Menambah tema baru tidak perlu mengubah validasi.
- `authorize()` mengembalikan `true` karena pengecekan login sudah dilakukan middleware `auth:sanctum`, dan user hanya
  bisa membuat log atas namanya sendiri.

**Test:** `test_prompt_is_validated` (prompt kosong, 1001 karakter, tema tidak dikenal, `think: "mungkin"`).

**Kemungkinan pertanyaan**
- *Kenapa tidak `$request->validate()` di controller?* Bisa, tapi aturan yang sama harus ditulis dua kali. FormRequest
  juga memisahkan tanggung jawab: controller tidak mengurus validasi.
- *Kenapa batasnya 1000?* Diminta spesifikasi. Batas ini juga melindungi kuota dan memori server LLM.

---

## Bagian 3 — Database & riwayat chat

**File:** `database/migrations/2026_11_10_210214_create_chat_logs_table.php` ·
`database/migrations/2026_11_16_230716_add_think_to_chat_logs_table.php` · `app/Models/ChatLog.php` ·
`app/Models/User.php` (`chatLogs()`) · `ChatAiController` (`record`, `history`) · `database/seeders/DatabaseSeeder.php`.

**Cara kerja**
- Tabel `chat_logs` menyimpan: user, tema, provider, model, prompt, jawaban, status (`success`/`failed`), serta jumlah
  token (prompt, completion, total). Relasinya `User hasMany ChatLog` dan `ChatLog belongsTo User`.
- `record()` menyimpan lewat relasi: `$request->user()->chatLogs()->create([...])`. Dengan cara ini `user_id` diisi
  otomatis dari pemilik token, **bukan dari input klien**. Itulah sebabnya `user_id` sengaja tidak ada di `$fillable`.
- Request yang gagal juga dicatat (`status = failed`, `response` kosong atau berisi teks parsial dari streaming).
- `history()` mengembalikan log milik user itu saja, 10 per halaman, terbaru dulu.
- Kolom `think` (boolean) mencatat apakah mode berpikir dipakai. Kolom ini ditambahkan lewat **migrasi baru**, bukan
  dengan mengedit migrasi lama. Migrasi lama sudah di-push, dan anggota yang sudah menjalankannya tidak akan menerima
  perubahan bila file lama diedit; `php artisan migrate` hanya menjalankan migrasi yang belum pernah jalan. Model
  memakai cast `boolean` karena database menyimpan 0/1.

**Kenapa begini**
- `cascadeOnDelete()`: user dihapus → lognya ikut terhapus, tidak ada baris yatim.
- Indeks `(user_id, created_at)` sesuai pola kueri riwayat: filter per user, lalu urut waktu.
- Kolom token `nullable` karena tidak semua provider mengirim jumlah token (terutama saat streaming).
- Urutan memakai `latest('id')`, bukan `latest()` (kolom `created_at`). Dua log dalam detik yang sama punya
  `created_at` identik sehingga urutannya bisa acak. `id` selalu naik, jadi urutannya pasti.

**Test:** `test_chat_returns_resource_and_logs_to_database` (`assertDatabaseHas`),
`test_returns_503_and_logs_failure_when_all_providers_fail`, `test_history_only_lists_own_logs`.

**Kemungkinan pertanyaan**
- *Kenapa yang gagal ikut dicatat?* Rubrik meminta seluruh kueri terekam. Log gagal juga berguna untuk melihat kapan
  server AI bermasalah.
- *Bisakah user A melihat riwayat user B?* Tidak. Kueri selalu dimulai dari `$request->user()->chatLogs()`, dan ini
  dibuktikan oleh `test_history_only_lists_own_logs`.

---

## Bagian 4 — API Resource, bentuk JSON & halaman playground

**File:** `app/Http/Resources/ChatLogResource.php` · `ChatAiController` (`store` bagian respons, `tokens`,
`history`) · `resources/views/playground.blade.php` · `routes/web.php` · `notes/DESIGN.md`.

**Cara kerja**
- `ChatLogResource::toArray()` menentukan field apa saja yang keluar ke klien. Ada tiga jenis pengaturan:
  - **Mengganti nama:** `id → session_id`, `model → model_used`, `response → ai_response`.
  - **Mengelompokkan:** tiga kolom token menjadi satu objek `tokens`.
  - **Memformat:** `created_at` menjadi `Y-m-d H:i:s` (WIB, `config/app.php`).
- `user_id` dan kolom internal **tidak ikut keluar**. Field `think` ikut keluar supaya klien tahu mode mana yang
  dipakai.
- `store` menjawab **201 Created** (karena satu baris log baru dibuat) dengan bentuk `{"status", "data"}`, sama persis
  dengan contoh di slide tugas.
- `history` memakai `ChatLogResource::collection()` pada hasil `paginate()`. Laravel otomatis menambahkan `links` dan
  `meta` (halaman, total).
- Playground (`/`) adalah satu file Blade dengan CSS dan JS biasa, tanpa build npm. Halaman ini memanggil API yang
  sama seperti Postman. Teks dari AI dirender dengan `textContent`, bukan `innerHTML`, sehingga jawaban LLM tidak bisa
  menyisipkan HTML/script (XSS).

**Kenapa begini**
- Resource adalah "lapisan kontrak". Kalau kolom database diganti nama, cukup ubah `toArray()`; aplikasi klien tidak
  rusak.
- `tokens()` memetakan format `usage` OpenAI (`prompt_tokens`, dst.) ke kolom kita di satu tempat.

**Test:** `assertJsonPath`, `assertJsonStructure`, dan `assertJsonMissingPath('data.user_id')` di
`test_chat_returns_resource_and_logs_to_database`.

**Kemungkinan pertanyaan**
- *Kenapa tidak `return $log;` langsung?* Model mentah membocorkan semua kolom (termasuk `user_id`) dan mengikat klien
  ke struktur tabel.
- *Apa beda `new ChatLogResource($log)` dan `ChatLogResource::collection($logs)`?* Yang pertama untuk satu objek, yang
  kedua untuk daftar (plus metadata pagination).
- *Desainnya dari mana?* `notes/DESIGN.md`: mayoritas bahasa desain NVIDIA, komponen yang tidak ada di NVIDIA diambil
  dari Huawei.

---

## Bagian 5 — Integrasi LLM (Senopati) & fallback (Challenge 3)

**File:** `config/llm.php` (`order`, `providers`, `timeout`, `retries`, `temperature`) · `app/Services/LlmClient.php`
(`chat`, `attempts`, `providers`, `request`, `payload`, `fail`, `reason`, `isTransient`) · bagian "Uji fallback" dan
`renderRoute()` di `resources/views/playground.blade.php`.

**Cara kerja**
- Provider utama adalah **Senopati AI ITS**, gateway LLM kampus di
  `https://senopati.its.ac.id/llm-gateway/v1`, model `qwen3:14b`. Request-nya sama persis dengan contoh `curl` dari
  kampus: `POST {base_url}/chat/completions`, header `Authorization: Bearer <token>`, body `model` + `messages`.
- `request()` memasang token dari `SENOPATI_API_KEY` dengan `withToken()`, yang menghasilkan header Bearer.
- **Fallback (Challenge 3): Ollama lokal** di laptop (`http://localhost:11434/v1`, model dari `OLLAMA_MODEL`, mis.
  `deephat:latest`). Ollama punya endpoint dengan format yang sama dengan Senopati, jadi satu kode melayani dua
  provider. Urutannya diatur `LLM_PROVIDER_ORDER=senopati,ollama`.
- `providers()` mengurutkan provider sesuai `LLM_PROVIDER_ORDER`, dan melewati provider yang URL/model-nya kosong.
- `chat()` mencoba provider satu per satu:
  1. Kirim request dengan `timeout(60)`.
  2. Kalau gagal, ulangi sampai `LLM_RETRIES` kali, **hanya** untuk error yang bisa sembuh cepat (`isTransient`):
     koneksi putus atau HTTP 5xx. Error 4xx (mis. token salah) tidak diulang karena hasilnya akan sama. **Timeout juga
     tidak diulang**, karena satu percobaan sudah menghabiskan seluruh `LLM_TIMEOUT`.
  3. Masih gagal → `fail()` menulis pesan lengkap ke log, mencatat percobaan di `attempts`, lalu lanjut ke Ollama.
  4. Semua gagal → lempar `RuntimeException`; controller menjawab **503** (tetap menyertakan `attempts`).
- **Jalur percobaan (`attempts`)**: setiap provider yang dicoba dicatat sebagai `{provider, ok, reason}` dan dikirim ke
  klien (`attempts` di JSON, event `attempt` saat streaming). `reason()` sengaja meringkas error menjadi `HTTP 401`,
  `timeout 60 s`, `tidak dapat dihubungi`, atau `disimulasikan mati`, supaya URL atau pesan internal gateway tidak bocor
  ke klien. Timeout dikenali dari teks error **atau** dari lamanya percobaan (≥90% `LLM_TIMEOUT`), karena di mode
  streaming Guzzle melaporkan timeout dengan pesan generik "Connection refused".
- **Simulasi** (`simulate_primary_down`): provider pertama sengaja dibuat gagal **sebelum** request dikirim, lalu jalur
  fallback yang asli berjalan. Hanya berlaku bila `APP_DEBUG=true` (`ChatAiRequest::simulatePrimaryDown()`), jadi
  tidak bisa dipakai orang luar di server produksi.
- `->throw()` ditulis eksplisit karena `retry()` Laravel hanya melempar error sendiri bila jumlah percobaan > 1. Tanpa
  baris ini, dengan `LLM_RETRIES=1`, respons 500 bisa tercatat sebagai "sukses" dengan jawaban kosong.

**Kenapa begini**
- Slide memakai `/api/generate` (Ollama) dengan field `prompt`. Kita memakai format `messages` karena itulah format
  gateway Senopati, dan format ini punya peran `system` yang dibutuhkan tema di Bagian 6.
- Token Senopati hanya ada di `.env`, yang tidak ikut Git. Di kode tidak ada rahasia.
- `payload()` mengirim `"think": false` (diatur `LLM_THINK`). `qwen3:14b` secara default "berpikir" dulu sebelum
  menjawab, dan proses berpikir itu dikirim di field terpisah, `reasoning_content`. Hasil ukur ke gateway asli dengan
  pertanyaan satu kata:

  | Pengaturan | Waktu | Token jawaban |
  |---|---|---|
  | Default (berpikir) | 5,5 s | 137, dan 128 di antaranya token berpikir |
  | `/no_think` di system prompt | 2,7 s | 128 (masih berpikir, sakelar ini tidak berpengaruh di gateway ini) |
  | `"think": false` | 0,7 s | 4 |

**Batas yang perlu jujur diakui**
- Bila Senopati *menggantung* (koneksi diterima tetapi tidak menjawab), fallback baru terjadi setelah satu
  `LLM_TIMEOUT` (60 s). Ini terjadi sungguhan pada 2026-11-17: `SENOPATI GAGAL: TIMEOUT 60 S → OLLAMA BERHASIL`.
  Untuk demo, turunkan `LLM_TIMEOUT`, atau pakai tombol simulasi supaya fallback terjadi seketika.
- *Cold start* Ollama: jawaban pertama setelah model dilepas dari GPU butuh ±44 s (terukur di RTX 4050). Jalankan
  `ollama run NAMA_MODEL "halo"` sebelum presentasi. Setelah model dimuat, jawaban singkat ±0,5 s.
- Model fallback (`deephat`, keluarga qwen2 7B) berbeda dari Senopati (`qwen3:14b`), jadi gaya dan kualitas
  jawabannya bisa berbeda. Itu sebabnya `model_used` selalu dicatat.

**Test:**
- `test_chat_returns_resource_and_logs_to_database`: URL, header Bearer, model, dan isi `messages` dicek sama dengan
  contoh `curl` kampus.
- `test_falls_back_to_ollama_when_senopati_is_down`: Senopati gagal koneksi, Ollama menjawab, `attempts` benar.
- `test_simulated_primary_outage_falls_back_in_debug_mode`: simulasi aktif, dan Senopati **tidak pernah** dihubungi.
- `test_simulation_is_ignored_when_debug_is_off`: pengaman produksi.
- `test_timeout_is_not_retried_and_falls_back`: dengan `LLM_RETRIES=3`, Senopati hanya dipanggil sekali.
- `test_stream_timeout_is_recognised_by_elapsed_time` dan `test_stream_reports_fallback_path_before_tokens`.
- `test_wrong_senopati_token_is_not_retried`: 401 hanya dikirim sekali, walau retry diaktifkan.
- `test_returns_503_and_logs_failure_when_all_providers_fail`.

**Kemungkinan pertanyaan**
- *Bagaimana membuktikan fallback saat demo?* Di playground pilih *Simulasikan Senopati mati* lalu kirim prompt. Di bawah
  jawaban muncul `SENOPATI GAGAL: DISIMULASIKAN MATI → OLLAMA BERHASIL`, dan riwayat mencatat provider `ollama`. Versi
  tanpa simulasi: kosongkan `SENOPATI_API_KEY` (alasan `HTTP 401`).
- *Kenapa timeout wajib?* LLM bisa berpikir puluhan detik. Tanpa batas, proses PHP menggantung dan menghabiskan worker
  server.
- *Kalau token Senopati salah, apa yang terjadi?* Gateway menjawab 401. Error ini tidak di-retry, lalu API kita
  menjawab 503 dan alasan aslinya (`Authentication Error`) tercatat di `storage/logs/laravel.log`.
- *Bagaimana test berjalan tanpa token dan internet?* `Http::fake()` memalsukan semua respons HTTP. Test jadi cepat,
  tidak memakai kuota Senopati, dan hasilnya selalu sama.
- *Kenapa mode berpikir dimatikan? Bukankah jawabannya jadi kurang pintar?* Bisa jadi, untuk tugas yang berat. Tapi
  dalam ukuran kami, berpikir membuat jawaban ±8× lebih lambat dan hampir semua token habis untuk proses yang tidak
  ditampilkan. Outline FP PromptLab sendiri juga meminta agar chain-of-thought tidak ditampilkan. Pengaturannya ada di
  `.env` (`LLM_THINK=true`), jadi bisa dinyalakan tanpa mengubah kode bila kualitas lebih penting daripada kecepatan.

---

## Bagian 6 — Streaming SSE & tema system prompt (Challenge 1 & 2)

**File:** `app/Services/LlmClient.php` (`messages`, `stream`, `readSse`) · `ChatAiController` (`stream`, `sendEvent`) ·
`config/llm.php` (`themes`) · fungsi `streamAnswer()` di `resources/views/playground.blade.php`.

**Cara kerja: tema (Challenge 2)**
- `messages()` menaruh system prompt tema sebagai pesan pertama (`role: system`), lalu prompt user (`role: user`).
  Model membaca pesan system sebagai "instruksi peran".
- Empat tema mengikuti alur PromptLab *Ask → Context → Act → Evaluate*: `default` (Workspace), `prompt_engineer`,
  `context_engineer`, `evaluator`.

**Cara kerja: streaming (Challenge 1)**
1. `withOptions(['stream' => true])` membuat Guzzle **tidak menunggu** seluruh body, sehingga data bisa dibaca begitu
   tiba.
2. `readSse()` membaca per 1 KB. Potongan bisa terpotong di tengah baris, jadi sisa baris disimpan di `$buffer`.
   Setiap baris `data: {...}` di-parse, dan `choices[0].delta.content` di-`yield` sebagai satu potongan teks.
   `data: [DONE]` menandai akhir.
3. `stream()` adalah *generator*: memberi potongan teks satu per satu, dan nilai `return`-nya (provider, model, usage)
   diambil controller lewat `getReturn()`.
4. Controller memakai `response()->stream()`. Setiap potongan dikirim sebagai event `token`; di akhir, log disimpan dan
   dikirim event `done` berisi data Resource. Kalau gagal, dikirim event `error`.
5. `sendEvent()` memanggil `ob_flush()` + `flush()` supaya data langsung keluar ke klien, tidak ditahan buffer PHP.
   `ob_flush()` dijaga `ob_get_level() > 0` karena tanpa buffer aktif PHP memunculkan *notice*, dan Laravel mengubahnya
   menjadi exception. Header `X-Accel-Buffering: no` mencegah nginx menahan stream di server produksi.
6. Di browser, playground memakai `fetch()` + `ReadableStream`, bukan `EventSource`. Alasannya, `EventSource` hanya
   bisa GET dan tidak bisa mengirim header `Authorization`.
7. **Mode berpikir:** generator memakai *key* sebagai jenis event. `readSse()` meng-`yield 'thinking' => ''` **sekali**
   saat potongan `reasoning_content` pertama tiba, lalu `yield 'token' => teks` untuk setiap potongan jawaban.
   Controller membaca `foreach ($stream as $event => $delta)` dan mengirim event SSE dengan nama yang sama.
   Playground menampilkan "Menghubungi AI… N s" sejak tombol ditekan (header HTTP baru tiba bersama event pertama),
   lalu "Model sedang berpikir… N s" saat event `thinking` datang, dan menghapusnya saat kata pertama jawaban tiba.

**Bukti**
- Server LLM tiruan yang mengirim 1 kata per 0,3 detik: event `token` tiba di klien dengan jeda 0,3 detik juga. Artinya
  server Laravel benar-benar meneruskan data begitu tiba, bukan menimbunnya.
- Gateway Senopati asli (jawaban 226 token): kata pertama tiba di 2,3 s, kata terakhir di 4,7 s. Teks mengalir selama
  2,45 detik, bukan muncul sekaligus. Token tiba berkelompok (beberapa token per paket jaringan), tetapi bagi pengguna
  teks tetap terlihat mengalir.
- Proses berpikir `qwen3` dikirim di `delta.reasoning_content`. Parser hanya membaca `delta.content`, jadi isi pikiran
  model tidak pernah tampil ke pengguna maupun tersimpan di database.

**Kenapa begini**
- Fallback hanya bisa terjadi **sebelum** token pertama. Setelah teks terkirim ke pengguna, teks itu tidak bisa ditarik
  kembali. Kalau putus di tengah, teks parsial tetap disimpan dengan status `failed`.
- Validasi (422) dan auth (401) tetap berupa JSON biasa karena keduanya berjalan sebelum stream dibuka.

**Test:** `test_stream_sends_sse_events_and_logs_full_text` (memastikan `thinking` dikirim tepat sekali dan teks
penalaran palsu `RAHASIA-PIKIRAN` tidak muncul di respons maupun DB), `test_think_flag_is_sent_to_gateway_and_logged`;
tema dibuktikan di
`test_chat_returns_resource_and_logs_to_database` (`Http::assertSent` memeriksa pesan `system` pertama).

**Kemungkinan pertanyaan**
- *Kenapa streaming penting?* Pengguna melihat kata pertama dalam hitungan milidetik, bukan menunggu 10–15 detik.
  Server juga tidak menimbun seluruh jawaban di memori sebelum mengirim.
- *Bagaimana jumlah token didapat saat streaming?* Request mengirim `stream_options.include_usage = true`, sehingga
  provider mengirim `usage` di potongan terakhir. Kalau provider tidak mendukungnya, kolom token tetap `null`.

---

## Ringkasan

| Bagian | File utama | Test | Rubrik (bobot) |
|---|---|---|---|
| 1 Auth | `AuthController`, `bootstrap/app.php` | login/logout, wrong password, 401 tanpa Accept | Autentikasi & Validasi (20%) |
| 2 Validasi | `ChatAiRequest` | `prompt_is_validated` | Autentikasi & Validasi (20%) |
| 3 Database | migrasi `chat_logs`, `ChatLog`, `history` | logs to database, 503 logged, history own logs | Logging & Riwayat DB (20%) |
| 4 Resource & UI | `ChatLogResource`, playground | JSON path/structure | Fungsionalitas (40%) |
| 5 LLM & fallback | `LlmClient::chat`, `config/llm.php` | fallback, 401 tidak di-retry, 503 | Fungsionalitas (40%) + Challenge (10%) |
| 6 Stream & tema | `LlmClient::stream`, `ChatAiController::stream` | stream SSE, system prompt | Challenge (10%) |

Kerapian kode (10%) menjadi tanggung jawab bersama: `./vendor/bin/pint --test` harus `passed`.
