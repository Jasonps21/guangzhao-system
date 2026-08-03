<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kaitkan tagihan bulanan dengan nota yang melunasinya. Sebelumnya hubungan itu
 * hanya tersirat dari anggota + tahun + rentang bulan, sehingga membatalkan satu
 * nota bisa ikut mengembalikan bulan milik nota lain — di dua nota yang terbit
 * pada hari yang sama untuk anggota yang sama, rentangnya bisa saling menimpa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dues_records', function (Blueprint $table): void {
            $table->foreignId('receipt_id')
                ->nullable()
                ->after('submission_id')
                ->constrained('dues_receipts')
                ->nullOnDelete();
        });

        $this->backfillExistingReceipts();
    }

    public function down(): void
    {
        Schema::table('dues_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('receipt_id');
        });
    }

    /**
     * Isi kaitan untuk nota yang sudah telanjur terbit, memakai jejak yang
     * ditinggalkan pembayaran kantor: dilunasi oleh petugas yang sama, pada
     * tanggal yang sama, lewat jalur admin, dan bulannya masuk rentang nota.
     */
    private function backfillExistingReceipts(): void
    {
        DB::statement(<<<'SQL'
            UPDATE dues_records AS dr
            JOIN dues_receipts AS rc
              ON rc.member_id = dr.member_id
             AND rc.period_year = dr.period_year
             AND dr.period_month BETWEEN rc.from_month AND rc.to_month
             AND dr.paid_at = rc.issued_at
             AND dr.recorded_by <=> rc.issued_by
            SET dr.receipt_id = rc.id
            WHERE dr.receipt_id IS NULL
              AND dr.status = 'lunas'
              AND dr.collection_method = 'admin'
        SQL);
    }
};
