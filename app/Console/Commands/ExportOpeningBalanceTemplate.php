<?php

namespace App\Console\Commands;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Hasilkan templat Excel saldo awal, satu sheet per kelompok, sudah terisi
 * data anggota yang ada. Pengurus tinggal mengganti tanda B menjadi L pada
 * bulan yang sudah dibayar — tidak perlu mengetik ulang nama atau nomor.
 */
class ExportOpeningBalanceTemplate extends Command
{
    /**
     * @var string
     */
    protected $signature = 'dues:export-opening-balance-template
        {--year= : Tahun periode (default: tahun ini)}
        {--sampai= : Bulan jatuh tempo terakhir 1-12 (default: bulan lalu)}
        {--file= : Path file keluaran (default: storage/app/template-saldo-awal-{tahun}.xlsx)}';

    /**
     * @var string
     */
    protected $description = 'Buat templat Excel saldo awal iuran (satu sheet per kelompok, sudah terisi data anggota).';

    private const MONTH_HEADERS = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'ags', 'sep', 'okt', 'nov', 'des'];

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?? Carbon::now()->year);
        $throughMonth = (int) ($this->option('sampai') ?? Carbon::now()->subMonth()->month);

        if ($throughMonth < 1 || $throughMonth > 12) {
            $this->error('Opsi --sampai harus antara 1 dan 12.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('file') ?? storage_path("app/template-saldo-awal-{$year}.xlsx"));

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->buildInstructionSheet($spreadsheet, $year, $throughMonth);

        $groups = MemberGroup::query()->orderBy('name')->get();
        $totalMembers = 0;

        foreach ($groups as $group) {
            $members = Member::query()
                ->where('group_id', $group->getKey())
                ->where('status', MemberStatus::Aktif)
                ->orderBy('no_urut')
                ->orderBy('member_number')
                ->get();

            if ($members->isEmpty()) {
                continue;
            }

            $this->buildGroupSheet($spreadsheet, $group, $members, $year, $throughMonth);
            $totalMembers += $members->count();

            $this->line("  {$group->name}: {$members->count()} anggota");
        }

        if ($totalMembers === 0) {
            $this->error('Tidak ada anggota aktif. Impor data anggota terlebih dahulu.');

            return self::FAILURE;
        }

        (new Xlsx($spreadsheet))->save($path);

        $this->newLine();
        $this->info("Templat dibuat: {$path}");
        $this->line("Total {$totalMembers} anggota dalam ".($spreadsheet->getSheetCount() - 1).' sheet kelompok.');
        $this->newLine();
        $this->comment('Langkah berikutnya: isi tanda L pada bulan yang sudah dibayar, lalu jalankan');
        $this->comment("  php artisan dues:import-opening-balance {$path} --dry-run");

        return self::SUCCESS;
    }

    private function buildInstructionSheet(Spreadsheet $spreadsheet, int $year, int $throughMonth): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('PETUNJUK');

        $dueLabel = Carbon::create($year, $throughMonth, 1)->translatedFormat('F Y');

        $lines = [
            ["TEMPLAT SALDO AWAL IURAN {$year}"],
            [''],
            ['Isi tanda pada setiap kolom bulan di sheet kelompok:'],
            [''],
            ['L', 'Sudah LUNAS bulan itu'],
            ['B', 'BELUM bayar bulan itu (jadi tunggakan)'],
            ['-', 'Tidak berlaku — jangan dibuatkan tagihan'],
            [''],
            ['Aturan penting:'],
            ['1.', "Kolom Januari sampai {$dueLabel} sudah terisi B. Ganti menjadi L untuk bulan yang sudah dibayar."],
            ['2.', 'Kolom bulan setelah itu terisi -. Ganti menjadi L HANYA bila anggota sudah membayar di muka.'],
            ['3.', 'Sel yang bertanda - untuk anggota tertentu berarti dia belum bergabung atau bebas iuran. Biarkan.'],
            ['4.', 'JANGAN mengosongkan sel. Sel kosong akan ditolak saat impor.'],
            ['5.', 'JANGAN mengubah nomor_anggota, nama, atau baris header. Nomor anggota dipakai untuk mencocokkan data.'],
            ['6.', 'Boleh menghapus baris anggota yang tidak perlu diisi, tapi jangan menambah baris baru.'],
            [''],
            ['Setelah selesai, serahkan file ini untuk diimpor. Impor selalu diuji coba dulu'],
            ['(--dry-run) dan totalnya dicocokkan dengan catatan Anda sebelum disimpan.'],
        ];

        $sheet->fromArray($lines, null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A5:A7')->getFont()->setBold(true);
        $sheet->getStyle('A9')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(95);
    }

    /**
     * @param  Collection<int, Member>  $members
     */
    private function buildGroupSheet(Spreadsheet $spreadsheet, MemberGroup $group, $members, int $year, int $throughMonth): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->safeSheetTitle($group->name));

        $sheet->fromArray(
            [array_merge(['nomor_anggota', 'nama', 'iuran_per_bulan'], self::MONTH_HEADERS)],
            null,
            'A1',
        );

        $rowNumber = 2;

        foreach ($members as $member) {
            $sheet->fromArray(
                [array_merge(
                    [$member->member_number, $member->displayName(), (float) $member->monthly_dues_amount],
                    $this->defaultMarks($member, $year, $throughMonth),
                )],
                null,
                "A{$rowNumber}",
            );

            $rowNumber++;
        }

        $this->styleGroupSheet($sheet, $rowNumber - 1);
    }

    /**
     * Tanda awal tiap bulan: B untuk bulan yang sudah jatuh tempo, - untuk bulan
     * yang belum jatuh tempo, sebelum anggota bergabung, atau anggota bebas iuran.
     *
     * @return array<int, string>
     */
    private function defaultMarks(Member $member, int $year, int $throughMonth): array
    {
        if ((float) $member->monthly_dues_amount <= 0.0) {
            return array_fill(0, 12, '-');
        }

        $startMonth = 1;

        if ($member->joined_at !== null && $member->joined_at->year === $year) {
            $startMonth = (int) $member->joined_at->month;
        }

        $marks = [];

        for ($month = 1; $month <= 12; $month++) {
            $marks[] = ($month >= $startMonth && $month <= $throughMonth) ? 'B' : '-';
        }

        return $marks;
    }

    private function styleGroupSheet(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        $sheet->getStyle('A1:O1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FDE68A');

        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(32);
        $sheet->getColumnDimension('C')->setWidth(16);

        foreach (range('D', 'O') as $column) {
            $sheet->getColumnDimension($column)->setWidth(6);
        }

        $sheet->getStyle("D2:O{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Kolom identitas dikunci agar tidak ikut tergeser saat menggulir ke kanan.
        $sheet->freezePane('D2');
    }

    /**
     * Excel melarang beberapa karakter di nama sheet dan membatasi 31 karakter.
     */
    private function safeSheetTitle(string $name): string
    {
        return mb_substr(str_replace(['/', '\\', '?', '*', '[', ']', ':'], '-', $name), 0, 31);
    }
}
