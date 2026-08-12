# 🌍 Project: NOO+ v2.0 Ecosystem Migration

## 📍 Status & Kemajuan Proyek Terakhir
- **Analisis Sistem Lama (Apps Script & Spreadsheets)**:
  - Telah mempelajari seluruh alur kerja `NOO_API`, `DISTRIBUTOR_PORTAL`, `PORTAL_SPV_NEW`, `NOO_EDP_PORTAL`, dan `NOO_MASTER_PORTAL`.
  - Memahami struktur tabel Google Sheets (`NOO_INBOX`, `JKS_QUEUE`, `EDP_REVIEW_QUEUE`, `COUNTER_SEQ`, dll) serta alur pengunggahan foto ke Google Drive.
- **Duplikasi Aplikasi Mobile**:
  - Aplikasi Android `NOO` telah berhasil diduplikasi menjadi `NOO+ v2.0` di folder `c:\Users\ITSALES-02\AndroidStudioProjects\NOO+ v2.0`.
  - `settings.gradle.kts` diubah menjadi `rootProject.name = "NOO+ v2.0"`.
  - `app/build.gradle.kts` diubah menjadi `applicationId = "com.example.noo2"`, `versionCode = 1`, `versionName = "2.0"`.
- **Portal Web Monolith (Laravel 12 + Vue 3 Inertia + Light Mode Theme)**:
  - Portal Admin Distributor, SPV Area, dan Portal Principal telah terpisah secara arsitektur, rute, dan domain (`DOMAIN_DISTRIBUTOR`, `DOMAIN_SPV`, `DOMAIN_EDP`).
  - Halaman login menggunakan **Cascading Login** (Principal `A - ASWFOODS` & `I - INAFOODS` ➔ Region ➔ Entity ➔ Branch ➔ PIN Branch) yang terurut Ascending (A-Z).
  - Tampilan UI seluruh portal web mengadopsi **Design System Light Mode**:
    - **Font Family**: Google Font `Inter`
    - **Page Title**: `32px / 700` (`#111827`)
    - **Section Title**: `22px / 600` (`#1F2937`)
    - **Body Text**: `14px / 400 / line-height 20px` (`#374151`)
    - **Form Input**: `16px / 400`, **Form Label**: `14px / 500`
    - **Table Header**: `14px / 600` (`#1F2937` on `#F3F4F6`), **Content**: `14px / 400`
    - **Button Text**: `15px / 600` (`#2563EB` hover `#1D4ED8`)
    - **Badge Status**: `13px / 600` (Approved `#DCFCE7`/`#15803D`, Pending `#FEF3C7`/`#B45309`, Rejected `#FEE2E2`/`#B91C1C`, Info `#DBEAFE`/`#1D4ED8`)
    - **Border Radius**: 8–10px
  - Tabel Inbox menggunakan `table-fixed w-full` dengan 2 kolom Cust Code (Distributor & Principal) yang simetris tanpa offside.
  - Penginputan `custcode_distributor` & `admin_notes` dilakukan langsung pada Modal Preview Detail.
  - Foto toko dilindungi (Anti-Save / Disable Right Click) dengan Security Watermark Teks tanpa embel-embel ASWFOODS/INAFOODS.
  - Filter isolasi cabang diperketat per `branch_id` pada model `User` (`$fillable`).
