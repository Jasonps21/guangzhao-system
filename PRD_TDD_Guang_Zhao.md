# PRD & TDD — Sistem Manajemen Anggota & Iuran
### Perkumpulan Sosial Guang Zhao Makassar

> **Versi:** 1.0 (DRAFT) · **Tanggal:** Juni 2026 · **Penyusun:** Jason (byjason.dev)
> Dokumen ini menggabungkan Product Requirements (PRD) dan Technical Design (TDD). Bagian "Keputusan Terbuka" di akhir harus dikunci sebelum implementasi dimulai.

---

## 1. Ringkasan Produk

Aplikasi web (dengan mode PWA untuk ponsel) untuk mengelola **±1000 anggota** beserta **iuran bulanan** sebuah yayasan sosial. Menggantikan pengelolaan manual berbasis Excel yang terpisah-pisah.

**Sasaran utama:**
1. Satu sumber data tunggal yang konsisten.
2. Menghapus ketergantungan pada ingatan perorangan (jejak permanen & dapat ditelusuri).
3. Otomatisasi yang menghilangkan kesalahan "lupa" (mis. anggota non-aktif otomatis tidak ditagih).
4. Dapat dioperasikan pengguna lanjut usia.
5. Berkelanjutan melewati pergantian kepengurusan.

**Batasan kunci yang membentuk seluruh desain:**
- Pengguna mayoritas lansia (60+). Antarmuka harus minim langkah & berbahasa Indonesia.
- Sistem & datanya harus aman walau pengurus/kolektor berganti.
- Penagihan harus mendukung **dua mode** (input lapangan & input admin).

---

## 2. Pengguna & Hak Akses (RBAC)

| Peran | Deskripsi | Hak Akses Utama |
|---|---|---|
| **Super Admin** (Ketua/Sekretaris) | Pemegang kendali penuh, ≥2 orang | Semua + kelola user, lihat audit, kelola backup |
| **Admin/Bendahara** | Pengurus administrasi | CRUD anggota, generate tagihan, input iuran, cetak, laporan |
| **Kolektor** | Penagih lapangan | Hanya lihat anggota di kelompok yang ditugaskan; tandai iuran lunas |

- Implementasi: `filament/filament` Shield **atau** `spatie/laravel-permission` langsung.
- **Prinsip wajib:** minimal 2 Super Admin sejak awal — tidak boleh ada satu kunci tunggal.
- Kolektor terhubung ke kelompok (lihat skema `collector_group`).

---

## 3. Keputusan Teknis (TDD)

| Komponen | Pilihan | Catatan |
|---|---|---|
| Backend | **Laravel 12** | Stable terkini |
| Admin panel | **Filament** (v4 stabil / v5 jika sudah Anda validasi) | Pilih major yang Anda nyaman pelihara; kestabilan > kebaruan |
| Database | **PostgreSQL** | |
| UI Kolektor | **Livewire/Volt page sederhana** + PWA | Terpisah dari panel admin; "satu tombol LUNAS" |
| Konversi nama | **`overtrue/pinyin`** | Mode marga (`name()`/surname) untuk akurasi `张 → zhang` |
| Cetak PDF | **`barryvdh/laravel-dompdf`** (default) | Ringan, tanpa headless browser → cocok hosting murah. Pakai `spatie/laravel-pdf` (Chromium) hanya jika butuh presisi layout tinggi |
| Auth | Filament panel auth | |
| Backup | `spatie/laravel-backup` + scheduler | DB harian + ekspor Excel bulanan ke email yayasan |

**Catatan PWA/offline:** MVP berasumsi **online** saat penagihan (kolektor punya data/WhatsApp). Mode offline-sync ditunda ke fase berikutnya bila benar-benar diperlukan.

---

## 4. Model Data (Schema)

### 4.1 `members` (anggota)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| member_number | string, unique | Nomor anggota |
| name_hanzi | string, nullable | Nama Mandarin (aksara Han) |
| name_pinyin | string, nullable | Hasil konversi otomatis, **dapat diedit** |
| name_indonesian | string, nullable | |
| address | text, nullable | |
| phone | string, nullable | Sering kosong di data lama — **tidak wajib** |
| photo_path | string, nullable | Untuk kartu anggota |
| dues_category | enum, nullable | `prasejahtera` / `kurang_mampu` / `menengah` / `mampu` (label opsional) |
| monthly_dues_amount | decimal(12,2) | Nominal per anggota; `0` untuk prasejahtera |
| group_id | FK → member_groups | |
| status | enum | `aktif` / `mengundurkan_diri` / `pindah` / `meninggal` |
| status_changed_at | timestamp, nullable | |
| joined_at | date, nullable | |
| notes | text, nullable | |
| timestamps, softDeletes | | Jangan hard-delete (jejak & keberlanjutan) |

