# Kupon Cadangan Pengisi Slot Kosong — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Setiap kotak yang sekarang tercetak polos pada lembar kupon iuran diisi kupon cadangan siap tulis — kop, Bulan, Kelompok, dan tanda tangan sudah tercetak; Nama, Alamat, Iuran, dan No. Urut berupa garis isian.

**Architecture:** Seluruh perubahan berada di satu berkas Blade, `resources/views/pdf/coupons.blade.php`. Slot sisa saat ini bernilai `null` dan dirender sebagai `<td class="coupon empty-cell"></td>`. Cabang itu dihapus; sebagai gantinya `$member` dibuat boleh `null` di dalam loop yang sudah ada, dan hanya empat sel nilainya yang bercabang menjadi garis isian. Nama kelompok dibawa ke tiap lembar lewat struktur `$pages` karena slot sisa tidak punya anggota untuk ditanyai.

**Tech Stack:** Laravel 13, PHP 8.3, Blade, `barryvdh/laravel-dompdf` (DomPDF), PHPUnit 12, Laravel Pint.

## Global Constraints

- **Jangan mengubah jumlah lembar.** Tidak boleh ada lembar tambahan yang dicetak hanya demi menghasilkan kupon cadangan. Kelompok yang jumlahnya pas kelipatan 10 mendapat 0 cadangan.
- **Jangan mengubah imposisi *cut & stack*.** `$items->get($slot * $sheetCount + $sheet)` tetap apa adanya. Test `test_coupons_are_laid_out_for_cut_and_stack_per_group` harus tetap lolos tanpa diubah.
- **Kupon cadangan tidak boleh punya penanda apa pun.** Tanpa tulisan "CADANGAN", tanpa bingkai putus-putus, tanpa warna berbeda. Bentuknya sama persis dengan kupon asli.
- **Jangan mengubah** controller, route, model, migrasi, UI Filament, ukuran kertas F4, atau mekanisme cache & lock.
- Ukuran kertas tetap `[0, 0, 609.45, 935.43]` (F4 215×330 mm), 10 kupon/lembar (2×5).
- Komentar kode di berkas ini ditulis dalam bahasa Indonesia (ikuti konvensi berkas yang ada).
- Nama kelas CSS baru: `fill`, `fill-amount`, `fill-number` — persis seperti ini; test bergantung pada nama tersebut.
- Setelah mengubah PHP/Blade, jalankan `vendor/bin/pint --dirty --format agent`.

---

### Task 1: Slot sisa dirender sebagai kupon cadangan siap tulis

**Files:**
- Modify: `resources/views/pdf/coupons.blade.php`
- Test: `tests/Feature/CouponPdfTest.php`

**Interfaces:**
- Consumes: struktur `$pages` yang dibangun blok `@php` di `coupons.blade.php:174-189`, dan variabel view `$records`, `$period`, `$organization`, `$logo` yang dikirim `PdfController::coupons()`.
- Produces: HTML kupon dengan kelas CSS `fill` (garis Nama/Alamat), `fill-amount` (garis Iuran), `fill-number` (garis No. Urut). Kelas `empty-cell` tidak ada lagi. Tiap slot menghasilkan tepat satu `<div class="inner">`.

- [ ] **Step 1: Tulis helper dan empat test yang gagal**

Tambahkan ke `tests/Feature/CouponPdfTest.php`, di dalam kelas `CouponPdfTest`, tepat sebelum `test_coupon_name_shows_indonesian_prominently_with_small_pinyin`:

