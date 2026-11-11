---
version: alpha
name: PromptLab-API-Hub-design
description: Halaman playground untuk Secure LLM Chat API Hub (login token, kirim prompt, lihat jawaban AI mengalir, riwayat chat). Mayoritas diadaptasi dari bahasa desain NVIDIA (hitam/putih/abu + satu aksen hijau, sudut tajam, garis tipis). Komponen yang tidak ada di NVIDIA (dialog, hover, label/error form) diambil dari Huawei ID. Komponen khusus chat dirancang dari token NVIDIA. Tanpa logo dan nama merek NVIDIA/Huawei di UI.
source:
  nvidia: "VoltAgent/awesome-design-md, design-md/nvidia/DESIGN.md (MIT), dicek ulang ke https://www.nvidia.com/en-us/ pada 2026-11-11 lewat computed style"
  huawei: "Computed style + CSS rule halaman login https://id1.cloud.huawei.com/CAS/portal/loginAuth.html pada 2026-11-11"
  note: "Nilai diekstrak dari CSS publik, tanpa jaminan. Kontras dihitung dengan rumus WCAG 2.x."

colors:
  # --- NVIDIA (apa adanya) ---
  primary: "#76b900"          # NVIDIA Green: latar tombol utama, tab aktif, corner-square. Terverifikasi live.
  on-primary: "#000000"       # teks di atas hijau (8,71:1)
  primary-dark: "#5a8d00"     # pressed + hover (lihat catatan Huawei) + ring fokus di atas putih
  ink: "#000000"
  body: "#1a1a1a"
  mute: "#757575"             # 4,61:1 di putih (lolos AA); 4,3:1 di surface-soft (hanya teks >= 14px tebal)
  canvas: "#ffffff"
  surface-soft: "#f7f7f7"
  surface-dark: "#000000"
  surface-elevated: "#1a1a1a"
  hairline: "#cccccc"         # dekoratif saja (1,61:1), jangan jadi satu-satunya batas input
  hairline-strong: "#5e5e5e"
  on-dark: "#ffffff"
  on-dark-mute: "rgba(255,255,255,0.7)"
  link-blue: "#0046a4"        # hanya link di dalam paragraf (8,71:1)
  error: "#e52020"            # 4,61:1 di putih
  success-deep: "#3f8500"     # teks "berhasil" di putih (4,6:1); hijau brand tidak boleh jadi teks di putih
  # --- ADAPTASI ---
  warning-text: "#b35100"     # sumber #df6500 hanya 3,5:1 di putih; digelapkan agar lolos AA (5,13:1)
  # --- HUAWEI ---
  overlay: "rgba(0,0,0,0.6)"  # latar belakang dialog (hwid-dialog-overlay)
  input-fill: "rgba(0,0,0,0.05)" # latar input "filled" Huawei; dipakai untuk textarea prompt

rounded:
  none: 0px                   # nav, footer, strip gelap
  sm: 2px                     # kartu, badge, input (sumber NVIDIA)
  md: 4px                     # ADAPTASI: tombol, mengikuti situs live (.btncta border-radius 4px), bukan 2px di sumber
  square: 0px                 # corner-square

spacing:
  xxs: 2px
  xs: 4px
  sm: 8px
  md: 12px
  lg: 16px
  xl: 24px
  xxl: 32px
  section: 64px

