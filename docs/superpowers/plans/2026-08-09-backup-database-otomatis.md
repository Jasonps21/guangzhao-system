# Backup Database Otomatis — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Database produksi ter-backup otomatis setiap hari pukul 18:00 WITA, terverifikasi keutuhannya, dan backup lama dipangkas tanpa pernah menyentuh berkas milik aplikasi lain.

**Architecture:** Satu Artisan command `backup:database` menjalankan `mysqldump` ke berkas lalu mengompresnya, memverifikasi hasilnya lewat tiga pemeriksaan, dan baru setelah itu memangkas backup kedaluwarsa. Kredensial dibaca dari config Laravel sehingga tidak diduplikasi ke skrip mana pun. Satu baris crontab memanggilnya.

**Tech Stack:** Laravel 13, PHP 8.3, `symfony/process`, `mysqldump` + `gzip`, PHPUnit 12, MariaDB 10.6 (server) / MySQL 8 (lokal), Laravel Pint.

## Global Constraints

- **Verifikasi sebelum memangkas, tanpa kecuali.** Bila dump gagal atau tidak lolos verifikasi: hapus berkas gagalnya, **jangan panggil pemangkasan**, kembalikan exit code bukan nol.
- **Pemangkasan hanya boleh menghapus** berkas yang berada langsung di folder tujuan, namanya cocok regex `^guangzhao-\d{8}-\d{6}\.sql\.gz$`, **dan** mtime-nya lebih tua dari `--keep` hari. Tidak rekursif, tidak pernah menghapus direktori.
- **Password tidak pernah menjadi argumen baris perintah.** Kirim lewat variabel lingkungan `MYSQL_PWD` agar tidak terlihat di `ps`.
- **Jangan memakai pipe `mysqldump | gzip`.** `/bin/sh` di Ubuntu adalah dash yang tidak punya `pipefail`, sehingga exit code yang terbaca adalah milik `gzip` dan kegagalan mysqldump lolos tanpa terdeteksi.
- Flag mysqldump persis: `--single-transaction --quick --routines --no-tablespaces --default-character-set=utf8mb4`. Kelimanya sudah diverifikasi ada di MySQL 8 dan MariaDB 10.6.
- Nama berkas persis: `guangzhao-YYYYMMDD-HHMMSS.sql.gz`, jam mengikuti `app.timezone` (**UTC**, tidak diubah).
- Jangan menambah dependensi Composer.
- Jangan mengubah `app.timezone`, `config/database.php`, atau data apa pun.
- Komentar kode dalam bahasa Indonesia.
- Setelah mengubah PHP, jalankan `vendor/bin/pint --dirty --format agent`.

---

### Task 1: Command membuat dump terverifikasi

**Files:**
- Create: `app/Console/Commands/BackupDatabase.php`
- Test: `tests/Feature/BackupDatabaseTest.php`

**Interfaces:**
- Consumes: `config('database.connections.'.config('database.default'))` untuk host, port, username, password, database.
- Produces: command `backup:database` dengan opsi `--path` (default `storage_path('app/backups')`) dan `--keep` (default `30`). Menghasilkan berkas `<path>/guangzhao-YYYYMMDD-HHMMSS.sql.gz`. Exit `0` sukses, `1` gagal.

- [ ] **Step 1: Tulis dua test yang gagal**