- **Modul Android NOO+ v2.0 (Lokasi, EXIF, & Filter Sub-Grup)**:
  - **Verifikasi EXIF Geotagging & Centroid (<15m)**:
    - Penanaman koordinat GPS instan ke metadata EXIF file JPEG saat shutter kamera foto depan & dalam ditekan (`LocationCentroidHelper.kt`).
    - Algoritma continuous satellite sampling 30 detik untuk menghasilkan koordinat Centroid toko yang presisi dan bebas drift.
    - Validasi Haversine distance (`distDepan <= 15m ATAU distDalam <= 15m`). Jika melebihi 15m, `etLatitude` & `etLongitude` otomatis dikosongkan dan `MaterialAlertDialogBuilder` utuh ditampilkan.
    - Penanganan dialog pop-up utuh untuk foto yang belum diambil atau koordinat GPS foto yang gagal terdeteksi.
  - **Filter Sub-Grup Daerah Principal & Verifikasi PIN Cabang (`MainActivity.kt` & `MobileApiController.php`)**:
    - Dropdown Principal memiliki 6 pilihan sub-grup daerah: `ASWFOODS - SUMATERA`, `ASWFOODS - JAWA`, `ASWFOODS - PULAU`, `INAFOODS - JAWA`, `INAFOODS - PULAU`, `INAFOODS - SUMATERA`.
    - Penyaringan Region mendukung prefix `ASWJWA` (`ASWJWA1`, `ASWJWA2` -> `ASW JAWA 1`, `ASW JAWA 2`) secara terpisah saat memilih `ASWFOODS - JAWA` tanpa tersatukan ke `ASWFOODS - SUMATERA`.
    - Menyaring Region, Entity, Branch, dan Salesman secara bertangga (cascading).
    - **Perbaikan parsing `getCodeFromLabel`**: Menangani pemisahan ID cabang yang terpotong di UI Android secara aman agar `branch_id` (seperti `DAMGT001`) selalu terekstrak dengan presisi.
    - **Penyambungan PIN Branch Terpusat**: Menghapus `unset($b->pin_branch)` pada `MobileApiController.php` endpoint `/api/v1/master/branches` sehingga kolom `pin_branch` dari database PostgreSQL terkirim utuh ke aplikasi Android.
    - **Validasi PIN Cabang (`isPinValid`)**: Menggunakan perbandingan *case-insensitive*, penanganan *trimming*, dan perbandingan serba-luwes untuk memastikan PIN (seperti `3333`) pada cabang `DAMGT001` (Magetan) terverifikasi dengan akurat 100%.
  - **Frame Kamera KTP (`KtpOverlayView.kt`)**:
    - Lebar frame diperbesar hingga 93% dari lebar layar HP untuk foto *close-up* NIK/Nama yang tajam dan terbaca.
    - 100% aman di dalam Safe Zone 1:1 tanpa ada bagian KTP yang terpotong saat penempelan banner watermark foto.

---

# 🧠 Aturan Dokumentasi & Komentar (WAJIB DIPATUHI)
Saya bekerja sendiri (Solo Developer). Kode harus bisa menjelaskan dirinya sendiri.

1. **Bahasa Komentar:**
   - SELURUH komentar dalam kode WAJIB menggunakan **Bahasa Indonesia**.

2. **Kualitas Komentar:**
   - Jangan menjelaskan SYNTAX (Contoh salah: `// Ini loop`).
   - Jelaskan **TUJUAN BISNIS** (Contoh benar: `// Loop rekomendasi PO untuk memfilter stok distributor yang kosong`).
   - Setiap `Function` atau `Method` wajib memiliki komentar blok di atasnya yang menjelaskan:
     - Apa fungsinya?
     - Apa parameternya?
     - Apa yang dikembalikan (return)?

---

# 🛠 Standar Coding & Arsitektur Baru

1. **Architecture:** Monolith.
2. **Stack:** Laravel 12, Vue 3 (Composition API), Inertia.js, Tailwind CSS, PostgreSQL.
3. **Design System:** Light Mode Theme, Inter Font, Prescribed Typography & Color Palette Tokens.

4. **Laravel (Backend):**
   - Gunakan **Strict Types** (`declare(strict_types=1);`) di baris pertama file PHP.
   - Hindari logic berat di Controller. Pindahkan kalkulasi/workflow ke folder `app/Services`.
   - Gunakan **Enum** untuk status workflow NOO dan Role (`App\Enums\...`).

5. **Vue.js (Frontend):**
   - **DILARANG** menggunakan Class-Based Component.
   - **WAJIB** menggunakan `<script setup lang="js">`.
   - Pecah UI yang rumit menjadi komponen kecil di folder `Components`.