typography:
  # ADAPTASI: NVIDIA-EMEA / NVIDIA-NALA proprietary -> Inter (substitut yang disebut sumber), fallback Arial.
  # ADAPTASI: NVIDIA tidak punya font mono; JetBrains Mono ditambahkan khusus untuk jawaban AI, JSON, token.
  heading-xl:  { fontFamily: "Inter, Arial, sans-serif", fontSize: 24px, fontWeight: 700, lineHeight: 1.25 }
  heading-md:  { fontFamily: "Inter, Arial, sans-serif", fontSize: 20px, fontWeight: 700, lineHeight: 1.25 }
  card-title:  { fontFamily: "Inter, Arial, sans-serif", fontSize: 17px, fontWeight: 700, lineHeight: 1.47 }
  body-md:     { fontFamily: "Inter, Arial, sans-serif", fontSize: 16px, fontWeight: 400, lineHeight: 1.5 }
  body-strong: { fontFamily: "Inter, Arial, sans-serif", fontSize: 16px, fontWeight: 700, lineHeight: 1.5 }
  body-sm:     { fontFamily: "Inter, Arial, sans-serif", fontSize: 15px, fontWeight: 400, lineHeight: 1.67 }
  button-md:   { fontFamily: "Inter, Arial, sans-serif", fontSize: 14px, fontWeight: 700, lineHeight: 1.25 }  # live: 14px/700
  caption-md:  { fontFamily: "Inter, Arial, sans-serif", fontSize: 14px, fontWeight: 700, lineHeight: 1.43, textTransform: uppercase }
  caption-sm:  { fontFamily: "Inter, Arial, sans-serif", fontSize: 12px, fontWeight: 400, lineHeight: 1.25 }
  code:        { fontFamily: "'JetBrains Mono', ui-monospace, Menlo, monospace", fontSize: 14px, fontWeight: 400, lineHeight: 1.6 }
  code-sm:     { fontFamily: "'JetBrains Mono', ui-monospace, Menlo, monospace", fontSize: 12px, fontWeight: 400, lineHeight: 1.4 }

components:
  # --- NVIDIA (apa adanya / sedikit disesuaikan) ---
  primary-nav:      { backgroundColor: "{colors.surface-dark}", textColor: "{colors.on-dark}", typography: "{typography.body-strong}", height: 64px, rounded: "{rounded.none}" }
  breadcrumb-bar:   { backgroundColor: "{colors.surface-soft}", textColor: "{colors.body}", typography: "{typography.caption-md}", height: 48px }
  button-primary:   { backgroundColor: "{colors.primary}", textColor: "{colors.on-primary}", typography: "{typography.button-md}", rounded: "{rounded.md}", height: 44px, padding: "0 {spacing.xl}" }
  button-primary-active: { backgroundColor: "{colors.primary-dark}", textColor: "{colors.on-primary}" }
  button-outline:   { backgroundColor: transparent, textColor: "{colors.ink}", border: "2px solid {colors.primary}", typography: "{typography.button-md}", rounded: "{rounded.md}", height: 44px }
  button-disabled:  { backgroundColor: "{colors.surface-soft}", textColor: "{colors.mute}", rounded: "{rounded.md}" }  # ADAPTASI: sumber ash #a7a7a7 (2,4:1)
  pill-tab:         { backgroundColor: transparent, textColor: "{colors.ink}", border: "1px solid {colors.ink}", typography: "{typography.button-md}", rounded: "{rounded.sm}", padding: "10px 18px" }
  pill-tab-active:  { backgroundColor: "{colors.ink}", textColor: "{colors.on-dark}" }
  text-input:       { backgroundColor: "{colors.canvas}", textColor: "{colors.ink}", border: "1px solid {colors.hairline-strong}", typography: "{typography.body-md}", rounded: "{rounded.sm}", height: 44px, padding: "0 {spacing.lg}" }  # ADAPTASI: border #ccc -> #5e5e5e agar batas input >= 3:1
  text-input-focused: { border: "2px solid {colors.primary-dark}" }  # ADAPTASI: hijau brand hanya 2,41:1 di putih
  card:             { backgroundColor: "{colors.canvas}", border: "1px solid {colors.hairline}", rounded: "{rounded.sm}", padding: "{spacing.xl}", shadow: none }
  corner-square:    { backgroundColor: "{colors.primary}", size: 12px, position: "top-left, menempel di sudut kartu" }
  badge-tag:        { backgroundColor: "{colors.surface-soft}", textColor: "{colors.body}", typography: "{typography.caption-md}", rounded: "{rounded.sm}", padding: "4px 10px" }
  link-inline:      { textColor: "{colors.link-blue}", textDecoration: underline }
  footer-section:   { backgroundColor: "{colors.surface-dark}", textColor: "{colors.on-dark-mute}", typography: "{typography.caption-sm}", padding: "{spacing.xl} {spacing.xl}" }
  # --- HUAWEI (tidak ada di NVIDIA) ---
  form-field:       { label: "{typography.body-strong} 14px, {colors.ink}, di atas input, jarak {spacing.xs}", helper: "{typography.caption-sm} {colors.mute}", error: "{typography.caption-sm} {colors.error}, input border {colors.error}" }
  textarea-prompt:  { backgroundColor: "{colors.input-fill}", border: "1px solid transparent", focusBorder: "2px solid {colors.primary-dark}", rounded: "{rounded.sm}", minHeight: 140px, padding: "{spacing.md} {spacing.lg}", counter: "kanan bawah, {typography.code-sm} {colors.mute}, merah saat > 1000" }
  dialog:           { overlay: "{colors.overlay}", backgroundColor: "{colors.canvas}", rounded: "{rounded.sm}", padding: "{spacing.xl}", maxWidth: 480px, footer: "kanan: tombol batal (outline 1px {colors.hairline}) + tombol utama" }
  hover-rule:       { rule: "warna aksen digelapkan +/-20% saat hover (Huawei: #007dff -> #0064cc). Di sini: primary -> primary-dark; link-blue -> underline tebal" }
  # --- RANCANGAN BARU (dari token NVIDIA) ---
  response-panel:   { backgroundColor: "{colors.surface-elevated}", textColor: "{colors.on-dark}", typography: "{typography.code}", rounded: "{rounded.sm}", padding: "{spacing.xl}", minHeight: 240px, caret: "blok 8x16px {colors.primary} berkedip saat streaming" }
  meta-row:         { items: "badge-tag untuk PROVIDER, MODEL, TOKEN, STATUS", gap: "{spacing.sm}" }
  status-badge:     { base: "{components.badge-tag}", marker: "kotak 8px di kiri teks: success = {colors.primary}, failed = {colors.error}", text: "SUCCESS / FAILED (selalu ada teks)" }
  alert-inline:     { backgroundColor: "{colors.surface-soft}", borderLeft: "4px solid {colors.error}", textColor: "{colors.body}", typography: "{typography.body-sm}", padding: "{spacing.md} {spacing.lg}" }
  history-table:    { headerBackground: "{colors.surface-soft}", headerTypography: "{typography.caption-md}", bodyTypography: "{typography.body-sm}", rowBorder: "1px solid {colors.hairline}", cellPadding: "{spacing.sm} {spacing.md}" }
