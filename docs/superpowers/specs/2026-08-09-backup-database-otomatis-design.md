# Backup database otomatis

Tanggal: 2026-08-09

## Masalah

Produksi berjalan tanpa backup otomatis apa pun. Survei server pada 2026-08-09
menemukan:

- Crontab `root_test` hanya berisi satu baris: scheduler aplikasi `crm-wa`.
  `/etc/cron.d/` tidak memuat pekerjaan backup.
- Sepuluh berkas backup di `~/backups` dan `~/db_backups`, semuanya dibuat
  manual. Yang terbaru bertanggal 31 Juli 2026.
- Salah satunya, `crm-wa-db-20260723_042353.sql`, berukuran **0 byte** — bukti
  bahwa backup yang gagal diam-diam sudah pernah terjadi di mesin ini.

Data yang dipertaruhkan adalah 1.240 anggota sebuah perkumpulan nyata beserta
riwayat iuran mereka.

## Angka yang mendasari rancangan

| | |
|---|---|
| Database `guangzhao` | 8,7 MB, 26 tabel |
| Dump ter-gzip | ±188 KB (terukur dari snapshot 31 Juli 2026) |
| Disk server | 20 GB, 6,4 GB sisa (68% terpakai) |
| MariaDB | 10.6.22 |
| Zona waktu server | UTC. Pengguna di WITA (UTC+8) |

Retensi 30 hari berbiaya ±5,6 MB — tidak berarti dibanding sisa disk.

## Keputusan dan batasannya

**Backup disimpan di server saja.** Ini keputusan sadar pemilik sistem setelah
diberi tahu konsekuensinya: rancangan ini melindungi dari salah hapus, migrasi
yang merusak, dan bug aplikasi — **tidak** melindungi dari VPS mati, disk rusak,
atau akun disuspend, karena backup berada di disk yang sama dengan databasenya.
Menambahkan salinan ke penyimpanan awan kelak hanya perlu satu langkah di atas
fondasi ini.

**Hanya database `guangzhao`.** Lima database lain di server (`erp_mitra`,
`laku`, `eo_gms`, `adms_db`, `adms_zkteco`) milik aplikasi lain dan berada di
luar lingkup. Karena cakupannya satu aplikasi, kodenya tinggal di dalam repo ini:
ikut version control, ikut ter-deploy lewat `git pull`, dan bisa diuji.

## Rancangan

### Bentuk

Artisan command `backup:database` di `app/Console/Commands/BackupDatabase.php`,
mengikuti enam command yang sudah ada di folder tersebut. Bukan paket baru
(tidak menambah dependensi), bukan skrip bash.

Alasannya: kredensial dibaca dari config Laravel, sehingga tidak diduplikasi ke
skrip atau `~/.my.cnf` yang bisa basi diam-diam ketika password diganti.

### Opsi command

| Opsi | Default | Keterangan |
|---|---|---|
| `--path` | `storage_path('app/backups')` | folder tujuan; dibuat bila belum ada |
| `--keep` | `30` | umur maksimum backup dalam hari |

### Alur

1. Jalankan `mysqldump` dan salurkan ke `gzip`, menghasilkan
   `<path>/guangzhao-YYYYMMDD-HHMMSS.sql.gz`.

   Flag: `--single-transaction --quick --routines --no-tablespaces
   --default-character-set=utf8mb4`. Kelimanya sudah diverifikasi tersedia baik
   di MySQL 8 (klien lokal, dipakai test) maupun MariaDB 10.6 (server).

   `--single-transaction` menghasilkan dump konsisten **tanpa mengunci tabel**,
   sehingga aplikasi tetap melayani kolektor selama backup berjalan.

   Password dikirim lewat variabel lingkungan `MYSQL_PWD`, bukan argumen
   baris perintah, sehingga tidak pernah terlihat di `ps`.

2. Verifikasi hasilnya sebelum dianggap sukses:
   - berkas ada dan tidak nol byte;
   - arsip gzip utuh (`gzip -t`);
   - isinya memuat penanda `-- Dump completed` yang ditulis mysqldump di akhir,
     sehingga dump yang terpotong di tengah tertangkap.

3. Baru setelah verifikasi lolos, pangkas backup yang lebih tua dari `--keep`
   hari.

4. Bila gagal di titik mana pun: hapus berkas gagalnya, **jangan jalankan
   pemangkasan**, dan keluar dengan exit code bukan nol.

