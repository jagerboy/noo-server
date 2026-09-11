# NOO+ Server Documentation & Architecture Decisions

## Master Data - Master Salesman (`/principal/master-salesman`)
- **Controller**: `app/Http/Controllers/Web/EdpMasterController.php` (`masterSalesman`, `getFilterOptions`)
- **View**: `resources/js/Pages/Edp/Master/MasterSalesman.vue`
- **Fitur Filter Bertingkat (Cascading Filter)**:
  - Tersedia 4 filter: **REGION**, **ENTITY**, **CABANG / BRANCH**, dan **CARI SALESMAN**.
  - Berlaku untuk seluruh role: `SUPERADMIN`, `ADMIN_PRINCIPAL`, dan `EDP_REGION`.
  - Filter bertingkat:
    1. Memilih **Region** akan menyaring daftar **Entity** dan daftar **Cabang** yang tersedia.
    2. Memilih **Entity** akan menyaring daftar **Cabang** yang terikat dengan entity tersebut (baik dengan region maupun tanpa region terpilih).
    3. Jika Region atau Entity diubah ke pilihan yang tidak lagi memuat Entity/Cabang yang sedang aktif, pilihan dropdown yang terdampak otomatis di-reset.
  - **Instant Client-Side Filtering**: Dilakukan pada browser (Vue 3 `computed`) untuk kecepatan respons tanpa perlu reload halaman atau mencemari query URL.
  - **Entity Column**: Kolom `Entity` ditampilkan di tabel Master Salesman untuk memverifikasi entitas principal masing-masing salesman.

## Executive Dashboard Principal (`/principal/dashboard`)
- **Controller**: `app/Http/Controllers/Web/EdpDashboardController.php` (`index`, `exportChartExcel`, `exportChartPdf`)
- **Service**: `app/Services/ExcelExportService.php` (`generateDashboardChartExcel`)
- **View**: `resources/js/Pages/Edp/Dashboard.vue`
- **Print/PDF Template**: `resources/views/edp/dashboard_chart_pdf.blade.php`
- **Sinkronisasi Filter Grafik**:
  - Filter tahun mandiri di masing-masing 4 grafik telah dihilangkan seluruhnya.
  - Seluruh grafik tersinkronisasi mengikuti filter global (Bulan & Tahun) dari bilah filter utama di atas halaman.
- **Fitur Export (Excel, PDF, JPG)**:
  - Tersedia pada 3 grafik utama:
    1. **Perbandingan Status Submisi NOO** (`comparison`)
    2. **Analisis Submisi vs Approval per Region Area** (`areas`)
    3. **Sebaran Submisi per Tipe Outlet / Channel** (`outlet_types`)
  - Grafik **Top Cabang Submisi NOO Terbanyak** tidak memiliki tombol ekspor (sesuai instruksi pengguna).
  - Desain tombol: Dropdown menu minimalis elegan `Export ▼` (tanpa emoji/ikon berlebihan).
  - **Excel (.xlsx)**: Dihasilkan via `PhpSpreadsheet` dengan judul besar laporan eksekutif, metadata filter aktif, tabel ringkasan metrik statistik grafik, dan tabel detail data pengajuan NOO lengkap (Region, Entity, Cabang Distributor, Nama Toko, Salesman, Tanggal Submisi, Status, dan Tipe Outlet).
  - **PDF Document**: Dihasilkan via Blade print view dengan styling khusus cetak `@page { size: A4 landscape; margin: 10mm; }`, tabel `table-layout: fixed` dengan `word-wrap: break-word` (menjamin **fit to width** tanpa overflow/terpotong), otomatis memicu dialog cetak/simpan PDF browser.
  - **Gambar JPG (.jpg)**: Dihasilkan via HTML5 high-res Canvas rendering langsung di browser dan diunduh instan sebagai file gambar `.jpg` resmi beresolusi tajam.

