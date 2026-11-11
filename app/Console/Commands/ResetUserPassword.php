<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('pointage:mot-de-passe {identifiant : E-mail ou téléphone du compte}')]
#[Description('Donne un mot de passe provisoire à un compte, à changer à la prochaine connexion')]
class ResetUserPassword extends Command
{
    public function handle(): int
    {
        $identifier = trim((string) $this->argument('identifiant'));

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('phone', preg_replace('/\s+/', '', $identifier))
            ->first();

        if ($user === null) {
            $this->error("Aucun compte ne correspond à « {$identifier} ».");

            return self::FAILURE;
        }

        $password = Str::password(10, symbols: false);

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
            'is_active' => true,
        ])->save();

        $this->info("Mot de passe provisoire de {$user->name} ({$user->role->label()}) : {$password}");
        $this->line('Il devra en choisir un nouveau à sa prochaine connexion.');

        return self::SUCCESS;
    }
}
