<?php

namespace App\Support;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Impor saldo awal iuran dari rekap Excel lama.
 *
 * Dua format didukung:
 *
 * 1. {@see self::importMatrix()} — tanda per bulan (kolom jan..des). Ini format
 *    utama, dipakai oleh templat yang dihasilkan `dues:export-template-saldo-awal`.
 *    Bisa menyatakan pembayaran yang loncat-loncat (Januari lunas, Februari
 *    bolong, Maret lunas).
 * 2. {@see self::import()} — satu angka "bulan terakhir bayar" per anggota.
 *    Lebih ringkas, tapi menganggap pembayaran selalu berurutan.
 *
 * Bulan yang lunas ditandai {@see CollectionMethod::Migrasi} agar bisa dibedakan
 * dari uang yang benar-benar ditagih lewat aplikasi.
 */
class OpeningBalanceImporter
{
    private const MONTH_NAMES = [
        'januari' => 1, 'jan' => 1,
        'februari' => 2, 'pebruari' => 2, 'feb' => 2,
        'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5,
        'juni' => 6, 'jun' => 6,
        'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'agu' => 8, 'ags' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10,
        'november' => 11, 'nopember' => 11, 'nov' => 11,
        'desember' => 12, 'des' => 12,
    ];

    /**
     * Tanda "sudah lunas" dan "belum bayar" yang diterima di sel bulan.
     */
    private const MARK_PAID = ['l', 'lunas', 'v', '✓', 'ya', '1'];

    private const MARK_UNPAID = ['b', 'belum', 'x', '0'];

    /**
     * Tanda "bulan ini tidak berlaku" — belum bergabung, bebas iuran, atau
     * bulan yang memang belum jatuh tempo. Tidak dibuatkan tagihan sama sekali.
     */
    private const MARK_NOT_APPLICABLE = ['-', '–', 'n/a', 'na'];

    /**
     * Ditolak: sel kosong atau tulisan yang tidak dikenali.
     */
    private const MARK_INVALID = 'tidak-terbaca';

    /**
     * Impor dari tanda per bulan.
     *
     * @param  array<int, array{row: int, member_number: mixed, sheet?: string, months: array<int, mixed>}>  $rows
     */
    public function importMatrix(array $rows, int $year, bool $dryRun = false): OpeningBalanceResult
    {
        return $this->run($rows, $dryRun, function (array $row): array|false {
            $map = [];

            foreach ($row['months'] as $month => $mark) {
                $parsed = $this->parseMark($mark);

                if ($parsed === self::MARK_INVALID) {
                    return false;
                }

                if ($parsed !== null) {
                    $map[$month] = $parsed;
                }
            }

            return $map;
        }, $year, fn (): string => 'Ada sel bulan yang kosong atau tidak dikenali (isi dengan L, B, atau -)');
    }

    /**
     * Impor dari satu angka "bulan terakhir bayar" per anggota.
     *
     * @param  array<int, array{row: int, member_number: mixed, last_paid_month: mixed}>  $rows
     * @param  int  $throughMonth  Bulan jatuh tempo terakhir yang dibuatkan tagihan (mis. 7 = sampai Juli).
     */
    public function import(array $rows, int $year, int $throughMonth, bool $dryRun = false): OpeningBalanceResult
    {
        return $this->run($rows, $dryRun, function (array $row) use ($throughMonth): array|false {
            $paidThrough = $this->parseMonth($row['last_paid_month']);

            if ($paidThrough === false) {
                return false;
            }

            $map = [];

            for ($month = 1; $month <= max($paidThrough ?? 0, $throughMonth); $month++) {
                $map[$month] = $paidThrough !== null && $month <= $paidThrough;
            }

            return $map;
        }, $year, fn (array $row): string => 'Bulan terakhir bayar tidak terbaca: '.(string) $row['last_paid_month']);
    }

    /**
     * Kerangka bersama kedua format: resolusi anggota, transaksi, dry-run.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>, Member): (array<int, bool>|false)  $buildMonthMap
     * @param  callable(array<string, mixed>): string  $describeFailure
     */
    private function run(array $rows, bool $dryRun, callable $buildMonthMap, int $year, callable $describeFailure): OpeningBalanceResult
    {
        $result = new OpeningBalanceResult;

        /*
         * Impor saldo awal menyentuh ratusan tagihan sekaligus. Barisnya tidak
         * dicatat satu per satu; yang tercatat adalah satu ringkasan di bawah.
         */
        activity()->withoutLogs(function () use ($rows, $year, $dryRun, $buildMonthMap, $describeFailure, $result): void {
            DB::transaction(function () use ($rows, $year, $dryRun, $buildMonthMap, $describeFailure, $result): void {
                foreach ($rows as $row) {
                    $memberNumber = trim((string) ($row['member_number'] ?? ''));

                    if ($memberNumber === '') {
                        continue;
                    }

                    $member = Member::query()
                        ->where('member_number', $memberNumber)
                        ->where('status', MemberStatus::Aktif)
                        ->first();

                    if ($member === null) {
                        $result->addIssue((string) ($row['sheet'] ?? '-'), $row['row'], $memberNumber, 'Nomor anggota tidak ditemukan / tidak aktif');

                        continue;
                    }

                    $monthMap = $buildMonthMap($row, $member);

                    if ($monthMap === false) {
                        $result->addIssue((string) ($row['sheet'] ?? '-'), $row['row'], $memberNumber, $describeFailure($row));

                        continue;
                    }

                    $this->applyMonthMap($member, $year, $monthMap, $result);
                }

                if ($dryRun) {
                    DB::rollBack();
                }
            });
        });

        /*
         * Uji-coba tidak menulis apa pun ke database, jadi tidak ada yang perlu
         * dipertanggungjawabkan di jejak audit.
         */
        if (! $dryRun) {
            app(ActivityRecorder::class)->imported('Impor saldo awal iuran', [
                'tahun' => $year,
                'anggota_diproses' => $result->membersProcessed,
                'tagihan_lunas' => $result->paidCreated,
                'tagihan_tunggakan' => $result->arrearsCreated,
                'total_tunggakan' => $result->arrearsTotal,
                'baris_bermasalah' => count($result->issues),
            ]);
        }

        return $result;
    }

