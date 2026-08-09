# Cetak kupon anggota tertentu

Tanggal: 2026-08-09

## Masalah

Kupon iuran hanya bisa dicetak satu kelompok penuh sekali jalan
(`ListDuesRecords::printCoupons`, kelompok wajib dipilih). Ketika kupon seorang
anggota robek atau kena air sebelum sampai ke tangannya, satu-satunya cara
mencetak ulang adalah mencetak seluruh kelompoknya — 20 lembar untuk mengganti
satu kupon.

Kebutuhannya: memilih beberapa anggota **lepas dari kelompok**. Tiga nama dari
tiga kelompok berbeda harus tetap bisa dicetak bersama, dan datanya tetap
bersumber dari master data anggota.

## Lingkup

**Bertambah:** satu tombol baru, satu route baru, satu method controller baru,
dan satu mode tata letak baru pada template kupon.

**Tidak berubah:** tombol "Cetak Kupon" per kelompok beserta cache/lock-nya,
imposisi *cut & stack*, format kupon, ukuran kertas F4, model, dan migrasi.

## Rancangan

### Tombol

Aksi header baru di halaman Tagihan, **terpisah** dari "Cetak Kupon" per
kelompok, berlabel **"Cetak Kupon Anggota Tertentu"**. Bukan "Cetak Ulang",
karena pencarian memuat seluruh anggota aktif sehingga tombol ini juga melayani
anggota baru yang tagihannya belum digenerate — bukan hanya kupon rusak.

Isi modalnya:

| Field | Isi |
|---|---|
| Tahun | angka, default tahun berjalan, wajib |
| Bulan | pilihan nama bulan, default bulan berjalan, wajib |
| Anggota | multi-pilih dengan pencarian, wajib minimal satu |

Pencarian anggota memuat **seluruh anggota aktif** tanpa penyaringan kelompok,
dicocokkan ke `name_indonesian`, `name_pinyin`, `name_hanzi`, dan
`member_number`. Karena ada 1.240 anggota, daftarnya **tidak** di-`preload`:
pilihan diambil lewat `getSearchResultsUsing` (dibatasi 50 hasil) dan label
pilihan yang sudah dipilih lewat `getOptionLabelsUsing`. Label memuat nama,
nomor anggota, dan kelompoknya sehingga nama yang mirip tetap bisa dibedakan.

Submit mengarahkan ke route baru dengan `year`, `month`, dan `members[]` berisi
id anggota.

### Route dan akses

```php
Route::get('cetak/kupon-pilihan', [PdfController::class, 'selectedCoupons'])
    ->name('dues.coupons.selected');
```

Diletakkan di dalam grup `['auth', 'manager']` yang sudah ada, sehingga kolektor
tetap tertutup meski menebak URL-nya.

### Nominal iuran

Untuk tiap anggota terpilih:

1. Bila ada `DuesRecord` pada periode tersebut, pakai itu — nominalnya **identik
   dengan kupon aslinya yang rusak**.
2. Bila tidak ada, buat objek `DuesRecord` **yang tidak disimpan**, dengan
   `amount_due` diambil dari `members.monthly_dues_amount`. Sumber yang sama
   dipakai `DuesGenerator` saat membuat tagihan, jadi nominalnya konsisten.

Relasi `member` di-`setRelation` pada kedua kasus, sehingga template kupon tidak
perlu tahu bedanya.

**Mencetak kupon tidak membuat tagihan.** Tidak ada satu baris pun yang ditulis
ke database. Mencetak bukan menagih.

Anggota non-aktif disaring keluar di sisi server, bukan hanya di pencarian —
sehingga id yang diketik langsung di URL pun tidak bisa menembusnya.

### Tata letak

Template kupon menerima variabel baru `$splitPerGroup`, bernilai `true` bila
tidak dikirim sehingga pemanggil lama tidak perlu diubah.

| Nilai | Dipakai oleh | Perilaku |
|---|---|---|
| `true` | cetak per kelompok | tiap kelompok mulai lembar baru; kupon cadangan mencetak nama kelompok lembar itu |
| `false` | cetak anggota tertentu | seluruh pilihan jadi satu tumpukan datar tanpa pemisah kelompok; kupon cadangan mengosongkan baris Kelompok |

