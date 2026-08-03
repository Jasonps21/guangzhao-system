<?php

namespace App\Support;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Enums\SubmissionStatus;
use App\Models\DuesReceipt;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\PaymentSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Mark a single dues record paid in full. (§6.2 / §6.3)
     */
    public function pay(DuesRecord $record, User $by, CollectionMethod $method): bool
    {
        return $record->markPaid($by, $method);
    }

    /**
     * Batalkan pembayaran lapangan yang baru saja salah dicatat kolektor.
     * Ditolak bila bukan miliknya, sudah lewat tenggang, atau bagian setoran.
     */
    public function undoFieldPayment(DuesRecord $record, User $by): bool
    {
        if (! $record->canBeUndoneBy($by)) {
            return false;
        }

        return $record->revertToUnpaid();
    }

    /**
     * Pay a contiguous range of months for one member in a single transaction.
     * Creates any missing dues rows using the member's current monthly amount. (§6.4)
     *
     * @return int Number of records marked paid.
     */
    public function payRange(Member $member, int $year, int $fromMonth, int $toMonth, User $by, CollectionMethod $method): int
    {
        return $this->payMonths($member, $year, $fromMonth, $toMonth, $by, $method)->count();
    }

    /**
     * Sama seperti {@see self::payRange()}, tetapi sekalian menerbitkan nota
     * TANDA TERIMA yang bisa dicetak dan dicetak ulang. Hanya bulan yang benar
     * benar baru dilunasi yang masuk hitungan nota.
     *
     * @param  float|null  $totalReceived  Uang yang benar-benar diterima di meja.
     *                                     Boleh berbeda dari jumlah tagihannya —
     *                                     yang tercetak di nota adalah angka ini.
     *                                     Null berarti pakai jumlah tagihan.
     * @param  string|null  $receivedByName  Nama penerima uang bila bukan petugas
     *                                       yang menginput (mis. uang dititipkan).
     * @return DuesReceipt|null Null bila tak ada bulan baru yang dilunasi.
     */
    public function payRangeAndIssueReceipt(
        Member $member,
        int $year,
        int $fromMonth,
        int $toMonth,
        User $by,
        CollectionMethod $method,
        ?float $totalReceived = null,
        ?string $receivedByName = null,
    ): ?DuesReceipt {
        return DB::transaction(function () use ($member, $year, $fromMonth, $toMonth, $by, $method, $totalReceived, $receivedByName): ?DuesReceipt {
            $paid = $this->payMonths($member, $year, $fromMonth, $toMonth, $by, $method);

            if ($paid->isEmpty()) {
                return null;
            }

            $receipt = DuesReceipt::create([
                'member_id' => $member->getKey(),
                'period_year' => $year,
                'from_month' => (int) $paid->min('period_month'),
                'to_month' => (int) $paid->max('period_month'),
                'months_count' => $paid->count(),
                'total_amount' => $totalReceived ?? $paid->sum(fn (DuesRecord $record): float => (float) $record->amount_paid),
                'issued_at' => CarbonImmutable::now(),
                'issued_by' => $by->getKey(),
                'received_by_name' => $receivedByName,
            ]);

            // Tautkan agar pembatalan nota nanti tahu persis bulan mana miliknya,
            // tidak menebak dari rentang tanggal. {@see self::cancelReceipt()}
            DuesRecord::whereKey($paid->pluck('id')->all())->update(['receipt_id' => $receipt->getKey()]);

            return $receipt;
        });
    }

    /**
     * Batalkan pembayaran kantor: nota dihapus dan seluruh bulan yang dilunasinya
     * kembali BELUM BAYAR, sehingga uangnya juga keluar dari Laporan Keuangan.
     *
     * Hanya bulan yang benar-benar tertaut pada nota ini yang dikembalikan; bulan
     * yang sudah lunas lebih dulu lewat jalur lain tidak ikut terbawa. Nota lama
     * yang terbit sebelum kolom tautan ada akan jatuh ke pencocokan periode.
     *
     * @return int Jumlah bulan yang dikembalikan menjadi belum bayar.
     */
    public function cancelReceipt(DuesReceipt $receipt, User $by): int
    {
        return DB::transaction(function () use ($receipt, $by): int {
            $records = $receipt->duesRecords()->get();

            if ($records->isEmpty()) {
                $records = $this->guessRecordsFor($receipt);
            }

            $reverted = $records->filter(fn (DuesRecord $record): bool => $record->revertToUnpaid());

            activity()
                ->causedBy($by)
                ->performedOn($receipt)
                ->withProperties([
                    'receipt_number' => $receipt->receipt_number,
                    'member' => $receipt->member?->displayName(),
                    'member_number' => $receipt->member?->member_number,
                    'period' => $receipt->periodRangeLabel(),
                    'total_amount' => (float) $receipt->total_amount,
                    'months_reverted' => $reverted->count(),
                    'dues_record_ids' => $reverted->pluck('id')->all(),
                ])
                ->log('Nota pembayaran kantor dibatalkan');

            $receipt->delete();

            return $reverted->count();
        });
    }

    /**
     * Cari tagihan milik nota yang terbit sebelum kolom `receipt_id` ada, memakai
     * jejak pembayaran kantor: petugas & tanggal yang sama, lewat jalur admin.
     *
     * @return Collection<int, DuesRecord>
     */
    private function guessRecordsFor(DuesReceipt $receipt): Collection
    {
        return DuesRecord::query()
            ->where('member_id', $receipt->member_id)
            ->where('period_year', $receipt->period_year)
            ->whereBetween('period_month', [$receipt->from_month, $receipt->to_month])
            ->where('status', DuesStatus::Lunas)
            ->where('collection_method', CollectionMethod::Admin)
            ->whereNull('receipt_id')
            ->whereDate('paid_at', $receipt->issued_at)
            ->get();
    }

    /**
     * Perkiraan tagihan untuk rentang bulan tersebut — dipakai sebagai angka
     * awal kolom "Total Uang Diterima" sebelum petugas menyesuaikannya. Bulan
     * yang sudah lunas tidak ikut dihitung, dan bulan yang barisnya belum ada
     * memakai iuran bulanan anggota saat ini.
     */
    public function outstandingTotal(Member $member, int $year, int $fromMonth, int $toMonth): float
    {
        [$fromMonth, $toMonth] = [min($fromMonth, $toMonth), max($fromMonth, $toMonth)];

        $existing = $member->duesRecords()
            ->where('period_year', $year)
            ->whereBetween('period_month', [$fromMonth, $toMonth])
            ->get()
            ->keyBy('period_month');

        $total = 0.0;

        for ($month = $fromMonth; $month <= $toMonth; $month++) {
            $record = $existing->get($month);

            if ($record === null) {
                $total += (float) $member->monthly_dues_amount;

                continue;
            }

            if ($record->status !== DuesStatus::Lunas) {
                $total += (float) $record->amount_due;
            }
        }

        return $total;
    }

    /**
     * Lunasi rentang bulan dalam satu transaksi, membuat baris tagihan yang
     * belum ada memakai iuran bulanan anggota saat ini.
     *
     * @return Collection<int, DuesRecord> Tagihan yang berubah menjadi lunas kali ini.
     */
    private function payMonths(Member $member, int $year, int $fromMonth, int $toMonth, User $by, CollectionMethod $method): Collection
    {
        [$fromMonth, $toMonth] = [min($fromMonth, $toMonth), max($fromMonth, $toMonth)];
        $paidAt = CarbonImmutable::now();

        return DB::transaction(function () use ($member, $year, $fromMonth, $toMonth, $by, $method, $paidAt): Collection {
            $paid = collect();

            for ($month = $fromMonth; $month <= $toMonth; $month++) {
                $record = $member->duesRecords()->firstOrCreate(
                    ['period_year' => $year, 'period_month' => $month],
                    ['amount_due' => $member->monthly_dues_amount, 'status' => DuesStatus::BelumBayar],
                );

                if ($record->markPaid($by, $method, $paidAt)) {
                    $paid->push($record);
                }
            }

            return $paid;
        });
    }

    /**
     * Catat setoran kolektor untuk beberapa bulan sekaligus sebagai MENUNGGU
     * PERSETUJUAN admin — belum dihitung lunas sampai uangnya diverifikasi. (§6.4)
     *
     * Tagihan yang sudah lunas / sudah menunggu dilewati; baris bulan yang belum
     * ada dibuat otomatis memakai iuran bulanan anggota saat ini.
     *
     * @return PaymentSubmission|null Setoran yang dibuat, atau null bila tak ada bulan yang bisa diajukan.
     */
    public function submitRange(Member $member, int $year, int $fromMonth, int $toMonth, User $collector): ?PaymentSubmission
    {
        [$fromMonth, $toMonth] = [min($fromMonth, $toMonth), max($fromMonth, $toMonth)];

        return DB::transaction(function () use ($member, $year, $fromMonth, $toMonth, $collector): ?PaymentSubmission {
            $submission = PaymentSubmission::create([
                'member_id' => $member->getKey(),
                'collector_id' => $collector->getKey(),
                'period_year' => $year,
                'from_month' => $fromMonth,
                'to_month' => $toMonth,
                'total_amount' => 0,
                'status' => SubmissionStatus::Pending,
            ]);

            $total = 0.0;

            for ($month = $fromMonth; $month <= $toMonth; $month++) {
                $record = $member->duesRecords()->firstOrCreate(
                    ['period_year' => $year, 'period_month' => $month],
                    ['amount_due' => $member->monthly_dues_amount, 'status' => DuesStatus::BelumBayar],
                );

                if ($record->markPendingApproval($collector, $submission)) {
                    $total += (float) $record->amount_due;
                }
            }

            if ($submission->duesRecords()->count() === 0) {
                $submission->delete();

                return null;
            }

            $submission->update(['total_amount' => $total]);

            return $submission;
        });
    }

    /**
     * Admin menyetujui setoran: seluruh bulan menunggu menjadi LUNAS. (§6.4)
     */
    public function approveSubmission(PaymentSubmission $submission, User $by): bool
    {
        if (! $submission->isPending()) {
            return false;
        }

        return DB::transaction(function () use ($submission, $by): bool {
            $paidAt = CarbonImmutable::now();

            foreach ($submission->duesRecords as $record) {
                $record->confirmPaid($paidAt);
            }

            $submission->approve($by);

            return true;
        });
    }

    /**
     * Admin menolak setoran: seluruh bulan kembali BELUM BAYAR. (§6.4)
     */
    public function rejectSubmission(PaymentSubmission $submission, User $by, ?string $notes = null): bool
    {
        if (! $submission->isPending()) {
            return false;
        }

        return DB::transaction(function () use ($submission, $by, $notes): bool {
            foreach ($submission->duesRecords as $record) {
                $record->releaseToUnpaid();
            }

            $submission->reject($by, $notes);

            return true;
        });
    }
}
