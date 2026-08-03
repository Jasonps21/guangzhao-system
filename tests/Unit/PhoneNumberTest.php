<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('rawNumbers')]
    public function test_it_normalizes_what_a_collector_types(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($raw));
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function rawNumbers(): array
    {
        return [
            'sudah rapi' => ['082291297777', '082291297777'],
            'berspasi' => ['0895 8034 5498', '089580345498'],
            'berstrip' => ['0895-8034-5498', '089580345498'],
            'berkurung' => ['(0411) 362589', '0411362589'],
            'awalan +62' => ['+62 895 8034 5498', '089580345498'],
            'awalan 62' => ['62 812 4189 723', '08124189723'],
            'awalan +62 rapat' => ['+62895 80345498', '089580345498'],
            'kosong' => ['', null],
            'spasi saja' => ['   ', null],
            'null' => [null, null],
        ];
    }

    /**
     * Sebagian besar anggota lansia dan hanya memakai telepon rumah, jadi
     * nomor rumah harus ikut diterima — bukan hanya nomor HP.
     */
    #[DataProvider('validityChecks')]
    public function test_it_accepts_any_reachable_indonesian_number(?string $raw, bool $expected): void
    {
        $this->assertSame($expected, PhoneNumber::isValid($raw));
    }

    /**
     * @return array<string, array{?string, bool}>
     */
    public static function validityChecks(): array
    {
        return [
            'HP 12 digit' => ['082291297777', true],
            'HP 13 digit' => ['0895 3998 13035', true],
            'HP 11 digit' => ['0812 4189 723', true],
            'HP dari +62' => ['+62 812 4189 723', true],
            'telepon rumah berkode area' => ['0411-362589', true],
            'telepon rumah 8 digit' => ['0411-3622772', true],
            'telepon rumah tanpa kode area' => ['3617746', true],
            'dua nomor sekaligus' => ['0816 4383 039 / 0851 0175 0137', false],
            'dua nomor dipisah koma' => ['3613717, 085343591718', false],
            'salah ketik berawalan angka' => ['10 0895347208453', false],
            'terlalu pendek' => ['36177', false],
            'terlalu panjang' => ['08123456789012345', false],
            'huruf' => ['tidak punya', false],
            'kosong' => ['', false],
            'null' => [null, false],
        ];
    }
}