## Portal SPV Area - Login (`/spv-login`)
- **Controller**: `app/Http/Controllers/Auth/SpvLoginController.php` (`create`, `store`, `destroy`)
- **View**: `resources/js/Pages/Auth/SpvLogin.vue`
- **Asset Visual**: `Photo-Pabrik-ASW-Foods-Revisi.jpg` (disajikan via `public/Photo-Pabrik-ASW-Foods-Revisi.jpg` dan fallback route `routes/web.php`)
- **Desain Antarmuka (UI/UX)**:
  - **Split-Card Layout Modern**: Menggunakan kanvas luar dengan latar gradien halus berkarakter (`#E2E8F0` via `#E8EDF5` to `#DCE4EF`) dan kartu kontainer `rounded-[26px] sm:rounded-[30px]` berbayang lembut (`shadow-[0_20px_60px_-15px_rgba(15,23,42,0.12)]`).
  - **Sisi Kiri (Form Kredensial SPV)**:
    - Form bersih dan intuitif dengan brand identity NOO+, ASWFOODS, dan INAFOODS.
    - Input Salescode otomatis diformat huruf kapital (`uppercase`).
    - Input Password dilengkapi tombol **Eye / Eye-Slash toggle** (SVG icon inline) untuk melihat/sembunyikan password.
    - Opsi **"Ingat Salescode di perangkat ini"** dengan penyimpanan otomatis ke `localStorage` (`noo_spv_remembered_salescode`).
    - Animasi getar responsif (`animate-shake`) saat terjadi kesalahan autentikasi serta alert pesan error modern.
    - Tombol masuk dengan gradien korporat dinamis (Royal Purple `#542B85` ke Navy `#1E2B7B`) dilengkapi indikator loading spinner animasi SVG.
  - **Sisi Kanan (Hero Visual Pabrik & Text Blur Fade)**:
    - Menampilkan foto fasilitas manufaktur **Pabrik ASW Foods Revisi** (`Photo-Pabrik-ASW-Foods-Revisi.jpg`) dengan posisi yang dinaikkan (`object-[center_70%]`, `translate-y-8 md:translate-y-10`, `scale-[1.18] md:scale-[1.20]`) sehingga atap lengkung kuning dan kompleks gedung pabrik tampak jelas dan tidak tertutup teks.
    - Teks deskripsi resmi di bagian bawah foto bertuliskan **"NOO+ (New Open Outlet) - SPV Area"** dengan **background teks berbentuk blur fade ringkas** (`bg-gradient-to-t from-black/95 via-black/75 to-transparent backdrop-blur-[2px]`) yang proporsional dan tidak menutupi gedung pabrik.
  - **Dukungan Tampilan Desktop & Tablet**:
    - Kontainer fleksibel `max-w-md md:max-w-3xl lg:max-w-[940px]`.
    - Pada tablet (iPad / Android Tablet 768px - 1024px) dan desktop, tata letak dual-panel (form kredensial 6 cols & visual hero pabrik 6 cols) tampil proporsional, lapang, dan bebas dari distorsi.

## Portal SPV Area - Inbox Submisi (`/spv/inbox`)
- **Controller**: `app/Http/Controllers/Web/SpvPortalController.php` (`index`, `approve`, `reject`)
- **View**: `resources/js/Pages/Spv/Inbox.vue`
- **Komponen Pendukung**: `resources/js/Components/Pagination.vue`, `resources/js/Pages/Spv/Components/ProgressTrackingModal.vue`
- **Tata Letak Tabel 8 Kolom Minimalis (Sesuai Referensi & Bebas Ikon Sampah)**:
  - Seluruh ikon emoji sampah (👤, 📞, 📍, 🏢, 🕒, 📅, ⚠️, ⚡, 📋) telah dihilangkan agar desain tampil bersih, modern, dan minimalis.
  - Urutan 8 kolom tabel:
    1. **Toko & Sub-Toko**: Menampilkan Nama Toko (`nama_noo`), badge kode sub-toko (`type_outlet_code`) & deskripsi. **Tanpa info principal (badge ASWFOODS dihilangkan) dan tanpa badge EXIF**. Nama pemilik outlet dan No. HP disatukan secara ringkas di bawahnya (`Nama Pemilik • No HP`).
    2. **Salesman & Cabang**: Nama salesman (`salesman_name`), kode salesman, dan nama cabang binaan (`branch_name` / `branch_id`).
    3. **Alamat Ringkas**: Alamat jalan toko (`alamat_noo`) dan wilayah ringkas (Kecamatan, Kab/Kota).
    4. **Status**: Badge pill status approval (*Pushed to SPV*, *Approved SPV*, *Approved EDP*, *Ditolak*).
    5. **Cust Dist.**: Monospace badge rapi untuk `custcode_distributor` (atau tanda `-` bila belum diisi).
    6. **Cust Principal**: Monospace badge rapi untuk `code_noo_principal` (atau tanda `-` bila belum diisi).
    7. **Rute Kunjungan**: Rincian hari kunjungan (`Hari: ...`) & pola minggu (`Minggu: ...`), atau teks minimalis `Belum di-set`.
    8. **Aksi**: Tombol biru solid `#2563EB` **Kelola & Rute** untuk memicu modal detail verifikasi dan penyusunan rute toko.