---

# Desain PromptLab API Hub (mayoritas NVIDIA, celah dari Huawei)

## Ringkasan
Karakter visual NVIDIA: **bingkai hitam** (nav + footer), **isi putih**, garis tipis abu, sudut nyaris tajam, dan
**satu aksen hijau `#76b900`** untuk aksi utama. Tidak ada gradien, tidak ada bayangan kartu. Hierarki dibangun dari
berat huruf (400 vs 700), bukan dari warna. Halaman playground meniru pola "60/40" NVIDIA: kolom kiri (40%) form,
kolom kanan (60%) panel jawaban gelap, riwayat chat di bawahnya selebar penuh.

Pembagian sumber:
- **NVIDIA:** warna, tipografi, spacing, tombol, tab, input, kartu, badge, nav, footer, corner-square.
- **Huawei ID:** dialog (overlay 60% hitam, footer tombol batal + utama), aturan hover (aksen digelapkan), pola
  label/helper/error form, textarea "filled" `rgba(0,0,0,.05)`. Diambil karena sumber NVIDIA menyebut sendiri
  "Known Gaps": hover, dialog/modal, form lengkap, halaman login.
- **Rancangan baru:** panel jawaban AI (mono, gelap), badge status, alert, tabel riwayat. Tidak ada di kedua sumber.

## Warna
- **Satu aksen:** hijau hanya untuk latar tombol utama, tab aktif, corner-square, dan caret streaming. Maksimal satu
  tombol hijau solid per area pandang (aturan sumber).