### 4.2 `member_groups` (kelompok)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | mis. "Kelompok 1 — Daya" |
| basis | enum, nullable | `wilayah` / `status` |
| description | text, nullable | |
| timestamps | | |

### 4.3 `dues_records` (tagihan & pembayaran iuran — *ledger*)
> Inti sistem. Satu baris = satu tagihan bulanan satu anggota.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| member_id | FK → members | |
| period_year | smallint | |
| period_month | tinyint | 1–12 |
| amount_due | decimal(12,2) | Snapshot nominal saat tagihan dibuat |
| amount_paid | decimal(12,2), nullable | |
| status | enum | `belum_bayar` / `lunas` |
| paid_at | date, nullable | |
| recorded_by | FK → users, nullable | **Jejak: siapa yang mencatat** |
| collection_method | enum, nullable | `lapangan` / `admin` |
| notes | text, nullable | |
| **unique** | (member_id, period_year, period_month) | Cegah duplikat |
| timestamps | | |

**Keputusan desain — tagihan dibuat di muka (bukan hanya saat bayar):** Setiap awal periode, sebuah scheduled command membuat baris `dues_records` berstatus `belum_bayar` untuk **semua anggota aktif** memakai `monthly_dues_amount` saat itu. Manfaat:
- Query tunggakan jadi sepele (`WHERE status = belum_bayar`).
- Cocok dengan model mental "kupon bulanan = tagihan".
- Anggota non-aktif otomatis tidak dapat tagihan → otomatis tidak dapat kupon. (Lihat §6.5)

### 4.4 `users` (pengurus & kolektor)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id, name, email/username, password | | |
| role | enum | `super_admin` / `admin` / `kolektor` |
| is_active | boolean | |
| timestamps | | |

### 4.5 `collector_group` (pivot)
`collector_id (FK users)` × `group_id (FK member_groups)` — seorang kolektor dapat menangani satu/lebih kelompok.

### 4.6 (Opsional) `activity_log`
Pakai `spatie/laravel-activitylog` untuk audit perubahan data anggota & pembayaran — memperkuat ketertelusuran.

---

## 5. Modul Fungsional

### A. Data Master Anggota
- CRUD penuh (Filament Resource).
- Saat `name_hanzi` diisi → `name_pinyin` terisi otomatis (dapat diedit). (§6.6)
- Upload foto (opsional, dengan fallback bila kosong).
- Filter & pencarian by nama (hanzi/pinyin/Indonesia), kelompok, status.

### B. Manajemen Kelompok
- CRUD kelompok; penugasan kolektor ke kelompok.
- Relation manager: daftar anggota per kelompok.

### C. Pencatatan & Penagihan Iuran (ledger)
- Generate tagihan bulanan (command terjadwal / tombol manual). (§6.1)
- Tandai lunas/belum; dukung **bayar beberapa bulan sekaligus**. (§6.4)
- Setiap aksi menyimpan `recorded_by` + `collection_method`.

### D. Dua Mode Penagihan
- **Mode kolektor (lapangan):** halaman PWA sederhana → login → pilih kelompok → daftar anggota periode berjalan → tombol besar **LUNAS**. (§6.2)
- **Mode admin:** pengurus menandai dari kertas yang dibawa kolektor lansia. (§6.3)

### E. Status Anggota & Otomatisasi
- Ubah status → `status_changed_at` tercatat.
- Non-aktif (`pindah`/`mengundurkan_diri`/`meninggal`) **otomatis dikecualikan** dari generate tagihan, cetak kupon, dan cetak kartu. (§6.5)

### F. Cetak Kupon Iuran
- PDF **F4 (215 × 330 mm)**, **8 kupon/lembar** (2 kolom × 4 baris, ±107 × 82 mm/kupon) dengan garis potong.
- Hanya anggota **aktif** yang punya tagihan periode terpilih; dapat difilter **per kelompok**.
- Isi kupon: nomor anggota, nama, kelompok, periode, nominal, area stempel. (Konten final → §10)

### G. Cetak Kartu Anggota
- PDF ukuran kartu (mis. 86 × 54 mm) berisi data + foto; cetak ulang sewaktu-waktu.

### H. Konversi Nama Pinyin Otomatis
- `overtrue/pinyin` mode marga; hasil sebagai **saran** yang bisa dikoreksi manual (kasus 多音字). (§6.6)

### I. Hak Akses & Multi-Admin
- Sesuai §2. Mode kolektor hanya akses kelompoknya.

### J. Laporan Keuangan
- Total iuran terkumpul per periode & per kelompok.
- Daftar tunggakan (per anggota/kelompok).
- Tanpa pencatatan pengeluaran (di luar lingkup).

### K. Keberlanjutan Data
- Backup DB harian; ekspor Excel bulanan otomatis ke email yayasan.
- Dokumen serah-terima (panduan + kredensial).

---

## 6. Logika Bisnis Kunci

