<?php

namespace App\Console\Commands;

use App\Support\OpeningBalanceImporter;
use App\Support\OpeningBalanceResult;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportOpeningBalance extends Command
{
    /**
     * @var string
     */
    protected $signature = 'dues:import-opening-balance
        {file : Path ke file .xlsx templat saldo awal yang sudah diisi}
        {--year= : Tahun periode (default: tahun ini)}
        {--dry-run : Hitung dan tampilkan hasilnya tanpa menyimpan apa pun}';

    /**
     * @var string
     */
    protected $description = 'Impor saldo awal iuran dari templat Excel yang sudah diisi (semua sheet kelompok dibaca).';

    private const MONTH_HEADERS = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'ags', 'sep', 'okt', 'nov', 'des'];

    public function handle(OpeningBalanceImporter $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $year = (int) ($this->option('year') ?? Carbon::now()->year);
        $dryRun = (bool) $this->option('dry-run');

        $rows = $this->readAllSheets($file);

        if ($rows === null) {
            return self::FAILURE;
        }

        $this->info(($dryRun ? '[UJI COBA] ' : '')."Impor saldo awal tahun {$year} — ".count($rows).' baris anggota.');
        $this->newLine();

        $result = $importer->importMatrix($rows, $year, $dryRun);

        $this->report($result, $dryRun);

        return $result->hasIssues() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Baca setiap sheet yang punya kolom nomor_anggota. Sheet lain (mis. PETUNJUK)
     * dilewati tanpa keluhan.
     *
     * @return array<int, array{sheet: string, row: int, member_number: mixed, months: array<int, mixed>}>|null
     */
    private function readAllSheets(string $file): ?array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);

        $rows = [];
        $sheetsRead = 0;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetRows = $this->readSheet($sheet);

            if ($sheetRows === null) {
                continue;
            }

            $rows = array_merge($rows, $sheetRows);
            $sheetsRead++;

            $this->line("  {$sheet->getTitle()}: ".count($sheetRows).' baris');
        }

        if ($sheetsRead === 0) {
            $this->error('Tidak ada sheet yang punya kolom "nomor_anggota" beserta kolom bulan (jan..des).');

            return null;
        }

        return $rows;
    }

    /**
     * @return array<int, array{sheet: string, row: int, member_number: mixed, months: array<int, mixed>}>|null
     */
    private function readSheet(Worksheet $sheet): ?array
    {
        $raw = $sheet->toArray(null, true, false, false);

        if ($raw === []) {
            return null;
        }

        $header = array_map(
            fn ($cell): string => str_replace(' ', '_', mb_strtolower(trim((string) $cell))),
            array_shift($raw),
        );

        $numberIndex = array_search('nomor_anggota', $header, true);

        if ($numberIndex === false) {
            return null;
        }

        $monthIndexes = [];

        foreach (self::MONTH_HEADERS as $offset => $columnName) {
            $position = array_search($columnName, $header, true);

            if ($position === false) {
                return null;
            }

            $monthIndexes[$offset + 1] = $position;
        }

        $rows = [];

        foreach ($raw as $offset => $cells) {
            if (blank($cells[$numberIndex] ?? null)) {
                continue;
            }

            $months = [];

            foreach ($monthIndexes as $month => $position) {
                $months[$month] = $cells[$position] ?? null;
            }

            $rows[] = [
                'sheet' => $sheet->getTitle(),
                // +2: baris header terbuang & Excel mulai dari 1.
                'row' => $offset + 2,
                'member_number' => $cells[$numberIndex],
                'months' => $months,
            ];
        }

        return $rows;
    }

    private function report(OpeningBalanceResult $result, bool $dryRun): void
    {
        $this->newLine();
        $this->table(['Rekonsiliasi', 'Jumlah'], [
            ['Anggota diproses', $result->membersProcessed],
            ['Bulan ditandai LUNAS (saldo awal)', $result->paidCreated],
            ['Bulan jadi TUNGGAKAN', $result->arrearsCreated],
            ['Total nilai tunggakan', 'Rp '.number_format($result->arrearsTotal, 0, ',', '.')],
            ['Dilewati (sudah ada tagihannya)', $result->skippedExisting],
            ['Dilewati (bebas iuran / belum bergabung)', $result->skippedFreeOfDues],
        ]);

        if ($result->hasIssues()) {
            $this->newLine();
            $this->warn(count($result->issues).' baris tidak diproses dan harus diperbaiki di Excel:');

            $this->table(
                ['Sheet', 'Baris', 'Nomor Anggota', 'Masalah'],
                array_map(
                    fn (array $issue): array => [$issue['sheet'], $issue['row'], $issue['member_number'], $issue['reason']],
                    $result->issues,
                ),
            );

            $this->warn('Tidak ada data yang disimpan untuk baris bermasalah. Perbaiki lalu jalankan ulang.');
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('UJI COBA — tidak ada data yang disimpan. Cocokkan total tunggakan di atas dengan catatan Anda, lalu jalankan ulang tanpa --dry-run.');

            return;
        }

        $this->info('Saldo awal tersimpan.');
    }
}
