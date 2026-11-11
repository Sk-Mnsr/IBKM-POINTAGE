<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Institut
    |--------------------------------------------------------------------------
    */

    'institute_name' => env('POINTAGE_INSTITUTE_NAME', 'Institut de Beauté King Maelick'),

    /*
    |--------------------------------------------------------------------------
    | Durée d'un QR de borne
    |--------------------------------------------------------------------------
    |
    | La tablette affiche un code renouvelé à chaque fenêtre. Le téléphone
    | peut encore valider le code de la fenêtre précédente, le temps d'un scan.
    |
    */

    'qr_window_seconds' => (int) env('POINTAGE_QR_WINDOW', 30),

    /*
    |--------------------------------------------------------------------------
    | Tolérance de retard
    |--------------------------------------------------------------------------
    |
    | Une arrivée au-delà de l'heure prévue plus cette tolérance (en minutes)
    | est comptée comme un retard, pour sa durée totale.
    |
    */

    'late_tolerance_minutes' => (int) env('POINTAGE_LATE_TOLERANCE', 5),

    /*
    |--------------------------------------------------------------------------
    | Mode hors ligne
    |--------------------------------------------------------------------------
    |
    | La tablette garde des codes pré-signés pour les heures à venir, et un
    | téléphone sans réseau peut envoyer plus tard un code scanné dans ce délai.
    |
    */

    'kiosk_offline_hours' => (int) env('POINTAGE_KIOSK_OFFLINE_HOURS', 12),

    'offline_sync_hours' => (int) env('POINTAGE_OFFLINE_SYNC_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Sauvegardes
    |--------------------------------------------------------------------------
    |
    | La commande pointage:sauvegarder copie la base chaque nuit dans
    | storage/app/backups et supprime les copies plus anciennes que ce délai.
    |
    */

    'backup_retention_days' => (int) env('POINTAGE_BACKUP_RETENTION_DAYS', 30),

    'backup_path' => env('POINTAGE_BACKUP_PATH', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | Premier compte gérant
    |--------------------------------------------------------------------------
    |
    | Utilisé par le seeder pour créer le compte gérant. Le mot de passe reste
    | dans le fichier .env, jamais dans le code.
    |
    */

    'admin' => [
        'name' => env('POINTAGE_ADMIN_NAME', 'Gérant'),
        'email' => env('POINTAGE_ADMIN_EMAIL'),
        'phone' => env('POINTAGE_ADMIN_PHONE'),
        'password' => env('POINTAGE_ADMIN_PASSWORD'),
    ],

];
