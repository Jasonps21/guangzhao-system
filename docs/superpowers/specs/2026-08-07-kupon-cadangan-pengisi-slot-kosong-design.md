# Kupon cadangan pengisi slot kosong

Tanggal: 2026-08-07

## Masalah

Kupon iuran dicetak 10-up di lembar F4 (2 kolom × 5 baris) dengan imposisi *cut &
stack*, dan **setiap kelompok selalu mulai di lembar baru**. Akibatnya jumlah
anggota yang bukan kelipatan 10 menyisakan kotak polos di lembar terakhir tiap
kelompok. Klien menganggap ini pemborosan kertas.

Kondisi nyata untuk satu bulan penuh (1.240 anggota aktif, 10 kelompok):

| Kelompok | Kupon | Lembar | Kotak kosong |
|---|---|---|---|
| 1 | 194 | 20 | 6 |
| 2 | 170 | 17 | 0 |
| 3 | 178 | 18 | 2 |
| 4 | 135 | 14 | 5 |
| 5 | 43 | 5 | 7 |
| 6 | 92 | 10 | 8 |
| 7 | 137 | 14 | 3 |
| 8 | 119 | 12 | 1 |
| 9 | 64 | 7 | 6 |
| 10 | 108 | 11 | 2 |
| **Total** | **1.240** | **128** | **40** |

Karena imposisi *cut & stack*, kotak kosong itu **tidak menumpuk di satu halaman
terakhir**: kotak kosong menempati indeks terbesar, sehingga tersebar sebagai satu
kotak di pojok kanan-bawah pada beberapa lembar terakhir kelompok tersebut.

## Keputusan dan alasannya

Dua hal dipertimbangkan dan sengaja **dipisah** karena efeknya berbeda:

1. **Menggabungkan cetakan antar-kelompok** — satu-satunya cara mengurangi jumlah
   lembar (128 → 124). **Ditolak** oleh pemilik keputusan: alur cetak per kelompok
   tidak boleh berubah, dan cetak besar berisiko lambat/timeout.
2. **Mengisi kotak kosong dengan kupon cadangan** — **tidak mengubah jumlah lembar
   sama sekali**. Lembar itu tetap tercetak entah kotaknya diisi atau tidak; yang
   bertambah hanya tinta. Bedanya, hasil potongannya menjadi kupon cadangan yang
   bisa dipakai, bukan potongan kertas polos yang dibuang. **Inilah yang dipilih.**

Konsekuensi yang diterima secara sadar: cetakan tetap 128 lembar/bulan, dan
menghasilkan ±40 kupon cadangan/bulan. Kelebihan cadangan disadari dan diterima.

## Lingkup

**Berubah:** tampilan slot kosong pada PDF kupon.

**Tidak berubah:** alur cetak per kelompok, jumlah lembar, imposisi *cut & stack*,
ukuran kertas F4, mekanisme cache & lock, route, model, migrasi, dan UI Filament.

## Rancangan

### Isi kupon cadangan

Bentuknya **sama persis dengan kupon asli — tanpa penanda apa pun**. Setelah
ditulis tangan, tidak dapat dibedakan dari kupon cetak, sehingga anggota tidak
menerima kupon yang terlihat "kelas dua".

Sudah tercetak (karena pasti diketahui saat mencetak):

- Kop organisasi (nama, hanzi, baris kontak)
- Watermark logo
- **Bulan** — periode cetakan, tetap berwarna merah seperti kupon asli
- **Kelompok** — nama kelompok pemilik lembar tersebut
- Blok tanda tangan Ketua / Bendahara

Dikosongkan sebagai garis titik-titik untuk ditulis tangan:

- **Nama**
- **Alamat**
- **Iuran** — nominal berbeda-beda per anggota (Rp15.000–Rp100.000, 10 varian pada
  periode Juli 2026), jadi tidak mungkin dipra-isi. Teks `Rp.` dan `,-` tetap
  tercetak mengapit garisnya.
- **No. Urut**

### Perubahan kode

Seluruh perubahan berada di `resources/views/pdf/coupons.blade.php`.

**a. Bawa identitas kelompok ke tiap lembar.** Kupon cadangan perlu tahu nama
kelompoknya padahal slot-nya bernilai `null`. Ganti

```php
$pages->push(collect($slots));
```

menjadi struktur yang membawa kelompoknya:

```php
$pages->push([
    'group' => $items->first()->member->group,
    'slots' => collect($slots),
]);
```

Aman karena `groupBy(fn ($record) => $record->member->group_id)` menjamin satu
lembar hanya berisi satu kelompok. Bila `group` bernilai `null` (anggota tanpa
kelompok), kupon cadangan mencetak `-`, sama seperti kupon asli.

**b. Ekstrak isi kupon menjadi partial** `resources/views/pdf/partials/coupon.blade.php`.
Isi kupon asli dan cadangan hampir seluruhnya sama. Tanpa ekstraksi, ±70 baris
markup tersalin dua kali dan kedua salinan akan menyimpang saat kupon diubah lagi.