- **Filter Toolbar Semi-Bertingkat (Cascading Filter)**:
  - **Cabang Binaan**: Dropdown cabang yang secara ketat hanya menampilkan daftar cabang (`myBranches`) yang dinaungi oleh akun SPV aktif dari tabel `master_spvs`.
  - **Sub-Grup**: Dropdown sub-grup / tipe outlet toko.
  - **Filter Status SPV**: Pilihan untuk membedakan data:
    - `Semua Status SPV`
    - `Belum Diproses SPV` (Menunggu review & approval SPV: status `PUSHED_TO_SPV` / `ADMIN_APPROVED`)
    - `Sudah Diproses SPV` (Sudah diapprove SPV atau ditolak SPV)
  - **Filter Status EDP (Terpisah & Semi-Bertingkat)**:
    - Pilihan: `Semua Status EDP`, `EDP: Pending Review`, `EDP: Approved`, `EDP: Rejected`.
    - **Logika Semi-Bertingkat**: Ketika Status SPV bernilai *"Belum Diproses SPV"*, filter Status EDP otomatis dinonaktifkan (*disabled*) dengan label *"Belum ke EDP"* karena data submisi belum dikirim ke principal EDP. Begitu status EDP tertentu dipilih, filter status SPV otomatis menyesuaikan data yang sudah diproses.
  - **Urutan (Sort)**: Dropdown sort Terbaru, Terlama, Nama Toko (A-Z / Z-A), dan Salesman (A-Z).
  - **Metric Cards Interaktif**: Kartu metrik di atas (*Pending Review*, *Disetujui SPV*, *EDP Approved*, *Ditolak*) dapat diklik untuk menyaring data dengan instan.
  - **Dua Lapis Filtering (Hybrid Fast & Server Query)**: Menggunakan Inertia `router.get` dengan query URL otomatis ter-update (debouce 400ms) sekaligus reaktivitas client-side Vue untuk performa kilat.
- **Perbaikan Dropdown Pagination & Input Select**:
  - Pada `Pagination.vue` dan seluruh `<select>` dropdown di bilah filter, diterapkan `appearance-none` dengan padding kanan lapang (`pr-7` / `pr-8`) serta ikon panah dropdown chevron SVG tersendiri di posisi yang presisi (`right-2` / `right-2.5`), menjamin teks pilihan panjang (seperti *"Tampilkan Semua"*) **tidak akan pernah menimpa ikon panah dropdown**.

## Standar Perintah Deployment Git (CMD & Terminus)
- **Wajib Diberikan**: Setiap kali ada perubahan fitur atau kode selesai dikerjakan, selalu sertakan perintah copy-paste berikut:
  1. **CMD Windows (Lokal)**:
     ```cmd
     cd /d d:\AndroidStudioProjects\noo-server
     npm run build
     git add .
     git commit -m "<deskripsi perubahan>"
     git push origin main
     ```
  2. **Terminus (SSH Server ASWFOODS 172.22.1.232)**:
     ```bash
     cd /var/www/noo-server
     git pull origin main
     docker compose exec app php artisan optimize:clear
     ```

## Portal Admin Distributor - Login (`/distributor-login`)
- **Controller**: `app/Http/Controllers/Auth/DistributorLoginController.php` (`create`, `store`, `destroy`, `getBootstrapData`)
- **View**: `resources/js/Pages/Auth/DistributorLogin.vue`
- **Inspirasi Desain**: Dribbble Form Prototype (Dual-Panel Split Container)
- **Tata Letak & Fitur**:
  - **Panel Kiri (Form Kredensial Bertingkat Ringkas & Tanpa Scroll)**:
    - Desain proporsional hemat vertikal sehingga muat 100% di layar laptop/desktop tanpa scrollbar (`md:overflow-hidden`, padding responsif compact).
    - Seluruh dropdown dibuat satu baris penuh (*full-width*) agar nama Region dan Entity tidak terpotong (...).
    - Menghilangkan duplikasi ikon dropdown (hanya 1 ikon chevron bersih pada setiap select via `style="background-image: none !important;"`).
    - Dilengkapi **satu tombol reset tunggal (`✕ Reset Pilihan`)** terpusat di baris Principal Area yang muncul secara dinamis jika salah satu atau seluruh dropdown telah dipilih, mereset seluruh pilihan bertingkat sekaligus.
    - Dropdown bertingkat (*cascading*): **Principal Area** (ASW SUMATERA, ASW JAWA, ASW PULAU, INA JAWA, INA PULAU, INA SUMATERA), **Region**, **Entity Principal**, dan **Branch / Distributor**.
    - Input **PIN Branch** dalam format password dengan inline **Eye / Eye-Slash toggle** (SVG icon), proteksi kerahasiaan, dan pesan error informatif.
    - Opsi **"Ingat pilihan cabang di perangkat ini"** dengan penyimpanan otomatis di `localStorage` (`noo_distributor_remembered_branch`).
    - Animasi getar (`animate-shake`) saat terjadi kegagalan otentikasi.
    - Tombol masuk dengan gradien korporat dinamis (Navy `#1E2B7B` ke Royal Blue `#2563EB`) dilengkapi indikator loading spinner animasi SVG.
  - **Panel Kanan (Slide Animated Alur NOO+ - Dribbble Style)**:
    - Background gradien biru korporat (`from-[#1E2B7B] via-[#1D4ED8] to-[#2563EB]`) berpadu dengan partikel melayang (*floating confetti shapes*).
    - Stack kartu mengambang (*floating 3D-card mockups*) interaktif yang memvisualisasikan 4 tahapan alur NOO+:
      1. **Tahap 1**: *Salesman Input di Lapangan* (Mockup visual frame aplikasi mobile Android NOO+ v2.0 form **Inputan SE** yang lengkap dengan: Data Toko Baru, **GPS Locked & Akurasi 3.2m**, serta **2 Foto Fisik Toko: Tampak Depan & Tampak Dalam** terlampir, plus tombol submit data).
      2. **Tahap 2**: *Admin Input Customer Code Distributor* (Mockup ERP cabang, input customer code versi distributor untuk di-mapping-kan ke Eskalink).
      3. **Tahap 3**: *SPV Mengisi & Menentukan JKS* (Mockup validasi rute kunjungan, penetapan hari H1-H7 & minggu M1-M4, verifikasi radius outlet).
      4. **Tahap 4**: *Principal (EDP) Melakukan Approval NOO* (Validasi Customer Master dan inject data outlet baru ke Eskalink).
    - Navigasi slide otomatis (interval 5.5 detik, pause saat hover), indikator titik (*morphing pagination dots*), dan tombol chevron prev/next.

