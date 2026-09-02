# 🚀 Rekap Deployment NOO-Server ke Server ASWFOODS (172.22.1.232)

## 📌 Informasi Server & Project
- **IP Server**: `172.22.1.232` (Server Perusahaan ASWFOODS - Ubuntu 24.04 LTS)
- **User SSH**: `adminit`
- **Direktori Project di Server**: `/var/www/noo-server`
- **Repository GitHub**: `https://github.com/jagerboy/noo-server.git` (Branch: `main`)
- **Port Web Portal**: Port `80` (HTTP default) / Port `8000` (`http://172.22.1.232/`)
- **Port Database PostgreSQL (Container)**: `5433` (Internal container `5432`)

---

## 🛠️ Status & Step yang Sudah Selesai (Completed)

1. **Git Commit & Push dari Lokal (Windows)**:
   - Berhasil dipush ke GitHub repo `origin/main`.

2. **Persiapan Direktori & Container Docker**:
   - Clone repo ke `/var/www/noo-server`.
   - `docker compose up -d --build` (Container `noo_app`, `noo_webserver`, dan `noo_db` running `Up`).

3. **Inisialisasi Container & Migrasi Database**:
   - Composer install, Key Generate, Clear Config.
   - Migrasi PostgreSQL: `docker compose exec app php artisan migrate --force` (**100% SUCCESS**)

4. **Instalasi Node.js v20 LTS & Build Asset**:
   - Upgrade Node.js ke v20 LTS.
   - `npm install` && `npm run build` (**SUCCESS**)
   - Unignore `/public/build` di `.gitignore` agar aset ter-compile selalu ikut ter-push ke repository.

5. **Storage Link & Permissions**:
   - Symlink storage disetup (`public/storage`).
   - Permission `/var/www/storage` dan `/var/www/bootstrap/cache` diset ke `www-data:www-data` (`775`).

6. **Import Master Data / Restore Database**:
   - Dump data SQL dari lokal di-import ke PostgreSQL server container (`noo_v2_db`).
   - Data `master_branches`, `master_salesmen`, `master_spvs`, `users`, `counter_sequences`, dll. berhasil masuk.

7. **Fitur & Fix Terbaru yang Diimplementasikan**:
   - **Fix Login Error (500)**: Penambahan pengecekan null `$user` & `$spvFirst` sebelum mengakses property `password` di `EdpLoginController.php` dan `SpvLoginController.php`.
   - **Fix Master Branch Filter**: Pembersihan awalan `ADMIN.` dari `region_code` serta pencocokan prefix `LIKE 'ASW%'` untuk `entity_code_principal` pada `EdpMasterController.php`.
   - **Region Scope Management (3 Section)**: 
     - Section 1 (Global Scope): Checkbox Semua Region.
     - Section 2 (Principal Area): Single Select (Radio) otomatis memilih sub-region tunggalnya.
     - Section 3 (Single Region): Multiple Select (Checkbox) fleksibel.
     - Penambahan tombol *Reset/Unselect Pilihan*, validasi mandatory, serta perbaikan *disabled state* pada tombol submit.
   - **Fix Rename Outlet Name**: Penyembunyian instan tombol Simpan/Batal saat melakukan update `nama_noo` pada modal Inbox Admin Distributor.
   - **Smart Redirection URL Entrypoints**:
     - `http://172.22.1.232/admin-distributor`: Jika belum login $\rightarrow$ `/distributor-login`. Jika sudah login di device $\rightarrow$ langsung masuk ke `/admin-distributor/inbox`.
     - `http://172.22.1.232/spv`: Jika belum login $\rightarrow$ `/spv-login`. Jika sudah login di device $\rightarrow$ langsung masuk ke `/spv/inbox`.
     - `http://172.22.1.232/principal`: Jika belum login $\rightarrow$ `/principal-login`. Jika sudah login di device $\rightarrow$ langsung masuk ke `/principal/dashboard`.

---

## 🎉 DEPLOYMENT SELESAI
Web Portal NOO Server sudah aktif dan data master telah siap:
🌐 **http://172.22.1.232/**

> [!NOTE]
> **Catatan Server & Docker Execution**:
> Selalu jalankan `npm run build` di lokal sebelum git push. Di server prod, gunakan `docker compose exec app php artisan optimize:clear` untuk memperbarui cache Laravel.