```php
    /**
     * Slot yang melewati jumlah anggota tidak boleh tercetak polos. Lembarnya
     * tetap keluar dari printer entah kotaknya diisi atau tidak, jadi mengisinya
     * dengan kupon siap tulis tidak memakan kertas tambahan sama sekali —
     * bedanya hanya hasil potongannya berguna, bukan jadi kertas buangan.
     */
    public function test_leftover_slots_are_filled_with_ready_to_write_spare_coupons(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 5']);

        // 12 anggota → P = ceil(12/10) = 2 lembar = 20 slot, jadi 8 slot sisa.
        foreach (range(1, 12) as $number) {
            $member = Member::factory()->active()->create([
                'group_id' => $group->id,
                'no_urut' => $number,
            ]);
            DuesRecord::factory()->create([
                'member_id' => $member->id,
                'period_year' => 2025,
                'period_month' => 12,
            ]);
        }

        $html = $this->renderCouponsHtml();

        $this->assertStringNotContainsString(
            'empty-cell',
            $html,
            'Masih ada kotak polos — slot sisa belum jadi kupon cadangan.',
        );
        $this->assertSame(
            20,
            substr_count($html, 'class="inner"'),
            'Tiap slot harus berisi kupon utuh: 12 asli + 8 cadangan.',
        );
        $this->assertSame(
            8,
            substr_count($html, 'fill-number'),
            'Delapan slot sisa harus jadi delapan kupon cadangan.',
        );
    }

    /**
     * Bulan dan Kelompok sudah pasti diketahui saat mencetak, jadi keduanya
     * ikut tercetak di kupon cadangan supaya petugas cukup menulis empat hal.
     */
    public function test_spare_coupons_carry_the_period_and_the_group(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 5']);
        $member = Member::factory()->active()->create([
            'group_id' => $group->id,
            'no_urut' => 1,
        ]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $html = $this->renderCouponsHtml();

        // 1 kupon asli + 9 cadangan, semuanya membawa periode dan kelompoknya.
        $this->assertSame(10, substr_count($html, 'Desember 2025'));
        $this->assertSame(10, substr_count($html, 'Kelompok 5'));
        $this->assertSame(9, substr_count($html, 'fill-number'));
    }

    /**
     * Data anggota tidak boleh menular ke kotak sebelahnya saat slot sisa
     * dirender — kupon cadangan harus benar-benar kosong pada empat baris itu.
     */
    public function test_spare_coupons_leave_the_member_fields_blank(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 5']);
        $member = Member::factory()->active()->create([
            'group_id' => $group->id,
            'no_urut' => 7,
            'name_hanzi' => null,
            'name_pinyin' => null,
            'name_indonesian' => 'Budi Santoso',
            'address' => 'Jl. Somba Opu 12',
        ]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
            'amount_due' => 15000,
        ]);

        $html = $this->renderCouponsHtml();

        $this->assertSame(1, substr_count($html, 'Budi Santoso'));
        $this->assertSame(1, substr_count($html, 'Jl. Somba Opu 12'));
        $this->assertSame(1, substr_count($html, 'Rp.15.000,-'));
        $this->assertSame(9, substr_count($html, 'fill-amount'));
        $this->assertSame(9, substr_count($html, 'fill-number'));
    }

    /**
     * Menambah lembar hanya demi menghasilkan cadangan justru memboroskan
     * kertas — persis keberatan yang membuat fitur ini dibuat.
     */
    public function test_a_group_that_fills_the_sheet_gets_no_spare_coupons(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 2']);

        foreach (range(1, 10) as $number) {
            $member = Member::factory()->active()->create([
                'group_id' => $group->id,
                'no_urut' => $number,
            ]);
            DuesRecord::factory()->create([
                'member_id' => $member->id,
                'period_year' => 2025,
                'period_month' => 12,
            ]);
        }

        $html = $this->renderCouponsHtml();

        $this->assertSame(10, substr_count($html, 'class="inner"'));
        $this->assertStringNotContainsString('fill-number', $html);
        $this->assertStringNotContainsString('page-break-after', $html);
    }

    /**
     * HTML kupon periode Desember 2025, diurutkan seperti PdfController.
     */
    private function renderCouponsHtml(): string
    {
        $records = DuesRecord::query()
            ->with(['member.group'])
            ->forPeriod(2025, 12)
            ->get()
            ->sortBy(fn (DuesRecord $record) => [
                $record->member->group?->name,
                $record->member->no_urut ?? PHP_INT_MAX,
            ])
            ->values();

        return view('pdf.coupons', [
            'records' => $records,
            'period' => 'Desember 2025',
            'organization' => OrganizationSetting::current(),
            'logo' => null,
        ])->render();
    }
```

