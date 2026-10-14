<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Excel yang memuat nomor urut kembar dulu tetap diimpor — kembarannya diberi
 * akhiran "-2" diam-diam. Begitulah dua anggota Kelompok 7 sama-sama bernomor
 * 56 sampai tercetak di kupon. Sekarang impor harus menolak file seperti itu.
 */
class ImportMembersFromExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sheet_with_duplicate_numbers_is_rejected_before_anything_is_written(): void
    {
        $file = $this->workbook([
            'klp 7' => [
                [55, '陈秋貞', 'Tjin Shiu Neng'],
                [56, '陈秋云', 'Tjin Tjoei Wun'],
                [56, '冯长顺', 'Johnny Sunjaya'],
            ],
            'klp 9' => [
                [1, '何锦瑞', 'Ho Kim Sui'],
            ],
        ]);

        $this->artisan('members:import-excel', ['file' => $file])
            ->expectsOutputToContain('Sheet klp 7: No 56 dipakai di baris 4, 5')
            ->assertFailed();

        $this->assertSame(0, Member::query()->count());
    }

    public function test_a_clean_workbook_imports_with_numbers_matching_no_urut(): void
    {
        $file = $this->workbook([
            'klp 7' => [
                [56, '陈秋云', 'Tjin Tjoei Wun'],
                [57, '冯长顺', 'Johnny Sunjaya'],
            ],
        ]);

        $this->artisan('members:import-excel', ['file' => $file])->assertSuccessful();

        $this->assertDatabaseHas('members', ['name_indonesian' => 'Tjin Tjoei Wun', 'no_urut' => 56, 'member_number' => '7-056']);
        $this->assertDatabaseHas('members', ['name_indonesian' => 'Johnny Sunjaya', 'no_urut' => 57, 'member_number' => '7-057']);
    }

    public function test_imported_members_are_billed_for_the_current_month(): void
    {
        $file = $this->workbook([
            'klp 7' => [
                [56, '陈秋云', 'Tjin Tjoei Wun'],
            ],
        ]);
        $now = now();

        $this->artisan('members:import-excel', ['file' => $file])->assertSuccessful();

        $member = Member::query()->where('name_indonesian', 'Tjin Tjoei Wun')->firstOrFail();

        $this->assertDatabaseHas('dues_records', [
            'member_id' => $member->id,
            'period_year' => $now->year,
            'period_month' => $now->month,
            'amount_due' => 20000.00,
        ]);
    }

    /**
     * Susunan kolom mengikuti file asli: Klp, No, 中文名, Nama, Alamat, Iuran, Keterangan.
     *
     * @param  array<string, list<array{0: int, 1: string, 2: string}>>  $sheets
     */
    private function workbook(array $sheets): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $name => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($name);
            $sheet->fromArray([
                ['KELOMPOK 2026'],
                ['Klp', 'No', '中文名', 'Nama', 'Alamat', 'Iuran', 'Keterangan'],
                ...array_map(fn (array $row): array => [$name, $row[0], $row[1], $row[2], 'jl. Contoh 1', 20000, null], $rows),
            ]);
        }

        $path = tempnam(sys_get_temp_dir(), 'anggota').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
