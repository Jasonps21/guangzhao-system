<?php

namespace App\Support;

/**
 * Nomor telepon seperti yang diketik orang di lapangan: berspasi, berstrip,
 * kadang berawalan +62. Kelas ini merapikannya menjadi satu bentuk baku dan
 * menilai apakah hasilnya masih masuk akal sebagai nomor yang bisa dihubungi.
 */
class PhoneNumber
{
    /**
     * Satu nomor Indonesia yang wajar setelah dirapikan — angka semua,
     * 7 sampai 13 digit. Rentang itu menampung ketiganya: HP (08xx, 11–13
     * digit), telepon rumah berkode area (0411xxxxxx, 10–12), dan nomor
     * rumah lokal tanpa kode area (3617746, 7). Banyak anggota lansia hanya
     * punya telepon rumah, jadi nomor rumah harus ikut diterima.
     *
     * Yang tersaring: dua nomor sekaligus dalam satu kolom, huruf, dan
     * deretan angka yang terlalu panjang untuk jadi satu nomor.
     */
    private const VALID_PATTERN = '/^\d{7,13}$/';

    /**
     * Buang pemisah yang biasa diketik, lalu samakan awalan +62/62 menjadi 0.
     * Mengembalikan null bila tidak ada isi yang tersisa.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = (string) preg_replace('/[\s().\-]/u', '', $raw);
        $value = (string) preg_replace('/^\+?62/', '0', $value);

        return $value === '' ? null : $value;
    }

    /**
     * Apakah nilai ini satu nomor telepon yang wajar setelah dirapikan?
     * HP maupun telepon rumah sama-sama diterima.
     */
    public static function isValid(?string $raw): bool
    {
        return preg_match(self::VALID_PATTERN, (string) self::normalize($raw)) === 1;
    }
}