Buat `tests/Feature/BackupDatabaseTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Memakai DatabaseMigrations, bukan RefreshDatabase seperti test lain di proyek
 * ini. RefreshDatabase membungkus tiap test dalam transaksi yang tidak pernah
 * di-commit, sedangkan mysqldump menyambung lewat koneksi terpisah dan hanya
 * melihat data yang sudah ter-commit — barisnya akan tampak kosong dan test
 * kehilangan maknanya.
 */
class BackupDatabaseTest extends TestCase
{
    use DatabaseMigrations;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/backups-'.uniqid());
        File::ensureDirectoryExists($this->path);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    public function test_the_dump_contains_the_schema_and_the_rows(): void
    {
        Member::factory()->active()->create(['name_indonesian' => 'Budi Backup Satu']);
        Member::factory()->active()->create(['name_indonesian' => 'Siti Backup Dua']);

        $this->artisan('backup:database', ['--path' => $this->path])->assertSuccessful();

        $sql = (string) gzdecode((string) file_get_contents($this->onlyBackup()));

        $this->assertStringContainsString('CREATE TABLE `members`', $sql);
        $this->assertStringContainsString('Budi Backup Satu', $sql);
        $this->assertStringContainsString('Siti Backup Dua', $sql);
        $this->assertStringContainsString('-- Dump completed', $sql);
    }

    /**
     * Backup yang belum pernah dicoba pulihkan bukan backup. Pemulihannya
     * memakai database test, bukan data produksi.
     */
    public function test_the_dump_can_be_restored_into_an_empty_database(): void
    {
        Member::factory()->active()->count(4)->create();
        $expected = Member::count();

        $this->artisan('backup:database', ['--path' => $this->path])->assertSuccessful();

        $scratch = 'db_guangzhao_restore_test';
        DB::statement("DROP DATABASE IF EXISTS `{$scratch}`");
        DB::statement("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4");

        $connection = config('database.connections.'.config('database.default'));

        $restore = Process::fromShellCommandline(
            'gzip -dc '.escapeshellarg($this->onlyBackup())
                .' | mysql --host='.escapeshellarg((string) $connection['host'])
                .' --port='.escapeshellarg((string) $connection['port'])
                .' --user='.escapeshellarg((string) $connection['username'])
                .' '.escapeshellarg($scratch),
            null,
            ['MYSQL_PWD' => (string) $connection['password']],
            null,
            300,
        );
        $restore->run();

        $this->assertTrue($restore->isSuccessful(), 'Restore gagal: '.$restore->getErrorOutput());

        $restored = (int) DB::selectOne("SELECT COUNT(*) AS c FROM `{$scratch}`.`members`")->c;

        DB::statement("DROP DATABASE IF EXISTS `{$scratch}`");

        $this->assertSame($expected, $restored);
    }

    /**
     * Satu-satunya berkas backup di folder uji.
     */
    private function onlyBackup(): string
    {
        $files = glob($this->path.'/guangzhao-*.sql.gz');

        $this->assertNotEmpty($files, 'Tidak ada berkas backup yang terbentuk.');
        $this->assertCount(1, $files, 'Seharusnya hanya ada satu berkas backup.');

        return $files[0];
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run:
```bash
php artisan test --compact tests/Feature/BackupDatabaseTest.php
```

Expected: dua test GAGAL dengan `The command "backup:database" does not exist.`

- [ ] **Step 3: Buat command-nya**

Buat `app/Console/Commands/BackupDatabase.php`:

```php
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
```

- [ ] **Step 4: Jalankan test, pastikan LULUS**

Run:
```bash
php artisan test --compact tests/Feature/BackupDatabaseTest.php
```

Expected: 2 test LULUS.

Bila `test_the_dump_can_be_restored_into_an_empty_database` gagal pada `CREATE DATABASE`, berarti pengguna database lokal tidak punya privilege itu. Jangan melewati test-nya — beri pengguna lokal privilege `CREATE`, karena kemampuan restore adalah inti dari fitur ini.

- [ ] **Step 5: Rapikan format dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/BackupDatabase.php tests/Feature/BackupDatabaseTest.php
git commit -m "$(cat <<'MSG'
Dump the database to a verified gzip archive

Production has never had an automated backup, and one of the hand-made
dumps sitting on the server is 0 bytes — a backup can fail and still
look like a file.

So the dump is checked three ways before it counts: non-empty, a valid
gzip archive, and carrying mysqldump's own trailer, which is what
catches a dump that stopped halfway with a plausible size. The dump is
written with --result-file rather than piped into gzip, because dash has
no pipefail and would report gzip's exit code instead of mysqldump's.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
MSG
)"
```

---

### Task 2: Pemangkasan backup lama yang tidak pernah salah sasaran

**Files:**
- Modify: `app/Console/Commands/BackupDatabase.php`
- Test: `tests/Feature/BackupDatabaseTest.php`

**Interfaces:**
- Consumes: command dan opsi `--path` / `--keep` dari Task 1.
- Produces: method privat `BackupDatabase::prune(string $path, int $keepDays): int` yang mengembalikan jumlah berkas terhapus. Ringkasan yang dicetak command bertambah menyebut jumlah itu.