6. **Docker Workflow:**
   - Environment berjalan di Docker.
   - Jika diminta menjalankan perintah artisan/composer, gunakan prefix:
     `docker-compose exec app [command]`

7. **Network & Access Requirement:**
   - Database PostgreSQL & Storage Server menjadi **Single Source of Truth** terpusat.
   - **Portal Admin Distributor**: Akses publik (Domain Publik / Reverse Proxy) tanpa menggunakan Google Sheets/Apps Script lagi.
   - **Portal SPV Area, Portal Principal, & NOO Master**: Diakses melalui jaringan internal/port server Ubuntu perusahaan.

---

# 🚫 Larangan Keras
- Jangan pernah menghapus komentar yang sudah ada kecuali kodenya dihapus.
- Jangan gunakan jQuery.
- Jangan gunakan `any` atau `mixed` type jika tipe datanya sudah jelas.
- Dilarang lagi menggunakan komponen Apapun yang berhubungan dengan Google (Google Apps Script, Google Sheets, Google Drive).

---

# 📌 Rencana Pengerjaan Selanjutnya (Next Steps)
1. Verifikasi alur submit dari Android ke Portal Admin Distributor (Light Mode).
2. Membangun & menyesuaikan UI Portal SPV Area dan Portal Principal dengan Design System Light Mode yang seragam.
3. Menjalankan pengujian end-to-end workflow NOO dari SE ➔ Admin Distributor ➔ SPV Area ➔ Portal Principal.

---

## 🚀 Perbaruan Fitur & Optimasi Terbaru (NOO+ v2.0)

1. **Sticky Network & GPS Monitor Bar (`NetworkGpsMonitor.kt`)**:
   - Menampilkan status Latensi Ping, Kecepatan Internet, Akurasi GPS (dalam meter), dan Indikator Sinyal (🟢/🟡/🔴) secara real-time.
   - Pinned sticky di bagian atas `MainActivity` & `OutletFormActivity` sehingga tetap terlihat jelas saat form di-scroll.
   - Menggunakan `pingClient` dedicated (Timeout 1.5s) ke endpoint lightweight `/api/v1/echo` dengan instant live fallback ke socket Google DNS (`8.8.8.8:53`) untuk akurasi latensi tanpa membebankan database.

2. **Modal Preview Foto Ukuran Besar (Anti Blur)**:
   - Menambahkan preview foto resolusi tinggi saat thumbnail foto yang diambil (Depan, Dalam, KTP) diklik pada `OutletFormActivity`.
   - Menggunakan `MaterialAlertDialogBuilder` dengan tombol tutup untuk memastikan foto yang diambil tidak blur/buram sebelum di-submit.

3. **Efisiensi Kuota Internet Super Hemat (Data Saver)**:
   - **Disk HTTP Cache (OkHttp 10MB)** & **GZIP Compression (`Accept-Encoding: gzip`)** di `ApiClient.kt`.
   - **Persistent Local Disk Cache (`MasterCache.kt`)**: Menyimpan `MasterResponse` di `SharedPreferences` sehingga aplikasi langsung terbuka tanpa perlu re-download data master setiap kali diluncurkan.
   - **Kompresi Foto Otomatis**: Foto toko dikompresi dari 5–8 MB menjadi **~150–250 KB per foto** (Kualitas 60%, Max Width 900px).
   - **Total Kuota Data**: Pendaftaran 1 Toko Baru (Form + 3 Foto) hanya memakan **~0.4 - 0.7 MB**, hemat >95% kuota data harian user.

4. **Proteksi Instant Double-Submit**:
   - Memasukkan penguncian variabel `isSubmitting = true` & `updateSubmitButtonState()` secara **sinkron di milidetik pertama UI Thread** saat tombol submit diklik.
   - Tombol Submit seketika ter-disable (`isEnabled = false`), teks berubah menjadi `"Mengirim..."`, dan warna berubah redup, mencegah terjadinya duplikasi data toko jika tombol diklik berulang kali dengan cepat.

