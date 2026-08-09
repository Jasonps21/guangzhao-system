# Cetak Kupon Anggota Tertentu — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Manajer bisa mencetak kupon iuran untuk beberapa anggota yang dipilih per nama, bebas lintas kelompok, tanpa harus mencetak seluruh kelompoknya.

**Architecture:** Template kupon mendapat satu mode tata letak baru (`$splitPerGroup`) yang mematikan pemisahan lembar per kelompok, sehingga pilihan lintas kelompok muat dalam satu tumpukan datar. Route dan method controller baru menyiapkan tagihan anggota terpilih — memakai tagihan yang ada bila ada, atau objek tagihan yang tidak disimpan bila belum ada. Aksi header Filament baru menyediakan pemilihnya.

**Tech Stack:** Laravel 13, PHP 8.3, Filament v5, Livewire v4, Blade, `barryvdh/laravel-dompdf` (DomPDF), PHPUnit 12, Laravel Pint.

## Global Constraints

- **Jangan mengubah cetak per kelompok.** Tombol "Cetak Kupon", cache 6 jam + lock-nya, dan imposisi *cut & stack* tetap apa adanya. Seluruh `tests/Feature/CouponPdfTest.php` harus tetap lolos tanpa diubah.
- **`$splitPerGroup` bernilai `true` bila tidak dikirim**, supaya pemanggil lama (termasuk test yang ada) tidak perlu diubah.
- **Mencetak kupon tidak boleh menulis apa pun ke database.** Tidak ada tagihan baru yang dibuat.
- **Anggota non-aktif disaring di sisi server**, bukan hanya di pencarian — id yang diketik langsung di URL pun tidak boleh menembusnya.
- **Tanpa penanda "CETAK ULANG"** pada kupon. Formatnya sama persis dengan kupon asli.
- Label tombol persis: `Cetak Kupon Anggota Tertentu`.
- Nama route persis: `dues.coupons.selected`, path `cetak/kupon-pilihan`, di dalam grup middleware `['auth', 'manager']` yang sudah ada.
- Ukuran kertas tetap `[0, 0, 609.45, 935.43]` (F4 215×330 mm), 10 kupon/lembar.
- Nama kelas CSS baru: `fill-group` — persis seperti ini; test bergantung padanya.
- Komentar kode ditulis dalam bahasa Indonesia (ikuti konvensi berkas yang ada).
- Setelah mengubah PHP/Blade, jalankan `vendor/bin/pint --dirty --format agent`.

---

### Task 1: Mode tata letak datar pada template kupon

**Files:**
- Modify: `resources/views/pdf/coupons.blade.php`
- Test: `tests/Feature/SelectedCouponTest.php` (buat baru)

**Interfaces:**
- Consumes: variabel view `$records`, `$period`, `$organization`, `$logo` yang sudah ada.
- Produces: variabel view opsional `$splitPerGroup` (bool, default `true`). Kelas CSS `fill-group` untuk baris Kelompok yang dikosongkan. Struktur `$pages` tetap `['group' => ?MemberGroup, 'slots' => Collection]`.

- [ ] **Step 1: Tulis tiga test yang gagal**