Urutan langkah 3 setelah langkah 2 menjaga skenario terburuk: dump gagal
sementara pemangkasan tetap berjalan, sehingga backup lama hilang dan yang baru
tidak ada.

### Pemangkasan yang aman

Pemangkasan hanya menghapus berkas yang memenuhi **semua** syarat berikut:

- berada langsung di dalam folder `--path` (tidak rekursif);
- namanya cocok pola `guangzhao-YYYYMMDD-HHMMSS.sql.gz`;
- **waktu modifikasi berkasnya** (mtime) lebih tua dari `--keep` hari.

Umur dihitung dari mtime, bukan dari tanggal pada nama berkas. Keduanya sama saat
berkas ditulis, tetapi mtime tetap benar bila ada yang menyalin atau mengganti
nama berkas, dan bisa diuji langsung dengan `touch`.

Direktori tidak pernah dihapus. Berkas yang tidak cocok pola tidak pernah
disentuh.

Sifat ini penting karena `~/backups` bercampur milik aplikasi lain — termasuk
`meddiag-files-backup-20260722.tar.gz` berukuran 342 MB. Karena itu cron menulis
ke subfolder tersendiri, `~/backups/guangzhao/`, bukan ke folder campur tersebut.

### Jadwal

Backup berjalan **setiap hari pukul 18:00 WITA**.

Server berzona **UTC**, sehingga baris crontab-nya `0 10 * * *`. Ditulis `0 18`
akan menjalankan backup pukul 2 dini hari waktu Makassar. Crontab diberi komentar
eksplisit agar tidak ada yang "membetulkannya" kelak.

```cron
# Backup DB guangzhao — 10:00 UTC = 18:00 WITA. Server berzona UTC; jangan
# diubah jadi 18 kecuali zona waktu server ikut berubah.
0 10 * * * cd /home/root_test/guangzhao-system && php artisan backup:database --path=/home/root_test/backups/guangzhao --keep=30 >> /home/root_test/backups/guangzhao/backup.log 2>&1
```

Baris scheduler `crm-wa` yang sudah ada tetap utuh.

Cron langsung dipakai, bukan Laravel scheduler: guangzhao tidak punya scheduled
task lain, sedangkan scheduler menuntut proses PHP setiap menit. Satu pekerjaan
harian tidak sepadan dengan biaya itu di VPS yang disknya 68% penuh.

Nama berkas memakai jam UTC (`-100000`) agar cocok dengan `ls` di server.
Tanggal kalendernya tetap sama dengan tanggal WITA, karena 18:00 WITA dan 10:00
UTC jatuh di hari yang sama.

## Pengujian

Berkas baru `tests/Feature/BackupDatabaseTest.php`.

| Test | Yang dijaga |
|---|---|
| Dump memuat struktur dan data | berkas `.gz` terbentuk, terbuka, memuat `CREATE TABLE` untuk `members` |
| Dump bisa dipulihkan | dump di-restore ke database kosong, jumlah baris `members` sama dengan asalnya |
| Backup kedaluwarsa dipangkas | berkas berumur lebih dari `--keep` hari terhapus |
| Backup dalam rentang dipertahankan | berkas yang belum kedaluwarsa tetap ada |
| Berkas asing tidak disentuh | berkas tua yang tidak cocok pola tetap utuh di folder yang sama |
| Dump gagal tidak memangkas | koneksi rusak: exit code bukan nol, backup lama tetap ada |

Uji pulih memakai **database test lokal**, bukan data produksi, sehingga tidak
ada data anggota yang berpindah mesin hanya demi pengujian. Backup yang belum
pernah dicoba pulihkan bukan backup.

## Verifikasi manual di server

1. Jalankan command sekali secara manual, periksa berkasnya terbentuk dan
   ukurannya wajar (±188 KB).
2. Verifikasi integritas arsipnya dengan `gzip -t` dan pastikan penanda
   `-- Dump completed` ada.
3. Periksa daftar tabel di dalam dump berjumlah 26.
4. Pasang baris crontab, lalu tampilkan `crontab -l` untuk membuktikan baris
   `crm-wa` masih utuh.
5. Buktikan penjadwalannya benar dengan memeriksa `date -u` di server terhadap
   waktu WITA.

Restore penuh di server sengaja tidak dilakukan: itu berarti membuat database
baru di mesin produksi, dan pemilik sistem memegang kendali penuh atas database
produksi. Kemampuan restore sudah dibuktikan lewat test lokal.