- [ ] **Step 1: Tulis empat test yang gagal**

Tambahkan ke `tests/Feature/BackupDatabaseTest.php`, sebelum method privat `onlyBackup()`:

```php
    public function test_backups_older_than_the_keep_window_are_removed(): void
    {
        $old = $this->path.'/guangzhao-20200101-000000.sql.gz';
        File::put($old, 'backup lama');
        touch($old, now()->subDays(31)->getTimestamp());

        $this->artisan('backup:database', ['--path' => $this->path, '--keep' => 30])
            ->assertSuccessful();

        $this->assertFileDoesNotExist($old);
    }

    public function test_backups_inside_the_keep_window_are_left_alone(): void
    {
        $recent = $this->path.'/guangzhao-20200101-000001.sql.gz';
        File::put($recent, 'backup baru');
        touch($recent, now()->subDays(29)->getTimestamp());

        $this->artisan('backup:database', ['--path' => $this->path, '--keep' => 30])
            ->assertSuccessful();

        $this->assertFileExists($recent);
    }

    /**
     * Folder backup di server bercampur milik aplikasi lain — termasuk sebuah
     * arsip 342 MB. Pemangkasan tidak boleh pernah menyentuh apa pun di luar
     * pola namanya sendiri.
     */
    public function test_files_that_are_not_our_backups_are_never_touched(): void
    {
        $foreign = $this->path.'/meddiag-files-backup-20260722.tar.gz';
        $oddName = $this->path.'/guangzhao-catatan.sql.gz';

        File::put($foreign, 'milik aplikasi lain');
        File::put($oddName, 'bukan pola backup');
        touch($foreign, now()->subYears(2)->getTimestamp());
        touch($oddName, now()->subYears(2)->getTimestamp());

        $this->artisan('backup:database', ['--path' => $this->path, '--keep' => 1])
            ->assertSuccessful();

        $this->assertFileExists($foreign);
        $this->assertFileExists($oddName);
    }

    /**
     * Yang paling merugikan bukan dump yang gagal, melainkan dump gagal yang
     * tetap memangkas: backup lama hilang dan penggantinya tidak ada.
     */
    public function test_a_failed_dump_neither_prunes_nor_reports_success(): void
    {
        $old = $this->path.'/guangzhao-20200101-000000.sql.gz';
        File::put($old, 'backup lama');
        touch($old, now()->subDays(365)->getTimestamp());

        // Port yang tidak ada membuat mysqldump gagal secara deterministik,
        // apa pun konfigurasi password di mesin yang menjalankan test.
        config(['database.connections.'.config('database.default').'.port' => 1]);

        $this->artisan('backup:database', ['--path' => $this->path, '--keep' => 30])
            ->assertFailed();

        $this->assertFileExists($old, 'Backup lama tidak boleh dihapus saat dump gagal.');
        $this->assertCount(1, File::files($this->path), 'Berkas dump yang gagal harus dibersihkan.');
    }
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run:
```bash
php artisan test --compact --filter='keep_window|not_our_backups|failed_dump' tests/Feature/BackupDatabaseTest.php
```

Expected: `test_backups_older_than_the_keep_window_are_removed` GAGAL karena berkas lama masih ada (pemangkasan belum ada). Tiga lainnya LULUS — semuanya guard yang memang harus lulus di kedua sisi.

- [ ] **Step 3: Tambahkan pemangkasan**

Di `app/Console/Commands/BackupDatabase.php`, ganti blok ringkasan di `handle()`:

```php
        $removed = $this->prune($path, $keepDays);

        $this->info(sprintf(
            'Backup dibuat: %s (%s KB). %d backup lebih tua dari %d hari dihapus.',
            basename($file),
            number_format(filesize($file) / 1024, 1),
            $removed,
            $keepDays,
        ));

        return self::SUCCESS;
```

Lalu tambahkan komentar pada cabang `catch` tepat sebelum `return self::FAILURE;`:

```php
            // Pemangkasan sengaja TIDAK dijalankan di sini. Memangkas setelah
            // dump gagal menghapus backup lama tanpa ada penggantinya.
            return self::FAILURE;