Buat `tests/Feature/SelectedCouponTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\OrganizationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelectedCouponTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tanpa mode datar, tiga anggota dari tiga kelompok akan menghasilkan tiga
     * lembar yang masing-masing hanya berisi satu kupon asli — persis pemborosan
     * kertas yang membuat fitur kupon cadangan dibuat.
     */
    public function test_members_from_different_groups_share_one_sheet(): void
    {
        $this->makeMemberWithRecord('Kelompok 1', 1);
        $this->makeMemberWithRecord('Kelompok 5', 2);
        $this->makeMemberWithRecord('Kelompok 8', 3);

        $html = $this->renderFlat();

        $this->assertStringNotContainsString(
            'page-break-after',
            $html,
            'Tiga kupon harus muat di satu lembar, bukan tiga.',
        );
        $this->assertSame(10, substr_count($html, 'class="inner"'));
        $this->assertSame(7, substr_count($html, 'class="fill fill-number"'));
    }

    /**
     * Lembar campur kelompok tidak punya satu kelompok pun yang benar untuk
     * kupon cadangan, jadi barisnya dikosongkan. Kupon aslinya tetap wajib
     * mencetak kelompoknya sendiri.
     */
    public function test_spares_blank_the_group_while_real_coupons_keep_theirs(): void
    {
        $this->makeMemberWithRecord('Kelompok 1', 1);
        $this->makeMemberWithRecord('Kelompok 5', 2);
        $this->makeMemberWithRecord('Kelompok 8', 3);

        $html = $this->renderFlat();

        $this->assertSame(7, substr_count($html, 'class="fill fill-group"'));
        $this->assertSame(1, substr_count($html, '<td>Kelompok 1</td>'));
        $this->assertSame(1, substr_count($html, '<td>Kelompok 5</td>'));
        $this->assertSame(1, substr_count($html, '<td>Kelompok 8</td>'));
    }

    /**
     * Cetak per kelompok tidak boleh ikut berubah hanya karena mode baru ada.
     */
    public function test_the_group_split_stays_on_by_default(): void
    {
        $this->makeMemberWithRecord('Kelompok 1', 1);
        $this->makeMemberWithRecord('Kelompok 5', 2);

        $html = view('pdf.coupons', [
            'records' => $this->records(),
            'period' => 'Desember 2025',
            'organization' => OrganizationSetting::current(),
            'logo' => null,
        ])->render();

        $this->assertStringContainsString('page-break-after', $html);
        $this->assertSame(20, substr_count($html, 'class="inner"'));
    }

    private function makeMemberWithRecord(string $groupName, int $noUrut): Member
    {
        $group = MemberGroup::factory()->create(['name' => $groupName]);
        $member = Member::factory()->active()->create([
            'group_id' => $group->id,
            'no_urut' => $noUrut,
        ]);

        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        return $member;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DuesRecord>
     */
    private function records(): \Illuminate\Database\Eloquent\Collection
    {
        return DuesRecord::query()
            ->with(['member.group'])
            ->forPeriod(2025, 12)
            ->get()
            ->sortBy(fn (DuesRecord $record) => [
                $record->member->group?->name,
                $record->member->no_urut ?? PHP_INT_MAX,
            ])
            ->values();
    }

    private function renderFlat(): string
    {
        return view('pdf.coupons', [
            'records' => $this->records(),
            'period' => 'Desember 2025',
            'organization' => OrganizationSetting::current(),
            'logo' => null,
            'splitPerGroup' => false,
        ])->render();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run:
```bash
php artisan test --compact tests/Feature/SelectedCouponTest.php
```

Expected: `test_members_from_different_groups_share_one_sheet` GAGAL karena `page-break-after` masih muncul (tiga kelompok → tiga lembar). `test_the_group_split_stays_on_by_default` sudah LULUS — itu guard mundur, wajar lulus di kedua sisi.

- [ ] **Step 3: Tambahkan kelas CSS `fill-group`**

Di `resources/views/pdf/coupons.blade.php`, ganti baris `.fill-number { width: 16mm; }` menjadi:

```css
        .fill-number { width: 16mm; }
        /* Kelompok dikosongkan hanya pada lembar campur kelompok (cetak anggota
           tertentu). Nama kelompok terpanjang di data nyata adalah "Kelompok 10
           (Bebas Iuran)", jadi garisnya dibuat selebar itu. */
        .fill-group { width: 26mm; }
