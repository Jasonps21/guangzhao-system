<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f4f2ef">
    <link rel="manifest" href="{{ route('kolektor.manifest') }}">
    <title>{{ $title ?? 'Penagihan Iuran' }} — {{ config('app.name') }}</title>

    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/kolektor/instrument-sans-600.woff2') }}" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/kolektor/instrument-sans-500.woff2') }}" crossorigin>

    <style>
        /* ───────────────────────────────────────────────────────────
           Instrument Sans — self-hosted (subset latin) agar mode
           lapangan tetap tampil benar tanpa koneksi ke CDN font.
           ─────────────────────────────────────────────────────────── */
        @font-face {
            font-family: 'Instrument Sans';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url('{{ asset('fonts/kolektor/instrument-sans-400.woff2') }}') format('woff2');
        }
        @font-face {
            font-family: 'Instrument Sans';
            font-style: normal;
            font-weight: 500;
            font-display: swap;
            src: url('{{ asset('fonts/kolektor/instrument-sans-500.woff2') }}') format('woff2');
        }
        @font-face {
            font-family: 'Instrument Sans';
            font-style: normal;
            font-weight: 600;
            font-display: swap;
            src: url('{{ asset('fonts/kolektor/instrument-sans-600.woff2') }}') format('woff2');
        }

        /* ── Design tokens ─────────────────────────────────────────── */
        :root {
            --ink:        #16130f;
            --ink-2:      #57504a;
            --ink-3:      #8d857c;

            --surface:    #f4f2ef;
            --surface-2:  #edeae5;
            --card:       #ffffff;

            --shell:      rgba(22, 19, 15, .035);
            --hairline:   rgba(22, 19, 15, .075);
            --hairline-2: rgba(22, 19, 15, .05);

            --brand:      #a3540d;
            --brand-deep: #7c3f07;
            --brand-soft: #fbf1e4;

            --ok:         #0f6b46;
            --ok-deep:    #0b5236;
            --ok-soft:    #e7f3ed;

            --warn:       #96590a;
            --warn-soft:  #fcf0dc;

            --danger:     #9b2c2c;
            --danger-soft:#fbeded;

            --info:       #1f4f8f;
            --info-soft:  #ebf1fa;

            /* Bayangan ambient berlapis — lembut, tanpa garis gelap keras. */
            --lift-1: 0 1px 2px rgba(22, 19, 15, .04), 0 4px 12px -4px rgba(22, 19, 15, .06);
            --lift-2: 0 2px 4px rgba(22, 19, 15, .04), 0 14px 34px -10px rgba(22, 19, 15, .13);
            --inner-hi: inset 0 1px 0 rgba(255, 255, 255, .9);

            --r-shell: 1.5rem;
            --r-core:  1.1875rem; /* calc(1.5rem - .3125rem) — lengkung konsentris */
            --r-pill:  999px;

            --ease: cubic-bezier(.32, .72, 0, 1);
            --dur:   .55s;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: 17px;
            line-height: 1.5;
            letter-spacing: -.011em;
            color: var(--ink);
            background: var(--surface);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            padding-bottom: calc(env(safe-area-inset-bottom) + 2.5rem);
        }

        h1, h2, h3 { margin: 0; font-weight: 600; letter-spacing: -.028em; }

        /* ── App bar: pulau kaca melayang, bukan bilah penuh menempel ── */
        .appbar {
            position: sticky;
            top: 0;
            z-index: 20;
            padding: calc(env(safe-area-inset-top) + .7rem) .875rem .8rem;
            background: linear-gradient(to bottom, var(--surface) 42%, rgba(244, 242, 239, .82) 72%, rgba(244, 242, 239, 0));
        }
        .appbar__inner {
            max-width: 34rem;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: .625rem;
            padding: .4375rem .4375rem .4375rem 1.125rem;
            border-radius: var(--r-shell);
            background: rgba(255, 255, 255, .78);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--hairline);
            box-shadow: var(--lift-2), var(--inner-hi);
        }
        .appbar__title {
            min-width: 0;
            flex: 1;
        }
        .appbar__eyebrow {
            display: block;
            font-size: .625rem;
            font-weight: 600;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--brand);
            margin-bottom: .0625rem;
        }
        .appbar__name {
            font-size: 1.0625rem;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Tombol lingkaran di app bar (kembali / keluar). */
        .icon-btn {
            flex: none;
            width: 2.75rem;
            height: 2.75rem;
            display: grid;
            place-items: center;
            border: 1px solid var(--hairline-2);
            border-radius: var(--r-pill);
            background: rgba(22, 19, 15, .035);
            color: var(--ink-2);
            text-decoration: none;
            cursor: pointer;
            padding: 0;
            transition: transform var(--dur) var(--ease), background-color var(--dur) var(--ease), color var(--dur) var(--ease);
        }
        .icon-btn:active { transform: scale(.93); background: rgba(22, 19, 15, .07); }
        .icon-btn svg { width: 1.25rem; height: 1.25rem; }

        .shell { max-width: 34rem; margin: 0 auto; padding: 0 .875rem; }

        /* ── Pencarian anggota ─────────────────────────────────────── */
        .searchbar {
            position: relative;
            display: flex;
            align-items: center;
            max-width: 34rem;
            margin: .625rem auto 0;
            padding: 0 .375rem 0 1rem;
            border-radius: var(--r-pill);
            background: rgba(255, 255, 255, .82);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--hairline);
            box-shadow: var(--lift-1), var(--inner-hi);
            transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
        }
        .searchbar:focus-within {
            border-color: rgba(163, 84, 13, .45);
            box-shadow: var(--lift-1), 0 0 0 3.5px rgba(163, 84, 13, .12);
        }
        .searchbar__ico { flex: none; color: var(--ink-3); }
        .searchbar__input {
            flex: 1;
            min-width: 0;
            min-height: 3rem;
            border: 0;
            background: transparent;
            font-family: inherit;
            font-size: 1.0625rem;
            color: var(--ink);
            padding: .5rem .625rem;
            outline: 0;
            appearance: none;
            -webkit-appearance: none;
        }
        .searchbar__input::placeholder { color: var(--ink-3); }
        /* Tombol clear bawaan Safari/Chrome digantikan tombol sendiri agar cukup besar. */
        .searchbar__input::-webkit-search-cancel-button { -webkit-appearance: none; display: none; }
        .searchbar__clear {
            flex: none;
            width: 2.75rem;
            height: 2.75rem;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: var(--r-pill);
            background: var(--surface-2);
            color: var(--ink-2);
            cursor: pointer;
            transition: transform var(--dur) var(--ease), opacity var(--dur) var(--ease);
        }
        .searchbar__clear:active { transform: scale(.9); }
        .searchbar__clear.is-hidden { display: none; }

        /* ── Ikon garis tipis (sprite) ─────────────────────────────── */
        .ico {
            width: 1.125rem;
            height: 1.125rem;
            flex: none;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .ico--sm { width: .9375rem; height: .9375rem; }
        .ico--lg { width: 1.375rem; height: 1.375rem; }

        /* ── Meta baris periode ────────────────────────────────────── */
        .period {
            display: flex;
            align-items: baseline;
            gap: .5rem;
            flex-wrap: wrap;
            margin: .25rem 0 1.125rem;
            padding: 0 .375rem;
        }
        .period__label {
            font-size: .625rem;
            font-weight: 600;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--ink-3);
        }
        .period__value { font-size: 1.5rem; font-weight: 600; letter-spacing: -.03em; }
        .period__who { font-size: .875rem; color: var(--ink-3); margin-left: auto; }

        /* ── Doppelrand: cangkang luar + inti dalam ────────────────── */
        .shellcard {
            padding: .3125rem;
            border-radius: var(--r-shell);
            background: var(--shell);
            border: 1px solid var(--hairline-2);
            margin-bottom: .875rem;
        }
        .shellcard__core {
            border-radius: var(--r-core);
            background: var(--card);
            box-shadow: var(--lift-1), var(--inner-hi);
            overflow: hidden;
        }

        /* ── Kartu kelompok ────────────────────────────────────────── */
        .group {
            display: block;
            text-decoration: none;
            color: inherit;
            transition: transform var(--dur) var(--ease);
        }
        .group:active { transform: scale(.985); }
        .group__body {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.125rem 1.125rem 1.125rem 1.25rem;
        }
        .group__text { min-width: 0; flex: 1; }
        .group__name { font-size: 1.125rem; line-height: 1.3; }
        .group__meta {
            display: flex;
            align-items: center;
            gap: .4375rem;
            margin-top: .3125rem;
            font-size: .875rem;
            color: var(--ink-3);
        }
        .group__meta strong { color: var(--brand); font-weight: 600; }
        .group__meta--clear { color: var(--ok); }
        /* Tombol-dalam-tombol: panah duduk di lingkarannya sendiri. */
        .group__go {
            flex: none;
            width: 2.375rem;
            height: 2.375rem;
            display: grid;
            place-items: center;
            border-radius: var(--r-pill);
            background: var(--brand-soft);
            color: var(--brand);
            transition: transform var(--dur) var(--ease), background-color var(--dur) var(--ease);
        }
        .group:active .group__go { transform: translateX(2px) scale(1.06); background: var(--brand); color: #fff; }

        /* ── Kartu anggota ─────────────────────────────────────────── */
        .row__head {
            display: flex;
            align-items: flex-start;
            gap: .875rem;
            padding: 1.0625rem 1.125rem .9375rem;
        }
        /* Nomor urut — kolektor menagih berurutan, jadi posisi baris itu informasi. */
        .row__index {
            flex: none;
            min-width: 2.125rem;
            height: 2.125rem;
            padding: 0 .375rem;
            display: grid;
            place-items: center;
            border-radius: .75rem;
            background: var(--surface-2);
            color: var(--ink-3);
            font-size: .8125rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            letter-spacing: -.01em;
        }
        .row__title { min-width: 0; flex: 1; }
        .row__name {
            font-size: 1.0625rem;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }
        .row__meta {
            display: flex;
            align-items: center;
            gap: .4375rem;
            margin-top: .25rem;
            font-size: .8125rem;
            color: var(--ink-3);
            font-variant-numeric: tabular-nums;
        }
        .row__meta b { color: var(--ink-2); font-weight: 600; }
        /* Alamat: dua baris cukup, sisanya dipotong agar kartu tidak memanjang. */
        .row__address {
            display: flex;
            align-items: flex-start;
            gap: .375rem;
            margin: .4375rem 0 0;
            font-size: .875rem;
            line-height: 1.35;
            color: var(--ink-2);
        }
        .row__address .ico { margin-top: .1875rem; color: var(--ink-3); }
        .row__address span {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .sep {
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: currentColor;
            opacity: .45;
            flex: none;
        }

        /* Pil status */
        .pill {
            flex: none;
            display: inline-flex;
            align-items: center;
            gap: .375rem;
            padding: .4375rem .6875rem;
            border-radius: var(--r-pill);
            font-size: .8125rem;
            font-weight: 600;
            letter-spacing: -.005em;
            white-space: nowrap;
        }
        .pill--ok    { background: var(--ok-soft);   color: var(--ok); }
        .pill--wait  { background: var(--warn-soft); color: var(--warn); }

        /* Strip lokasi */
        .locbar {
            padding: .8125rem 1.125rem;
            border-top: 1px solid var(--hairline-2);
            background: linear-gradient(to bottom, rgba(22, 19, 15, .012), transparent);
        }
        .locbar__status {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .875rem;
            font-weight: 500;
        }
        .locbar__status--ok   { color: var(--ok); }
        .locbar__status--warn { color: var(--warn); }
        .locbar__nav {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: .3125rem;
            padding: .5rem .875rem;
            min-height: 2.75rem;
            border-radius: var(--r-pill);
            background: var(--info-soft);
            color: var(--info);
            font-size: .8125rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform var(--dur) var(--ease);
        }
        .locbar__nav:active { transform: scale(.95); }
        /* Dua aksi berlawanan arti — dijauhkan & dibedakan warna agar tidak salah tekan. */
        .locbar__actions { display: flex; gap: .75rem; margin-top: .75rem; }
        .chip {
            flex: 1;
            min-height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4375rem;
            padding: .5rem .75rem;
            border-radius: .875rem;
            border: 1px solid transparent;
            font-family: inherit;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform var(--dur) var(--ease), background-color var(--dur) var(--ease);
        }
        .chip:active { transform: scale(.965); }
        .chip--confirm { background: var(--ok-soft); color: var(--ok); border-color: rgba(15, 107, 70, .14); }
        .chip--neutral { background: var(--surface-2); color: var(--ink-2); border-color: var(--hairline-2); }
        .chip[disabled] { opacity: .5; }

        /* Zona aksi bawah kartu */
        .actions {
            padding: .9375rem 1.125rem 1.0625rem;
            border-top: 1px solid var(--hairline-2);
            background: rgba(22, 19, 15, .015);
        }
        /* Alasan singkat di atas tombol — menggantikan bilah peringatan terpisah. */
        .actions__note {
            display: flex;
            align-items: center;
            gap: .4375rem;
            margin: 0 0 .75rem;
            padding-left: .125rem;
            font-size: .8125rem;
            font-weight: 500;
            color: var(--warn);
        }

        /* ── Tombol ────────────────────────────────────────────────── */
        .btn {
            width: 100%;
            min-height: 3.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .8125rem 1.125rem;
            border: 0;
            border-radius: var(--r-pill);
            font-family: inherit;
            font-size: 1.0625rem;
            font-weight: 600;
            letter-spacing: -.014em;
            cursor: pointer;
            position: relative;
            transition: transform var(--dur) var(--ease), background-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
        }
        .btn:active { transform: scale(.975); }
        .btn[disabled] { opacity: .55; }

        .btn--pay {
            background: var(--ok);
            color: #fff;
            padding-right: .4375rem;
            box-shadow: 0 1px 2px rgba(11, 82, 54, .2), 0 8px 20px -8px rgba(11, 82, 54, .5), inset 0 1px 0 rgba(255, 255, 255, .18);
        }
        .btn--pay:active { background: var(--ok-deep); }

        .btn--locate {
            background: var(--brand);
            color: #fff;
            padding-right: .4375rem;
            box-shadow: 0 1px 2px rgba(124, 63, 7, .2), 0 8px 20px -8px rgba(124, 63, 7, .5), inset 0 1px 0 rgba(255, 255, 255, .18);
        }
        .btn--locate:active { background: var(--brand-deep); }

        /* Batal pembayaran salah tekan — sengaja kalem agar tak menyaingi LUNAS. */
        .btn--undo {
            background: transparent;
            color: var(--danger);
            border: 1px solid rgba(155, 44, 44, .22);
            min-height: 2.875rem;
            font-size: .9375rem;
        }
        .btn--undo:active { background: var(--danger-soft); }

        /* Ikon bersarang di dalam tombol utama (button-in-button). */
        .btn__ico {
            flex: none;
            width: 2rem;
            height: 2rem;
            margin-left: auto;
            display: grid;
            place-items: center;
            border-radius: var(--r-pill);
            background: rgba(255, 255, 255, .18);
            transition: transform var(--dur) var(--ease);
        }
        .btn:active .btn__ico { transform: translate(2px, -1px) scale(1.06); }
        .btn__ico svg {
            width: 1.0625rem;
            height: 1.0625rem;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.75;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .btn__label { margin-right: auto; padding-left: .5rem; }

        /* Pelunasan beberapa bulan — kontrol tersegmentasi yang tenang. */
        .range {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-top: .75rem;
            padding: .3125rem;
            border-radius: var(--r-pill);
            background: var(--card);
            border: 1px solid var(--hairline-2);
        }
        .range__label {
            flex: none;
            padding-left: .75rem;
            font-size: .8125rem;
            font-weight: 500;
            color: var(--ink-3);
            white-space: nowrap;
        }
        .range__select {
            flex: 0 1 auto;
            min-width: 0;
            margin-right: auto;
            appearance: none;
            -webkit-appearance: none;
            border: 0;
            background: transparent;
            font-family: inherit;
            font-size: .9375rem;
            font-weight: 600;
            color: var(--ink);
            padding: .8125rem 1.125rem .8125rem .25rem;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238d857c' stroke-width='1.75' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .125rem center;
            background-size: .875rem;
        }
        .range__go {
            flex: none;
            min-height: 2.75rem;
            padding: .5rem 1.125rem;
            border: 0;
            border-radius: var(--r-pill);
            background: var(--ink);
            color: #fff;
            font-family: inherit;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform var(--dur) var(--ease), background-color var(--dur) var(--ease);
        }
        .range__go:active { transform: scale(.95); background: #000; }
        .range__go[disabled] { opacity: .5; }

        /* ── Panel kunjungan ───────────────────────────────────────── */
        .visit {
            padding: 1.0625rem 1.125rem 1.125rem;
            border-top: 1px solid var(--hairline-2);
            background: var(--surface);
        }
        .visit__legend {
            display: block;
            font-size: .625rem;
            font-weight: 600;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--ink-3);
            margin-bottom: .75rem;
        }
        .field { margin-top: 1rem; }
        .field__label {
            display: block;
            font-size: .8125rem;
            font-weight: 600;
            color: var(--ink-2);
            margin-bottom: .4375rem;
        }
        .input {
            width: 100%;
            font-family: inherit;
            font-size: 1rem;
            color: var(--ink);
            padding: .8125rem .9375rem;
            border: 1px solid var(--hairline);
            border-radius: .875rem;
            background: var(--card);
            box-shadow: var(--inner-hi);
            transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
        }
        textarea.input { resize: vertical; line-height: 1.45; }
        select.input {
            appearance: none;
            -webkit-appearance: none;
            font-weight: 500;
            padding-right: 2.25rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238d857c' stroke-width='1.75' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .875rem center;
            background-size: 1rem;
        }
        .input:focus {
            outline: 0;
            border-color: rgba(163, 84, 13, .5);
            box-shadow: 0 0 0 3.5px rgba(163, 84, 13, .12);
        }
        .input::placeholder { color: var(--ink-3); }

        /* Tombol GPS */
        .btn--gps {
            width: auto;
            min-height: 2.875rem;
            padding: .625rem 1.125rem;
            background: var(--card);
            color: var(--ink);
            border: 1px solid var(--hairline);
            box-shadow: var(--lift-1);
            font-size: .9375rem;
        }
        .btn--gps:active { background: var(--surface-2); }
        .gps-row { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
        .gps-ok {
            display: inline-flex;
            align-items: center;
            gap: .375rem;
            font-size: .8125rem;
            font-weight: 600;
            color: var(--ok);
            font-variant-numeric: tabular-nums;
        }

        /* Pemilih foto — input file asli disembunyikan. */
        .filepick {
            display: flex;
            align-items: center;
            gap: .75rem;
            width: 100%;
            padding: .875rem 1rem;
            border: 1px dashed rgba(22, 19, 15, .18);
            border-radius: .875rem;
            background: var(--card);
            color: var(--ink-2);
            font-size: .9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: border-color var(--dur) var(--ease), background-color var(--dur) var(--ease);
        }
        .filepick:active { background: var(--surface-2); border-color: rgba(163, 84, 13, .4); }
        .filepick input[type=file] { display: none; }
        .filepick__ico {
            width: 2.25rem;
            height: 2.25rem;
            flex: none;
            display: grid;
            place-items: center;
            border-radius: var(--r-pill);
            background: var(--brand-soft);
            color: var(--brand);
        }
        .preview {
            display: block;
            width: 100%;
            margin-top: .75rem;
            border-radius: .875rem;
            max-height: 15rem;
            object-fit: cover;
            border: 1px solid var(--hairline-2);
        }

        .visit__actions { display: flex; gap: .75rem; margin-top: 1.25rem; }
        .btn--save { background: var(--ok); color: #fff; box-shadow: 0 1px 2px rgba(11, 82, 54, .2), 0 8px 20px -8px rgba(11, 82, 54, .5), inset 0 1px 0 rgba(255, 255, 255, .18); }
        .btn--save:active { background: var(--ok-deep); }
        .btn--ghost {
            width: auto;
            flex: none;
            padding: .8125rem 1.25rem;
            background: transparent;
            color: var(--ink-2);
            border: 1px solid var(--hairline);
            font-size: .9375rem;
        }
        .btn--ghost:active { background: var(--surface-2); }

        .hint { font-size: .8125rem; color: var(--ink-3); margin: .4375rem 0 0; }
        .err {
            display: flex;
            align-items: center;
            gap: .375rem;
            font-size: .8125rem;
            font-weight: 500;
            color: var(--danger);
            margin: .4375rem 0 0;
        }

        /* ── Notifikasi & kondisi kosong ───────────────────────────── */
        .flash {
            display: flex;
            align-items: flex-start;
            gap: .625rem;
            padding: .875rem 1rem;
            margin-bottom: 1rem;
            border-radius: 1rem;
            background: var(--info-soft);
            border: 1px solid rgba(31, 79, 143, .12);
            color: var(--info);
            font-size: .9375rem;
            font-weight: 500;
            line-height: 1.45;
        }
        .alert {
            display: flex;
            align-items: flex-start;
            gap: .625rem;
            padding: .875rem 1rem;
            margin-bottom: 1rem;
            border-radius: 1rem;
            background: var(--danger-soft);
            border: 1px solid rgba(155, 44, 44, .14);
            color: var(--danger);
            font-size: .9375rem;
            font-weight: 500;
        }
        .empty {
            text-align: center;
            color: var(--ink-3);
            padding: 3.5rem 1.5rem;
            font-size: .9375rem;
            line-height: 1.55;
        }
        .empty strong { font-weight: 600; color: var(--ink); }
        /* display:flex (bukan inline-flex) supaya tombol turun ke barisnya sendiri. */
        .empty__reset { display: flex; width: auto; margin: 1.5rem auto 0; }
        .empty__ico {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 1rem;
            display: grid;
            place-items: center;
            border-radius: var(--r-pill);
            background: var(--surface-2);
            color: var(--ink-3);
        }

        /* ── Layar masuk ───────────────────────────────────────────── */
        .auth { max-width: 25rem; margin: 0 auto; padding: 3rem 1.5rem 2rem; }
        .auth__eyebrow {
            display: inline-block;
            padding: .3125rem .625rem;
            border-radius: var(--r-pill);
            background: var(--brand-soft);
            color: var(--brand);
            font-size: .625rem;
            font-weight: 600;
            letter-spacing: .18em;
            text-transform: uppercase;
        }
        .auth__title {
            font-size: 2.125rem;
            line-height: 1.1;
            letter-spacing: -.04em;
            margin: 1.125rem 0 .5rem;
        }
        .auth__sub { color: var(--ink-3); font-size: .9375rem; margin: 0 0 2rem; }
        .auth__submit { margin-top: 1.75rem; background: var(--ink); color: #fff; padding-right: .4375rem; box-shadow: 0 1px 2px rgba(22, 19, 15, .2), 0 10px 24px -10px rgba(22, 19, 15, .6), inset 0 1px 0 rgba(255, 255, 255, .14); }
        .auth__submit:active { background: #000; }

        /* ── Gerak: masuk perlahan, berjenjang ─────────────────────── */
        @keyframes rise {
            from { opacity: 0; transform: translate3d(0, 16px, 0); }
            to   { opacity: 1; transform: none; }
        }
        .rise { animation: rise .7s var(--ease) both; }
        .list > *:nth-child(1) { animation-delay: 0ms; }
        .list > *:nth-child(2) { animation-delay: 45ms; }
        .list > *:nth-child(3) { animation-delay: 90ms; }
        .list > *:nth-child(4) { animation-delay: 135ms; }
        .list > *:nth-child(5) { animation-delay: 180ms; }
        .list > *:nth-child(6) { animation-delay: 225ms; }
        .list > *:nth-child(n+7) { animation-delay: 260ms; }

        [wire\:loading] { opacity: .6; }

        @media (prefers-reduced-motion: reduce) {
            .rise { animation: none; }
            *, *::before, *::after { transition-duration: .01ms !important; }
        }
    </style>
    @livewireStyles
</head>
<body>
    {{-- Sprite ikon garis tipis — dipakai ulang lewat <use>, hemat markup. --}}
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s7-5.686 7-11a7 7 0 1 0-14 0c0 5.314 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7"/></symbol>
        <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.25V12l3 1.75"/></symbol>
        <symbol id="i-alert" viewBox="0 0 24 24"><path d="M10.3 3.9 2.6 17.1A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.9L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 16.5h.01"/></symbol>
        <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol>
        <symbol id="i-arrow-left" viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></symbol>
        <symbol id="i-arrow-ur" viewBox="0 0 24 24"><path d="M7 17 17 7"/><path d="M8 7h9v9"/></symbol>
        <symbol id="i-camera" viewBox="0 0 24 24"><path d="M3 8.8A2.5 2.5 0 0 1 5.5 6.3h1.4a1 1 0 0 0 .83-.45l.94-1.4A1 1 0 0 1 9.5 4h5a1 1 0 0 1 .83.45l.94 1.4a1 1 0 0 0 .83.45h1.4A2.5 2.5 0 0 1 21 8.8v8.7A2.5 2.5 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5Z"/><circle cx="12" cy="13" r="3.2"/></symbol>
        <symbol id="i-crosshair" viewBox="0 0 24 24"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2.2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></symbol>
        <symbol id="i-logout" viewBox="0 0 24 24"><path d="M15 17l5-5-5-5"/><path d="M20 12H9"/><path d="M12 4H6.5A2.5 2.5 0 0 0 4 6.5v11A2.5 2.5 0 0 0 6.5 20H12"/></symbol>
        <symbol id="i-undo" viewBox="0 0 24 24"><path d="M4 9.5h9.5a5 5 0 0 1 0 10H8"/><path d="M4 9.5 8 5.5"/><path d="M4 9.5 8 13.5"/></symbol>
        <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 7.75h.01"/></symbol>
        <symbol id="i-inbox" viewBox="0 0 24 24"><path d="M3.5 13h4l1.5 2.5h6L16.5 13h4"/><path d="M5.4 5.6 3.5 13v4.5A1.5 1.5 0 0 0 5 19h14a1.5 1.5 0 0 0 1.5-1.5V13l-1.9-7.4A1.5 1.5 0 0 0 17.15 4.5H6.85A1.5 1.5 0 0 0 5.4 5.6Z"/></symbol>
        <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.75"/><path d="m16 16 4.25 4.25"/></symbol>
        <symbol id="i-close" viewBox="0 0 24 24"><path d="M6.5 6.5l11 11"/><path d="M17.5 6.5l-11 11"/></symbol>
        <symbol id="i-users" viewBox="0 0 24 24"><path d="M15.5 20v-1.6a3.6 3.6 0 0 0-3.6-3.6H6.6A3.6 3.6 0 0 0 3 18.4V20"/><circle cx="9.25" cy="7.6" r="3.6"/><path d="M21 20v-1.6a3.6 3.6 0 0 0-2.7-3.48"/><path d="M15.75 4.12a3.6 3.6 0 0 1 0 6.97"/></symbol>
    </svg>

    {{ $slot }}
    @livewireScripts
</body>
</html>
