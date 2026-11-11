<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Signature('pointage:sauvegarder {--connexion= : Connexion à sauvegarder, celle par défaut sinon}')]
#[Description('Sauvegarde la base PostgreSQL dans storage/app/backups et supprime les anciennes copies')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $connection = config('database.connections.'.($this->option('connexion') ?: config('database.default')));

        if (($connection['driver'] ?? null) !== 'pgsql') {
            $this->error('La sauvegarde automatique ne prend en charge que PostgreSQL.');

            return self::FAILURE;
        }

        $directory = self::directory();
        File::ensureDirectoryExists($directory, 0750);

        $path = $directory.'/pointage-'.now()->format('Y-m-d-His').'.dump';

        $result = Process::env(['PGPASSWORD' => (string) $connection['password']])
            ->timeout(600)
            ->run([
                'pg_dump',
                '--format=custom',
                '--no-owner',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--file='.$path,
                $connection['database'],
            ]);

        if ($result->failed()) {
            File::delete($path);
            $this->error('La sauvegarde a échoué : '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $removed = $this->removeOldBackups($directory);

        $this->info('Sauvegarde créée : '.$path.($removed > 0 ? " ({$removed} ancienne(s) copie(s) supprimée(s))" : ''));

        return self::SUCCESS;
    }

    public static function directory(): string
    {
        return (string) config('pointage.backup_path');
    }

    private function removeOldBackups(string $directory): int
    {
        $limit = now()->subDays((int) config('pointage.backup_retention_days'))->getTimestamp();
        $removed = 0;

        foreach (File::glob($directory.'/pointage-*.dump') as $file) {
            if (File::lastModified($file) < $limit) {
                File::delete($file);
                $removed++;
            }
        }

        return $removed;
    }
}