Tanpa mode ini, memilih 3 anggota dari 3 kelompok akan menghasilkan **3 lembar**
masing-masing berisi 1 kupon asli dan 9 cadangan — persis pemborosan kertas yang
baru saja diperbaiki. Dengan mode datar, ketiganya muat di **1 lembar** (3 asli +
7 cadangan).

Imposisi *cut & stack* tetap dipakai apa adanya. Untuk 10 kupon atau kurang
`P = 1`, sehingga urutan slot sama dengan urutan baca biasa.

Urutan cetak sama dengan cetak per kelompok: nama kelompok, lalu No. Urut, lalu
nomor anggota.

### Baris Kelompok pada kupon

Aturannya berbeda antara kupon asli dan kupon cadangan, dan ini **memperbaiki
sebuah bug yang akan muncul** kalau tidak ditangani:

- **Kupon asli** mencetak kelompok anggotanya sendiri (`$member->group?->name`).
  Saat ini template mengambilnya dari kelompok lembar (`$page['group']`) — pada
  mode datar nilainya `null`, sehingga seluruh kupon asli akan mencetak `-`.
  Karena itu kupon asli dikembalikan membaca kelompoknya sendiri.
- **Kupon cadangan** mencetak kelompok lembar bila ada; pada lembar campur
  kelompok (`$page['group']` bernilai `null`) barisnya menjadi garis isian.

### Tanpa cache

Route lama menyimpan hasil di cache 6 jam dengan lock karena satu kelompok bisa
20 lembar dan petugas kerap menekan tombol dua kali. Cetak anggota tertentu
umumnya satu lembar (±1 detik), jadi tidak di-cache. Efek sampingnya justru
diinginkan: bila data anggota baru saja diperbaiki, cetak ulangnya langsung
memakai data terbaru tanpa menunggu cache kedaluwarsa.

### Pilihan kosong

Bila tidak ada anggota aktif yang cocok dengan id yang dikirim, template sudah
punya cabang `@empty` yang mencetak "Tidak ada tagihan untuk periode …". Tidak
perlu penanganan galat khusus — PDF tetap keluar dan pesannya jelas.

## Asumsi yang ditetapkan

1. **Tanpa penanda "CETAK ULANG".** Formatnya sama persis dengan kupon asli.
2. **Anggota non-aktif tidak muncul** di pencarian dan disaring di server.
3. **Tidak ada pencatatan riwayat** siapa mencetak ulang apa dan kapan.
4. **Slot sisa tetap menjadi kupon cadangan**, konsisten dengan cetak per
   kelompok.

## Pengujian

Berkas baru `tests/Feature/SelectedCouponPdfTest.php` — `CouponPdfTest` sudah
besar dan tanggung jawabnya berbeda.

| Test | Yang dijaga |
|---|---|
| Tiga anggota beda kelompok muat satu lembar | tidak ada `page-break-after`; 10 kotak; 7 cadangan |
| Cadangan pada lembar campur mengosongkan Kelompok | cadangan memakai garis isian, sementara tiap kupon asli tetap mencetak nama kelompoknya sendiri |
| Anggota tanpa tagihan tetap tercetak | nominal diambil dari `monthly_dues_amount` |
| Mencetak tidak membuat tagihan | jumlah baris `dues_records` sebelum sama dengan sesudah |
| Anggota non-aktif disaring | id non-aktif yang dikirim lewat URL tidak ikut tercetak |
| Aksi mengarahkan ke route yang benar | uji Livewire atas aksi header, termasuk validasi anggota wajib diisi |

Rute baru ditambahkan ke `PrintRouteAccessTest::printUrls()`, sehingga lima uji
akses yang sudah ada (kolektor, manajer nonaktif, tamu, admin, super admin)
langsung mencakupnya tanpa test tambahan.

Test yang sudah ada dan harus tetap lolos tanpa diubah: seluruh
`CouponPdfTest`, terutama `test_coupons_are_laid_out_for_cut_and_stack_per_group`
dan keempat test kupon cadangan.

## Verifikasi manual

Cetak tiga anggota dari tiga kelompok berbeda, lalu periksa PDF-nya: satu lembar,
tiga kupon asli dengan nama kelompok masing-masing, tujuh kupon cadangan dengan
baris Kelompok berupa garis isian.