5. **Splash Screen Non-Blocking**:
   - Splash screen diberikan timeout maksimal **2.5 detik** (`splashScreen.setKeepOnScreenCondition { !masterReady && !splashTimeout }`) dan melepas kondisi secara otomatis agar aplikasi tidak pernah freeze/stuck saat koneksi lambat.

6. **Indikator Loading & Progress Tracking Toko Ditolak**:
   - Menambahkan indikator loading dan penonaktifan tombol konfirmasi (disable) saat klik konfirmasi Tolak / Approve di modal verifikasi.
   - Progress bar / timeline status pada modal detail tracking untuk toko yang **Rejected** berubah warna menjadi **Merah** secara spesifik pada stage tempat proses terhenti (bukan seluruh baris).
   - Header dan tombol close pada modal detail progress tracking diset `sticky top-0` agar tetap berada di atas saat modal di-scroll.

7. **Sidebar Menu Hover & Animate Icon Toggle**:
   - Submenu Master Data (semi-hide) tetap muncul saat kursor berada di atasnya (on hover) dan otomatis tersembunyi ketika kursor keluar.
   - Tombol toggle sidebar menu pada topbar memiliki animasi transisi smooth dari ikon garis 3 (hamburger) ke ikon silang (`✕`).

8. **Format Default Counter Sequence Cabang Baru**:
   - Saat cabang baru ditambahkan di Master Branch (`storeBranch`), record Counter Sequence dibuat secara otomatis dengan format:
     - `principal_code`: `principal_code` / `entity_code_principal` (contoh: `A` / `ASW`)
     - `prefix`: karakter ke-3, 4, dan 5 dari `branch_id` (contoh: `DAMDN003` ➔ `MDN`)
     - `last_seq`: `0`
   - Dilengkapi fungsi auto-sync sequence untuk cabang yang belum memiliki record sequence.

9. **Manajemen Akun & User Role Manager (`AccountManagement.vue`)**:
   - **Dua Tab Navigasi**: Tab 👥 `Daftar Pengguna` & Tab 🛡️ `Matriks Hak Akses (User Role Manager)`.
   - **Form Add & Edit Akun**: Username, Nama, & Password tetap menggunakan textfield biasa. Role & Region Scope disetting via Radio Buttons terstruktur. Status Akun via Radio Buttons (`Aktif`/`Non-Aktif`).
   - **Matriks Hak Akses (User Role Manager)**: Permisssion matrix table yang mengelompokkan otorisasi menu portal (`NOO VERIFICATION`, `PROGRESS TRACKING NOO`, `NOO MASTER DATA`, `MANAJEMEN AKUN DAN LOGS & AUDIT`) untuk masing-masing peran (**EDP Region**, **Admin Principal**, **Superadmin**).
   - **Tombol Edit & Simpan Matriks**: Tombol `✏️ Edit Matriks` di header card yang mengaktifkan checkbox otorisasi saat diklik oleh Superadmin, dilengkapi tombol `💾 Simpan Matriks` & `Batal` beserta local success banner.

10. **Notifikasi Master Data & Tombol Close Banner**:
    - Seluruh aksi Master Data (Bulk Upload, Add, Edit, Delete) kini menghasilkan notifikasi detail:
      - **Bulk Upload**: Menampilkan total persis baris data (`{inserted}`) yang diimpor.
      - **Add/Edit/Delete**: Menampilkan nama & kode item data yang diproses.
      - Auto-creation cabang distributor jika `branch_id` belum terdaftar pada bulk upload Salesman/SPV untuk mencegah FK constraint error.
    - Banner Notifikasi di atas halaman (`EdpLayout.vue`) dilengkapi tombol close (`✕`) untuk menyembunyikan notifikasi secara manual.

11. **Perbaikan Pemetaan Region Portal Distributor (`DistributorLoginController.php`)**:
    - Memperbaiki logika pencocokan prefix `region_code` untuk `ASW_JAWA` (`ASWJWA1`) yang sebelumnya terhambat hardcoded prefix `ASWJAWA` (double A).
    - Dengan pencocokan fleksibel `ASW` & `JWA`/`JAWA`, memilih Principal **ASW JAWA** di halaman login distributor kini menampilkan region **`ASWJWA1 - ASW JAWA 1`** dan seluruh cabang distributornya secara sempurna.