- **Hijau tidak pernah jadi teks/garis di atas putih.** Kontras `#76b900` di putih hanya 2,41:1. Di atas hitam
  aman (8,71:1). Untuk teks "berhasil" di putih pakai `success-deep #3f8500` (4,6:1); untuk ring fokus pakai
  `primary-dark #5a8d00` (4,0:1, lolos syarat 3:1 untuk elemen non-teks).
- **Teks:** `ink` judul, `body` paragraf, `mute` metadata. `ash`/`stone` dari sumber tidak dipakai untuk teks (gagal AA).
- **Status:** success = kotak hijau + teks `SUCCESS`; failed = kotak merah + teks `FAILED`. Warna tidak pernah sendirian.

### Tabel kontras (dihitung, rumus WCAG 2.x)
| Pasangan | Rasio | Hasil |
|---|---|---|
| `#000000` di `#76b900` (teks tombol) | 8,71 | AA/AAA |
| `#76b900` di `#ffffff` | 2,41 | **gagal**, jangan untuk teks/fokus |
| `#5a8d00` di `#ffffff` (ring fokus) | 4,00 | lolos non-teks (3:1) |
| `#3f8500` di `#ffffff` | 4,60 | AA |
| `#757575` di `#ffffff` | 4,61 | AA |
| `#e52020` di `#ffffff` | 4,61 | AA |
| `#ffffff` di `#1a1a1a` (panel jawaban) | 17,4 | AAA |
| `#5e5e5e` di `#ffffff` (border input) | 6,48 | lolos non-teks |

## Tipografi
Inter 400/700 untuk UI (substitut resmi yang disebut sumber NVIDIA), JetBrains Mono 400 untuk jawaban AI, JSON, dan
token. Judul halaman `heading-xl` (24/700), judul kartu `card-title` (17/700), label tombol `button-md` (14/700),
eyebrow & header tabel `caption-md` huruf kapital. `display-xl` 48px tidak dipakai (khusus hero marketing).

## Tata letak
- Lebar konten maks 1280px, gutter 24px (16px di mobile). Antar seksi 32–64px.
- ≥1024px: grid 2 kolom `2fr 3fr` (form | jawaban). <1024px: satu kolom, panel jawaban di bawah form.
- Kartu = border `hairline` 1px + corner-square hijau 12px di pojok kiri atas. **Tanpa bayangan.**
- Tabel riwayat dibungkus `overflow-x:auto` agar halaman tidak menggulir ke samping di layar sempit.
- Target sentuh ≥44px (tombol & input 44px).

## Komponen
| Komponen | Sumber | Bentuk |
|---|---|---|
| Nav atas | NVIDIA | hitam 64px, nama app tebal putih + kotak hijau kecil sebagai "logo", kanan: email user + tombol keluar |
| Breadcrumb | NVIDIA | strip `surface-soft` 48px, huruf kapital: `API HUB / PLAYGROUND` |
| Tombol utama | NVIDIA | hijau, teks hitam 14/700, radius 4px, tinggi 44px |
| Tombol sekunder | NVIDIA | transparan, border hijau 2px, teks hitam |
| Hover / pressed | Huawei + NVIDIA | hover dan pressed → `primary-dark` |
| Input teks | NVIDIA | 44px, border 1px, fokus border 2px `primary-dark` |
| Label/helper/error | Huawei | label tebal di atas, helper abu di bawah, error merah + border merah |
| Textarea prompt | Huawei | latar `input-fill`, penghitung `0/1000` di kanan bawah |
| Pilihan mode | NVIDIA pill-tab | `Biasa` / `Streaming`, aktif = latar hitam teks putih |
| Pilihan tema agent | NVIDIA input | `<select>` bawaan dengan gaya input |
| Panel jawaban | baru | latar `#1a1a1a`, teks putih mono, caret hijau saat streaming |
| Baris meta | NVIDIA badge-tag | `PROVIDER` `MODEL` `TOKEN` `STATUS` |
| Dialog token | Huawei | `<dialog>` bawaan HTML, overlay 60%, tampilkan token Bearer sekali + tombol salin |
| Alert error | baru | latar `surface-soft`, garis kiri merah 4px |
| Tabel riwayat | NVIDIA | header `surface-soft` kapital, baris dipisah `hairline` |
| Footer | NVIDIA | hitam, teks putih 70%, satu baris |

