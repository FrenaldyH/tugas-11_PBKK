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
5. `LlmClient::chat()` memanggil Ollama. Kalau gagal → retry → fallback ke Senopati → **Bagian 5**.
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

**File:** `app/Http/Requests/ChatAiRequest.php` (`authorize`, `rules`, `messages`, `theme`).

**Cara kerja**
- Controller menerima `ChatAiRequest`, bukan `Request` biasa. Laravel memvalidasi input **sebelum** method controller
  dijalankan. Kalau gagal, Laravel melempar `ValidationException` dan menjawab **422** dengan daftar `errors`.
  Controller tidak dijalankan sama sekali, jadi server LLM juga tidak dipanggil (dibuktikan oleh
  `Http::assertNothingSent()` di test).
- Aturan: `prompt` wajib, berupa string, maksimal 1000 **karakter** (Laravel menghitung string dengan `mb_strlen`,
  jadi huruf beraksen dihitung 1). `theme` opsional dan harus salah satu kunci di `config('llm.themes')`.
- `messages()` berisi pesan error berbahasa Indonesia. `theme()` mengembalikan `default` bila tema tidak dikirim.

**Kenapa begini**
- Satu FormRequest dipakai **dua endpoint** (`/chat-ai` dan `/chat-ai/stream`). Aturannya cukup ditulis sekali dan
  controller tetap ringkas.
- Daftar tema yang boleh dibaca langsung dari config. Menambah tema baru tidak perlu mengubah validasi.
- `authorize()` mengembalikan `true` karena pengecekan login sudah dilakukan middleware `auth:sanctum`, dan user hanya
  bisa membuat log atas namanya sendiri.

**Test:** `test_prompt_is_validated` (prompt kosong, 1001 karakter, tema tidak dikenal).

**Kemungkinan pertanyaan**
- *Kenapa tidak `$request->validate()` di controller?* Bisa, tapi aturan yang sama harus ditulis dua kali. FormRequest
  juga memisahkan tanggung jawab: controller tidak mengurus validasi.
- *Kenapa batasnya 1000?* Diminta spesifikasi. Batas ini juga melindungi kuota dan memori server LLM.

---

## Bagian 3 — Database & riwayat chat

**File:** `database/migrations/2026_11_10_210214_create_chat_logs_table.php` · `app/Models/ChatLog.php` ·
`app/Models/User.php` (`chatLogs()`) · `ChatAiController` (`record`, `history`) · `database/seeders/DatabaseSeeder.php`.

**Cara kerja**
- Tabel `chat_logs` menyimpan: user, tema, provider, model, prompt, jawaban, status (`success`/`failed`), serta jumlah
  token (prompt, completion, total). Relasinya `User hasMany ChatLog` dan `ChatLog belongsTo User`.
- `record()` menyimpan lewat relasi: `$request->user()->chatLogs()->create([...])`. Dengan cara ini `user_id` diisi
  otomatis dari pemilik token, **bukan dari input klien**. Itulah sebabnya `user_id` sengaja tidak ada di `$fillable`.
- Request yang gagal juga dicatat (`status = failed`, `response` kosong atau berisi teks parsial dari streaming).
- `history()` mengembalikan log milik user itu saja, 10 per halaman, terbaru dulu.

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
- `user_id` dan kolom internal **tidak ikut keluar**.
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

## Bagian 5 — Integrasi LLM & fallback (Challenge 3)

**File:** `config/llm.php` (`order`, `providers`, `timeout`, `retries`, `temperature`) · `app/Services/LlmClient.php`
(`chat`, `providers`, `request`, `payload`, `isTransient`).

**Cara kerja**
- Ollama dan Senopati sama-sama dipanggil dengan format **Chat Completion OpenAI** (`POST {base_url}/chat/completions`).
  Ollama punya endpoint kompatibel di `/v1`, jadi satu kode melayani dua provider.
- `providers()` mengurutkan provider sesuai `LLM_PROVIDER_ORDER`, dan melewati provider yang URL/model-nya kosong.
- `chat()` mencoba provider satu per satu:
  1. Kirim request dengan `timeout(60)`.
  2. Kalau gagal, ulangi sampai `LLM_RETRIES` kali, **hanya** untuk error yang mungkin sembuh (`isTransient`): koneksi
     putus/timeout atau HTTP 5xx. Error 4xx (mis. token Senopati salah) tidak diulang karena hasilnya akan sama.
  3. Masih gagal → tulis `Log::warning`, lanjut ke provider berikutnya.
  4. Semua gagal → lempar `RuntimeException`; controller menjawab **503**.
- `->throw()` ditulis eksplisit karena `retry()` Laravel hanya melempar error sendiri bila jumlah percobaan > 1. Tanpa
  baris ini, dengan `LLM_RETRIES=1`, respons 500 bisa tercatat sebagai "sukses" dengan jawaban kosong.

**Kenapa begini**
- Slide memakai `/api/generate` dengan field `prompt`. Kita memakai format `messages` karena format itu punya peran
  `system` (dibutuhkan tema di Bagian 6) dan sama dengan format Senopati.
- Semua nilai (URL, model, token, timeout) ada di `.env`, jadi tidak ada rahasia di kode.

**Batas yang perlu jujur diakui:** bila server *menggantung* (bukan mati), satu provider bisa menunggu sampai
`timeout × retries` = 60 × 3 = ±3 menit sebelum fallback. Server yang mati (*connection refused*) gagal seketika.
Untuk demo, turunkan `LLM_TIMEOUT` dan/atau `LLM_RETRIES` di `.env`.

**Test:** `test_falls_back_to_senopati_when_ollama_is_down` (Ollama dibuat gagal koneksi, Senopati menjawab, header
Bearer dicek), `test_returns_503_and_logs_failure_when_all_providers_fail`.

**Kemungkinan pertanyaan**
- *Kenapa timeout wajib?* LLM bisa berpikir puluhan detik. Tanpa batas, proses PHP menggantung dan menghabiskan worker
  server.
- *Format API Senopati sudah pasti?* Belum; dokumentasi publiknya tidak ditemukan. Kita mengasumsikan format OpenAI dan
  semuanya bisa diatur dari `.env` (tertulis di README).
- *Bagaimana test berjalan tanpa Ollama?* `Http::fake()` memalsukan semua respons HTTP. Test jadi cepat, tidak butuh
  GPU, dan hasilnya selalu sama.

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

**Bukti:** pada uji dengan server LLM tiruan yang mengirim 1 kata per 0,3 detik, event `token` tiba di klien dengan
jeda 0,3 detik juga. Artinya data benar-benar mengalir, bukan dikirim sekaligus di akhir.

**Kenapa begini**
- Fallback hanya bisa terjadi **sebelum** token pertama. Setelah teks terkirim ke pengguna, teks itu tidak bisa ditarik
  kembali. Kalau putus di tengah, teks parsial tetap disimpan dengan status `failed`.
- Validasi (422) dan auth (401) tetap berupa JSON biasa karena keduanya berjalan sebelum stream dibuka.

**Test:** `test_stream_sends_sse_events_and_logs_full_text`; tema dibuktikan di
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
| 5 LLM & fallback | `LlmClient::chat`, `config/llm.php` | fallback, 503 | Fungsionalitas (40%) + Challenge (10%) |
| 6 Stream & tema | `LlmClient::stream`, `ChatAiController::stream` | stream SSE, system prompt | Challenge (10%) |

Kerapian kode (10%) menjadi tanggung jawab bersama: `./vendor/bin/pint --test` harus `passed`.