## Impor Migrasi Total & Backup Foto Legacy
- **Commands**:
  - `php artisan noo:import-full-legacy {path}`
  - `php artisan noo:import-legacy-photos {path} [--dry-run] [--force]`
- **Command Files**:
  - `app/Console/Commands/ImportFullLegacyCommand.php`
  - `app/Console/Commands/ImportLegacyPhotosCommand.php`
- **Fitur Impor & Optimasi**:
  1. **Full Legacy Migrator (`noo:import-full-legacy`)**:
     - Menggabungkan data toko dari sheet Admin Distributor (`02_ADMIN_SHEET/`), SPV (`03_SPV_SHEET/NOO_SPV_JKS.xlsx`), dan EDP (`04_EDP_SHEET/NOO_EDP_REVIEW.xlsx`).
     - **Deduplikasi Strict**: Menggunakan `request_id` (UUID) sebagai kunci unik untuk mengabaikan submisi yang sudah ada di database `noo_submissions`.
     - **Auto Branch Registration**: Pendaftaran otomatis kode cabang baru ke `master_branches` bila belum terdaftar.
     - **Optimasi RAM PhpSpreadsheet**: Menggunakan `setReadDataOnly(true)` dan menonaktifkan evaluasi formula (`$calculateFormulas = false`) serta formatting (`$formatData = false`) di `toArray()` agar memuat file Excel besar (>40.000 baris) dengan cepat tanpa OOM (Out Of Memory / Exit Code 137) di container Docker.
     - **Excel Serial Date Converter**: Menggunakan `\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject()` untuk mengonversi angka float serial Excel (contoh: `46134.6455` -> `2026-04-22 15:29:32`), menyelesaikan masalah tanggal `submitted_at` terset ke Epoch Unix `1970-01-01 12:49:10`.
     - **Auto Repair Tanggal 1970**: Otomatis memperbarui kolom `submitted_at` pada data toko lama di database yang terlanjur bernilai `1970-01-01`.
  2. **Legacy Photo Importer (`noo:import-legacy-photos`)**:
     - **Multi-Passe Engine**:
       - **Passe 0**: Deteksi UUID `request_id` di folder/filename foto (100% Direct Match).
       - **Passe 1**: Pencocokan Kode Principal (`code_noo_principal`, `previous_code_noo_principal`, `custcode_distributor`).
       - **Passe 2**: Pencocokan Nama Toko (`nama_noo`).
       - **Passe 3**: Pencocokan Cabang + Tanggal Presisi (`branch_id` + `submitted_at`).
       - **Passe 4**: Fallback Match per Cabang.
     - **Klasifikasi Tipe Foto**: Mendukung keyword `DEPAN` (`DEPAN`, `STORE`, `OUTLET`, `OUTSIDE`, `TOKO`, `FRONT`), `DALAM` (`DALAM`, `INSIDE`, `INTERIOR`), dan `KTP` (`KTP`, `SELFIE`, `OWNER`, `PEMILIK`, `TAX`, `NPWP`).
     - **Path Storage Target**: Disimpan ke `storage/app/public/noo_photos/{branch_id}/{submitted_at_date}/{request_id}_{type}.jpg` dan dapat diakses publik secara instan via static route `/storage/`.
