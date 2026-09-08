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
- **Dukungan Tampilan Desktop & Tablet**:
  - **Header & Metric Cards**: Menggunakan grid responsif 2 kolom di ponsel dan 4 kolom di tablet & desktop (`grid-cols-2 sm:grid-cols-4`) dengan kartu berukuran proporsional (`min-w-[110px] md:min-w-[125px]`).
  - **Filter Bar**: Ditata sejajar di tablet dan desktop (`md:flex-row md:items-center md:justify-between`) sehingga pencarian dan dropdown filter status/sort tidak menumpuk vertikal dan menghemat ruang layar tablet.
  - **Tabel Submisi**: Mendukung lebar minimum adaptif `min-w-[860px] md:min-w-[920px] lg:min-w-[960px]` dengan padding sel responsif (`px-3 md:px-4 py-2.5 md:py-3.5`), teks rapi, dan tombol "Kelola & Rute" yang pas di tablet.
  - **Modal Detail & Rute**: Dialog modal dengan tinggi maksimal adaptif (`max-h-[88vh] md:max-h-[85vh]`), header/body/footer proporsional, tombol pemilihan hari rute (H1-H7) dan minggu rute (M1-M4) yang ramah sentuhan (*touch-friendly*) di tablet.

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
