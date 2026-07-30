<?php

namespace Tests\Feature;

use App\Enums\DuesStatus;
use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OpeningBalanceTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 2026;

    /**
     * Go-live Agustus: tagihan lama dibuat sampai Juli.
     */
    private const THROUGH_MONTH = 7;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('app/test-template-saldo-awal.xlsx');
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_it_creates_one_sheet_per_group_plus_an_instruction_sheet(): void
    {
        $satu = MemberGroup::factory()->create(['name' => 'Kelompok 1']);
        $dua = MemberGroup::factory()->create(['name' => 'Kelompok 2']);

        $this->member('A-001', 30000, $satu);
        $this->member('A-002', 30000, $dua);

        $this->export();

        $titles = array_map(
            fn (Worksheet $sheet): string => $sheet->getTitle(),
            IOFactory::load($this->path)->getAllSheets(),
        );

        $this->assertSame(['PETUNJUK', 'Kelompok 1', 'Kelompok 2'], $titles);
    }

    public function test_member_rows_are_prefilled_so_nothing_has_to_be_retyped(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 1']);
        $this->member('A-014', 30000, $group, ['name_indonesian' => 'Budi Santoso']);

        $this->export();

        $sheet = IOFactory::load($this->path)->getSheetByName('Kelompok 1');

        $this->assertSame('nomor_anggota', $sheet->getCell('A1')->getValue());
        $this->assertSame('A-014', $sheet->getCell('A2')->getValue());
        $this->assertSame('Budi Santoso', $sheet->getCell('B2')->getValue());
        $this->assertSame(30000.0, (float) $sheet->getCell('C2')->getValue());
    }

    public function test_due_months_are_prefilled_b_and_later_months_are_not_applicable(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 1']);
        $this->member('A-014', 30000, $group);

        $this->export();

        $marks = $this->marksFor('Kelompok 1', 2);

        // Januari–Juli jatuh tempo, Agustus–Desember belum.
        $this->assertSame(array_fill(0, 7, 'B'), array_slice($marks, 0, 7));
        $this->assertSame(array_fill(0, 5, '-'), array_slice($marks, 7, 5));
    }

    public function test_a_member_who_joined_mid_year_has_earlier_months_marked_not_applicable(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 1']);
        $this->member('A-070', 30000, $group, ['joined_at' => '2026-05-10']);

        $this->export();

        $marks = $this->marksFor('Kelompok 1', 2);

        $this->assertSame(['-', '-', '-', '-'], array_slice($marks, 0, 4));
        $this->assertSame(['B', 'B', 'B'], array_slice($marks, 4, 3));
    }

    public function test_members_free_of_dues_have_every_month_marked_not_applicable(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 10']);
        $this->member('A-080', 0, $group);

        $this->export();

        $this->assertSame(array_fill(0, 12, '-'), $this->marksFor('Kelompok 10', 2));
    }

    public function test_it_fails_when_there_are_no_active_members_to_export(): void
    {
        $this->artisan('dues:export-opening-balance-template', [
            '--year' => self::YEAR,
            '--sampai' => self::THROUGH_MONTH,
            '--file' => $this->path,
        ])->assertFailed();
    }

    public function test_it_rejects_an_out_of_range_due_month(): void
    {
        $this->artisan('dues:export-opening-balance-template', [
            '--year' => self::YEAR,
            '--sampai' => 13,
            '--file' => $this->path,
        ])->assertFailed();
    }

    /**
     * Uji bolak-balik: templat yang dihasilkan harus bisa dibaca kembali oleh
     * perintah impor, apa adanya.
     */
    public function test_the_generated_template_can_be_filled_in_and_imported_back(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 1']);
        $budi = $this->member('A-014', 30000, $group);
        $siti = $this->member('A-021', 30000, $group);

        $this->export();

        // Budi lunas Januari–Maret, sisanya menunggak. Siti lunas Januari–Juli.
        $this->fillMarks('Kelompok 1', $this->rowFor('Kelompok 1', 'A-014'), ['L', 'L', 'L', 'B', 'B', 'B', 'B', '-', '-', '-', '-', '-']);
        $this->fillMarks('Kelompok 1', $this->rowFor('Kelompok 1', 'A-021'), array_merge(array_fill(0, 7, 'L'), array_fill(0, 5, '-')));

        $this->artisan('dues:import-opening-balance', [
            'file' => $this->path,
            '--year' => self::YEAR,
        ])->assertSuccessful();

        $this->assertMonthsHaveStatus($budi, [1, 2, 3], DuesStatus::Lunas);
        $this->assertMonthsHaveStatus($budi, [4, 5, 6, 7], DuesStatus::BelumBayar);
        $this->assertMonthsHaveStatus($siti, range(1, 7), DuesStatus::Lunas);
        $this->assertSame(0, $siti->duesRecords()->where('status', DuesStatus::BelumBayar)->count());
    }

    public function test_the_instruction_sheet_is_ignored_on_import(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 1']);
        $this->member('A-014', 30000, $group);

        $this->export();
        $this->fillMarks('Kelompok 1', 2, array_merge(array_fill(0, 7, 'B'), array_fill(0, 5, '-')));

        $this->artisan('dues:import-opening-balance', [
            'file' => $this->path,
            '--year' => self::YEAR,
        ])->assertSuccessful();

        $this->assertDatabaseCount('dues_records', 7);
    }

    private function export(): void
    {
        $this->artisan('dues:export-opening-balance-template', [
            '--year' => self::YEAR,
            '--sampai' => self::THROUGH_MONTH,
            '--file' => $this->path,
        ])->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(string $number, float $duesAmount, MemberGroup $group, array $attributes = []): Member
    {
        return Member::factory()->active()->create([
            'member_number' => $number,
            'monthly_dues_amount' => $duesAmount,
            'group_id' => $group->getKey(),
            'joined_at' => null,
            ...$attributes,
        ]);
    }

    /**
     * Cari nomor baris seorang anggota. Urutan baris tidak boleh diasumsikan —
     * anggota diurutkan berdasarkan no_urut yang tidak selalu terisi.
     */
    private function rowFor(string $sheetName, string $memberNumber): int
    {
        $sheet = IOFactory::load($this->path)->getSheetByName($sheetName);

        foreach ($sheet->toArray(null, true, false, false) as $offset => $cells) {
            if (($cells[0] ?? null) === $memberNumber) {
                return $offset + 1;
            }
        }

        $this->fail("Anggota {$memberNumber} tidak ada di sheet {$sheetName}.");
    }

    /**
     * Dua belas tanda bulan pada satu baris (kolom D sampai O).
     *
     * @return array<int, string>
     */
    private function marksFor(string $sheetName, int $row): array
    {
        $sheet = IOFactory::load($this->path)->getSheetByName($sheetName);
        $marks = [];

        foreach (range('D', 'O') as $column) {
            $marks[] = (string) $sheet->getCell($column.$row)->getValue();
        }

        return $marks;
    }

    /**
     * @param  array<int, string>  $marks
     */
    private function fillMarks(string $sheetName, int $row, array $marks): void
    {
        $spreadsheet = IOFactory::load($this->path);
        $sheet = $spreadsheet->getSheetByName($sheetName);

        foreach (array_combine(range('D', 'O'), $marks) as $column => $mark) {
            $sheet->setCellValue($column.$row, $mark);
        }

        (new Xlsx($spreadsheet))->save($this->path);
    }

    /**
     * @param  array<int, int>  $months
     */
    private function assertMonthsHaveStatus(Member $member, array $months, DuesStatus $status): void
    {
        foreach ($months as $month) {
            $record = $member->duesRecords()
                ->where('period_year', self::YEAR)
                ->where('period_month', $month)
                ->first();

            $this->assertNotNull($record, "Tagihan bulan {$month} tidak dibuat.");
            $this->assertSame($status, $record->status, "Status bulan {$month} tidak sesuai.");
        }
    }
}
