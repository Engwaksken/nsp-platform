<?php

namespace App\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Backups (OPS-703): one zip per run with the database, the private files (data room, CVs)
 * and the public media, plus a manifest of SHA-256 checksums. Encrypted with AES-256 when
 * NSP_BACKUP_PASSWORD is set; production refuses to write an unencrypted backup.
 * Restoring is a manual, documented procedure (docs/operations-runbook.md) so that no
 * command can overwrite live data by accident.
 */
class Backup
{
    public function directory(): string
    {
        return (string) config('nsp.backup.path');
    }

    public function create(): string
    {
        $password = (string) config('nsp.backup.password');
        if ($password === '' && app()->environment('production')) {
            throw new RuntimeException('Set NSP_BACKUP_PASSWORD: production backups must be encrypted.');
        }
        File::ensureDirectoryExists($this->directory(), 0700);
        $work = storage_path('framework/backup-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($work, 0700);
        $name = 'nsp-backup-'.now()->format('Ymd-His').'.zip';
        $target = $this->directory().DIRECTORY_SEPARATOR.$name;

        try {
            $dbFile = $this->dumpDatabase($work);
            $files = ['database/'.basename($dbFile) => $dbFile];
            foreach (['private' => storage_path('app/private'), 'public' => storage_path('app/public')] as $prefix => $root) {
                if (! is_dir($root)) {
                    continue;
                }
                foreach (File::allFiles($root) as $file) {
                    $relative = str_replace('\\', '/', $file->getRelativePathname());
                    if ($relative === '.gitignore') {
                        continue;
                    }
                    $files['files/'.$prefix.'/'.$relative] = $file->getPathname();
                }
            }

            $manifest = [
                'created_at' => now()->toIso8601String(),
                'app' => config('app.name'),
                'database_driver' => DB::connection(config('nsp.backup.connection'))->getDriverName(),
                'encrypted' => $password !== '',
                'files' => collect($files)->map(fn ($path) => ['sha256' => hash_file('sha256', $path), 'bytes' => filesize($path)])->all(),
            ];

            $zip = new ZipArchive;
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Could not create '.$target);
            }
            if ($password !== '') {
                $zip->setPassword($password);
            }
            foreach ($files as $entry => $path) {
                $zip->addFile($path, $entry);
                if ($password !== '') {
                    $zip->setEncryptionName($entry, ZipArchive::EM_AES_256);
                }
            }
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if ($password !== '') {
                $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256);
            }
            if (! $zip->close()) {
                throw new RuntimeException('Could not finish writing the backup archive.');
            }
            @chmod($target, 0600);
        } finally {
            File::deleteDirectory($work);
        }

        return $target;
    }

    /**
     * Opens a backup, checks every file against the manifest and checks the database copy.
     *
     * @return array{ok: bool, files: int, bytes: int, problems: list<string>}
     */
    public function verify(string $path): array
    {
        $problems = [];
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return ['ok' => false, 'files' => 0, 'bytes' => 0, 'problems' => ['The archive cannot be opened.']];
        }
        if ((string) config('nsp.backup.password') !== '') {
            $zip->setPassword((string) config('nsp.backup.password'));
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest) || ! isset($manifest['files'])) {
            $zip->close();

            return ['ok' => false, 'files' => 0, 'bytes' => 0, 'problems' => ['The manifest is missing or cannot be read (wrong password?).']];
        }

        $bytes = 0;
        $work = storage_path('framework/verify-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($work, 0700);
        try {
            foreach ($manifest['files'] as $entry => $expected) {
                $stream = $zip->getStream($entry);
                if ($stream === false) {
                    $problems[] = "Missing: {$entry}";

                    continue;
                }
                $context = hash_init('sha256');
                $size = hash_update_stream($context, $stream);
                fclose($stream);
                $bytes += $size;
                if (hash_final($context) !== $expected['sha256']) {
                    $problems[] = "Checksum mismatch: {$entry}";
                }
                if (str_starts_with($entry, 'database/')) {
                    $copy = $work.'/'.basename($entry);
                    file_put_contents($copy, $zip->getFromName($entry));
                    $problems = array_merge($problems, $this->checkDatabaseCopy($copy, $manifest['database_driver'] ?? ''));
                }
            }
        } finally {
            $zip->close();
            File::deleteDirectory($work);
        }

        return ['ok' => $problems === [], 'files' => count($manifest['files']), 'bytes' => $bytes, 'problems' => $problems];
    }

    /** Deletes the oldest backups beyond the configured number to keep. Returns how many were deleted. */
    public function rotate(): int
    {
        $keep = max(1, (int) config('nsp.backup.keep', 14));
        $old = array_slice($this->list(), $keep);
        foreach ($old as $file) {
            @unlink($file);
        }

        return count($old);
    }

    /** @return list<string> newest first */
    public function list(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'nsp-backup-*.zip') ?: [];
        rsort($files);

        return array_values($files);
    }

    public function latest(): ?string
    {
        return $this->list()[0] ?? null;
    }

    private function dumpDatabase(string $work): string
    {
        $connection = DB::connection(config('nsp.backup.connection'));
        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            $target = $work.'/database.sqlite';
            // A consistent copy even while the app is writing.
            $connection->statement('VACUUM INTO '.$connection->getPdo()->quote($target));

            return $target;
        }
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $config = $connection->getConfig();
            $credentials = $work.'/my.cnf';
            // Credentials go in a private option file, never on the command line (where other users could see them).
            $quote = fn ($value) => '"'.addcslashes((string) $value, '"\\').'"';
            file_put_contents($credentials, "[client]\nuser=".$quote($config['username'])."\npassword=".$quote($config['password'])
                ."\nhost=".$quote($config['host'])."\nport=".$quote($config['port'])."\n");
            @chmod($credentials, 0600);
            $target = $work.'/database.sql';
            $process = new Process([
                (string) config('nsp.backup.mysqldump', 'mysqldump'), '--defaults-extra-file='.$credentials,
                '--single-transaction', '--quick', '--routines', '--no-tablespaces', '--default-character-set=utf8mb4',
                '--result-file='.$target, $config['database'],
            ]);
            $process->setTimeout(1800)->run();
            @unlink($credentials);
            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysqldump failed: '.mb_substr($process->getErrorOutput(), 0, 500));
            }

            return $target;
        }

        throw new RuntimeException("Backups are not set up for the {$driver} database driver.");
    }

    /** @return list<string> */
    private function checkDatabaseCopy(string $path, string $driver): array
    {
        if ($driver === 'sqlite') {
            try {
                $pdo = new \PDO('sqlite:'.$path);
                $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
                $migrations = (int) $pdo->query('SELECT count(*) FROM migrations')->fetchColumn();

                return $integrity === 'ok' && $migrations > 0 ? [] : ['The database copy failed its integrity check.'];
            } catch (\Throwable $e) {
                return ['The database copy cannot be opened: '.$e->getMessage()];
            }
        }
        $tail = file_get_contents($path, false, null, max(0, filesize($path) - 200));

        return str_contains((string) $tail, 'Dump completed') ? [] : ['The SQL dump looks incomplete.'];
    }
}
