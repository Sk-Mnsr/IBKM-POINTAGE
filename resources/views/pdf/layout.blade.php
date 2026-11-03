<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <title>@yield('title')</title>
        <style>
            @page { margin: 28px 32px; }
            * { font-family: 'DejaVu Sans', sans-serif; }
            body { color: #111311; font-size: 10px; }
            .header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #111311; margin-bottom: 14px; }
            .header td { padding: 0 0 10px; vertical-align: bottom; }
            .header .logo { text-align: right; width: 130px; }
            .header .logo img { width: 120px; }
            .brand { color: #557a16; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; }
            h1 { font-size: 18px; margin: 4px 0 2px; }
            .muted { color: #6b7065; }
            .cards { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px; }
            .cards td { background: #eef3e2; border-radius: 6px; padding: 8px 10px; width: 25%; }
            .cards .label { color: #6b7065; font-size: 8px; text-transform: uppercase; letter-spacing: 1px; }
            .cards .value { font-size: 15px; font-weight: bold; margin-top: 2px; }
            table.data { width: 100%; border-collapse: collapse; }
            table.data th { background: #111311; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px; text-align: left; }
            table.data td { border-bottom: 1px solid #e2e6da; padding: 5px 6px; }
            table.data tr:nth-child(even) td { background: #f8faf4; }
            table.data tfoot td { background: #eef3e2; font-weight: bold; border-top: 2px solid #111311; }
            .right { text-align: right; }
            .late { color: #b45309; font-weight: bold; }
            .absent { color: #be123c; font-weight: bold; }
            .faded { color: #a8a29e; }
            .footer { margin-top: 18px; font-size: 8px; color: #6b7065; }
            .signatures { width: 100%; margin-top: 30px; }
            .signatures td { width: 50%; padding-top: 34px; border-top: 1px solid #111311; font-size: 9px; }
        </style>
    </head>
    <body>
        <table class="header">
            <tr>
                <td>
                    <div class="brand">{{ config('pointage.institute_name') }}</div>
                    <h1>@yield('title')</h1>
                    <div class="muted">@yield('subtitle')</div>
                </td>
                <td class="logo">
                    <img src="{{ public_path('images/logo-king-maelick.png') }}" alt="King Maelick">
                </td>
            </tr>
        </table>

        @yield('content')

        <div class="footer">Document généré le {{ now()->format('d/m/Y à H:i') }}. Tolérance de retard : {{ config('pointage.late_tolerance_minutes') }} minutes.</div>
    </body>
</html>