```

Dan tambahkan method berikut setelah `assertDumpIsComplete()`:

```php
    /**
     * Hanya berkas backup milik aplikasi ini yang boleh dihapus.
     *
     * Folder tujuan di server bercampur dengan backup aplikasi lain, jadi
     * pemangkasan dibatasi tiga syarat sekaligus: berada langsung di folder itu
     * (glob tidak rekursif), namanya cocok pola persis, dan mtime-nya sudah
     * lewat. Direktori tidak pernah disentuh.
     */
    private function prune(string $path, int $keepDays): int
    {
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach ((array) glob($path.'/guangzhao-*.sql.gz') as $candidate) {
            if (! is_file($candidate)) {
                continue;
            }

            // Pola diperiksa ulang agar berkas seperti "guangzhao-catatan.sql.gz"
            // yang lolos glob tidak ikut terhapus.
            if (preg_match('/^guangzhao-\d{8}-\d{6}\.sql\.gz$/', basename($candidate)) !== 1) {
                continue;
            }

            if (filemtime($candidate) > $cutoff) {
                continue;
            }

            if (unlink($candidate)) {
                $removed++;
            }
        }

        return $removed;
    }
```

- [ ] **Step 4: Jalankan seluruh berkas test, pastikan LULUS**

Run:
```bash
php artisan test --compact tests/Feature/BackupDatabaseTest.php
```

Expected: 6 test LULUS.

- [ ] **Step 5: Jalankan seluruh test suite**

Run:
```bash
php artisan test --compact
```

Expected: seluruh test lolos (298 sebelumnya + 6 baru = 304).

- [ ] **Step 6: Rapikan format dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/BackupDatabase.php tests/Feature/BackupDatabaseTest.php
git commit -m "$(cat <<'MSG'
Prune old backups only after the new one checks out

Pruning before the dump is verified is the one failure that loses data
rather than merely failing to protect it: the old backups go and nothing
replaces them. So prune runs only on the success path.

It also refuses to touch anything but its own files. The backup folder on
the server is shared with other apps, including a 342MB archive, so the
match is by exact filename pattern, non-recursive, files only.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
MSG
)"
```

---

### Task 3: Pasang di server dan buktikan cron-nya jalan

Task ini berjalan di server produksi. Tidak ada satu pun perintah yang menulis ke database.

**Files:**
- Modify: crontab pengguna `root_test` di server
- Create: `/home/root_test/backups/guangzhao/` di server

**Interfaces:**
- Consumes: command `backup:database` dari Task 1 dan 2, sudah ter-merge ke `main` dan ter-deploy.
- Produces: satu baris crontab harian dan folder backup berisi arsip pertama.

- [ ] **Step 1: Deploy kode ke server**

Run:
```bash
ssh root_test@103.189.234.38 'cd /home/root_test/guangzhao-system && git pull --ff-only && php artisan optimize:clear < /dev/null && php artisan optimize < /dev/null'
```

Expected: pull fast-forward ke commit terbaru, cache dibangun ulang.

- [ ] **Step 2: Pastikan command terdaftar di server**

Run:
```bash
ssh root_test@103.189.234.38 'cd /home/root_test/guangzhao-system && php artisan list backup < /dev/null'
```

Expected: `backup:database` muncul beserta deskripsinya.

- [ ] **Step 3: Buat folder tujuan dan jalankan sekali secara manual**

Run:
```bash
ssh root_test@103.189.234.38 'mkdir -p /home/root_test/backups/guangzhao && cd /home/root_test/guangzhao-system && php artisan backup:database --path=/home/root_test/backups/guangzhao --keep=30 < /dev/null; echo "exit=$?"'
```

Expected: baris `Backup dibuat: guangzhao-...sql.gz (… KB). 0 backup lebih tua dari 30 hari dihapus.` dan `exit=0`. Ukurannya wajar bila berkisar 180–200 KB.

- [ ] **Step 4: Verifikasi arsipnya sendiri, bukan sekadar percaya pesannya**

Run:
```bash
ssh root_test@103.189.234.38 'f=$(ls -t /home/root_test/backups/guangzhao/guangzhao-*.sql.gz | head -1); echo "berkas: $f"; gzip -t "$f" && echo "gzip: utuh"; gzip -dc "$f" | tail -1; echo "jumlah tabel: $(gzip -dc "$f" | grep -c "^CREATE TABLE")"'
```

