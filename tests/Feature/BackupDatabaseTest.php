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

        // Nama database yang tidak ada membuat mysqldump gagal secara
        // deterministik di mesin mana pun. Mengubah port saja tidak cukup:
        // dengan host `localhost`, MySQL menyambung lewat unix socket dan
        // mengabaikan port sepenuhnya.
        config(['database.connections.'.config('database.default').'.database' => 'db_yang_tidak_pernah_ada']);

        $this->artisan('backup:database', ['--path' => $this->path, '--keep' => 30])
            ->assertFailed();

        $this->assertFileExists($old, 'Backup lama tidak boleh dihapus saat dump gagal.');
        $this->assertCount(1, File::files($this->path), 'Berkas dump yang gagal harus dibersihkan.');
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
