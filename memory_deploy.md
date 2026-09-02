# 🚀 Rekap Deployment NOO-Server ke Server ASWFOODS (172.22.1.232)

## 📌 Informasi Server & Project
- **IP Server**: `172.22.1.232` (Server Perusahaan ASWFOODS - Ubuntu 24.04 LTS)

- **User SSH**: `adminit`
- **Direktori Project di Server**: `/var/www/noo-server`
- **Repository GitHub**: `https://github.com/jagerboy/noo-server.git` (Branch: `main`)
- **Port Web Portal**: `8000` (URL: `http://172.22.1.232:8000`)
- **Port Database PostgreSQL (Container)**: `5433` (Internal container `5432`)

---

## 🛠️ Status & Step yang Sudah Selesai (Completed)

1. **Git Commit & Push dari Lokal (Windows)**:
   - Berhasil dipush ke GitHub repo `origin/main` (Commit ID: `900cd94`).

2. **Persiapan Direktori & Container Docker**:
   - Clone repo ke `/var/www/noo-server`.
   - `docker compose up -d --build` (Container `noo_app`, `noo_webserver`, dan `noo_db` running `Up`).

3. **Inisialisasi Container & Migrasi Database**:
   - Composer install, Key Generate, Clear Config.
   - Migrasi PostgreSQL: `docker compose exec app php artisan migrate --force` (**100% SUCCESS**)

4. **Instalasi Node.js v20 LTS & Build Asset**:
   - Upgrade Node.js ke v20 LTS (NodeSource repo).
   - `npm install` && `npm run build` (**SUCCESS - Vite built in 7.21s**)

5. **Storage Link & Permissions**:
   - Symlink storage disetup (`public/storage`).
   - Permission `/var/www/storage` dan `/var/www/bootstrap/cache` diset ke `www-data:www-data` (`775`).

6. **Import Master Data / Restore Database**:
   - Dump data SQL dari lokal di-import ke PostgreSQL server container (`noo_v2_db`).
   - Data `master_branches`, `master_salesmen`, `master_spvs`, `users`, `counter_sequences`, dll. berhasil masuk.

---

## 🎉 DEPLOYMENT SELESAI
Web Portal NOO Server sudah aktif dan data master telah siap:
🌐 **http://172.22.1.232:8000**

> [!NOTE]
> **Catatan Koneksi & Port (Android ApiClient)**:
> Nginx container di server `172.22.1.232` di-binding ke host port `8000` (`8000:80`).
> Oleh karena itu, aplikasi Android `NOO+ v2.0` pada `ApiClient.kt` dikonfigurasi menggunakan:
> `BASE_URL = "http://172.22.1.232:8000/api/v1/"`