Semua kelas yang dipakai (`Member`, `MemberGroup`, `DuesRecord`, `OrganizationSetting`) sudah ter-`use` di berkas test tersebut — tidak ada import baru.

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run:
```bash
php artisan test --compact --filter='spare_coupons|leftover_slots|fills_the_sheet'
```

Expected: 4 test GAGAL. Yang pertama gagal pada `assertStringNotContainsString('empty-cell', ...)` karena slot sisa masih dirender polos.

- [ ] **Step 3: Tambahkan CSS garis isian dan hapus `.empty-cell`**

Di `resources/views/pdf/coupons.blade.php`, ganti baris `.empty-cell { border: 1px solid #111; }` (baris 60) dengan:

```css
        /* Garis isian pada kupon cadangan yang menempati slot sisa satu kelompok.
           Dibuat dari border, bukan deretan karakter titik, supaya panjangnya
           selalu pas selebar kolomnya berapa pun ukuran fontnya. Kotak kosong
           setinggi 3,4mm dengan vertical-align baseline bawaan membuat garisnya
           jatuh tepat di garis dasar teks — seperti isian formulir. Tanpa height
           eksplisit, inline-block tanpa isi bisa runtuh jadi nol di DomPDF dan
           garisnya tidak tergambar. */
        .fill {
            display: inline-block;
            width: 100%;
            height: 3.4mm;
            border-bottom: 0.6pt dotted #333;
        }
        /* Iuran dan No. Urut cukup garis pendek: "Rp." dan ",-" ikut tercetak
           mengapit garisnya, dan nomor urut paling panjang tiga angka. */
        .fill-amount { width: 26mm; }
        .fill-number { width: 16mm; }
```

- [ ] **Step 4: Bawa kelompok ke tiap lembar**

Ganti blok `$pages->push(collect($slots));` (baris 187) menjadi:

```php
            /*
             * Kelompoknya ikut dibawa karena slot sisa bernilai null: kupon
             * cadangan tetap harus mencetak nama kelompok pemilik lembar ini,
             * padahal tidak ada anggota di slot itu untuk ditanyai. Satu lembar
             * selalu milik satu kelompok — groupBy di atas yang menjaminnya.
             */
            $pages->push([
                'group' => $items->first()->member->group,
                'slots' => collect($slots),
            ]);
```

- [ ] **Step 5: Render slot sisa sebagai kupon cadangan**

Ganti seluruh isi `@forelse ($pages as $page)` sampai `@endforelse` (baris 192-311) dengan:

```blade
@forelse ($pages as $page)
    <div class="sheet">
        <table class="grid">
            @php $rows = $page['slots']->chunk(2); @endphp
            @foreach ($rows as $pair)
                <tr>
                    @foreach ($pair as $record)
                        @php
                            /*
                             * $record bernilai null pada slot sisa. Kupon tetap
                             * dicetak utuh; hanya empat baris datanya yang jadi
                             * garis isian, siap ditulis tangan untuk anggota baru
                             * atau kupon yang hilang. Lembarnya toh tetap keluar
                             * dari printer entah kotak ini diisi atau tidak.
                             */
                            $member = $record?->member;

                            /*
                             * Distribute the leftover vertical space evenly between the four
                             * data rows so short-data coupons fill the box instead of leaving
                             * a gap above the pinned Kelompok / No. Urut. We estimate how many
                             * wrapped lines each field needs (value column fits ~26 latin chars
                             * at 14pt; a hanzi glyph ≈ 2 of those), then spread the remaining
                             * millimetres as equal padding under each row. Long data yields
                             * ~0 gap and stays tight; .body-top clips any overflow as a guard.
                             */
                            $valueChars = 26;
                            $alamatText = '-';

                            if ($member !== null) {
                                $nameLatin = ($member->name_indonesian ?: $member->name_pinyin) ?? '';
                                $namaWidth = mb_strlen($member->name_hanzi ?? '') * 2 + mb_strlen($nameLatin)
                                    + ($member->name_indonesian && $member->name_pinyin ? mb_strlen($member->name_pinyin) + 3 : 0);
                                $namaLines = max(1, (int) ceil($namaWidth / $valueChars));

                                $alamatText = $member->address ? \Illuminate\Support\Str::limit($member->address, 48) : '-';
                                $alamatLines = max(1, (int) ceil(mb_strlen($alamatText) / $valueChars));
                            } else {
                                // Garis isian selalu satu baris, jadi jaraknya melebar penuh.
                                $namaLines = 1;
                                $alamatLines = 1;
                            }

                            $usedMm = ($namaLines + $alamatLines + 2) * 5.2;
                            $rowGapMm = round(max(0.0, 31 - $usedMm) / 4, 2);
                        @endphp
                        <td class="coupon">
                            <div class="inner">
                                @if (! empty($logo))
                                    <img class="watermark" src="{{ $logo }}" alt="">
                                @endif
                                <div class="body-top">
                                <div class="head">
                                    <div class="org">{{ $organization->name }}</div>
                                    <div class="hanzi"><span class="hzb">{{ $organization->name_hanzi }}<span class="over">{{ $organization->name_hanzi }}</span></span></div>
                                    @if ($organization->contact_line)
                                        <div class="contact">{{ $organization->contact_line }}</div>
                                    @endif
                                </div>

                                <table class="info">
                                    <tr>
                                        <td class="label" style="padding-bottom: {{ $rowGapMm }}mm;">Nama</td>
                                        <td class="sep">:</td>
                                        <td style="padding-bottom: {{ $rowGapMm }}mm;">@if ($member)@if ($member->name_hanzi)<span class="hz">{{ $member->name_hanzi }}</span> @endif{{ $member->name_indonesian ?: $member->name_pinyin }}@if ($member->name_indonesian && $member->name_pinyin)<span class="pinyin"> ({{ $member->name_pinyin }})</span>@endif@else<span class="fill"></span>@endif</td>
                                    </tr>
                                    <tr>
                                        <td class="label" style="padding-bottom: {{ $rowGapMm }}mm;">Alamat</td>
                                        <td class="sep">:</td>
                                        <td style="padding-bottom: {{ $rowGapMm }}mm;">@if ($member){{ $alamatText }}@else<span class="fill"></span>@endif</td>
                                    </tr>
                                    <tr>
                                        <td class="label" style="padding-bottom: {{ $rowGapMm }}mm;">Iuran</td>
                                        <td class="sep">:</td>
                                        <td style="padding-bottom: {{ $rowGapMm }}mm;"><span class="amount">Rp.@if ($member){{ number_format($record->amount_due, 0, ',', '.') }}@else<span class="fill fill-amount"></span>@endif,-</span></td>
                                    </tr>
                                    <tr>
                                        <td class="label">Bulan</td>
                                        <td class="sep">:</td>
                                        <td><span class="month">{{ $period }}</span></td>
                                    </tr>
                                </table>
                                </div>

                                <div class="foot-box">
                                    <table class="foot">
                                        <tr>
                                            <td class="label">Kelompok</td>
                                            <td class="sep">:</td>
                                            <td>{{ $page['group']?->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">No. Urut</td>
                                            <td class="sep">:</td>
                                            <td>@if ($member){{ $member->no_urut ?? '-' }}@else<span class="fill fill-number"></span>@endif</td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="sign-box">
                                    <table class="sign">
                                        <tr>
                                            <td>KETUA</td>
                                            <td>BENDAHARA</td>
                                        </tr>
                                        <tr>
                                            <td class="gap"></td>
                                            <td class="gap"></td>
                                        </tr>
                                        <tr>
                                            <td>@if ($organization->chairman_name)( {{ $organization->chairman_name }} )@endif</td>
                                            <td>@if ($organization->treasurer_name)( {{ $organization->treasurer_name }} )@endif</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>
    @if (! $loop->last)
        <div style="page-break-after: always;"></div>
    @endif
@empty
    <p style="padding: 20mm; text-align:center;">Tidak ada tagihan untuk periode {{ $period }}.</p>
@endforelse
```

Tiga hal yang sengaja hilang dibanding versi lama:

1. Cabang `@if ($record === null) <td class="coupon empty-cell"></td>` — slot sisa sekarang dirender penuh.
2. Cabang `@if ($pair->count() === 1) <td class="coupon empty-cell"></td>` — kode mati: `$slots` selalu tepat 10 elemen, jadi `chunk(2)` tidak pernah menghasilkan pasangan ganjil.
3. Atribut `style="padding-bottom: ..."` pada `<tr>` baris Nama — tidak berpengaruh apa pun (padding sel yang menentukan) dan hanya menyalin nilai yang sudah ada di kedua `<td>`-nya.

- [ ] **Step 6: Jalankan test baru, pastikan LULUS**

Run:
```bash
php artisan test --compact --filter='spare_coupons|leftover_slots|fills_the_sheet'
```

Expected: 4 test LULUS.

- [ ] **Step 7: Pastikan test kupon yang lama tidak ada yang rusak**

Run:
```bash
php artisan test --compact tests/Feature/CouponPdfTest.php
```

Expected: seluruh test lolos, termasuk `test_coupons_are_laid_out_for_cut_and_stack_per_group` (urutan `1,3,5,7,9,11,2,4,6,8,10,12` tidak berubah karena regex-nya hanya menangkap `(\d+)`, sedangkan cadangan mengisi No. Urut dengan `<span class="fill fill-number"></span>`) dan `test_coupon_pdf_embeds_only_a_font_subset` (PDF tetap di bawah 2 MB).

- [ ] **Step 8: Rapikan format**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 9: Commit**

```bash
git add resources/views/pdf/coupons.blade.php tests/Feature/CouponPdfTest.php
git commit -m "$(cat <<'MSG'
Fill leftover coupon slots with ready-to-write spares

A group whose size is not a multiple of 10 leaves blank boxes on its
last sheets — 40 of them across a full month. Those sheets print either
way, so the boxes cost nothing extra; leaving them empty just turns
them into scrap once the stack is cut.

They now print as complete coupons with the letterhead, month, group,
and signatures already on them, and dotted lines where the name,
address, amount, and number go. Cut & stack puts them at the bottom of
each group's pile on its own, so nobody has to sort them out.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
MSG
)"
```

---

### Task 2: Buktikan garis isian benar-benar tergambar di PDF

DomPDF tidak merender CSS seperti browser, dan berkas ini penuh penyetelan yang hanya benar setelah dilihat hasil cetaknya (lihat komentar panjang pada blok `.hz` di `coupons.blade.php`). Lolosnya test HTML **tidak** membuktikan garis titik-titiknya tergambar. Task ini membuktikannya dengan render PDF sungguhan.

**Files:**
- Create: `<scratchpad>/render-kupon.php` (berkas sementara, tidak di-commit)
- Modify (bila perlu penyetelan): `resources/views/pdf/coupons.blade.php`

**Interfaces:**
- Consumes: kelas CSS `fill`, `fill-amount`, `fill-number` dari Task 1.
- Produces: tidak ada kode baru; hanya keputusan "garisnya sudah benar" atau penyetelan CSS-nya.

- [ ] **Step 1: Tulis skrip render**

Simpan sebagai `render-kupon.php` di direktori scratchpad sesi:

```php
<?php

use App\Models\DuesRecord;
use App\Models\OrganizationSetting;
use Barryvdh\DomPDF\Facade\Pdf;

// Kelompok 5 pada periode Juli 2026: 43 anggota → 5 lembar, 7 slot sisa.
$records = DuesRecord::query()
    ->with(['member.group'])
    ->forPeriod(2026, 7)
    ->whereHas('member', fn ($query) => $query->where('group_id', 5)->where('status', 'aktif'))
    ->get()
    ->sortBy(fn (DuesRecord $record) => $record->member->no_urut ?? PHP_INT_MAX)
    ->values();

echo 'Kupon: '.$records->count().PHP_EOL;

$pdf = Pdf::loadView('pdf.coupons', [
    'records' => $records,
    'period' => 'Juli 2026',
    'organization' => OrganizationSetting::current(),
    'logo' => null,
])->setOption('enable_font_subsetting', true)->setPaper([0, 0, 609.45, 935.43]);

file_put_contents('/tmp/kupon-cadangan.pdf', $pdf->output());

echo 'PDF ditulis ke /tmp/kupon-cadangan.pdf'.PHP_EOL;
```