    /**
     * Tuliskan status tiap bulan untuk satu anggota.
     *
     * @param  array<int, bool>  $monthMap  bulan => sudah lunas?
     */
    private function applyMonthMap(Member $member, int $year, array $monthMap, OpeningBalanceResult $result): void
    {
        // Anggota bebas iuran / prasejahtera tidak pernah punya tagihan.
        if ((float) $member->monthly_dues_amount <= 0.0) {
            $result->skippedFreeOfDues++;

            return;
        }

        $startMonth = $this->startMonth($member, $year);

        if ($startMonth === null) {
            $result->skippedFreeOfDues++;

            return;
        }

        $result->membersProcessed++;
        $arrearsMonths = 0;
        $arrearsAmount = 0.0;

        foreach ($monthMap as $month => $isPaid) {
            // Anggota yang baru bergabung tengah tahun tidak ditagih bulan sebelumnya.
            if ($month < $startMonth) {
                continue;
            }

            if (! $this->createRecord($member, $year, $month, $isPaid)) {
                $result->skippedExisting++;

                continue;
            }

            if ($isPaid) {
                $result->paidCreated++;

                continue;
            }

            $result->arrearsCreated++;
            $arrearsMonths++;
            $arrearsAmount += (float) $member->monthly_dues_amount;
        }

        $result->arrearsTotal += $arrearsAmount;

        if ($arrearsMonths > 0) {
            $result->arrearsPerMember[] = [
                'member_number' => $member->member_number,
                'name' => $member->displayName(),
                'months' => $arrearsMonths,
                'amount' => $arrearsAmount,
            ];
        }
    }

    /**
     * Baris iuran baru untuk satu bulan. Baris yang sudah ada tidak disentuh —
     * impor tidak boleh menimpa catatan pembayaran yang sudah berjalan.
     */
    private function createRecord(Member $member, int $year, int $month, bool $isPaid): bool
    {
        $exists = $member->duesRecords()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->exists();

        if ($exists) {
            return false;
        }

        $attributes = [
            'period_year' => $year,
            'period_month' => $month,
            'amount_due' => $member->monthly_dues_amount,
            'status' => $isPaid ? DuesStatus::Lunas : DuesStatus::BelumBayar,
        ];

        if ($isPaid) {
            $attributes += [
                'amount_paid' => $member->monthly_dues_amount,
                'paid_at' => $this->paidAtFor($year, $month),
                'collection_method' => CollectionMethod::Migrasi,
                'notes' => 'Saldo awal dari rekap Excel.',
            ];
        }

        $member->duesRecords()->create($attributes);

        return true;
    }

    /**
     * Tanggal bayar untuk data migrasi: akhir bulan periode, tapi tidak pernah
     * melewati hari ini (anggota yang bayar di muka tetap tercatat hari impor).
     */
    private function paidAtFor(int $year, int $month): Carbon
    {
        $endOfPeriod = Carbon::create($year, $month, 1)->endOfMonth();

        return $endOfPeriod->isFuture() ? Carbon::now()->startOfDay() : $endOfPeriod->startOfDay();
    }

    /**
     * Bulan pertama yang boleh ditagih.
     *
     * @return int|null null bila anggota belum bergabung di tahun tersebut.
     */
    private function startMonth(Member $member, int $year): ?int
    {
        if ($member->joined_at === null) {
            return 1;
        }

        if ($member->joined_at->year > $year) {
            return null;
        }

        return $member->joined_at->year === $year ? (int) $member->joined_at->month : 1;
    }

    /**
     * @return bool|string|null true = lunas, false = belum bayar, null = bulan
     *                          tidak berlaku, {@see self::MARK_INVALID} = ditolak.
     */
    private function parseMark(mixed $value): bool|string|null
    {
        $raw = mb_strtolower(trim((string) ($value ?? '')));

        if (in_array($raw, self::MARK_NOT_APPLICABLE, true)) {
            return null;
        }

        if (in_array($raw, self::MARK_PAID, true)) {
            return true;
        }

        if (in_array($raw, self::MARK_UNPAID, true)) {
            return false;
        }

        // Sel kosong ikut ditolak: templat sudah terisi penuh, jadi kosong
        // berarti terhapus tanpa sengaja — bukan sesuatu yang boleh ditebak.
        return self::MARK_INVALID;
    }

    /**
     * @return int|null|false Nomor bulan, null bila kosong (belum pernah bayar), false bila tidak terbaca.
     */
    private function parseMonth(mixed $value): int|null|false
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '' || $raw === '-' || $raw === '0') {
            return null;
        }

        if (is_numeric($raw)) {
            $month = (int) $raw;

            return ($month >= 1 && $month <= 12) ? $month : false;
        }

        return self::MONTH_NAMES[mb_strtolower($raw)] ?? false;
    }
}
