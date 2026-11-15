<?php

namespace Tests\Feature;

use App\Enums\PunchType;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\Shop;
use App\Models\User;
use App\Support\PunchSpreadsheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class PunchImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_imports_punches_from_excel(): void
    {
        $shop = Shop::factory()->create();
        $manager = User::factory()->forShops($shop)->create();
        $employee = User::factory()->create(['name' => 'Awa Diop', 'shop_id' => $shop->id]);
        $kiosk = Kiosk::factory()->create(['shop_id' => $shop->id, 'name' => 'Accueil']);

        $path = $this->writeSpreadsheet([
            ['Date', 'Heure', 'Employé', 'Département', 'Action', 'Borne'],
            ['15/11/2026', '09:05:00', 'Awa Diop', 'Coiffeuses', 'Arrivée', 'Accueil'],
            ['15/11/2026', '18:30:00', 'Awa Diop', 'Coiffeuses', 'Départ', 'Accueil'],
        ]);

        $this->actingAs($manager)
            ->post(route('punches.import'), [
                'fichier' => new UploadedFile($path, 'pointages.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseCount('punches', 2);
        $this->assertDatabaseHas('punches', [
            'user_id' => $employee->id,
            'kiosk_id' => $kiosk->id,
            'type' => PunchType::Arrival->value,
            'is_manual' => true,
        ]);
    }

    public function test_import_skips_unknown_employees_and_duplicates(): void
    {
        $shop = Shop::factory()->create();
        $manager = User::factory()->forShops($shop)->create();
        $employee = User::factory()->create(['name' => 'Awa Diop', 'shop_id' => $shop->id]);

        Punch::factory()->create([
            'user_id' => $employee->id,
            'type' => PunchType::Arrival,
            'punched_at' => '2026-11-15 09:05:00',
            'is_manual' => true,
        ]);

        $path = $this->writeSpreadsheet([
            ['Date', 'Heure', 'Employé', 'Département', 'Action', 'Borne'],
            ['15/11/2026', '09:05:00', 'Awa Diop', '', 'Arrivée', 'Saisie du gérant'],
            ['15/11/2026', '10:00:00', 'Inconnue', '', 'Arrivée', ''],
            ['15/11/2026', '18:00:00', 'Awa Diop', '', 'Départ', ''],
        ]);

        $result = app(PunchSpreadsheetImporter::class)->import(
            new UploadedFile($path, 'pointages.xlsx', null, null, true),
            [$shop->id],
        );

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['skipped']);
        $this->assertNotEmpty($result['errors']);
        $this->assertDatabaseCount('punches', 2);
    }

    public function test_staff_cannot_import_punches(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('punches.import'), [
                'fichier' => UploadedFile::fake()->create('pointages.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ])
            ->assertForbidden();
    }

    public function test_manager_can_download_the_import_template(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('punches.import.template'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function writeSpreadsheet(array $rows): string
    {
        Storage::fake('local');
        $path = sys_get_temp_dir().'/pointage-import-'.uniqid().'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }
}
