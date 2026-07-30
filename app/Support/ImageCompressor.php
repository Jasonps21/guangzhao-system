<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mengompres gambar unggahan menjadi WebP/JPEG berkualitas tinggi memakai GD.
 *
 * Foto dari HP bisa berukuran beberapa MB; setelah di-resize ke dimensi wajar
 * dan disandikan ulang, ukurannya turun drastis tanpa terlihat pecah, sehingga
 * disk server tetap lega meski anggota bertambah banyak. WebP paling hemat untuk
 * tampilan web; JPEG dipakai bila gambar harus tercetak di PDF (dompdf tidak
 * mendukung WebP).
 */
class ImageCompressor
{
    /**
     * Kompres file lalu simpan pada disk tujuan.
     *
     * @param  'webp'|'jpeg'  $format
     * @return string Path relatif terhadap root disk (untuk disimpan ke kolom model).
     */
    public function store(
        UploadedFile $file,
        string $disk,
        string $directory,
        int $maxDimension = 1600,
        int $quality = 82,
        string $visibility = 'public',
        string $format = 'webp',
    ): string {
        $image = $this->createImage($file);
        $image = $this->correctOrientation($image, $file);
        $image = $this->resize($image, $maxDimension);

        $path = trim($directory, '/').'/'.Str::random(40).'.'.($format === 'jpeg' ? 'jpg' : 'webp');

        Storage::disk($disk)->put($path, $this->encode($image, $format, $quality), $visibility);

        imagedestroy($image);

        return $path;
    }

    private function createImage(UploadedFile $file): GdImage
    {
        $contents = file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($contents !== false ? $contents : '');

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Berkas gambar tidak dapat dibaca.');
        }

        return $image;
    }

    /**
     * Putar gambar sesuai metadata EXIF agar foto HP tidak tampil miring.
     */
    private function correctOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? 1;

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        imagedestroy($image);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * Perkecil gambar bila sisi terpanjang melebihi $maxDimension, dengan
     * penyandelan bikubik agar hasil tetap tajam. Gambar kecil dibiarkan apa adanya.
     */
    private function resize(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longestSide = max($width, $height);

        if ($longestSide <= $maxDimension) {
            return $image;
        }

        $scale = $maxDimension / $longestSide;
        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);

        $resized = imagescale($image, $newWidth, $newHeight, IMG_BICUBIC);
        imagedestroy($image);

        if (! $resized instanceof GdImage) {
            throw new RuntimeException('Gambar gagal di-resize.');
        }

        return $resized;
    }

    /**
     * @param  'webp'|'jpeg'  $format
     */
    private function encode(GdImage $image, string $format, int $quality): string
    {
        ob_start();

        if ($format === 'jpeg') {
            // Latar putih agar area transparan (mis. PNG) tidak menjadi hitam pada JPEG.
            $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagejpeg($canvas, null, $quality);
            imagedestroy($canvas);
        } else {
            imagewebp($image, null, $quality);
        }

        return (string) ob_get_clean();
    }
}