## Halaman
1. **Belum login:** kartu login (email, password, tombol "Masuk"). Setelah berhasil, dialog menampilkan token Bearer
   (bisa disalin untuk Postman), lalu form chat muncul.
2. **Sudah login:** form prompt (mode, tema, textarea) di kiri; panel jawaban + meta di kanan; tabel riwayat di bawah.
3. **Keadaan kosong:** panel jawaban berisi teks abu "Jawaban AI akan muncul di sini."; tabel riwayat "Belum ada riwayat."
4. **Error:** alert inline di atas panel jawaban (mis. "Semua server AI tidak dapat dihubungi").

## Do & Don't
**Do:** satu tombol hijau per area; radius 2px kartu/input, 4px tombol; fokus keyboard selalu terlihat
(`outline: 2px solid #5a8d00; outline-offset: 2px`); hormati `prefers-reduced-motion` (caret tidak berkedip); status
selalu bertulisan.
**Don't:** gradien · bayangan pada kartu · tombol pill bulat · hijau sebagai teks di atas putih · warna aksen kedua ·
logo/nama merek NVIDIA atau Huawei · biru Huawei `#007dff` (hanya pola perilakunya yang diambil, bukan warnanya).

## Pemetaan ke CSS (variabel di `resources/views/playground.blade.php`)
```css
/* Design tokens from notes/DESIGN.md */
:root {
    --primary: #76b900; --primary-dark: #5a8d00; --on-primary: #000;
    --ink: #000; --body: #1a1a1a; --mute: #757575;
    --canvas: #fff; --surface-soft: #f7f7f7; --surface-dark: #000; --surface-elevated: #1a1a1a;
    --hairline: #ccc; --hairline-strong: #5e5e5e; --on-dark: #fff; --on-dark-mute: rgba(255,255,255,.7);
    --error: #e52020; --success-deep: #3f8500; --link: #0046a4;
    --overlay: rgba(0,0,0,.6); --input-fill: rgba(0,0,0,.05);
    --r-sm: 2px; --r-md: 4px;
    --font: Inter, Arial, Helvetica, sans-serif;
    --mono: "JetBrains Mono", ui-monospace, Menlo, monospace;
}
```
Font dimuat dari Bunny Fonts (bawaan Laravel): Inter 400/700, JetBrains Mono 400.

## Catatan penyimpangan dari sumber
| Penyimpangan | Alasan |
|---|---|
| Radius tombol 2px → 4px | situs live NVIDIA (`.btncta`) sekarang 4px; kartu/input tetap 2px |
| NVIDIA-EMEA/NALA → Inter | font proprietary; Inter disebut sumber sebagai substitut terdekat |
| Tambah JetBrains Mono | NVIDIA tidak punya mono; jawaban AI dan JSON perlu lebar huruf tetap |
| Ring fokus hijau → `primary-dark` | hijau brand 2,41:1 di putih, gagal syarat 3:1 |
| Border input `#ccc` → `#5e5e5e` | `#ccc` hanya 1,61:1, batas input tak terlihat jelas |
| `warning #df6500` → `#b35100` | 3,5:1 gagal AA untuk teks kecil |
| Dialog, hover, form label/error, textarea filled | tidak ada di NVIDIA ("Known Gaps"); diambil dari Huawei ID, warna biru Huawei diganti token NVIDIA |
| Panel jawaban, badge status, alert, tabel riwayat | tidak ada di kedua sumber; dirancang dari token NVIDIA |
| Tanpa mode gelap terpisah | NVIDIA tidak punya; bingkai hitam + panel gelap sudah memberi kontras |