Expected: `gzip: utuh`, baris terakhir `-- Dump completed on …`, dan `jumlah tabel: 26`.

- [ ] **Step 5: Pasang baris crontab tanpa menghilangkan yang sudah ada**

Run:
```bash
ssh root_test@103.189.234.38 'crontab -l > /tmp/cron.bak && cp /tmp/cron.bak /tmp/cron.new && cat >> /tmp/cron.new <<'"'"'CRON'"'"'

# Backup DB guangzhao setiap hari 18:00 WITA (server berzona Asia/Makassar).
0 18 * * * cd /home/root_test/guangzhao-system && php artisan backup:database --path=/home/root_test/backups/guangzhao --keep=30 >> /home/root_test/backups/guangzhao/backup.log 2>&1
CRON
crontab /tmp/cron.new && echo "terpasang" && rm -f /tmp/cron.new'
```

Expected: `terpasang`.

- [ ] **Step 6: Buktikan crontab utuh dan jadwalnya benar**

Run:
```bash
ssh root_test@103.189.234.38 'echo "=== crontab:"; crontab -l; echo "=== zona server:"; timedatectl | grep -i "time zone"; echo "=== sekarang:"; date "+%Y-%m-%d %H:%M:%S %Z"'
```

Expected: baris scheduler `crm-wa` **masih ada**, baris backup baru ada di bawahnya, zona `Asia/Makassar (WITA, +0800)`.

- [ ] **Step 7: Buktikan cron benar-benar menjalankannya**

Jangan menunggu pukul 18:00. Pasang sementara jadwal satu menit ke depan, tunggu, lalu kembalikan.

Run (ganti `MM` dengan menit satu-dua menit dari sekarang menurut jam server dari Step 6):
```bash
ssh root_test@103.189.234.38 'crontab -l | sed "s|^0 18 \* \* \*|MM * * * *|" | crontab - && crontab -l | tail -2'
```

Tunggu sampai menit itu lewat, lalu:
```bash
ssh root_test@103.189.234.38 'cat /home/root_test/backups/guangzhao/backup.log; ls -la --time-style=+%H:%M /home/root_test/backups/guangzhao/'
```

Expected: `backup.log` memuat baris `Backup dibuat: …` dan ada arsip kedua yang waktunya sesuai.

Lalu kembalikan jadwalnya:
```bash
ssh root_test@103.189.234.38 'crontab -l | sed "s|^MM \* \* \* \*|0 18 * * *|" | crontab - && crontab -l | tail -2'
```

Expected: baris kembali menjadi `0 18 * * *`.

- [ ] **Step 8: Bereskan arsip uji**

Hapus arsip kedua hasil uji cron agar folder hanya berisi backup sungguhan, dan tampilkan isi akhirnya:
```bash
ssh root_test@103.189.234.38 'ls -t /home/root_test/backups/guangzhao/guangzhao-*.sql.gz | tail -n +2 | xargs -r rm -f; ls -la /home/root_test/backups/guangzhao/'
```

Expected: satu berkas `.sql.gz` dan satu `backup.log`.

---

## Catatan untuk pelaksana

**Kenapa `--result-file` dan bukan pipe.** `/bin/sh` di Ubuntu adalah dash, yang tidak mengenal `set -o pipefail`. Pada `mysqldump | gzip > berkas`, exit code yang dikembalikan shell adalah milik `gzip`, dan gzip sukses mengompres nol byte. Backup 0 byte yang sudah ada di server itu kemungkinan besar lahir persis begitu.

**Kenapa penanda `-- Dump completed` bisa dipercaya.** Sudah diverifikasi langsung pada dump nyata buatan MariaDB 10.6 di server ini: baris terakhirnya `-- Dump completed on 2026-07-31  7:06:02`. Penanda itu hilang bila mysqldump berhenti di tengah.

**Yang tidak dilindungi rancangan ini.** Backup berada di disk yang sama dengan databasenya. VPS mati, disk rusak, atau akun disuspend menghabiskan keduanya. Ini keputusan sadar pemilik sistem; menambahkan salinan ke penyimpanan awan kelak hanya perlu satu langkah di atas fondasi ini.

**Jangan menyentuh data.** Tidak satu pun langkah di Task 3 boleh menulis ke database. `mysqldump` dengan `--single-transaction` hanya membaca.