- [ ] **Step 2: Render PDF-nya**

Run (ganti `<scratchpad>` dengan direktori scratchpad sesi):
```bash
php artisan tinker <scratchpad>/render-kupon.php
```

Expected: `Kupon: 43` lalu `PDF ditulis ke /tmp/kupon-cadangan.pdf`.

- [ ] **Step 3: Ubah lembar terakhir jadi gambar dan lihat**

Run:
```bash
command -v pdftoppm && pdftoppm -png -r 110 -f 5 -l 5 /tmp/kupon-cadangan.pdf /tmp/kupon-hal || echo "pdftoppm tidak ada — pakai: pdftocairo, atau buka PDF-nya manual"
```

Lalu baca `/tmp/kupon-hal-5.png` dengan tool Read untuk melihatnya.

- [ ] **Step 4: Periksa empat hal ini pada gambar**

1. Garis titik-titik pada Nama dan Alamat tergambar penuh selebar kolom, lurus, dan sejajar garis dasar teks.
2. Garis Iuran berada di antara `Rp.` dan `,-`, tidak menabrak keduanya.
3. Garis No. Urut tergambar di dalam `.foot-box`, tidak terpotong bingkai kupon.
4. Kotak cadangan terisi merata — tidak ada celah menganga di tengah, dan Bulan tetap merah serta Kelompok terisi `Kelompok 5`.

Bila `dotted` tergambar buruk (titik-titiknya hilang, menyatu, atau garisnya tidak muncul), turunkan berurutan pada kelas `.fill`:
- pertama: `border-bottom: 0.5pt solid #555;`
- bila masih buruk: ganti span-nya dengan titik literal `<span class="fill">..............................</span>` dan hapus `width`/`height` dari `.fill`.

Setelah penyetelan apa pun, ulangi Step 2-4 sampai keempat butir di atas terpenuhi.

- [ ] **Step 5: Jalankan seluruh test suite**

Run:
```bash
php artisan test --compact
```

Expected: seluruh test lolos. Bila Step 4 memaksa mengubah CSS, jalankan juga `vendor/bin/pint --dirty --format agent`.

- [ ] **Step 6: Commit bila ada penyetelan**

Bila Step 4 tidak mengubah apa pun, lewati step ini — Task 1 sudah ter-commit.

```bash
git add resources/views/pdf/coupons.blade.php
git commit -m "$(cat <<'MSG'
Tune the spare coupon fill lines to how DomPDF actually draws them

Verified against a rendered F4 sheet, not the HTML.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
MSG
)"
```

- [ ] **Step 7: Bereskan berkas sementara**

Hapus `/tmp/kupon-cadangan.pdf`, `/tmp/kupon-hal-*.png`, dan skrip di scratchpad. Pastikan `git status` bersih selain berkas yang memang di-commit.

---

## Catatan untuk pelaksana

**Kenapa slot 0 selalu berisi anggota.** `$items->first()` di Task 1 Step 4 aman karena setiap lembar pasti punya minimal satu anggota: indeks slot 0 pada lembar ke-`$sheet` adalah `0 * $sheetCount + $sheet` = `$sheet`, dan `$sheet < $sheetCount ≤ N`. Jadi `$items->first()` tidak pernah `null`.

**Kenapa cadangan otomatis di tumpukan paling bawah.** Slot sisa selalu menempati indeks terbesar (`≥ N`). Setelah tumpukan dipotong jadi 10 dan disusun ulang sesuai urutan baca, urutan globalnya kembali `0..10P-1`, sehingga cadangan jatuh paling belakang. Ini konsekuensi matematika imposisi, bukan sesuatu yang perlu dikode.

**Yang tidak boleh "diperbaiki" sambil lewat.** Tinggi `.inner` 58mm, `.body-top` 46mm, offset `.hz .over`, dan angka `31` pada `$rowGapMm` semuanya hasil penyetelan terhadap DomPDF. Jangan diutak-atik dalam plan ini.