```

- [ ] **Step 4: Tambahkan mode datar pada pembangunan `$pages`**

Ganti awal blok `@php` (dari `$pages = collect();` sampai baris `foreach`) menjadi:

```php
    /*
     * Cetak per kelompok memisah lembar tiap ganti kelompok. Cetak anggota
     * tertentu tidak: pilihannya memang lintas kelompok, dan tiga nama dari tiga
     * kelompok harus muat di satu lembar, bukan tiga lembar yang hampir kosong.
     */
    $splitPerGroup = $splitPerGroup ?? true;

    $sheetGroups = $splitPerGroup
        ? $records->groupBy(fn ($record) => $record->member->group_id)
        : collect([$records]);

    $pages = collect();

    foreach ($sheetGroups as $groupRecords) {
```

- [ ] **Step 5: Kosongkan kelompok lembar pada mode datar**

Ganti isi `$pages->push([...])` menjadi:

```php
            /*
             * Kelompok lembar hanya dipakai kupon cadangan, yang tidak punya
             * anggota untuk ditanyai. Pada lembar campur kelompok nilainya null
             * dan barisnya jadi garis isian.
             */
            $pages->push([
                'group' => $splitPerGroup ? $items->first()->member->group : null,
                'slots' => collect($slots),
            ]);
```

- [ ] **Step 6: Perbaiki baris Kelompok pada kupon**

Ganti sel Kelompok di dalam `.foot-box`:

```blade
                                            <td>@if ($member){{ $member->group?->name ?? '-' }}@elseif ($page['group']){{ $page['group']->name }}@else<span class="fill fill-group"></span>@endif</td>
```

Ini juga memperbaiki bug yang akan muncul: sel itu sekarang membaca `$page['group']` untuk **semua** kupon, jadi pada mode datar seluruh kupon asli akan mencetak `-`. Kupon asli dikembalikan membaca kelompoknya sendiri; `$page['group']` disisakan khusus untuk kupon cadangan.

- [ ] **Step 7: Jalankan test, pastikan LULUS**

Run:
```bash
php artisan test --compact tests/Feature/SelectedCouponTest.php
```

Expected: 3 test LULUS.

- [ ] **Step 8: Pastikan cetak per kelompok tidak rusak**

Run:
```bash
php artisan test --compact tests/Feature/CouponPdfTest.php
```

Expected: 18 test LULUS, termasuk `test_coupons_are_laid_out_for_cut_and_stack_per_group` dan keempat test kupon cadangan.

- [ ] **Step 9: Rapikan format dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/pdf/coupons.blade.php tests/Feature/SelectedCouponTest.php
git commit -m "$(cat <<'MSG'
Let a coupon sheet hold members from more than one group

The template starts a fresh sheet whenever the group changes, which is
right when printing a whole group but wrong for a hand-picked set: three
members from three groups would land on three near-empty sheets.

A flat mode drops the per-group break so they share one sheet, and blanks
the group line on that sheet's spares, which no longer belong to any one
group. Real coupons go back to reading their own member's group — reading
it from the sheet would have printed a dash for every one of them.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
MSG
)"
```

---

### Task 2: Route dan controller cetak anggota terpilih

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/PdfController.php`
- Modify: `tests/Feature/PrintRouteAccessTest.php`
- Test: `tests/Feature/SelectedCouponTest.php`

**Interfaces:**
- Consumes: variabel view `splitPerGroup` dari Task 1; helper privat `render()` dan `logoPath()` yang sudah ada di `PdfController`.
- Produces: route bernama `dues.coupons.selected` menerima query `year` (int), `month` (int), `members` (array id anggota). Method publik `PdfController::selectedCoupons(Request $request): Response`. Method privat `PdfController::recordsForMembers(int $year, int $month, array $memberIds): Collection`.

- [ ] **Step 1: Tulis lima test yang gagal**

Tambahkan ke `tests/Feature/SelectedCouponTest.php`, di dalam kelas, sebelum method privat `makeMemberWithRecord`:

```php
    /**
     * Anggota baru yang tagihannya belum digenerate tetap harus bisa dapat
     * kupon; nominalnya diambil dari iuran bulanan di master data.
     */
    public function test_a_member_without_a_dues_record_still_gets_a_coupon(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $data = $this->captureCouponViewData($admin, [$member->id]);

        $this->assertCount(1, $data['records']);
        $this->assertSame(25000.0, (float) $data['records']->first()->amount_due);
        $this->assertFalse(
            $data['records']->first()->exists,
            'Tagihan penggantinya tidak boleh tersimpan ke database.',
        );
    }

    /**
     * Kupon pengganti harus identik dengan yang rusak, jadi nominalnya diambil
     * dari tagihan yang ada — bukan dari master data yang bisa sudah berubah.
     */
    public function test_an_existing_dues_record_wins_over_the_master_amount(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
            'amount_due' => 15000,
        ]);

        $data = $this->captureCouponViewData($admin, [$member->id]);

        $this->assertSame(15000.0, (float) $data['records']->first()->amount_due);
    }

    /**
     * Mencetak kupon bukan menagih — tidak boleh ada baris tagihan yang lahir.
     */
    public function test_printing_creates_no_dues_records(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $before = DuesRecord::count();

        $this->actingAs($admin)->get(route('dues.coupons.selected', [
            'year' => 2025,
            'month' => 12,
            'members' => [$member->id],
        ]))->assertOk();

        $this->assertSame($before, DuesRecord::count());
    }

    /**
     * Menyaring di pencarian saja tidak cukup: id anggota non-aktif yang
     * diketik langsung di URL juga harus ditolak.
     */
    public function test_inactive_members_are_dropped_even_when_their_id_is_passed(): void
    {
        $admin = User::factory()->admin()->create();
        $active = Member::factory()->active()->create(['name_indonesian' => 'Masih Aktif']);
        $inactive = Member::factory()->inactive()->create(['name_indonesian' => 'Sudah Pindah']);

        $data = $this->captureCouponViewData($admin, [$active->id, $inactive->id]);

        $this->assertCount(1, $data['records']);
        $this->assertSame($active->id, $data['records']->first()->member->id);
    }

    public function test_the_route_streams_a_flat_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create();

        $response = $this->actingAs($admin)->get(route('dues.coupons.selected', [
            'year' => 2025,
            'month' => 12,
            'members' => [$member->id],
        ]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * Data yang diterima template kupon saat mencetak anggota terpilih.
     *
     * @param  list<int>  $memberIds
     * @return array<string, mixed>
     */
    private function captureCouponViewData(User $admin, array $memberIds): array
    {
        $captured = [];
        view()->composer('pdf.coupons', function ($view) use (&$captured): void {
            $captured[] = $view->getData();
        });

        $this->actingAs($admin)->get(route('dues.coupons.selected', [
            'year' => 2025,
            'month' => 12,
            'members' => $memberIds,
        ]))->assertOk();

        $this->assertNotEmpty($captured, 'Template kupon tidak pernah dirender.');
        $this->assertFalse($captured[0]['splitPerGroup'], 'Cetak anggota terpilih harus memakai mode datar.');

        return $captured[0];
    }
```

Tambahkan `use App\Models\User;` ke blok `use` berkas test tersebut.

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run:
```bash
php artisan test --compact tests/Feature/SelectedCouponTest.php
```

Expected: 5 test baru GAGAL dengan `Route [dues.coupons.selected] not defined`.

- [ ] **Step 3: Daftarkan route**

Di `routes/web.php`, tepat di bawah baris `Route::get('cetak/kupon', ...)->name('dues.coupons');`, tambahkan:

```php
    Route::get('cetak/kupon-pilihan', [PdfController::class, 'selectedCoupons'])->name('dues.coupons.selected');
```

- [ ] **Step 4: Tambahkan method controller**

Di `app/Http/Controllers/PdfController.php`, tepat di bawah method privat `couponRecords()`, tambahkan:

```php
    /**
     * Kupon iuran untuk anggota yang dipilih satu per satu — untuk kupon yang
     * robek atau kena air, dan untuk anggota yang tagihannya belum digenerate.
     *
     * Pilihannya boleh lintas kelompok, jadi lembarnya tidak dipisah per
     * kelompok: tiga nama dari tiga kelompok muat di satu lembar, bukan tiga
     * lembar yang hampir kosong semua. Tidak di-cache seperti cetak per
     * kelompok — hasilnya umumnya satu lembar, dan tanpa cache setiap cetak
     * ulang otomatis memakai data anggota terbaru.
     */
    public function selectedCoupons(Request $request): Response
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $month = (int) $request->integer('month', (int) now()->month);
        /** @var list<int> $memberIds */
        $memberIds = array_map('intval', (array) $request->input('members', []));

        $organization = OrganizationSetting::current();

        $pdf = $this->render('pdf.coupons', [
            'records' => $this->recordsForMembers($year, $month, $memberIds),
            'period' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'organization' => $organization,
            // Watermark diulang di tiap kupon — pakai path, bukan data URI. (§logoPath)
            'logo' => $this->logoPath($organization),
            'splitPerGroup' => false,
        ])->setPaper([0, 0, 609.45, 935.43]); // F4 215×330mm in points

        return $pdf->stream('kupon-pilihan-'.$year.'-'.$month.'.pdf');
    }

    /**
     * Tagihan periode tersebut untuk anggota terpilih, siap dicetak.
     *
     * Anggota yang belum punya tagihan tetap dapat kupon: dibuatkan objek
     * tagihan yang TIDAK disimpan, bernominal iuran bulanan dari master data —
     * sumber yang sama dipakai DuesGenerator. Mencetak kupon bukan menagih,
     * jadi tidak ada satu baris pun yang ditulis ke database.
     *
     * Status anggota disaring di sini, bukan hanya di pencarian, supaya id yang
     * diketik langsung di URL pun tidak menembusnya.
     *
     * @param  list<int>  $memberIds
     * @return Collection<int, DuesRecord>
     */
    private function recordsForMembers(int $year, int $month, array $memberIds): Collection
    {
        $members = Member::query()
            ->with('group')
            ->whereKey($memberIds)
            ->where('status', MemberStatus::Aktif)
            ->get();

        $existing = DuesRecord::query()
            ->forPeriod($year, $month)
            ->whereIn('member_id', $members->modelKeys())
            ->get()
            ->keyBy('member_id');

        return $members
            ->map(function (Member $member) use ($existing, $year, $month): DuesRecord {
                $record = $existing->get($member->getKey()) ?? new DuesRecord([
                    'period_year' => $year,
                    'period_month' => $month,
                    'amount_due' => $member->monthly_dues_amount,
                ]);

                $record->setRelation('member', $member);

                return $record;
            })
            // Urutan sama dengan cetak per kelompok agar hasilnya mudah dicocokkan.
            ->sortBy(fn (DuesRecord $record) => [
                $record->member->group?->name,
                $record->member->no_urut ?? PHP_INT_MAX,
                $record->member->member_number,
            ])
            ->values();
    }
```

Seluruh kelas yang dipakai (`Member`, `MemberStatus`, `DuesRecord`, `Collection`, `Carbon`, `Request`, `Response`, `OrganizationSetting`) sudah ter-`use` di `PdfController` — tidak ada import baru.

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run:
```bash
php artisan test --compact tests/Feature/SelectedCouponTest.php
```

Expected: 8 test LULUS.

- [ ] **Step 6: Masukkan route baru ke uji akses terpusat**

Di `tests/Feature/PrintRouteAccessTest.php`, di dalam `printUrls()`, tambahkan satu baris tepat di bawah entri `dues.coupons`:

```php
            route('dues.coupons.selected', ['year' => now()->year, 'month' => now()->month, 'members' => [$member->id]]),
```

Ini langsung memberi lima uji akses yang sudah ada — kolektor ditolak, manajer nonaktif ditolak, tamu tidak menerima PDF, admin dan super admin tetap bisa mencetak — tanpa menulis test baru.

- [ ] **Step 7: Jalankan uji akses**

Run:
```bash
php artisan test --compact tests/Feature/PrintRouteAccessTest.php
```

Expected: 6 test LULUS.

- [ ] **Step 8: Rapikan format dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/web.php app/Http/Controllers/PdfController.php tests/Feature/PrintRouteAccessTest.php tests/Feature/SelectedCouponTest.php
git commit -m "$(cat <<'MSG'
Print coupons for a hand-picked set of members

Replacing one water-damaged coupon meant reprinting its whole group.
This route takes member ids instead, in flat layout so a cross-group
pick shares a sheet.

A member with no dues record for the month still gets a coupon, priced
from the member's own monthly amount via an unsaved record — printing a
coupon is not billing, so nothing is written. Inactive members are
filtered server-side, not just in the picker.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
MSG
)"
```

---

### Task 3: Tombol dan pemilih anggota di halaman Tagihan

**Files:**
- Modify: `app/Models/Member.php`
- Modify: `app/Filament/Resources/DuesRecords/Pages/ListDuesRecords.php`
- Test: `tests/Feature/SelectedCouponTest.php`

**Interfaces:**
- Consumes: route `dues.coupons.selected` dari Task 2; `ListDuesRecords::monthOptions()` yang sudah ada.
- Produces: scope `Member::scopeSearchByNameOrNumber(Builder $query, string $term): void`. Aksi header Filament bernama `printSelectedCoupons` dengan field `year`, `month`, `member_ids`.

- [ ] **Step 1: Tulis tiga test yang gagal**

Tambahkan ke `tests/Feature/SelectedCouponTest.php`, sebelum method privat:

```php
    public function test_member_search_matches_name_or_number(): void
    {
        Member::factory()->active()->create([
            'name_indonesian' => 'Budi Santoso',
            'member_number' => 'GZ-001',
        ]);
        Member::factory()->active()->create([
            'name_indonesian' => 'Siti Rahayu',
            'member_number' => 'GZ-002',
        ]);

        $byName = Member::query()->searchByNameOrNumber('budi')->pluck('name_indonesian')->all();
        $byNumber = Member::query()->searchByNameOrNumber('GZ-002')->pluck('name_indonesian')->all();

        $this->assertSame(['Budi Santoso'], $byName);
        $this->assertSame(['Siti Rahayu'], $byNumber);
    }

    public function test_the_action_redirects_to_the_selected_coupon_route(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Member::factory()->active()->create();
        $second = Member::factory()->active()->create();

        Livewire::actingAs($admin)
            ->test(ListDuesRecords::class)
            ->callAction('printSelectedCoupons', data: [
                'year' => 2025,
                'month' => 12,
                'member_ids' => [$first->id, $second->id],
            ])
            ->assertHasNoActionErrors()
            ->assertRedirect(route('dues.coupons.selected', [
                'year' => 2025,
                'month' => 12,
                'members' => [$first->id, $second->id],
            ]));
    }

    public function test_the_action_requires_at_least_one_member(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListDuesRecords::class)
            ->callAction('printSelectedCoupons', data: [
                'year' => 2025,
                'month' => 12,
                'member_ids' => [],
            ])
            ->assertHasActionErrors(['member_ids' => 'required'])
            ->assertNoRedirect();
    }
```

Tambahkan ke blok `use` berkas test:

```php
use App\Filament\Resources\DuesRecords\Pages\ListDuesRecords;
use Livewire\Livewire;
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run:
```bash
php artisan test --compact --filter='member_search|the_action_' tests/Feature/SelectedCouponTest.php
```

Expected: 3 test GAGAL — `searchByNameOrNumber` belum ada, dan aksi `printSelectedCoupons` belum ada.

- [ ] **Step 3: Tambahkan scope pencarian ke model Member**

Di `app/Models/Member.php`, tepat di bawah `scopeActive()`, tambahkan:

```php
    /**
     * Pencarian anggota untuk pemilih kupon — nama Indonesia, pinyin, hanzi,
     * atau nomor anggota. Dropdown "Cetak Kupon Anggota Tertentu" memakainya
     * karena memuat 1.240 anggota sekaligus terlalu berat.
     */
    public function scopeSearchByNameOrNumber(Builder $query, string $term): void
    {
        $query->where(function (Builder $inner) use ($term): void {
            foreach (['name_indonesian', 'name_pinyin', 'name_hanzi', 'member_number'] as $column) {
                $inner->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }
```

`Builder` sudah ter-`use` di berkas itu — tidak ada import baru.

- [ ] **Step 4: Tambahkan aksi header dan helper pemilih**

Di `app/Filament/Resources/DuesRecords/Pages/ListDuesRecords.php`, tepat di bawah aksi `printCoupons` (sebelum `CreateAction::make()`), tambahkan:

```php
            // Kupon robek / kena air, dan anggota yang tagihannya belum
            // digenerate. Sengaja tombol terpisah dari cetak per kelompok:
            // pilihannya per nama dan bebas lintas kelompok.
            Action::make('printSelectedCoupons')
                ->label('Cetak Kupon Anggota Tertentu')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->modalDescription('Untuk kupon yang robek atau rusak, dan anggota yang tagihannya belum digenerate. Boleh memilih lintas kelompok — semuanya dicetak dalam satu tumpukan.')
                ->modalSubmitActionLabel('Siapkan Kupon')
                ->schema([
                    TextInput::make('year')
                        ->label('Tahun')
                        ->numeric()
                        ->default((int) now()->year)
                        ->required(),
                    Select::make('month')
                        ->label('Bulan')
                        ->options(self::monthOptions())
                        ->default((int) now()->month)
                        ->required(),
                    Select::make('member_ids')
                        ->label('Anggota')
                        ->helperText('Cari nama atau nomor anggota. Boleh beberapa sekaligus, dan tidak harus satu kelompok.')
                        ->multiple()
                        ->searchable()
                        ->required()
                        // Sengaja tanpa ->preload(): 1.240 anggota terlalu berat
                        // untuk dimuat sekaligus di dropdown.
                        ->getSearchResultsUsing(fn (string $search): array => self::searchMembers($search))
                        ->getOptionLabelsUsing(fn (array $values): array => self::memberLabels($values)),
                ])
                ->action(fn (array $data) => redirect()->route('dues.coupons.selected', [
                    'year' => $data['year'],
                    'month' => $data['month'],
                    'members' => $data['member_ids'],
                ])),
```

Lalu tambahkan tiga helper di bawah `monthOptions()`:

```php
    /**
     * Hasil pencarian anggota aktif, dibatasi agar dropdown tetap ringan.
     *
     * @return array<int, string>
     */
    protected static function searchMembers(string $search): array
    {
        return Member::query()
            ->with('group')
            ->active()
            ->searchByNameOrNumber($search)
            ->orderBy('name_indonesian')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Member $member): array => [$member->getKey() => self::memberLabel($member)])
            ->all();
    }

    /**
     * Label anggota yang sudah terpilih, supaya tetap terbaca saat modal dibuka
     * ulang — hasil pencarian tidak lagi tersedia saat itu.
     *
     * @param  array<int, int|string>  $values
     * @return array<int, string>
     */
    protected static function memberLabels(array $values): array
    {
        return Member::query()
            ->with('group')
            ->whereKey($values)
            ->get()
            ->mapWithKeys(fn (Member $member): array => [$member->getKey() => self::memberLabel($member)])
            ->all();
    }

    /**
     * Nama, nomor anggota, dan kelompoknya — supaya nama yang mirip tetap bisa
     * dibedakan saat memilih.
     */
    protected static function memberLabel(Member $member): string
    {
        $name = $member->name_indonesian ?: ($member->name_pinyin ?: $member->name_hanzi);

        return sprintf(
            '%s — %s · %s',
            $name ?: '(tanpa nama)',
            $member->member_number,
            $member->group?->name ?? 'Tanpa Kelompok',
        );
    }
```

Tambahkan `use App\Models\Member;` ke blok `use` berkas tersebut.

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run:
```bash
php artisan test --compact tests/Feature/SelectedCouponTest.php
```

Expected: 11 test LULUS.

Bila `test_the_action_redirects_to_the_selected_coupon_route` gagal karena urutan
parameter query berbeda, samakan urutan kunci pada `route()` di test dengan
urutan pada `->action()` di `ListDuesRecords` — keduanya harus `year`, `month`,
`members`. Jangan melonggarkan assertion-nya menjadi pencocokan sebagian; URL
yang tepat justru bagian yang perlu dijaga.

- [ ] **Step 6: Rapikan format dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Member.php app/Filament/Resources/DuesRecords/Pages/ListDuesRecords.php tests/Feature/SelectedCouponTest.php
git commit -m "$(cat <<'MSG'
Add a picker for printing named members' coupons

Sits beside the per-group print button rather than folding into it: the
two answer different questions, and merging them would put a group
selector in front of a workflow that is deliberately group-agnostic.

The picker searches names and member numbers on demand instead of
preloading, since the association has 1240 members. Labels carry the
number and group so lookalike names stay distinguishable.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
MSG
)"
```

---

### Task 4: Buktikan hasil cetaknya di PDF sungguhan

Test HTML tidak membuktikan DomPDF menggambar lembarnya dengan benar. Task ini membuktikannya dengan render PDF nyata dari data produksi lokal.

**Files:**
- Create: `<scratchpad>/render-pilihan.php` (berkas sementara, tidak di-commit)

**Interfaces:**
- Consumes: `PdfController::recordsForMembers()` lewat route dari Task 2, dan mode datar dari Task 1.
- Produces: tidak ada kode baru — hanya bukti visual atau penyetelan bila ada yang salah gambar.

- [ ] **Step 1: Tulis skrip render**

Simpan sebagai `render-pilihan.php` di direktori scratchpad sesi:

```php
<?php

use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\OrganizationSetting;
use Barryvdh\DomPDF\Facade\Pdf;

// Satu anggota dari tiga kelompok berbeda, seperti kasus nyata kupon rusak.
$members = collect([1, 5, 8])
    ->map(fn (int $groupId) => Member::query()
        ->with('group')
        ->where('group_id', $groupId)
        ->where('status', 'aktif')
        ->orderBy('no_urut')
        ->first())
    ->filter()
    ->values();

echo 'anggota: '.$members->pluck('name_indonesian')->implode(', ').PHP_EOL;

$existing = DuesRecord::query()
    ->forPeriod(2026, 7)
    ->whereIn('member_id', $members->modelKeys())
    ->get()
    ->keyBy('member_id');

$records = $members->map(function (Member $member) use ($existing): DuesRecord {
    $record = $existing->get($member->getKey()) ?? new DuesRecord([
        'period_year' => 2026,
        'period_month' => 7,
        'amount_due' => $member->monthly_dues_amount,
    ]);
    $record->setRelation('member', $member);

    return $record;
});

$pdf = Pdf::loadView('pdf.coupons', [
    'records' => $records,
    'period' => 'Juli 2026',
    'organization' => OrganizationSetting::current(),
    'logo' => null,
    'splitPerGroup' => false,
])->setOption('enable_font_subsetting', true)->setPaper([0, 0, 609.45, 935.43]);

file_put_contents('/tmp/kupon-pilihan.pdf', $pdf->output());

echo 'PDF ditulis ke /tmp/kupon-pilihan.pdf'.PHP_EOL;
```

- [ ] **Step 2: Render PDF-nya**

Run (ganti `<scratchpad>` dengan direktori scratchpad sesi; `< /dev/null` mencegah tinker menggantung di REPL):
```bash
php artisan tinker <scratchpad>/render-pilihan.php < /dev/null
```

Expected: tiga nama anggota tercetak, lalu `PDF ditulis ke /tmp/kupon-pilihan.pdf`.

- [ ] **Step 3: Ubah jadi gambar dan lihat**

Run:
```bash
python3 -c "
import fitz
doc = fitz.open('/tmp/kupon-pilihan.pdf')
print('halaman:', doc.page_count)
doc[0].get_pixmap(dpi=125).save('/tmp/kupon-pilihan.png')
"
```

Lalu baca `/tmp/kupon-pilihan.png` dengan tool Read.

- [ ] **Step 4: Periksa empat hal ini**

1. **Satu halaman saja** — `halaman: 1`, bukan 3.
2. Tiga kupon asli teratas mencetak **nama kelompok masing-masing yang berbeda**, bukan `-` dan bukan kelompok yang sama semua.
3. Tujuh kupon cadangan mencetak baris Kelompok sebagai **garis isian**, bukan nama kelompok mana pun.
4. Nominal Iuran tiap kupon asli terisi angka, dan baris Bulan berwarna merah bertuliskan `Juli 2026`.

Bila ada yang meleset, perbaiki penyebabnya di kode lalu ulangi Step 2-4.

- [ ] **Step 5: Jalankan seluruh test suite**

Run:
```bash
php artisan test --compact
```

Expected: seluruh test lolos.

- [ ] **Step 6: Bereskan berkas sementara**

Hapus `/tmp/kupon-pilihan.pdf`, `/tmp/kupon-pilihan.png`, dan skrip di scratchpad. Pastikan `git status` bersih selain berkas yang memang di-commit.

---

## Catatan untuk pelaksana

**Kenapa `$items->first()` tetap aman.** Pada mode datar hanya ada satu "kelompok" berisi seluruh pilihan, dan `'group'` di-set `null` tanpa memanggil `first()`. Pada mode per kelompok, indeks slot 0 lembar ke-`$sheet` adalah `0 * $sheetCount + $sheet` = `$sheet`, dan `$sheet < $sheetCount ≤ N`, jadi slot 0 tidak pernah `null`.

**Kenapa pilihan kosong tidak perlu penanganan galat.** `whereKey([])` menghasilkan koleksi kosong, `$records` kosong, dan template sudah punya cabang `@empty` yang mencetak "Tidak ada tagihan untuk periode …". PDF tetap keluar dengan pesan yang jelas.

**Yang tidak boleh "diperbaiki" sambil lewat.** Tinggi `.inner` 58mm, `.body-top` 46mm, tinggi `.fill` 4,2mm, offset `.hz .over`, dan angka `31` pada `$rowGapMm` semuanya hasil penyetelan terhadap DomPDF. Jangan diutak-atik dalam plan ini.
