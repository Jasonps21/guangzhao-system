<?php

namespace App\Console\Commands;

use App\Enums\GroupBasis;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Support\DuesGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportMembersFromExcel extends Command
{
    /**
     * @var string
     */
    protected $signature = 'members:import-excel
        {file : Path ke file .xlsx data anggota}
        {--fresh : Hapus seluruh data anggota, kelompok, iuran & setoran lama sebelum impor}';

    /**
     * @var string
     */
    protected $description = 'Impor data anggota Guang Zhao dari file Excel (sheet klp 1-9 + klp10 Bebas iuran). Sheet "hapus" dan sheet PRINT diabaikan.';

    /**
     * Sheet nama => nomor kelompok. Sheet "hapus" & "* PRINT" sengaja tidak dimasukkan.
     *
     * @var array<string, int>
     */
    private const GROUP_SHEETS = [
        'klp 1' => 1,
        'klp 2' => 2,
        'klp 3' => 3,
        'klp 4' => 4,
        'klp 5' => 5,
        'klp 6' => 6,
        'klp 7' => 7,
        'klp 8' => 8,
        'klp 9' => 9,
        'klp10 Bebas iuran' => 10,
    ];

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);

        // Diperiksa sebelum --fresh menghapus apa pun: file yang ditolak
        // tidak boleh sempat mengosongkan data lama.
        $duplicates = $this->duplicateNumbers($spreadsheet);

        if ($duplicates !== []) {
            $this->error('Impor dibatalkan: ada nomor urut kembar. Betulkan dulu di Excel, tidak ada data yang diubah.');

            foreach ($duplicates as $duplicate) {
                $this->line("  Sheet {$duplicate['sheet']}: No {$duplicate['number']} dipakai di baris ".implode(', ', $duplicate['rows']));
            }

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->wipeExistingData();
        }

        $groups = $this->ensureGroups();

        $totalImported = 0;

        DB::transaction(function () use ($spreadsheet, $groups, &$totalImported): void {
            foreach (self::GROUP_SHEETS as $sheetName => $groupNumber) {
                $sheet = $spreadsheet->getSheetByName($sheetName);

                if (! $sheet instanceof Worksheet) {
                    $this->warn("Sheet tidak ditemukan, dilewati: {$sheetName}");

                    continue;
                }

                $imported = $this->importSheet($sheet, $groupNumber, $groups[$groupNumber]);
                $totalImported += $imported;
                $this->line("  {$sheetName} → Kelompok {$groupNumber}: {$imported} anggota");
            }
        });

        $this->info("Selesai. Total {$totalImported} anggota diimpor ke ".count($groups).' kelompok.');

        return self::SUCCESS;
    }

    private function wipeExistingData(): void
    {
        $this->warn('Menghapus data lama (anggota, kelompok, iuran, setoran)...');

        // TRUNCATE memicu implicit commit di MySQL, jadi tidak dibungkus transaksi.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (['dues_records', 'payment_submissions', 'collector_group', 'members', 'member_groups'] as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Buat kelompok 1-10 bila belum ada.
     *
     * @return array<int, MemberGroup>
     */
    private function ensureGroups(): array
    {
        $groups = [];

        for ($number = 1; $number <= 10; $number++) {
            $name = $number === 10 ? 'Kelompok 10 (Bebas Iuran)' : "Kelompok {$number}";

            $groups[$number] = MemberGroup::firstOrCreate(
                ['name' => $name],
                ['basis' => GroupBasis::Wilayah],
            );
        }

        return $groups;
    }

    /**
     * Nomor urut yang muncul lebih dari sekali dalam satu sheet.
     *
     * Dulu kembaran diberi akhiran "-2" lalu tetap diimpor, sehingga dua orang
     * tercetak dengan nomor yang sama di kupon. Sekarang file seperti itu ditolak.
     *
     * @return list<array{sheet: string, number: int, rows: list<int>}>
     */
    private function duplicateNumbers(Spreadsheet $spreadsheet): array
    {
        $duplicates = [];

        foreach (self::GROUP_SHEETS as $sheetName => $groupNumber) {
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if (! $sheet instanceof Worksheet) {
                continue;
            }

            $rowsByNumber = [];

            foreach ($this->memberRows($sheet, $groupNumber) as $memberRow) {
                $rowsByNumber[$memberRow['no_urut']][] = $memberRow['line'];
            }

            foreach ($rowsByNumber as $number => $lines) {
                if (count($lines) > 1) {
                    $duplicates[] = ['sheet' => $sheetName, 'number' => $number, 'rows' => $lines];
                }
            }
        }

        return $duplicates;
    }

    /**
     * Baris anggota dalam sebuah sheet beserta no. urut dan nomor barisnya di Excel.
     *
     * @return list<array{line: int, no_urut: int, row: array<int, mixed>, hanzi: ?string, indonesian: ?string}>
     */
    private function memberRows(Worksheet $sheet, int $groupNumber): array
    {
        $isBebasIuran = $groupNumber === 10;
        $memberRows = [];
        $sequence = 0;

        foreach ($sheet->toArray(null, true, false, false) as $index => $row) {
            $hanzi = $this->cleanString($row[2] ?? null);
            $indonesian = $this->cleanString($row[3] ?? null);

            // Lewati baris judul, header, dan baris kosong.
            if ($this->isHeaderOrEmpty($row, $hanzi, $indonesian)) {
                continue;
            }

            $sequence++;

            // Sheet bebas iuran memakai penomoran "No" yang berulang per sub-blok,
            // jadi pakai urutan berjalan agar nomor anggota tetap bersih & unik.
            $rawNo = $row[1] ?? null;

            $memberRows[] = [
                'line' => $index + 1,
                'no_urut' => ($isBebasIuran || ! is_numeric($rawNo)) ? $sequence : (int) $rawNo,
                'row' => $row,
                'hanzi' => $hanzi,
                'indonesian' => $indonesian,
            ];
        }

        return $memberRows;
    }

    /**
     * Nomor anggota tidak diisi di sini: model membentuknya dari kelompok dan
     * no. urut, sama seperti saat anggota disimpan dari panel.
     */
    private function importSheet(Worksheet $sheet, int $groupNumber, MemberGroup $group): int
    {
        $isBebasIuran = $groupNumber === 10;
        $imported = 0;
        $now = Carbon::now();
        $generator = app(DuesGenerator::class);

        foreach ($this->memberRows($sheet, $groupNumber) as $memberRow) {
            $row = $memberRow['row'];

            [$address, $phone] = $this->splitAddressAndPhone($this->cleanString($row[4] ?? null));

            // Kolom iuran hanya ada di sheet klp 1-9. Sheet bebas iuran = 0.
            $duesAmount = 0.0;
            if (! $isBebasIuran && is_numeric($row[5] ?? null)) {
                $duesAmount = (float) $row[5];
            }

            $notes = $isBebasIuran
                ? $this->cleanString($row[5] ?? null)
                : $this->cleanString($row[6] ?? null);

            $member = Member::create([
                'name_hanzi' => $memberRow['hanzi'],
                'name_indonesian' => $memberRow['indonesian'],
                'address' => $address,
                'phone' => $phone,
                'monthly_dues_amount' => $duesAmount,
                'group_id' => $group->id,
                'no_urut' => $memberRow['no_urut'],
                'status' => MemberStatus::Aktif,
                'notes' => $notes,
            ]);

            // Daftar kolektor digerakkan DuesRecord: tanpa tagihan bulan
            // berjalan, anggota hasil impor tak terlihat di HP lapangan.
            $generator->generateForMember($member, (int) $now->year, (int) $now->month);

            $imported++;
        }

        return $imported;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isHeaderOrEmpty(array $row, ?string $hanzi, ?string $indonesian): bool
    {
        // Baris tanpa nama sama sekali = pemisah/kosong.
        if (blank($hanzi) && blank($indonesian)) {
            return true;
        }

        // Baris header ("No" / "中文名" / "Nama").
        $secondCell = trim((string) ($row[1] ?? ''));

        if (strcasecmp($secondCell, 'No') === 0) {
            return true;
        }

        if ($hanzi === '中文名' || strcasecmp((string) $indonesian, 'Nama') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Pisahkan nomor telepon yang menempel di dalam alamat.
     * Nomor = rangkaian angka (boleh dipisah spasi/strip) dengan minimal 7 digit.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitAddressAndPhone(?string $address): array
    {
        if (blank($address)) {
            return [null, null];
        }

        $phones = [];

        $cleaned = preg_replace_callback(
            '/\d[\d\s\-]{5,}\d/',
            function (array $match) use (&$phones): string {
                $token = $match[0];
                $digitCount = strlen(preg_replace('/\D/', '', $token));

                if ($digitCount >= 7) {
                    $phones[] = trim(preg_replace('/\s+/', ' ', $token));

                    return ' ';
                }

                return $token;
            },
            $address,
        );

        $address = $this->tidyAddress((string) $cleaned);
        $phone = $phones === [] ? null : implode(' / ', $phones);

        return [$address ?: null, $phone];
    }

    private function tidyAddress(string $address): string
    {
        // Rapikan sisa tanda baca/spasi setelah nomor telepon dikeluarkan.
        $address = preg_replace('/\s+/', ' ', $address) ?? $address;
        $address = str_replace(['( )', '()', '/ /'], '', $address);
        $address = trim($address, " \t\n\r\0\x0B/-,");

        return trim($address);
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Buang apostrof/petik pengunci Excel dan spasi berlebih.
        $value = trim(str_replace(['‘', '’', '`'], '', (string) $value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value === '' ? null : $value;
    }
}