12. **Section Preview Maps Modal SPV Inbox (`Spv/Inbox.vue`)**:
    - Menambahkan section independen **`🌐 Preview Peta Lokasi Toko`** berbasis Google Maps `roadmap` embed (`output=embed`) tanpa tombol/mode Google Streetview pada Modal Detail Preview SPV Inbox (`/spv/inbox`), presisi persis seperti layout modal preview principal/inbox (`Edp/Inbox.vue`).
    - Diletakkan di bawah section *Track Record Persetujuan (Progress Tracker)* dan di atas *Berkas Foto Toko & KTP*.

13. **Integrasi Identitas Warna Korporat (ASWFOODS & INAFOODS)**:
    - **Palet Logo ASWFOODS**: Merah (`#D9232A`) & Biru Navy (`#1E2B7B`).
    - **Palet Logo INAFOODS**: Royal Purple (`#542B85`) & Crown Gold (`#F59E0B`).
    - Menambahkan token warna di `tailwind.config.js` (`asw.red`, `asw.blue`, `ina.purple`, `ina.gold`) dan CSS Custom Properties (`--asw-red`, `--asw-blue`, `--ina-purple`, `--ina-gold`) di `resources/css/app.css`.

14. **Diferensiasi UI Design & Theme Gradient 3 Portal Utama**:
    - **Portal Admin Distributor** (`AdminLayout.vue` & `DistributorLogin.vue`): Tema visual berbasis Merah ASW (`#D9232A`) & Navy (`#1E2B7B`) dengan lencana `DISTRIBUTOR AREA`.
    - **Portal SPV Area** (`SpvLayout.vue` & `SpvLogin.vue`): Tema visual berbasis INAFOODS Royal Purple (`#542B85`) & Crown Gold (`#F59E0B`) dengan `border-b-2 border-[#F59E0B]` dan lencana mahkota `👑 SPV SUPERVISOR`.
    - **Portal Principal** (`EdpLayout.vue` & `EdpLogin.vue`): Tema visual Executive Dark Multi-Gradient Dual Brand (`from-[#0F172A] via-[#1E2B7B] via-[#542B85] to-[#D9232A]`) sebagai otoritas master terpusat (ASWFOODS + INAFOODS).

15. **Pembaruan Sebutan Portal Utama Menjadi "PORTAL PRINCIPAL"**:
    - Mengubah secara menyeluruh sebutan portal utama dari *"Portal EDP"* menjadi **`PORTAL PRINCIPAL`** (menegaskan bahwa EDP hanyalah salah satu role/peran di dalam Portal Principal untuk NOO).
    - Memperbarui header layout navbar (`EdpLayout.vue`), halaman login (`EdpLogin.vue`), executive dashboard (`Dashboard.vue`), dan manajemen akun (`AccountManagement.vue`).

16. **Penghapusan Total Ikon Mata Bawaan Browser**:
    - Menambahkan aturan CSS khusus (`::-ms-reveal`, `::-ms-clear`, `::-webkit-contacts-auto-fill-button`, `::-webkit-credentials-auto-fill-button`) pada `resources/css/app.css` untuk mematikan ikon mata bawaan browser pada seluruh textfield password.

17. **Perbaikan Pemetaan Dropdown Cabang Distributor Cascading (`DITLG001` - `INA02`)**:
    - Menambahkan properti `'branch_id'` dan `'branch_name'` pada item array `$branchesByRegionEntity` di `DistributorLoginController.php`.
    - Memperbarui template dropdown `DistributorLogin.vue` agar membaca fallback `b.branch_id || b.code` dan `b.label` sehingga tidak ada lagi cabang distributor yang ter-render `undefined - undefined` (`"-"`).

18. **Logo Badge Header Murni `NOO+`**:
    - Memastikan logo badge pada header topbar seluruh portal murni menampilkan teks **`NOO+`** tanpa imbuhan teks "ASW" atau "INA" yang menempel pada badge.



