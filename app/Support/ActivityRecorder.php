<?php

namespace App\Support;

use App\Enums\ActivityCategory;
use App\Enums\PrintedDocument;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Satu-satunya pintu untuk mencatat kegiatan yang bukan perubahan data.
 *
 * Perubahan data dicatat sendiri oleh trait LogsActivity pada tiap model.
 * Yang lewat sini adalah kegiatan yang tidak meninggalkan jejak di tabel mana
 * pun — masuk/keluar, pencetakan, ekspor/impor — beserta ringkasan aksi massal
 * yang kalau dibiarkan akan menghasilkan ratusan baris serupa.
 *
 * Semua kalimat log lahir di kelas ini supaya tidak ada dua tempat yang menulis
 * kegiatan sama dengan istilah berbeda.
 */
class ActivityRecorder
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function printed(PrintedDocument $document, array $details = [], ?Model $subject = null): void
    {
        $this->write(
            ActivityCategory::Cetak,
            $document->getLabel(),
            ['dokumen' => $document->value] + $details,
            $subject,
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function exported(string $description, array $details = []): void
    {
        $this->write(ActivityCategory::Berkas, $description, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function imported(string $description, array $details = []): void
    {
        $this->write(ActivityCategory::Berkas, $description, $details);
    }

    /**
     * Ringkasan satu aksi yang menyentuh banyak baris sekaligus.
     *
     * Pemanggilnya bertanggung jawab membungkus perubahan barisnya dengan
     * `activity()->withoutLogs()`, supaya tindakan yang sama tidak tercatat
     * dua kali — sekali sebagai ringkasan dan sekali per baris.
     *
     * @param  array<string, mixed>  $details
     */
    public function bulk(string $description, array $details = [], ?Model $subject = null): void
    {
        $this->write(ActivityCategory::PerubahanData, $description, $details, $subject);
    }

    public function signedIn(Authenticatable $user): void
    {
        $this->write(ActivityCategory::Autentikasi, 'Masuk ke sistem', $this->request(), causer: $user);
    }

    public function signedOut(?Authenticatable $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        $this->write(ActivityCategory::Autentikasi, 'Keluar dari sistem', $this->request(), causer: $user);
    }

    /**
     * Hanya nama pengguna yang dicoba dan asal permintaannya yang disimpan.
     * Kata sandinya tidak pernah ikut: log ini dibaca Super Admin dan ikut
     * terbawa ke setiap cadangan basis data.
     */
    public function signInFailed(?string $username): void
    {
        $this->write(
            ActivityCategory::Autentikasi,
            'Gagal masuk ke sistem',
            ['nama_pengguna' => $username] + $this->request(),
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function write(
        ActivityCategory $category,
        string $description,
        array $properties = [],
        ?Model $subject = null,
        ?Authenticatable $causer = null,
    ): void {
        $logger = activity()->useLog($category->value);

        $causer ??= Auth::user();

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $logger->withProperties(array_filter(
            $properties,
            fn (mixed $value): bool => $value !== null && $value !== '',
        ))->log($description);
    }

    /**
     * @return array<string, string|null>
     */
    private function request(): array
    {
        return ['ip' => request()->ip()];
    }
}
