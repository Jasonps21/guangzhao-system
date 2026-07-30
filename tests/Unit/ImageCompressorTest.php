<?php

namespace Tests\Unit;

use App\Support\ImageCompressor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageCompressorTest extends TestCase
{
    private function makeImage(int $width, int $height, string $extension = 'jpg'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 160, 200));

        $path = tempnam(sys_get_temp_dir(), 'img').'.'.$extension;
        match ($extension) {
            'png' => imagepng($image, $path),
            default => imagejpeg($image, $path, 100),
        };
        imagedestroy($image);

        return new UploadedFile($path, 'photo.'.$extension, mime_content_type($path), null, true);
    }

    public function test_it_resizes_large_images_down_to_the_max_dimension(): void
    {
        Storage::fake('public');

        $path = app(ImageCompressor::class)->store(
            $this->makeImage(3000, 2000),
            disk: 'public',
            directory: 'member-houses',
            maxDimension: 1600,
        );

        Storage::disk('public')->assertExists($path);

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(1600, $width);
        $this->assertSame(1067, $height);
    }

    public function test_it_outputs_webp_and_shrinks_file_size(): void
    {
        Storage::fake('public');

        $source = $this->makeImage(2400, 1600);

        $path = app(ImageCompressor::class)->store(
            $source,
            disk: 'public',
            directory: 'member-houses',
            maxDimension: 1600,
        );

        $this->assertStringEndsWith('.webp', $path);
        $this->assertLessThan(
            $source->getSize(),
            strlen(Storage::disk('public')->get($path)),
        );
    }

    public function test_it_outputs_jpeg_when_requested(): void
    {
        Storage::fake('local');

        $path = app(ImageCompressor::class)->store(
            $this->makeImage(1000, 1000, 'png'),
            disk: 'local',
            directory: 'member-photos',
            maxDimension: 800,
            visibility: 'private',
            format: 'jpeg',
        );

        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('image/jpeg', mime_content_type(Storage::disk('local')->path($path)));
    }

    public function test_it_keeps_small_images_at_their_original_dimensions(): void
    {
        Storage::fake('public');

        $path = app(ImageCompressor::class)->store(
            $this->makeImage(500, 400),
            disk: 'public',
            directory: 'member-houses',
            maxDimension: 1600,
        );

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(500, $width);
        $this->assertSame(400, $height);
    }
}
