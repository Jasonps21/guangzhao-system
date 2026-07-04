<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class ExtractCjkFont extends Command
{
    /**
     * @var string
     */
    protected $signature = 'fonts:extract-cjk
        {--source=/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc : Path ke font CJK sumber (.ttf/.ttc)}
        {--face=0 : Indeks face bila sumber berupa TrueType Collection (.ttc)}';

    /**
     * @var string
     */
    protected $description = 'Ekstrak satu face dari font CJK (.ttc) menjadi storage/fonts/wqy-zenhei.ttf agar hanzi tampil pada kupon iuran (DomPDF tidak bisa subset .ttc).';

    public function handle(): int
    {
        $source = (string) $this->option('source');

        if (! is_file($source)) {
            $this->error("Font sumber tidak ditemukan: {$source}");

            return self::FAILURE;
        }

        $destination = storage_path('fonts/wqy-zenhei.ttf');

        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }

        try {
            $ttf = $this->extractFace((string) file_get_contents($source), (int) $this->option('face'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        file_put_contents($destination, $ttf);
        clearstatcache();

        $this->info(sprintf(
            'Font CJK ditulis ke %s (%s).',
            $destination,
            $this->humanFilesize((int) filesize($destination)),
        ));

        return self::SUCCESS;
    }

    /**
     * Salin satu face dari berkas sfnt/TTC menjadi TTF mandiri tanpa mengubah
     * data glyph (penyalinan byte tabel apa adanya, jadi outline tetap utuh).
     */
    private function extractFace(string $data, int $faceIndex): string
    {
        $tag = substr($data, 0, 4);

        if ($tag === 'ttcf') {
            $numFonts = $this->readUint32($data, 8);

            if ($faceIndex < 0 || $faceIndex >= $numFonts) {
                throw new RuntimeException("Face {$faceIndex} tidak tersedia (font punya {$numFonts} face).");
            }

            $offsetTableOffset = $this->readUint32($data, 12 + ($faceIndex * 4));
        } elseif ($tag === "\x00\x01\x00\x00" || $tag === 'true' || $tag === 'OTTO') {
            // Sudah berupa TTF tunggal — tidak perlu ekstraksi face.
            $offsetTableOffset = 0;
        } else {
            throw new RuntimeException('Format font sumber tidak dikenali.');
        }

        $sfntVersion = substr($data, $offsetTableOffset, 4);
        $numTables = $this->readUint16($data, $offsetTableOffset + 4);

        /** @var array<int, array{tag: string, checksum: string, body: string}> $tables */
        $tables = [];
        $recordOffset = $offsetTableOffset + 12;

        for ($i = 0; $i < $numTables; $i++) {
            $base = $recordOffset + ($i * 16);
            $tableTag = substr($data, $base, 4);
            $checksum = substr($data, $base + 4, 4);
            $tableOffset = $this->readUint32($data, $base + 8);
            $length = $this->readUint32($data, $base + 12);

            $tables[] = [
                'tag' => $tableTag,
                'checksum' => $checksum,
                'body' => substr($data, $tableOffset, $length),
            ];
        }

        usort($tables, fn (array $a, array $b): int => strcmp($a['tag'], $b['tag']));

        return $this->buildSfnt($sfntVersion, $tables);
    }

    /**
     * Rakit ulang sfnt: header + table directory dengan offset baru, lalu data
     * tabel yang di-pad ke kelipatan 4 byte sesuai spesifikasi OpenType.
     *
     * @param  array<int, array{tag: string, checksum: string, body: string}>  $tables
     */
    private function buildSfnt(string $sfntVersion, array $tables): string
    {
        $numTables = count($tables);
        $searchRange = (2 ** (int) floor(log($numTables, 2))) * 16;
        $entrySelector = (int) floor(log($numTables, 2));
        $rangeShift = ($numTables * 16) - $searchRange;

        $header = $sfntVersion
            .pack('n', $numTables)
            .pack('n', $searchRange)
            .pack('n', $entrySelector)
            .pack('n', $rangeShift);

        $directorySize = $numTables * 16;
        $offset = strlen($header) + $directorySize;

        $directory = '';
        $body = '';

        foreach ($tables as $table) {
            $length = strlen($table['body']);
            $padding = (4 - ($length % 4)) % 4;

            $directory .= $table['tag']
                .$table['checksum']
                .pack('N', $offset)
                .pack('N', $length);

            $body .= $table['body'].str_repeat("\x00", $padding);
            $offset += $length + $padding;
        }

        return $header.$directory.$body;
    }

    private function readUint32(string $data, int $offset): int
    {
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('N', substr($data, $offset, 4));

        return $unpacked[1];
    }

    private function readUint16(string $data, int $offset): int
    {
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('n', substr($data, $offset, 2));

        return $unpacked[1];
    }

    private function humanFilesize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }
}
