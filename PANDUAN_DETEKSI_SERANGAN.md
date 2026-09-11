# 🔍 Panduan Deteksi Serangan & Monitoring Server NOO+ (`noo.coreappl.id`)

Dokumen ini berisi panduan praktis bagi tim IT / DevOps untuk mendeteksi apakah server sedang dipindai (*scan*), diserang *brute-force*, atau dicoba dieksploitasi oleh tools peretas (seperti **Nmap**, **Metasploit**, **Gobuster**, **SQLMap**, dll).

---

## 1. Indikator & Deteksi Port Scanning (Nmap / ZMap / Masscan)

Penyerang yang menjalankan Nmap akan mengirimkan ribuan paket TCP SYN secara cepat ke berbagai port untuk memetakan servis yang aktif.

### Perintah Cek:
* **Melihat log pemblokiran UFW Firewall:**
  ```bash
  sudo grep -i "UFW BLOCK" /var/log/ufw.log | tail -n 50
  ```
  > **Tanda Serangan:** Muncul puluhan baris berurutan dari 1 IP yang sama mencoba koneksi ke berbagai port (misal 21, 23, 139, 445, 3306, 6379, 8080) dalam rentang beberapa detik.

* **Melihat koneksi aktif dan jumlah koneksi per IP secara real-time:**
  ```bash
  netstat -ant | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -nr | head -n 10
  ```
  > **Tanda Serangan:** Ada IP asing yang memiliki puluhan atau ratusan status `SYN_RECV` atau `TIME_WAIT`.

---

## 2. Indikator & Deteksi Serangan Brute-Force SSH (Port 22)

Botnet internet secara otomatis mencoba ribuan kombinasi username & password pada port SSH.

### Perintah Cek:
* **Melihat 30 percobaan login gagal terakhir:**
  ```bash
  sudo grep "Failed password" /var/log/auth.log | tail -n 30
  ```

* **Daftar 10 IP penyerang terbanyak:**
  ```bash
  sudo grep "Failed password" /var/log/auth.log | awk '{print $(NF-3)}' | sort | uniq -c | sort -nr | head -n 10
  ```
  > **Tanda Serangan:** Angka percobaan di depan IP mencapai puluhan hingga ribuan kali.

---

## 3. Indikator & Deteksi Directory Brute-Force & Metasploit (Web Nginx)

Tools seperti **Metasploit**, **Gobuster**, **Dirbuster**, **SQLMap**, atau **Nikto** membombardir web server dengan ratusan request per detik untuk mencari file sensitif (`.env`, `phpinfo.php`, `actuator`, `wp-login.php`, dll).

### Perintah Cek:
* **Pantau log akses Nginx secara live:**
  ```bash
  docker compose logs -f --tail=100 webserver
  ```
  > **Tanda Serangan:** Log mengalir sangat deras dengan kode status HTTP `404` atau `403` beruntun dalam hitungan milidetik.

* **Cek 15 URL/Path yang paling sering dibom:**
  ```bash
  docker compose exec webserver awk '($9 ~ /403|404/) {print $7}' /var/log/nginx/access.log | sort | uniq -c | sort -nr | head -n 15
  ```

* **Cek User-Agent penyerang yang mencurigakan:**
  ```bash
  docker compose exec webserver grep -iE "nikto|sqlmap|nmap|metasploit|gobuster|dirbuster|python-requests" /var/log/nginx/access.log
  ```

---

## 4. Pemantauan Melalui Dashboard Cloudflare (Paling Cepat & Visual)

Karena **`https://noo.coreappl.id/`** dilindungi oleh Cloudflare:
1. Login ke [Cloudflare Dashboard](https://dash.cloudflare.com/) $\rightarrow$ Pilih domain **`coreappl.id`**.
2. Masuk ke tab **Security** $\rightarrow$ **Events**.
3. Di halaman ini Anda dapat memantau:
   * **Total Threats Blocked**: Jumlah serangan exploit yang langsung ditangkal WAF.
   * **Top Threat Countries / IPs**: Peta negara dan alamat IP penyerang.
   * **WAF Rule Matches**: Aturan yang memblokir serangan (misal: *Bot Traffic*, *SQL Injection attempt*, *Path Traversal*).

---

## 5. Pertahanan Otomatis: Pasang `fail2ban`

Pasang **Fail2ban** agar server otomatis memblokir IP penyerang tanpa perlu Anda pantau secara manual:

```bash
# 1. Install Fail2ban di server Ubuntu
sudo apt update && sudo apt install -y fail2ban

# 2. Buat konfigurasi lokal
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local

# 3. Aktifkan servis Fail2ban
sudo systemctl enable --now fail2ban

# 4. Cek daftar IP yang berhasil ditangkap dan diblokir otomatis
sudo fail2ban-client status sshd
```

Jika ingin membuka blokir IP tertentu (misal IP kantor tidak sengaja terblokir):
```bash
sudo fail2ban-client set sshd unbanip <IP_YANG_DIBLOKIR>
```
