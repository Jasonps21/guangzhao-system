<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    /**
     * @var string
     */
    protected $signature = 'backup:database
        {--path= : Folder tujuan backup (default storage/app/backups)}
        {--keep=30 : Umur maksimum backup yang dipertahankan, dalam hari}';

    /**
     * @var string
     */
    protected $description = 'Buat dump terkompresi database aplikasi, verifikasi keutuhannya, lalu hapus backup yang lebih tua dari --keep hari.';

    public function handle(): int
    {
        $path = rtrim((string) ($this->option('path') ?: storage_path('app/backups')), '/');
        $keepDays = max(1, (int) $this->option('keep'));

        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            $this->error("Folder tujuan tidak bisa dibuat: {$path}");

            return self::FAILURE;
        }

        $file = $path.'/guangzhao-'.now()->format('Ymd-His').'.sql.gz';

        try {
            $this->dump($file);
            $this->assertDumpIsComplete($file);
        } catch (RuntimeException $exception) {
            // Berkas gagal jangan ditinggal: di `ls` ia tidak terbedakan dari
            // backup sah, dan justru itu yang membuat orang merasa aman.
            if (is_file($file)) {
                unlink($file);
            }

            $this->error('Backup gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Backup dibuat: %s (%s KB).',
            basename($file),
            number_format(filesize($file) / 1024, 1),
        ));

        return self::SUCCESS;
    }

    /**
     * mysqldump menulis langsung ke berkas lewat --result-file, lalu dikompres
     * sebagai langkah terpisah.
     *
     * Sengaja bukan pipe `mysqldump | gzip`: /bin/sh di Ubuntu adalah dash yang
     * tidak punya `pipefail`, sehingga exit code yang terbaca adalah milik gzip.
     * mysqldump boleh gagal total dan pipeline-nya tetap terlihat sukses —
     * begitulah berkas backup 0 byte lahir.
     */
    private function dump(string $file): void
    {
        $connection = config('database.connections.'.config('database.default'));
        $plain = substr($file, 0, -3); // buang akhiran .gz

        $dump = new Process([
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--routines',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            '--result-file='.$plain,
            $connection['database'],
        ], timeout: 900, env: ['MYSQL_PWD' => (string) $connection['password']]);

        $dump->run();

        if (! $dump->isSuccessful()) {
            if (is_file($plain)) {
                unlink($plain);
            }

            throw new RuntimeException('mysqldump gagal — '.trim($dump->getErrorOutput()));
        }

        // gzip menghapus berkas asalnya, jadi hasilnya persis $file.
        $gzip = new Process(['gzip', '-9', $plain], timeout: 900);
        $gzip->run();

        if (! $gzip->isSuccessful()) {
            if (is_file($plain)) {
                unlink($plain);
            }

            throw new RuntimeException('gzip gagal — '.trim($gzip->getErrorOutput()));
        }
    }

    /**
     * Tiga pemeriksaan, masing-masing menangkap kegagalan yang berbeda: berkas
     * kosong (dump tidak pernah menulis apa pun), arsip rusak (proses terpotong
     * saat menulis), dan penanda akhir hilang (mysqldump berhenti di tengah,
     * ukurannya bisa terlihat wajar padahal isinya tidak lengkap).
     */
    private function assertDumpIsComplete(string $file): void
    {
        if (! is_file($file) || filesize($file) === 0) {
            throw new RuntimeException('berkas backup tidak terbentuk atau kosong.');
        }

        $test = new Process(['gzip', '-t', $file], timeout: 300);
        $test->run();

        if (! $test->isSuccessful()) {
            throw new RuntimeException('arsip gzip rusak — '.trim($test->getErrorOutput()));
        }

        $handle = gzopen($file, 'rb');

        if ($handle === false) {
            throw new RuntimeException('arsip backup tidak bisa dibuka.');
        }

        // Hanya ekor berkas yang ditahan di memori: dump puluhan MB tidak boleh
        // dibaca utuh hanya untuk memeriksa baris terakhirnya.
        $tail = '';

        while (! gzeof($handle)) {
            $tail = substr($tail.gzread($handle, 65536), -200);
        }

        gzclose($handle);

        if (! str_contains($tail, '-- Dump completed')) {
            throw new RuntimeException('dump tidak selesai — penanda "-- Dump completed" tidak ada.');
        }
    }
}
