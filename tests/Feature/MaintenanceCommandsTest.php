<?php

namespace Tests\Feature;

use App\Console\Commands\BackupDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class MaintenanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pointage.backup_path' => sys_get_temp_dir().'/pointage-backups-'.getmypid()]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(BackupDatabase::directory());

        parent::tearDown();
    }

    public function test_backup_runs_pg_dump_and_removes_old_copies(): void
    {
        Process::fake();
        config([
            'database.connections.pgsql.database' => 'pointage',
            'database.connections.pgsql.username' => 'pointage',
            'database.connections.pgsql.password' => 'secret',
        ]);

        File::ensureDirectoryExists(BackupDatabase::directory());
        $oldBackup = BackupDatabase::directory().'/pointage-2026-01-01-023000.dump';
        $recentBackup = BackupDatabase::directory().'/pointage-2026-11-09-023000.dump';
        File::put($oldBackup, 'ancienne');
        File::put($recentBackup, 'récente');
        touch($oldBackup, now()->subDays(31)->getTimestamp());

        $this->artisan('pointage:sauvegarder', ['--connexion' => 'pgsql'])->assertSuccessful();

        Process::assertRan(fn ($process): bool => $process->command[0] === 'pg_dump'
            && in_array('pointage', $process->command, true)
            && $process->environment['PGPASSWORD'] === 'secret');

        $this->assertFileDoesNotExist($oldBackup);
        $this->assertFileExists($recentBackup);
    }

    public function test_failed_backup_is_reported(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'connexion refusée', exitCode: 1)]);
        $this->artisan('pointage:sauvegarder', ['--connexion' => 'pgsql'])
            ->expectsOutputToContain('connexion refusée')
            ->assertFailed();
    }

    public function test_password_reset_gives_a_temporary_password(): void
    {
        $manager = User::factory()->superAdmin()->create(['email' => 'gerant@ibkm.com', 'must_change_password' => false]);

        $this->artisan('pointage:mot-de-passe', ['identifiant' => 'gerant@ibkm.com'])
            ->expectsOutputToContain('Mot de passe provisoire de '.$manager->name)
            ->assertSuccessful();

        $manager->refresh();
        $this->assertTrue($manager->must_change_password);
        $this->assertFalse(Hash::check('password', $manager->password));
    }

    public function test_password_reset_reports_unknown_accounts(): void
    {
        $this->artisan('pointage:mot-de-passe', ['identifiant' => 'inconnu@ibkm.com'])->assertFailed();
    }
}