### 6.1 Generate Tagihan Bulanan
```
command: dues:generate {year} {month}
- ambil semua members WHERE status = 'aktif' AND monthly_dues_amount IS NOT NULL
- untuk tiap anggota: firstOrCreate dues_records (member_id, year, month)
  dengan amount_due = monthly_dues_amount saat ini, status = 'belum_bayar'
- idempotent (unique constraint mencegah duplikat) → aman dijalankan ulang
- dijadwalkan tiap tanggal 1, ATAU dipicu manual oleh admin
```

### 6.2 Penagihan Mode Lapangan (Kolektor)
```
kolektor pilih kelompok → tampil daftar dues_records periode berjalan (status belum_bayar)
tap LUNAS pada anggota →
  status='lunas', amount_paid=amount_due, paid_at=now,
  recorded_by=kolektor, collection_method='lapangan'
```

### 6.3 Penagihan Mode Admin
Sama dengan 6.2 tetapi `recorded_by=admin`, `collection_method='admin'`. Admin dapat menandai massal (bulk action) dari daftar.

### 6.4 Bayar Beberapa Bulan Sekaligus
Pilih anggota → pilih rentang periode (mis. Jan–Des) → tandai semua baris terkait `lunas` dalam satu transaksi. (Membuat baris bila belum ada.)

### 6.5 Anggota Berpulang/Keluar (otomatisasi)
```
ubah status → 'meninggal'/'pindah'/'mengundurkan_diri'
efek otomatis:
- tidak ikut dues:generate periode berikutnya
- dikecualikan dari query cetak kupon & kartu
→ "lupa menandai" tidak mungkin terjadi karena tak ada langkah manual untuk dilupakan
```

### 6.6 Konversi Pinyin
```
on saving member (name_hanzi terisi & name_pinyin kosong):
  name_pinyin = Pinyin::name(name_hanzi) // mode marga
admin tetap bisa override manual
```

### 6.7 Cetak Kupon (query)
```
members WHERE status='aktif' AND group_id IN (terpilih)
JOIN dues_records pada (periode terpilih)
→ render Blade → PDF 8-up F4
```

---

## 7. Kebutuhan Non-Fungsional

- **Usability:** tombol besar, langkah minimal, label Indonesia, konfirmasi jelas. Mode kolektor tidak boleh memakai panel admin penuh.
- **Performa:** 1000 anggota × 12 bulan ≈ 12.000 baris/tahun — ringan untuk PostgreSQL.
- **Keamanan & keberlanjutan:** RBAC, multi-admin, soft delete, backup otomatis, kepemilikan akun atas nama yayasan.
- **Auditability:** `recorded_by` di setiap pembayaran + activity log.

---

## 8. Di Luar Lingkup (Out of Scope)
- Pencatatan pengeluaran/santunan duka.
- Pengumpulan foto anggota (tugas yayasan).
- Pelengkapan data kosong pada Excel lama (bertahap oleh pengurus).
- Mode offline-sync penuh (fase lanjutan bila perlu).

---

## 9. Tahapan / Milestone
1. **Fondasi:** auth, RBAC, skema DB, CRUD anggota + kelompok.
2. **Migrasi data:** import Excel → `members` (toleran data tidak lengkap) + konversi pinyin.
3. **Iuran:** ledger, generate tagihan, dua mode penagihan, status & otomatisasi.
4. **Cetak:** kupon F4 8-up + kartu anggota.
5. **Laporan & keberlanjutan:** laporan keuangan, backup, ekspor otomatis.
6. **Uji coba 1 siklus + pelatihan + serah-terima.**

---

## 10. Keputusan Terbuka (kunci sebelum coding)

> Daftar ini sengaja saya buat eksplisit — jangan mulai sebelum ini jelas, agar tidak ada rework.

1. **Penugasan kolektor–kelompok:** satu kolektor = satu kelompok, atau bisa banyak? (skema sudah siap many-to-many, tinggal konfirmasi aturan main).
2. **Konten & layout kupon iuran final:** field apa saja yang dicetak per kupon? Apakah ada nomor seri kupon? Format stempel?
3. **Ukuran & layout kartu anggota:** ukuran final, field, apakah ada QR/barcode keanggotaan?
4. **Data pembayaran historis:** apakah perlu import riwayat lunas lama (kasus "sudah bayar setahun"), atau sistem mulai dari periode berjalan saja?
5. **Foto anggota:** apa yang dicetak bila foto belum ada? (placeholder / kartu tanpa foto?)
6. **Kategori & nominal iuran:** finalisasi label + nominal (15rb/30rb/100rb/0) — apakah hanya 4 tingkat ini?
7. **Keanggotaan ganda kelompok:** dipastikan satu anggota = satu kelompok? (asumsi saat ini: ya).
8. **Mulai periode:** sistem mulai mencatat dari bulan apa? Perlu generate tagihan mundur untuk bulan berjalan?

---
*Akhir dokumen v1.0 (DRAFT).*
