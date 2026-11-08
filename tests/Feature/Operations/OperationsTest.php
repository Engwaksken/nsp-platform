<?php

namespace Tests\Feature\Operations;

use App\Operations\Backup;
use App\Operations\Health;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class OperationsTest extends TestCase
{
    private string $dir;

    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing-backups-'.bin2hex(random_bytes(3)));
        config(['nsp.backup.path' => $this->dir, 'nsp.backup.password' => 'correct horse battery staple', 'nsp.backup.keep' => 2]);
        // Backups run outside any transaction in production. The default test connection is wrapped in one
        // (where SQLite refuses VACUUM INTO), so the backup reads a real database file of its own.
        $this->dbFile = tempnam(sys_get_temp_dir(), 'nspdb');
        config(['database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $this->dbFile, 'prefix' => '', 'foreign_key_constraints' => true],
            'nsp.backup.connection' => 'backup_test']);
        $this->artisan('migrate', ['--database' => 'backup_test', '--force' => true]);
        Storage::disk('local')->put('data-room/test-startup/deck.pdf', "%PDF-1.4\nsecret deck\n%%EOF");
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('data-room/test-startup');
        File::deleteDirectory($this->dir);
        DB::purge('backup_test');
        @unlink($this->dbFile);
        parent::tearDown();
    }

    public function test_backup_is_encrypted_complete_and_verifiable(): void
    {
        $this->artisan('nsp:backup')->expectsOutputToContain('Backup check passed')->assertSuccessful();
        $file = app(Backup::class)->latest();
        $this->assertNotNull($file);

        $zip = new ZipArchive;
        $zip->open($file);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entries[$stat['name']] = $stat['encryption_method'];
        }
        $zip->close();
        $this->assertArrayHasKey('database/database.sqlite', $entries);
        $this->assertArrayHasKey('files/private/data-room/test-startup/deck.pdf', $entries);
        $this->assertArrayHasKey('manifest.json', $entries);
        $this->assertSame([ZipArchive::EM_AES_256], array_values(array_unique($entries)), 'Every entry is AES-256 encrypted.');
        $this->assertStringNotContainsString('secret deck', (string) file_get_contents($file), 'File contents are not readable without the password.');

        // The database copy really holds the data.
        $this->assertTrue(app(Backup::class)->verify($file)['ok']);

        config(['nsp.backup.password' => 'wrong password']);
        $this->assertFalse(app(Backup::class)->verify($file)['ok']);
    }

    public function test_tampered_backups_fail_verification(): void
    {
        $file = app(Backup::class)->create();
        $zip = new ZipArchive;
        $zip->open($file);
        $zip->setPassword('correct horse battery staple');
        $zip->addFromString('files/private/data-room/test-startup/deck.pdf', 'tampered');
        $zip->setEncryptionName('files/private/data-room/test-startup/deck.pdf', ZipArchive::EM_AES_256);
        $zip->close();

        $report = app(Backup::class)->verify($file);
        $this->assertFalse($report['ok']);
        $this->assertStringContainsString('Checksum mismatch', implode(' ', $report['problems']));
        $this->artisan('nsp:backup', ['--verify-only' => $file])->assertFailed();
    }

    public function test_rotation_keeps_the_newest_and_production_requires_encryption(): void
    {
        File::ensureDirectoryExists($this->dir);
        foreach (['20260101-010000', '20260102-010000', '20260103-010000'] as $stamp) {
            touch($this->dir."/nsp-backup-{$stamp}.zip");
        }
        $this->assertSame(1, app(Backup::class)->rotate());
        $this->assertSame(['nsp-backup-20260103-010000.zip', 'nsp-backup-20260102-010000.zip'], array_map('basename', app(Backup::class)->list()));

        config(['nsp.backup.password' => null]);
        $this->app['env'] = 'production';
        $this->artisan('nsp:backup')->expectsOutputToContain('must be encrypted')->assertFailed();
    }

    public function test_health_checks_report_scheduler_backup_and_email_state(): void
    {
        $checks = collect(app(Health::class)->checks())->keyBy('key');
        $this->assertSame('fail', $checks['scheduler']['status'], 'No heartbeat yet.');
        $this->assertSame('warn', $checks['backup']['status'], 'No backup yet (warning outside production).');
        $this->assertSame('ok', $checks['database']['status']);

        Cache::forever(Health::HEARTBEAT_KEY, time());
        app(Backup::class)->create();
        $checks = collect(app(Health::class)->checks())->keyBy('key');
        $this->assertSame('ok', $checks['scheduler']['status']);
        $this->assertSame('ok', $checks['backup']['status']);

        $this->artisan('nsp:health')->expectsOutputToContain('Scheduler (cron)');
    }

    public function test_health_endpoint_is_off_without_a_token_and_needs_it(): void
    {
        $this->getJson('/health')->assertNotFound();

        config(['nsp.health_token' => 'monitor-secret-123']);
        $this->getJson('/health')->assertUnauthorized();
        $this->getJson('/health', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        $response = $this->getJson('/health', ['Authorization' => 'Bearer monitor-secret-123']);
        $response->assertStatus(503)->assertJsonPath('checks.scheduler', 'fail')->assertJsonMissingPath('checks.scheduler.detail');

        Cache::forever(Health::HEARTBEAT_KEY, time());
        $this->getJson('/health', ['Authorization' => 'Bearer monitor-secret-123'])->assertOk()->assertJsonPath('status', 'warn');
    }

    public function test_system_health_page_is_for_permitted_staff(): void
    {
        $this->actingAs($this->userWithRole('content_editor'))->get('/admin/system')->assertForbidden();
        $this->actingAs($this->userWithRole('auditor'))->get('/admin/system')->assertOk()->assertSee('Scheduler (cron)')->assertSee('Backups');
    }
}
