<?php

namespace App\Support;

/**
 * Ringkasan hasil impor saldo awal, dipakai untuk rekonsiliasi dengan Excel
 * sebelum data dianggap sah.
 */
class OpeningBalanceResult
{
    public int $membersProcessed = 0;

    public int $paidCreated = 0;

    public int $arrearsCreated = 0;

    public int $skippedExisting = 0;

    public int $skippedFreeOfDues = 0;

    public float $arrearsTotal = 0.0;

    /**
     * Baris yang tidak bisa diproses: nomor anggota tak dikenal, bulan tak
     * terbaca, dsb. Sengaja tidak ditebak — harus diperbaiki di Excel.
     *
     * @var array<int, array{sheet: string, row: int, member_number: string, reason: string}>
     */
    public array $issues = [];

    /**
     * Tunggakan per anggota untuk dicocokkan manual dengan Excel.
     *
     * @var array<int, array{member_number: string, name: string, months: int, amount: float}>
     */
    public array $arrearsPerMember = [];

    public function addIssue(string $sheet, int $row, string $memberNumber, string $reason): void
    {
        $this->issues[] = ['sheet' => $sheet, 'row' => $row, 'member_number' => $memberNumber, 'reason' => $reason];
    }

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }
}
