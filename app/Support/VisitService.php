<?php

namespace App\Support;

use App\Enums\VisitOutcome;
use App\Models\Member;
use App\Models\MemberVisit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VisitService
{
    private const DISK = 'public';

    private const DIRECTORY = 'member-houses';

    /**
     * Sisi terpanjang foto rumah dibatasi agar hemat storage & memori.
     */
    private const MAX_DIMENSION = 1280;

    /**
     * Catat satu kunjungan lapangan dan perbarui titik/foto/alamat terkini
     * anggota dengan data yang benar-benar diberikan. Riwayat tiap kunjungan
     * tetap tersimpan permanen di member_visits.
     *
     * @param  array{latitude?: float|string|null, longitude?: float|string|null, address?: string|null, outcome?: VisitOutcome|string|null, note?: string|null}  $data
     */
    public function record(Member $member, User $collector, array $data, ?UploadedFile $photo = null): MemberVisit
    {
        $latitude = $this->normalizeCoordinate($data['latitude'] ?? null);
        $longitude = $this->normalizeCoordinate($data['longitude'] ?? null);
        $address = $data['address'] ?? null;
        $photoPath = $photo !== null ? $this->storePhoto($photo) : null;

        return DB::transaction(function () use ($member, $collector, $data, $latitude, $longitude, $address, $photoPath): MemberVisit {
            $visit = $member->visits()->create([
                'user_id' => $collector->getKey(),
                'visited_at' => now(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'address_snapshot' => $address,
                'photo_path' => $photoPath,
                'outcome' => $data['outcome'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $update = [
                'location_updated_at' => now(),
                'location_updated_by' => $collector->getKey(),
            ];

            if ($latitude !== null && $longitude !== null) {
                $update['latitude'] = $latitude;
                $update['longitude'] = $longitude;
            }

            if ($photoPath !== null) {
                $update['house_photo_path'] = $photoPath;
            }

            if (filled($address)) {
                $update['address'] = $address;
            }

            $member->update($update);

            return $visit;
        });
    }

    /**
     * Kompres & simpan foto rumah sebagai JPEG berlatar putih pada disk publik.
     */
    private function storePhoto(UploadedFile $photo): string
    {
        $path = self::DIRECTORY.'/'.Str::random(40).'.jpg';

        Storage::disk(self::DISK)->put($path, $this->encodeCompressedJpeg((string) $photo->getRealPath()));

        return $path;
    }

    private function encodeCompressedJpeg(string $absolutePath): string
    {
        $info = @getimagesize($absolutePath);

        $source = match ($info['mime'] ?? null) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => @imagecreatefromwebp($absolutePath),
            'image/gif' => @imagecreatefromgif($absolutePath),
            default => false,
        };

        // Format tak dikenal GD — simpan apa adanya agar tetap tersimpan.
        if ($source === false) {
            return (string) file_get_contents($absolutePath);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 80);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }

    private function normalizeCoordinate(float|string|null $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
