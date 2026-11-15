<?php

namespace App\Support;

use App\Enums\PunchType;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Imports punches from an Excel file matching the export layout
 * (Date, Heure, Employé, Département, Action, Borne).
 */
class PunchSpreadsheetImporter
{
    /**
     * @param  list<int>  $shopIds
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function import(UploadedFile $file, array $shopIds): array
    {
        $employees = User::query()
            ->staff()
            ->whereIn('shop_id', $shopIds)
            ->get(['id', 'name', 'shop_id'])
            ->keyBy(fn (User $user): string => $this->normalize($user->name));

        $kiosks = Kiosk::query()
            ->whereIn('shop_id', $shopIds)
            ->get(['id', 'name', 'shop_id'])
            ->keyBy(fn (Kiosk $kiosk): string => $this->normalize($kiosk->name));

        $types = collect(PunchType::cases())
            ->keyBy(fn (PunchType $type): string => $this->normalize($type->label()));

        $imported = 0;
        $skipped = 0;
        /** @var list<string> $errors */
        $errors = [];
        $seen = [];

        foreach ($this->rows($file) as $index => $cells) {
            $line = $index + 1;

            if ($this->isHeadingOrMeta($cells)) {
                continue;
            }

            $parsed = $this->parseRow($cells, $employees, $kiosks, $types);

            if (is_string($parsed)) {
                $errors[] = "Ligne {$line} : {$parsed}";
                $skipped++;

                continue;
            }

            $fingerprint = $parsed['user_id'].'|'.$parsed['punched_at']->toDateTimeString().'|'.$parsed['type']->value;

            if (isset($seen[$fingerprint])) {
                $skipped++;

                continue;
            }

            $seen[$fingerprint] = true;

            $exists = Punch::query()
                ->where('user_id', $parsed['user_id'])
                ->where('type', $parsed['type'])
                ->where('punched_at', $parsed['punched_at'])
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            Punch::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $parsed['user_id'],
                'kiosk_id' => $parsed['kiosk_id'],
                'type' => $parsed['type'],
                'punched_at' => $parsed['punched_at'],
                'is_manual' => true,
            ]);

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 20),
        ];
    }

    /**
     * @return list<list<string>>
     */
    private function rows(UploadedFile $file): array
    {
        $reader = new Reader;
        $reader->open($file->getRealPath());

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(
                    fn ($value): string => $this->cellToString($value),
                    $row->toArray(),
                );
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * @param  list<string>  $cells
     */
    private function isHeadingOrMeta(array $cells): bool
    {
        $joined = $this->normalize(implode(' ', $cells));

        if ($joined === '') {
            return true;
        }

        if (str_contains($joined, 'date') && str_contains($joined, 'heure') && str_contains($joined, 'employe')) {
            return true;
        }

        if (str_contains($joined, 'historique des pointages') || str_contains($joined, 'periode du') || str_contains($joined, 'du ')) {
            if (! preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}/', $cells[0] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $cells
     * @param  Collection<string, User>  $employees
     * @param  Collection<string, Kiosk>  $kiosks
     * @param  Collection<string, PunchType>  $types
     * @return array{user_id: int, kiosk_id: ?int, type: PunchType, punched_at: CarbonImmutable}|string
     */
    private function parseRow(array $cells, Collection $employees, Collection $kiosks, Collection $types): array|string
    {
        $date = $this->extractDate($cells[0] ?? '');
        $time = $this->extractTime($cells[1] ?? ($cells[0] ?? ''));
        $name = $cells[2] ?? '';
        $action = $cells[4] ?? '';
        $kioskName = $cells[5] ?? '';

        if ($date === '' || $time === '' || $name === '' || $action === '') {
            return 'colonnes Date, Heure, Employé et Action obligatoires.';
        }

        $employee = $employees->get($this->normalize($name));

        if ($employee === null) {
            return "employé « {$name} » introuvable dans la boutique active.";
        }

        $type = $types->get($this->normalize($action))
            ?? PunchType::tryFrom($this->normalize($action));

        if (! $type instanceof PunchType) {
            return "action « {$action} » inconnue.";
        }

        try {
            $punchedAt = $this->dateTime($date, $time);
        } catch (Throwable) {
            return "date/heure invalides (« {$date} {$time} »).";
        }

        $kioskId = null;
        $normalizedKiosk = $this->normalize($kioskName);

        if ($normalizedKiosk !== '' && $normalizedKiosk !== $this->normalize('Saisie du gérant')) {
            $kiosk = $kiosks->get($normalizedKiosk);

            if ($kiosk === null) {
                return "borne « {$kioskName} » introuvable.";
            }

            $kioskId = $kiosk->id;
        }

        return [
            'user_id' => $employee->id,
            'kiosk_id' => $kioskId,
            'type' => $type,
            'punched_at' => $punchedAt,
        ];
    }

    private function dateTime(string $date, string $time): CarbonImmutable
    {
        $time = strlen($time) === 5 ? $time.':00' : $time;

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $parsed = CarbonImmutable::createFromFormat($format, trim($date.' '.$time));

            if ($parsed !== false) {
                return $parsed->setTimezone(config('app.timezone'));
            }
        }

        return CarbonImmutable::parse(trim($date.' '.$time))->setTimezone(config('app.timezone'));
    }

    private function cellToString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i:s');
        }

        if (is_array($value)) {
            return '';
        }

        return trim((string) ($value ?? ''));
    }

    private function extractDate(string $value): string
    {
        if (preg_match('/^(\d{1,2}\/\d{1,2}\/\d{4})/', $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches) === 1) {
            return $matches[1];
        }

        return trim($value);
    }

    private function extractTime(string $value): string
    {
        if (preg_match('/(\d{1,2}:\d{2}(?::\d{2})?)/', $value, $matches) === 1) {
            return $matches[1];
        }

        return trim($value);
    }

    private function normalize(string $value): string
    {
        $value = Str::of($value)->lower()->ascii()->trim()->toString();

        return (string) preg_replace('/\s+/', ' ', $value);
    }
}
