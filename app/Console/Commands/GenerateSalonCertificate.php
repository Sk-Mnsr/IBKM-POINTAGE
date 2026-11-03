<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Signature('salon:certificat {--ip=* : Adresse IP du PC sur le réseau du salon (détectée si absente)} {--renouveler : Recrée aussi l\'autorité du salon}')]
#[Description('Crée le certificat HTTPS du réseau local, pour le scan en direct sur les téléphones')]
class GenerateSalonCertificate extends Command
{
    public function handle(): int
    {
        $directory = storage_path('app/https');
        File::ensureDirectoryExists($directory, 0700);

        $ips = $this->option('ip') ?: $this->detectLocalAddresses();

        if ($ips === []) {
            $this->error('Aucune adresse IP locale détectée. Indiquez-la avec --ip=192.168.1.12');

            return self::FAILURE;
        }

        if ($this->option('renouveler') || ! File::exists("{$directory}/ca.crt")) {
            $this->runOpenssl([
                'openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-sha256', '-days', '3650',
                '-keyout', "{$directory}/ca.key", '-out', "{$directory}/ca.crt",
                '-subj', '/CN=King Maelick Pointage - salon/O=King Maelick',
                '-addext', 'basicConstraints=critical,CA:TRUE',
                '-addext', 'keyUsage=critical,keyCertSign,cRLSign',
            ]);
            $this->info('Autorité du salon créée.');
        }

        $alternativeNames = collect($ips)
            ->map(fn (string $ip): string => "IP:{$ip}")
            ->push('IP:127.0.0.1', 'DNS:localhost')
            ->implode(',');

        File::put("{$directory}/server.ext", implode("\n", [
            'basicConstraints=CA:FALSE',
            'keyUsage=critical,digitalSignature,keyEncipherment',
            'extendedKeyUsage=serverAuth',
            "subjectAltName={$alternativeNames}",
        ]));

        $this->runOpenssl([
            'openssl', 'req', '-newkey', 'rsa:2048', '-nodes',
            '-keyout', "{$directory}/server.key", '-out', "{$directory}/server.csr",
            '-subj', '/CN=King Maelick Pointage',
        ]);

        $this->runOpenssl([
            'openssl', 'x509', '-req', '-sha256', '-days', '397',
            '-in', "{$directory}/server.csr",
            '-CA', "{$directory}/ca.crt", '-CAkey', "{$directory}/ca.key", '-CAcreateserial',
            '-extfile', "{$directory}/server.ext",
            '-out', "{$directory}/server.crt",
        ]);

        File::chmod("{$directory}/ca.key", 0600);
        File::chmod("{$directory}/server.key", 0600);

        $this->info('Certificat HTTPS prêt pour : '.implode(', ', $ips));
        $this->line('Installez le certificat du salon sur chaque téléphone : http://'.$ips[0].':8000/certificat-salon');
        $this->line('Puis ouvrez : https://'.$ips[0].':8443');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $command
     */
    private function runOpenssl(array $command): void
    {
        Process::run($command)->throw();
    }

    /**
     * @return list<string>
     */
    private function detectLocalAddresses(): array
    {
        $output = Process::run(['hostname', '-I'])->output();

        return collect(preg_split('/\s+/', trim($output)) ?: [])
            ->filter(fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && ! str_starts_with($ip, '127.'))
            ->values()
            ->all();
    }
}
