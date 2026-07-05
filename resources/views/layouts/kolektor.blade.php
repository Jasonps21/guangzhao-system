<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#b45309">
    <link rel="manifest" href="{{ route('kolektor.manifest') }}">
    <title>{{ $title ?? 'Penagihan Iuran' }} — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f4;
            color: #1c1917;
            font-size: 18px;
            line-height: 1.5;
        }
        .topbar {
            background: #b45309;
            color: #fff;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .topbar h1 { font-size: 1.25rem; margin: 0; }
        .topbar a, .topbar button {
            color: #fff;
            background: rgba(255,255,255,.18);
            border: 0;
            border-radius: .5rem;
            padding: .5rem .85rem;
            font-size: 1rem;
            text-decoration: none;
            cursor: pointer;
        }
        .container { max-width: 640px; margin: 0 auto; padding: 1rem; }
        .card {
            background: #fff;
            border-radius: .9rem;
            padding: 1rem 1.15rem;
            margin-bottom: .85rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .card .name { font-weight: 600; font-size: 1.15rem; }
        .card .meta { color: #78716c; font-size: .95rem; }
        .btn-lunas {
            background: #16a34a;
            color: #fff;
            border: 0;
            border-radius: .75rem;
            padding: 1rem 1.5rem;
            font-size: 1.15rem;
            font-weight: 700;
            cursor: pointer;
            min-width: 120px;
            white-space: nowrap;
        }
        .btn-lunas:active { background: #15803d; }
        .pay-actions { display: flex; flex-direction: column; gap: .5rem; align-items: stretch; min-width: 150px; }
        .pay-range { display: flex; align-items: center; gap: .4rem; }
        .pay-range-label { color: #78716c; font-size: .9rem; white-space: nowrap; }
        .pay-range select {
            flex: 1;
            min-width: 0;
            padding: .55rem .5rem;
            font-size: 1rem;
            border: 1px solid #d6d3d1;
            border-radius: .55rem;
            background: #fff;
        }
        .btn-range {
            background: #b45309;
            color: #fff;
            border: 0;
            border-radius: .6rem;
            padding: .55rem .9rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-range:active { background: #92400e; }
        .badge-lunas {
            background: #dcfce7;
            color: #166534;
            border-radius: .75rem;
            padding: .75rem 1.25rem;
            font-weight: 700;
        }
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
            border-radius: .75rem;
            padding: .75rem 1rem;
            font-weight: 700;
            font-size: .9rem;
            text-align: center;
        }
        .flash {
            background: #dbeafe;
            color: #1e40af;
            padding: .85rem 1rem;
            border-radius: .6rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .group-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }
        .group-link .name { color: #b45309; }
        .empty { text-align: center; color: #78716c; padding: 2.5rem 1rem; }
        .login-box { max-width: 380px; margin: 3rem auto; padding: 0 1rem; }
        .login-box label { display: block; margin: .9rem 0 .35rem; font-weight: 600; }
        .login-box input {
            width: 100%;
            padding: .85rem 1rem;
            font-size: 1.1rem;
            border: 1px solid #d6d3d1;
            border-radius: .6rem;
        }
        .login-box button {
            width: 100%;
            margin-top: 1.5rem;
            background: #b45309;
            color: #fff;
            border: 0;
            border-radius: .6rem;
            padding: 1rem;
            font-size: 1.15rem;
            font-weight: 700;
            cursor: pointer;
        }
        .alert { background: #fee2e2; color: #991b1b; padding: .85rem 1rem; border-radius: .6rem; margin-bottom: 1rem; }
        .summary { color: #57534e; margin: .25rem 0 1rem; }
        h2 { font-size: 1.1rem; }

        /* Kartu penagihan + lokasi rumah */
        .card-wrap {
            background: #fff;
            border-radius: .9rem;
            margin-bottom: .85rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            overflow: hidden;
        }
        .card-wrap .card { margin-bottom: 0; box-shadow: none; border-radius: 0; padding-bottom: .65rem; }
        .btn-need-loc {
            background: #f97316; color: #fff; border: 0; border-radius: .75rem;
            padding: 1rem 1.1rem; font-size: 1.05rem; font-weight: 700; cursor: pointer;
            min-width: 120px; white-space: nowrap;
        }
        .btn-need-loc:active { background: #ea580c; }
        .loc-bar {
            display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
            padding: .6rem 1.15rem 1rem; border-top: 1px dashed #e7e5e4;
        }
        .loc-ok { color: #166534; font-size: .92rem; font-weight: 600; }
        .loc-warn { color: #b45309; font-size: .92rem; font-weight: 600; }
        .loc-link {
            color: #1d4ed8; font-weight: 600; text-decoration: none; font-size: .92rem;
            padding: .35rem .6rem; background: #eff6ff; border-radius: .5rem;
        }
        .loc-btn {
            margin-left: auto; background: #f5f5f4; color: #44403c; border: 1px solid #e7e5e4;
            border-radius: .5rem; padding: .4rem .75rem; font-size: .9rem; font-weight: 600; cursor: pointer;
        }
        .loc-btn + .loc-btn { margin-left: .4rem; }
        .loc-btn:active { background: #e7e5e4; }

        /* Panel kunjungan */
        .visit-panel { padding: .35rem 1.15rem 1.15rem; background: #fafaf9; border-top: 1px solid #f0efee; }
        .visit-row { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin-top: .6rem; }
        .btn-gps {
            background: #0f766e; color: #fff; border: 0; border-radius: .6rem;
            padding: .7rem 1rem; font-size: 1rem; font-weight: 700; cursor: pointer;
        }
        .btn-gps:active { background: #115e59; }
        .gps-ok { color: #166534; font-weight: 600; font-size: .92rem; }
        .visit-label { display: block; margin: .8rem 0 .3rem; font-weight: 600; font-size: .95rem; }
        .visit-input {
            width: 100%; padding: .7rem .85rem; font-size: 1rem;
            border: 1px solid #d6d3d1; border-radius: .55rem; background: #fff;
        }
        .visit-preview { margin-top: .6rem; max-width: 100%; max-height: 220px; border-radius: .6rem; display: block; }
        .visit-hint { color: #78716c; font-size: .9rem; margin-top: .35rem; }
        .visit-err { color: #b91c1c; font-size: .9rem; margin-top: .35rem; font-weight: 600; }
        .visit-actions { display: flex; gap: .6rem; margin-top: 1rem; }
        .btn-save-visit {
            flex: 1; background: #16a34a; color: #fff; border: 0; border-radius: .6rem;
            padding: .9rem; font-size: 1.05rem; font-weight: 700; cursor: pointer;
        }
        .btn-save-visit:active { background: #15803d; }
        .btn-cancel-visit {
            background: #e7e5e4; color: #44403c; border: 0; border-radius: .6rem;
            padding: .9rem 1.2rem; font-size: 1.05rem; font-weight: 600; cursor: pointer;
        }
    </style>
    @livewireStyles
</head>
<body>
    {{ $slot }}
    @livewireScripts
</body>
</html>