Kontrak partial:

- **Menerima:** `$record` (`DuesRecord|null`), `$group` (`MemberGroup|null`),
  `$organization`, `$period`, `$logo`
- **Menghasilkan:** satu `<td class="coupon">` lengkap
- **Perilaku:** bila `$record` bernilai `null`, empat baris data dirender sebagai
  garis titik-titik; sisanya identik

**c. Hapus cabang slot kosong.** `<td class="coupon empty-cell"></td>` dan kelas
CSS `.empty-cell` tidak lagi terpakai.

**d. Hapus kode mati.** Cabang `$pair->count() === 1` tidak pernah tercapai:
`$slots` selalu berisi tepat 10 elemen, sehingga `chunk(2)` selalu menghasilkan 5
pasangan berisi 2. Dihapus karena berada di loop yang sama dengan perubahan di atas.

### Garis titik-titik

Dibuat dengan `border-bottom: 0.6pt dotted` pada span `inline-block`, bukan dengan
mengetik karakter titik — supaya panjang garisnya rapi berapa pun lebar kolomnya.

Berkas ini penuh penyetelan khas DomPDF (lihat komentar panjang pada blok `.hz`),
jadi hasil `dotted` **wajib dibuktikan dengan render PDF sungguhan**, bukan ditebak
dari HTML. Bila DomPDF merender `dotted` dengan buruk, urutan cadangannya:

1. `border-bottom` `solid` tipis
2. deretan titik literal berhuruf Times New Roman

### Perataan jarak baris

Perhitungan `$rowGapMm` dipakai apa adanya. Kupon cadangan selalu 1 baris per data
(`$namaLines = 1`, `$alamatLines = 1`), sehingga jaraknya otomatis melebar dan
kotaknya terisi penuh tanpa celah menganga di tengah.

### Sifat yang didapat gratis

Slot kosong selalu jatuh di indeks terbesar. Setelah tumpukan dipotong menjadi 10
tumpukan lalu disusun ulang sesuai urutan baca, urutan globalnya kembali menjadi
`0..10P-1`, sehingga **kupon cadangan otomatis berada paling bawah pada tumpukan
tiap kelompok**. Pengurus tinggal mengambil beberapa potong terakhir; tidak perlu
menyortir.

## Asumsi yang ditetapkan

1. **Tidak pernah menambah lembar demi cadangan.** Kelompok yang jumlahnya pas
   kelipatan 10 (Kelompok 2 = 170 anggota) mendapat **0 cadangan**. Menambah lembar
   ke-18 hanya demi cadangan justru memboroskan kertas — persis keberatan awal klien.
2. **Selalu aktif**, tanpa tombol nyala/mati pada modal cetak.
3. **No. Urut dikosongkan**, tidak diisi nomor lanjutan otomatis, supaya tidak
   bentrok dengan penomoran yang nanti ditetapkan admin di sistem.

## Pengujian

Ditambahkan ke `tests/Feature/CouponPdfTest.php`:

| Test | Yang dijaga |
|---|---|
| Sisa slot terisi kupon cadangan | 12 anggota → 20 slot → 8 cadangan; tidak ada `empty-cell` tersisa di HTML |
| Cadangan membawa Bulan dan Kelompok | periode dan nama kelompok tercetak pada kupon cadangan |
| Cadangan mengosongkan empat baris | tidak ada nama, alamat, atau nominal anggota yang bocor ke kupon cadangan |
| Kelipatan 10 tidak menambah lembar | 10 anggota → 1 lembar, 10 kupon, 0 cadangan |

Test yang sudah ada dan **harus tetap lolos tanpa diubah**:

- `test_coupons_are_laid_out_for_cut_and_stack_per_group` — regex-nya hanya
  menangkap `(\d+)` pada No. Urut, sedangkan cadangan mengisinya dengan garis, jadi
  urutan `1,3,5,7,9,11,2,4,6,8,10,12` yang diperiksa tidak terpengaruh.
- `test_coupon_html_does_not_inline_the_logo_per_coupon` — kupon cadangan juga
  merujuk logo lewat path, bukan data URI.
- `test_coupon_pdf_embeds_only_a_font_subset` — PDF tetap di bawah 2 MB.
- `test_coupon_pins_kelompok_and_no_urut_outside_the_flowing_block` — blok
  `.foot-box` tetap ada setelah markup dipindah ke partial.

## Verifikasi manual

Render PDF satu kelompok yang jumlahnya bukan kelipatan 10 (mis. Kelompok 5, 43
anggota → 5 lembar, 7 cadangan) lalu periksa secara visual:

1. Garis titik-titik tampil rapi dan lurus di keempat baris.
2. Kotak cadangan terisi penuh, tidak menyisakan celah menganga.
3. Baris Bulan tetap merah dan Kelompok terisi benar.
4. Kupon cadangan tidak terpotong bingkainya.
