/*
 * HTTPS on the salon's local network: forwards https://<ip>:8443 to the Laravel server on 127.0.0.1:8000.
 * Phones only allow the live camera on secure pages, hence this small proxy until the app is hosted.
 * Certificate: php artisan salon:certificat
 */
import { readFileSync } from 'node:fs';
import http from 'node:http';
import https from 'node:https';

const directory = new URL('./storage/app/https/', import.meta.url);
const target = { host: '127.0.0.1', port: Number(process.env.SALON_APP_PORT ?? 8000) };
const port = Number(process.env.SALON_HTTPS_PORT ?? 8443);

const server = https.createServer(
    {
        key: readFileSync(new URL('server.key', directory)),
        cert: readFileSync(new URL('server.crt', directory)),
    },
    (request, response) => {
        const upstream = http.request(
            {
                ...target,
                method: request.method,
                path: request.url,
                headers: {
                    ...request.headers,
                    'x-forwarded-proto': 'https',
                    'x-forwarded-host': request.headers.host ?? '',
                    'x-forwarded-port': String(port),
                    'x-forwarded-for': request.socket.remoteAddress ?? '',
                },
            },
            (upstreamResponse) => {
                response.writeHead(upstreamResponse.statusCode ?? 502, upstreamResponse.headers);
                upstreamResponse.pipe(response);
            },
        );

        upstream.on('error', () => {
            response.writeHead(502, { 'content-type': 'text/plain; charset=utf-8' });
            response.end("Le serveur Laravel ne répond pas sur le port 8000.");
        });

        request.pipe(upstream);
    },
);

server.listen(port, '0.0.0.0', () => {
    console.log(`HTTPS du salon prêt sur le port ${port} → http://${target.host}:${target.port}`);
});
