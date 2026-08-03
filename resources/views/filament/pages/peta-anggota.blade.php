<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">

    <style>
        .gz-peta-kosong {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .5rem;
            padding: 1.5rem 1rem;
            text-align: center;
        }

        .gz-peta-kosong svg {
            width: 2.75rem;
            height: 2.75rem;
            color: rgb(217 119 6);
        }

        .gz-peta-kosong__judul {
            font-weight: 600;
            color: rgb(24 24 27);
        }

        .gz-peta-kosong__isi {
            max-width: 34rem;
            font-size: .875rem;
            line-height: 1.6;
            color: rgb(113 113 122);
        }

        .dark .gz-peta-kosong__judul { color: rgb(250 250 250); }
        .dark .gz-peta-kosong__isi { color: rgb(161 161 170); }

        /* ---------- kerangka halaman ---------- */
        .gz-peta {
            --gz-permukaan: #fff;
            --gz-permukaan-2: #fafaf9;
            --gz-garis: rgb(228 228 231);
            --gz-teks: rgb(24 24 27);
            --gz-redup: rgb(113 113 122);
            --gz-merek: rgb(217 119 6);
            --gz-bayang: 0 1px 2px rgb(24 24 27 / .04), 0 12px 32px -18px rgb(24 24 27 / .28);
            --gz-lengkung: 1rem;

            display: flex;
            flex-direction: column;
            gap: 1rem;
            color: var(--gz-teks);
        }

        .dark .gz-peta {
            --gz-permukaan: rgb(24 24 27);
            --gz-permukaan-2: rgb(39 39 42);
            --gz-garis: rgb(63 63 70);
            --gz-teks: rgb(250 250 250);
            --gz-redup: rgb(161 161 170);
            --gz-merek: rgb(245 158 11);
            --gz-bayang: 0 1px 2px rgb(0 0 0 / .4), 0 12px 32px -18px rgb(0 0 0 / .7);
        }

        /* ---------- ringkasan angka ---------- */
        .gz-peta__stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
            gap: .75rem;
        }

        .gz-stat {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            padding: .95rem 1.1rem;
            background: var(--gz-permukaan);
            border: 1px solid var(--gz-garis);
            border-radius: var(--gz-lengkung);
            box-shadow: var(--gz-bayang);
        }

        .gz-stat--utama { grid-column: span 1; }

        @media (min-width: 48rem) {
            .gz-peta__stats { grid-template-columns: 1.4fr 1fr 1fr; }
        }

        .gz-stat__label {
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--gz-redup);
        }

        .gz-stat__value {
            display: flex;
            align-items: baseline;
            gap: .4rem;
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }

        .gz-stat__value small {
            font-size: .8125rem;
            font-weight: 500;
            color: var(--gz-redup);
        }

        .gz-stat__hint {
            font-size: .75rem;
            color: var(--gz-redup);
        }

        .gz-progress {
            height: .375rem;
            margin-top: .15rem;
            background: var(--gz-permukaan-2);
            border: 1px solid var(--gz-garis);
            border-radius: 9999px;
            overflow: hidden;
        }

        .gz-progress span {
            display: block;
            height: 100%;
            border-radius: 9999px;
            background: linear-gradient(90deg, rgb(245 158 11), rgb(217 119 6));
        }

        /* ---------- kerangka peta ---------- */
        .gz-peta__shell {
            display: grid;
            grid-template-columns: 1fr;
            gap: .75rem;
            min-height: 0;
        }

        @media (min-width: 64rem) {
            .gz-peta__shell {
                grid-template-columns: 20.5rem 1fr;
                height: clamp(30rem, calc(100vh - 20rem), 46rem);
            }
        }

        /* Di layar sempit petanya didahulukan: itu yang dicari saat membuka
           halaman ini, dan daftar di bawahnya tinggal digulir bila perlu. */
        @media (max-width: 63.99rem) {
            .gz-peta__mapwrap { order: -1; }

            .gz-peta__stats { grid-template-columns: 1fr 1fr; }

            .gz-stat--utama { grid-column: 1 / -1; }

            /* Sepuluh kelompok memakan enam baris kalau dibungkus; digeser
               menyamping jauh lebih hemat ruang di ponsel. */
            .gz-peta .gz-rail__chips {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: .2rem;
                scrollbar-width: none;
            }

            .gz-peta .gz-rail__chips::-webkit-scrollbar { display: none; }

            .gz-peta .gz-chip { flex: none; }
        }

        /* ---------- panel samping ---------- */
        .gz-rail {
            display: flex;
            flex-direction: column;
            /* Tanpa min-width, deretan chip yang digeser menyamping melebarkan
               kolom grid sampai melewati lebar layar dan ikut menyeret peta. */
            min-width: 0;
            min-height: 0;
            padding: .85rem;
            background: var(--gz-permukaan);
            border: 1px solid var(--gz-garis);
            border-radius: var(--gz-lengkung);
            box-shadow: var(--gz-bayang);
        }

        .gz-rail__cari {
            position: relative;
            display: flex;
            align-items: center;
        }

        .gz-rail__cari svg {
            position: absolute;
            left: .7rem;
            width: 1.05rem;
            height: 1.05rem;
            color: var(--gz-redup);
            pointer-events: none;
        }

        .gz-rail__cari input {
            width: 100%;
            min-height: 2.75rem;
            padding: .5rem .75rem .5rem 2.35rem;
            font-size: .875rem;
            color: var(--gz-teks);
            background: var(--gz-permukaan-2);
            border: 1px solid var(--gz-garis);
            border-radius: .7rem;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .gz-rail__cari input::placeholder { color: var(--gz-redup); }

        .gz-rail__cari input:focus {
            outline: none;
            border-color: var(--gz-merek);
            box-shadow: 0 0 0 3px rgb(217 119 6 / .18);
        }

        .gz-rail__chips {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            margin-top: .7rem;
        }

        .gz-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            min-height: 2.25rem;
            padding: .3rem .7rem;
            font-size: .75rem;
            font-weight: 600;
            color: var(--gz-redup);
            background: var(--gz-permukaan-2);
            border: 1px solid var(--gz-garis);
            border-radius: 9999px;
            cursor: pointer;
            transition: color .15s ease, background-color .15s ease, border-color .15s ease;
        }

        .gz-chip:hover { color: var(--gz-teks); border-color: var(--gz-redup); }

        .gz-chip:focus-visible {
            outline: 2px solid var(--gz-merek);
            outline-offset: 2px;
        }

        .gz-chip.is-aktif {
            color: var(--gz-teks);
            background: rgb(217 119 6 / .12);
            border-color: rgb(217 119 6 / .55);
        }

        .gz-chip__jumlah {
            font-variant-numeric: tabular-nums;
            font-size: .6875rem;
            color: var(--gz-redup);
        }

        .gz-titik {
            width: .5rem;
            height: .5rem;
            border-radius: 9999px;
            background: var(--gz-pin, var(--gz-merek));
            flex: none;
        }

        .gz-rail__hitung {
            margin: .75rem .15rem .35rem;
            font-size: .75rem;
            font-weight: 500;
            color: var(--gz-redup);
            font-variant-numeric: tabular-nums;
        }

        .gz-rail__daftar {
            flex: 1;
            min-height: 0;
            max-height: 22rem;
            margin: 0;
            padding: 0;
            overflow-y: auto;
            list-style: none;
            scrollbar-width: thin;
        }

        @media (min-width: 64rem) {
            .gz-rail__daftar { max-height: none; }
        }

        .gz-baris {
            display: flex;
            gap: .65rem;
            width: 100%;
            padding: .5rem;
            text-align: left;
            background: transparent;
            border: 1px solid transparent;
            border-radius: .7rem;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease;
        }

        .gz-baris:hover { background: var(--gz-permukaan-2); }

        .gz-baris:focus-visible {
            outline: 2px solid var(--gz-merek);
            outline-offset: -2px;
        }

        .gz-baris.is-aktif {
            background: rgb(217 119 6 / .1);
            border-color: rgb(217 119 6 / .4);
        }

        .gz-baris__foto {
            width: 2.75rem;
            height: 2.75rem;
            flex: none;
            object-fit: cover;
            border-radius: .55rem;
            background: var(--gz-permukaan-2);
            border: 1px solid var(--gz-garis);
        }

        .gz-baris__foto--kosong {
            display: grid;
            place-items: center;
            color: var(--gz-redup);
        }

        .gz-baris__foto--kosong svg { width: 1.15rem; height: 1.15rem; }

        .gz-baris__isi {
            display: flex;
            flex-direction: column;
            gap: .15rem;
            min-width: 0;
        }

        .gz-baris__nama {
            font-size: .875rem;
            font-weight: 600;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .gz-baris__grup {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            font-size: .75rem;
            color: var(--gz-redup);
        }

        .gz-baris__tanda {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem;
            margin-top: .1rem;
        }

        .gz-tanda {
            padding: .05rem .35rem;
            font-size: .625rem;
            font-weight: 600;
            color: rgb(180 83 9);
            background: rgb(245 158 11 / .16);
            border-radius: .3rem;
        }

        .dark .gz-tanda { color: rgb(252 211 77); }

        .gz-rail__catatan {
            margin-top: .5rem;
            font-size: .75rem;
            line-height: 1.5;
            color: var(--gz-redup);
        }

        /* ---------- wadah peta ---------- */
        .gz-peta__mapwrap {
            position: relative;
            isolation: isolate;
            min-width: 0;
            min-height: min(60vh, 28rem);
            border: 1px solid var(--gz-garis);
            border-radius: var(--gz-lengkung);
            overflow: hidden;
            box-shadow: var(--gz-bayang);
        }

        @media (min-width: 64rem) {
            .gz-peta__mapwrap { min-height: 0; }
        }

        .gz-peta__map {
            height: 100%;
            min-height: min(60vh, 28rem);
            z-index: 0;
            background: var(--gz-permukaan-2);
        }

        .gz-map-tombol {
            position: absolute;
            top: .75rem;
            right: .75rem;
            z-index: 500;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            min-height: 2.5rem;
            padding: .4rem .75rem;
            font-size: .75rem;
            font-weight: 600;
            color: var(--gz-teks);
            background: rgb(255 255 255 / .86);
            backdrop-filter: blur(10px);
            border: 1px solid var(--gz-garis);
            border-radius: .65rem;
            box-shadow: var(--gz-bayang);
            cursor: pointer;
            transition: background-color .15s ease;
        }

        .dark .gz-map-tombol { background: rgb(24 24 27 / .84); }

        .gz-map-tombol:hover { background: var(--gz-permukaan); }

        .gz-map-tombol:focus-visible {
            outline: 2px solid var(--gz-merek);
            outline-offset: 2px;
        }

        .gz-map-tombol svg { width: 1rem; height: 1rem; }

        /* ---------- penanda ---------- */
        .gz-pin-wadah { background: none; border: 0; }

        .gz-pin {
            position: relative;
            display: block;
            width: 32px;
            height: 42px;
            transform-origin: 50% 100%;
            transition: transform .18s ease;
            filter: drop-shadow(0 3px 4px rgb(24 24 27 / .35));
        }

        .gz-pin svg { width: 32px; height: 42px; display: block; }

        .gz-pin b {
            position: absolute;
            top: 5px;
            left: 0;
            width: 32px;
            font-size: .625rem;
            font-weight: 800;
            line-height: 1;
            text-align: center;
            color: var(--gz-pin);
        }

        .leaflet-marker-icon:hover .gz-pin { transform: scale(1.12); }

        .gz-pin.is-aktif { transform: scale(1.2); }

        .gz-pin.is-aktif::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -6px;
            width: 34px;
            height: 34px;
            margin-left: -17px;
            border-radius: 9999px;
            background: var(--gz-pin);
            opacity: .28;
            animation: gz-denyut 1.8s ease-out infinite;
            z-index: -1;
        }

        @keyframes gz-denyut {
            0% { transform: scale(.55); opacity: .4; }
            70% { transform: scale(1.5); opacity: 0; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        /* ---------- kartu popup ---------- */
        .gz-popup .leaflet-popup-content-wrapper {
            padding: 0;
            overflow: hidden;
            background: var(--gz-permukaan, #fff);
            color: var(--gz-teks, #18181b);
            border-radius: .9rem;
            box-shadow: 0 18px 45px -20px rgb(24 24 27 / .55), 0 2px 6px rgb(24 24 27 / .08);
        }

        .gz-popup .leaflet-popup-content {
            width: auto !important;
            margin: 0;
            line-height: 1.45;
        }

        .gz-popup .leaflet-popup-tip { background: var(--gz-permukaan, #fff); }

        .gz-popup .leaflet-popup-close-button {
            top: .35rem;
            right: .35rem;
            width: 1.75rem;
            height: 1.75rem;
            display: grid;
            place-items: center;
            color: #fff;
            background: rgb(24 24 27 / .45);
            border-radius: 9999px;
            backdrop-filter: blur(4px);
        }

        .gz-popup .leaflet-popup-close-button:hover { background: rgb(24 24 27 / .7); color: #fff; }

        .gz-pop { width: 17rem; max-width: calc(100vw - 3.5rem); }

        .gz-popup .gz-pop__foto {
            position: relative;
            display: block;
            aspect-ratio: 16 / 9;
            max-height: 9rem;
            background: var(--gz-permukaan-2, #f4f4f5);
        }

        .gz-popup .gz-pop__foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .gz-popup .gz-pop__foto--kosong {
            display: grid;
            place-content: center;
            justify-items: center;
            gap: .35rem;
            color: var(--gz-redup, #71717a);
            background-image: repeating-linear-gradient(-45deg, rgb(120 113 108 / .07) 0 8px, transparent 8px 16px);
        }

        .gz-popup .gz-pop__foto--kosong svg { width: 1.6rem; height: 1.6rem; }

        .gz-popup .gz-pop__foto--kosong span { font-size: .75rem; font-weight: 500; }

        .gz-pop__zoom {
            position: absolute;
            left: .5rem;
            bottom: .5rem;
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            padding: .2rem .45rem;
            font-size: .6875rem;
            font-weight: 600;
            color: #fff;
            background: rgb(24 24 27 / .55);
            border-radius: .4rem;
            backdrop-filter: blur(4px);
            opacity: 0;
            transition: opacity .15s ease;
        }

        .gz-pop__zoom svg { width: .8rem; height: .8rem; }

        .gz-popup .gz-pop__foto:hover .gz-pop__zoom,
        .gz-popup .gz-pop__foto:focus-visible .gz-pop__zoom { opacity: 1; }

        .gz-pop__isi {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            padding: .7rem .8rem .75rem;
        }

        .gz-pop__nama {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.25;
            color: var(--gz-teks, #18181b);
        }

        .gz-pop__meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .4rem;
            margin-top: .25rem;
            font-size: .75rem;
            color: var(--gz-redup, #71717a);
        }

        .gz-pop__nomor {
            padding: .1rem .35rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            background: var(--gz-permukaan-2, #f4f4f5);
            border-radius: .3rem;
        }

        .gz-pop__kelompok { display: inline-flex; align-items: center; gap: .3rem; }

        .gz-pop__alamat {
            display: flex;
            gap: .4rem;
            font-size: .8125rem;
            color: var(--gz-redup, #71717a);
        }

        .gz-pop__alamat svg { width: .95rem; height: .95rem; flex: none; margin-top: .12rem; }

        .gz-pop__kontak { display: flex; flex-direction: column; gap: .3rem; }

        .gz-popup .gz-pop__baris {
            display: flex;
            align-items: center;
            gap: .55rem;
            min-height: 2.375rem;
            padding: .3rem .5rem;
            color: var(--gz-teks, #18181b);
            background: var(--gz-permukaan-2, #f4f4f5);
            border-radius: .55rem;
            text-decoration: none;
            transition: background-color .15s ease;
        }

        .gz-popup .gz-pop__baris:hover { background: rgb(217 119 6 / .12); }

        .gz-popup .gz-pop__baris svg { width: 1.05rem; height: 1.05rem; flex: none; color: var(--gz-merek, #d97706); }

        .gz-popup .gz-pop__baris--wa svg { color: #16a34a; }

        .gz-pop__baris-teks { display: flex; flex-direction: column; min-width: 0; }

        .gz-pop__baris-teks b { font-size: .8125rem; font-weight: 600; font-variant-numeric: tabular-nums; }

        .gz-pop__baris-teks small { font-size: .6875rem; color: var(--gz-redup, #71717a); }

        .gz-pop__nihil { font-size: .75rem; font-style: italic; color: var(--gz-redup, #71717a); }

        .gz-pop__aksi { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem; }

        .gz-popup .gz-tombol {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .3rem;
            min-height: 2.625rem;
            padding: .4rem .5rem;
            font-size: .8125rem;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            color: var(--gz-teks, #18181b);
            background: var(--gz-permukaan-2, #f4f4f5);
            border: 1px solid var(--gz-garis, #e4e4e7);
            border-radius: .6rem;
            transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
        }

        .gz-popup .gz-tombol svg { width: .95rem; height: .95rem; flex: none; }

        .gz-popup .gz-tombol:hover { border-color: var(--gz-merek, #d97706); }

        .gz-popup .gz-tombol:active { transform: translateY(1px); }

        .gz-popup .gz-tombol--utama {
            color: #fff;
            background: var(--gz-merek, #d97706);
            border-color: var(--gz-merek, #d97706);
        }

        .gz-popup .gz-tombol--utama:hover { background: rgb(180 83 9); }

        .gz-popup .gz-pop__aksi > .gz-tombol:only-child { grid-column: span 2; }

        .gz-popup .gz-pop__detail {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: .4rem;
            min-height: 2.25rem;
            padding: .25rem .55rem;
            font-size: .8125rem;
            font-weight: 600;
            color: var(--gz-merek, #d97706);
            text-decoration: none;
            border-radius: .55rem;
            transition: background-color .15s ease;
        }

        .gz-popup .gz-pop__detail:hover { background: rgb(217 119 6 / .12); }

        .gz-popup .gz-pop__detail svg { width: .95rem; height: .95rem; }

        /* ---------- kendali bawaan Leaflet ---------- */
        .gz-peta .leaflet-control-zoom a {
            width: 2rem;
            height: 2rem;
            line-height: 1.9rem;
            color: var(--gz-teks);
            background: var(--gz-permukaan);
            border-color: var(--gz-garis);
        }

        .gz-peta .leaflet-control-zoom a:hover { background: var(--gz-permukaan-2); }

        .gz-peta .leaflet-bar { border-radius: .5rem; overflow: hidden; box-shadow: var(--gz-bayang); }

        .gz-peta .leaflet-control-attribution {
            padding: .1rem .4rem;
            font-size: .625rem;
            color: var(--gz-redup);
            background: rgb(255 255 255 / .78);
        }

        .dark .gz-peta .leaflet-control-attribution { background: rgb(24 24 27 / .78); }

        .gz-peta .leaflet-control-attribution a { color: var(--gz-redup); }

        @media (prefers-reduced-motion: reduce) {
            .gz-peta *,
            .gz-popup * {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>

    @if ($points->isEmpty())
        <x-filament::section>
            <div class="gz-peta-kosong">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                </svg>
                <p class="gz-peta-kosong__judul">Belum ada anggota aktif dengan titik lokasi</p>
                <p class="gz-peta-kosong__isi">
                    Titik rumah terkumpul otomatis saat kolektor menagih di lapangan — begitu satu
                    titik tersimpan, rumahnya langsung muncul di peta ini.
                </p>
            </div>
        </x-filament::section>
    @else
        <div class="gz-peta" data-gz-peta wire:ignore>
            <div class="gz-peta__stats">
                <div class="gz-stat gz-stat--utama">
                    <span class="gz-stat__label">Rumah terpetakan</span>
                    <span class="gz-stat__value">
                        {{ number_format($mappedTotal, 0, ',', '.') }}
                        <small>/ {{ number_format($activeTotal, 0, ',', '.') }} anggota aktif</small>
                    </span>
                    @php $persen = $activeTotal > 0 ? round($mappedTotal / $activeTotal * 100, 1) : 0; @endphp
                    <div class="gz-progress" role="img" aria-label="{{ $persen }} persen anggota aktif sudah punya titik rumah">
                        <span style="width: {{ max($persen, 1.5) }}%"></span>
                    </div>
                    <span class="gz-stat__hint">{{ rtrim(rtrim(number_format($persen, 1, ',', '.'), '0'), ',') }}% sudah punya titik GPS</span>
                </div>

                <div class="gz-stat">
                    <span class="gz-stat__label">Ada foto rumah</span>
                    <span class="gz-stat__value">{{ number_format($withPhoto, 0, ',', '.') }}</span>
                    <span class="gz-stat__hint">
                        {{ $withPhoto === $mappedTotal
                            ? 'Semua titik sudah berfoto'
                            : number_format($mappedTotal - $withPhoto, 0, ',', '.').' titik belum difoto' }}
                    </span>
                </div>

                <div class="gz-stat">
                    <span class="gz-stat__label">Ada nomor telepon</span>
                    <span class="gz-stat__value">{{ number_format($withContact, 0, ',', '.') }}</span>
                    <span class="gz-stat__hint">
                        {{ $withContact === $mappedTotal
                            ? 'Semua bisa dihubungi'
                            : number_format($mappedTotal - $withContact, 0, ',', '.').' belum punya nomor' }}
                    </span>
                </div>
            </div>

            <div class="gz-peta__shell">
                <aside class="gz-rail">
                    <div class="gz-rail__cari">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input type="search" id="gz-peta-cari" placeholder="Cari nama, nomor, atau alamat…"
                            autocomplete="off" aria-label="Cari anggota di peta">
                    </div>

                    <div class="gz-rail__chips" role="group" aria-label="Saring menurut kelompok">
                        <button type="button" class="gz-chip" data-group="" aria-pressed="true">
                            Semua
                            <span class="gz-chip__jumlah">{{ $mappedTotal }}</span>
                        </button>
                        @foreach ($groupFilters as $filter)
                            <button type="button" class="gz-chip" data-group="{{ $filter['id'] }}" aria-pressed="false">
                                <i class="gz-titik" style="background: {{ $filter['color'] }}"></i>
                                {{ $filter['name'] }}
                                <span class="gz-chip__jumlah">{{ $filter['count'] }}</span>
                            </button>
                        @endforeach
                        @if ($ungroupedCount > 0)
                            <button type="button" class="gz-chip" data-group="0" aria-pressed="false">
                                <i class="gz-titik" style="background: {{ $ungroupedColor }}"></i>
                                Tanpa kelompok
                                <span class="gz-chip__jumlah">{{ $ungroupedCount }}</span>
                            </button>
                        @endif
                    </div>

                    <p class="gz-rail__hitung" id="gz-peta-hitung" aria-live="polite"></p>

                    <ul class="gz-rail__daftar" id="gz-peta-daftar"></ul>

                    <p class="gz-rail__catatan" id="gz-peta-catatan" hidden></p>
                </aside>

                <div class="gz-peta__mapwrap">
                    <div id="member-map" class="gz-peta__map"></div>

                    <button type="button" class="gz-map-tombol" id="gz-peta-semua" title="Tampilkan semua titik">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                        <span>Semua titik</span>
                    </button>
                </div>
            </div>
        </div>

        <script type="application/json" id="member-map-data">{!! $points->toJson(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        <script type="application/json" id="member-map-config">@json(['group' => $selectedGroup])</script>
        <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
        <script>
            (function () {
                const root = document.querySelector('[data-gz-peta]');
                const mapEl = document.getElementById('member-map');
                const dataEl = document.getElementById('member-map-data');
                if (! root || ! mapEl || ! dataEl || typeof L === 'undefined') return;
                if (root.dataset.gzSiap === '1') return;
                root.dataset.gzSiap = '1';

                const points = JSON.parse(dataEl.textContent);
                if (! points.length) return;

                const configEl = document.getElementById('member-map-config');
                const config = configEl ? JSON.parse(configEl.textContent) : {};

                const hematGerak = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                /* Daftar dibatasi agar ratusan baris tidak menumpuk sekaligus; sisanya
                   ditemukan lewat kotak cari, yang lebih cepat daripada menggulir. */
                const BATAS_DAFTAR = 120;

                /* ---------- ikon (Heroicons + lambang WhatsApp) ---------- */
                const IKON = {
                    pin: '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>',
                    telepon: '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>',
                    rumah: '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>',
                    kamera: '<path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>',
                    luar: '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>',
                    rute: '<path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>',
                    kanan: '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>',
                    perbesar: '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>',
                };

                const IKON_WA = '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413"/>';

                function ikon(nama, kelas) {
                    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    svg.setAttribute('viewBox', '0 0 24 24');
                    svg.setAttribute('aria-hidden', 'true');
                    if (nama === 'wa') {
                        svg.setAttribute('fill', 'currentColor');
                    } else {
                        svg.setAttribute('fill', 'none');
                        svg.setAttribute('stroke', 'currentColor');
                        svg.setAttribute('stroke-width', '1.7');
                    }
                    if (kelas) svg.setAttribute('class', kelas);
                    svg.innerHTML = nama === 'wa' ? IKON_WA : IKON[nama];
                    return svg;
                }

                function el(tag, opsi, anak) {
                    const node = document.createElement(tag);
                    if (opsi && opsi.class) node.className = opsi.class;
                    if (opsi && opsi.text != null) node.textContent = opsi.text;
                    if (opsi && opsi.attrs) {
                        Object.keys(opsi.attrs).forEach((k) => {
                            const v = opsi.attrs[k];
                            if (v != null) node.setAttribute(k, v);
                        });
                    }
                    (anak || []).forEach((c) => { if (c) node.appendChild(c); });
                    return node;
                }

                function tautanLuar(href) {
                    return { href: href, target: '_blank', rel: 'noopener noreferrer' };
                }

                /* ---------- peta ---------- */
                const UBIN = {
                    terang: {
                        url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
                        opsi: { subdomains: 'abcd', maxZoom: 20, attribution: '&copy; OpenStreetMap &middot; &copy; CARTO' },
                    },
                    gelap: {
                        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
                        opsi: { subdomains: 'abcd', maxZoom: 20, attribution: '&copy; OpenStreetMap &middot; &copy; CARTO' },
                    },
                    cadangan: {
                        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                        opsi: { maxZoom: 19, attribution: '&copy; OpenStreetMap' },
                    },
                };

                const map = L.map(mapEl, { zoomControl: false, attributionControl: true });
                L.control.zoom({ position: 'bottomright', zoomInTitle: 'Perbesar', zoomOutTitle: 'Perkecil' }).addTo(map);

                let lapisanUbin = null;
                let pakaiCadangan = false;
                let galatUbin = 0;

                function gelap() {
                    return document.documentElement.classList.contains('dark');
                }

                function pasangUbin() {
                    const pilihan = pakaiCadangan ? UBIN.cadangan : (gelap() ? UBIN.gelap : UBIN.terang);
                    const lapisanLama = lapisanUbin;
                    const lapisanBaru = L.tileLayer(pilihan.url, pilihan.opsi);

                    /* Bila penyedia ubin tidak terjangkau dari jaringan kantor, jatuh
                       balik ke OpenStreetMap sekali saja daripada peta tampil kosong.
                       Galat dari lapisan yang sudah diganti diabaikan: mengganti
                       lapisan membatalkan permintaan yang masih berjalan, dan
                       pembatalan itu bukan tanda penyedianya bermasalah. */
                    lapisanBaru.on('tileerror', function () {
                        if (pakaiCadangan || lapisanUbin !== lapisanBaru) return;

                        galatUbin += 1;
                        if (galatUbin > 8) {
                            pakaiCadangan = true;
                            pasangUbin();
                        }
                    });

                    lapisanBaru.addTo(map);
                    lapisanUbin = lapisanBaru;
                    galatUbin = 0;

                    /* Lapisan lama baru dilepas setelah penggantinya terpasang supaya
                       peta tidak berkedip kosong saat mode gelap dinyalakan. */
                    if (lapisanLama) map.removeLayer(lapisanLama);
                }

                pasangUbin();

                new MutationObserver(function () {
                    if (! pakaiCadangan) pasangUbin();
                }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

                /* ---------- penanda ---------- */
                const lapisanTitik = L.layerGroup().addTo(map);

                function buatPenanda(titik) {
                    const wadah = document.createElement('span');
                    wadah.className = 'gz-pin';
                    wadah.style.setProperty('--gz-pin', titik.color);
                    wadah.innerHTML = '<svg viewBox="0 0 32 42" aria-hidden="true">'
                        + '<path d="M16 41s13-13.6 13-24.2A13 13 0 1 0 3 16.8C3 27.4 16 41 16 41Z" fill="var(--gz-pin)" stroke="#fff" stroke-width="2.4" stroke-linejoin="round"/>'
                        + '<circle cx="16" cy="16.4" r="6.2" fill="#fff"/></svg>';
                    const huruf = document.createElement('b');
                    huruf.textContent = titik.initial || '?';
                    wadah.appendChild(huruf);

                    return L.marker([titik.lat, titik.lng], {
                        title: titik.name,
                        riseOnHover: true,
                        icon: L.divIcon({
                            className: 'gz-pin-wadah',
                            html: wadah.outerHTML,
                            iconSize: [32, 42],
                            iconAnchor: [16, 40],
                            popupAnchor: [0, -34],
                        }),
                    });
                }

                /* ---------- kartu popup ---------- */
                function kartuFoto(titik) {
                    if (titik.photo) {
                        const gambar = el('img', {
                            attrs: {
                                src: titik.photo,
                                alt: 'Foto rumah ' + titik.name,
                                loading: 'lazy',
                                decoding: 'async',
                            },
                        });
                        const bingkai = el('a', {
                            class: 'gz-pop__foto',
                            attrs: Object.assign(tautanLuar(titik.photo), { title: 'Buka foto ukuran penuh' }),
                        }, [gambar, el('span', { class: 'gz-pop__zoom' }, [ikon('perbesar'), el('span', { text: 'Perbesar' })])]);

                        /* Foto rumah tersimpan di disk publik; bila berkasnya hilang,
                           kotak kosong diganti keadaan "belum ada foto" yang sama. */
                        gambar.addEventListener('error', function () {
                            bingkai.replaceWith(kartuTanpaFoto());
                        });

                        return bingkai;
                    }

                    return kartuTanpaFoto();
                }

                function kartuTanpaFoto() {
                    return el('div', { class: 'gz-pop__foto gz-pop__foto--kosong' }, [
                        ikon('kamera'),
                        el('span', { text: 'Foto rumah belum diambil' }),
                    ]);
                }

                function barisKontak(titik) {
                    const baris = [];

                    if (titik.phone) {
                        baris.push(el('a', {
                            class: 'gz-pop__baris',
                            attrs: { href: 'tel:' + titik.phone.replace(/[^\d+]/g, '') },
                        }, [
                            ikon('telepon'),
                            el('span', { class: 'gz-pop__baris-teks' }, [
                                el('b', { text: titik.phone }),
                                el('small', { text: 'Telepon' }),
                            ]),
                        ]));
                    }

                    if (titik.whatsappUrl) {
                        baris.push(el('a', {
                            class: 'gz-pop__baris gz-pop__baris--wa',
                            attrs: tautanLuar(titik.whatsappUrl),
                        }, [
                            ikon('wa'),
                            el('span', { class: 'gz-pop__baris-teks' }, [
                                el('b', { text: titik.whatsapp || titik.phone }),
                                el('small', { text: titik.whatsappOwner ? 'WhatsApp · ' + titik.whatsappOwner : 'WhatsApp' }),
                            ]),
                        ]));
                    }

                    if (! baris.length) {
                        baris.push(el('p', { class: 'gz-pop__nihil', text: 'Nomor telepon belum tercatat.' }));
                    }

                    return el('div', { class: 'gz-pop__kontak' }, baris);
                }

                function kartu(titik) {
                    const kepala = [
                        el('h3', { class: 'gz-pop__nama', text: titik.name }),
                        el('div', { class: 'gz-pop__meta' }, [
                            titik.number ? el('span', { class: 'gz-pop__nomor', text: titik.number }) : null,
                            el('span', { class: 'gz-pop__kelompok' }, [
                                el('i', { class: 'gz-titik' }),
                                el('span', { text: titik.group || 'Tanpa kelompok' }),
                            ]),
                        ]),
                    ];

                    const isi = el('div', { class: 'gz-pop__isi' }, [
                        el('div', {}, kepala),
                        titik.address
                            ? el('p', { class: 'gz-pop__alamat' }, [ikon('pin'), el('span', { text: titik.address })])
                            : el('p', { class: 'gz-pop__alamat gz-pop__nihil' }, [ikon('pin'), el('span', { text: 'Alamat belum tercatat' })]),
                        barisKontak(titik),
                        el('div', { class: 'gz-pop__aksi' }, [
                            titik.directions
                                ? el('a', { class: 'gz-tombol gz-tombol--utama', attrs: tautanLuar(titik.directions) }, [ikon('rute'), el('span', { text: 'Rute ke sini' })])
                                : null,
                            titik.maps
                                ? el('a', { class: 'gz-tombol', attrs: tautanLuar(titik.maps) }, [ikon('luar'), el('span', { text: 'Google Maps' })])
                                : null,
                        ]),
                        titik.detail
                            ? el('a', { class: 'gz-pop__detail', attrs: { href: titik.detail } }, [
                                el('span', { text: 'Buka data anggota' }),
                                ikon('kanan'),
                            ])
                            : null,
                    ]);

                    const kotak = el('div', { class: 'gz-pop' }, [kartuFoto(titik), isi]);
                    kotak.style.setProperty('--gz-pin', titik.color);

                    return kotak;
                }

                /* ---------- data & keadaan ---------- */
                const entri = points.map(function (titik) {
                    const penanda = buatPenanda(titik);
                    penanda.bindPopup(function () { return kartu(titik); }, {
                        className: 'gz-popup',
                        maxWidth: 304,
                        minWidth: 264,
                        /* Batas tingginya disesuaikan ulang mengikuti tinggi peta
                           (lihat segarkanTinggiPopup) supaya kartu tidak terpotong
                           di layar besar dan tetap bisa digulir di layar pendek. */
                        maxHeight: 420,
                        autoPanPadding: [20, 32],
                        closeButton: true,
                    });
                    penanda.on('popupopen', function () { sorot(titik.id, true); });
                    penanda.on('popupclose', function () { sorot(null, false); });

                    return {
                        titik: titik,
                        penanda: penanda,
                        cari: [titik.name, titik.number, titik.address, titik.group, titik.phone]
                            .filter(Boolean).join(' ').toLowerCase(),
                    };
                });

                const keadaan = {
                    group: config.group ? String(config.group) : '',
                    kata: '',
                    terpilih: null,
                };

                /* Peta hanya dirapikan otomatis selama pengguna belum menggesernya. */
                let bolehRapikanUlang = true;

                const daftarEl = document.getElementById('gz-peta-daftar');
                const hitungEl = document.getElementById('gz-peta-hitung');
                const catatanEl = document.getElementById('gz-peta-catatan');
                const cariEl = document.getElementById('gz-peta-cari');
                const chipEls = Array.from(root.querySelectorAll('.gz-chip'));

                function cocok(item) {
                    if (keadaan.group !== '') {
                        const grup = item.titik.groupId ? String(item.titik.groupId) : '0';
                        if (grup !== keadaan.group) return false;
                    }
                    if (keadaan.kata && item.cari.indexOf(keadaan.kata) === -1) return false;

                    return true;
                }

                function barisDaftar(item) {
                    const titik = item.titik;
                    const muka = titik.photo
                        ? el('img', { class: 'gz-baris__foto', attrs: { src: titik.photo, alt: '', loading: 'lazy', decoding: 'async' } })
                        : el('span', { class: 'gz-baris__foto gz-baris__foto--kosong' }, [ikon('rumah')]);

                    if (titik.photo) {
                        muka.addEventListener('error', function () {
                            muka.replaceWith(el('span', { class: 'gz-baris__foto gz-baris__foto--kosong' }, [ikon('rumah')]));
                        });
                    }

                    const tanda = [];
                    if (! titik.photo) tanda.push(el('span', { class: 'gz-tanda', text: 'Tanpa foto' }));
                    if (! titik.phone && ! titik.whatsappUrl) tanda.push(el('span', { class: 'gz-tanda', text: 'Tanpa nomor' }));

                    const tombol = el('button', {
                        class: 'gz-baris',
                        attrs: { type: 'button', 'data-id': titik.id },
                    }, [
                        muka,
                        el('span', { class: 'gz-baris__isi' }, [
                            el('span', { class: 'gz-baris__nama', text: titik.name }),
                            el('span', { class: 'gz-baris__grup' }, [
                                el('i', { class: 'gz-titik' }),
                                el('span', { text: titik.group || 'Tanpa kelompok' }),
                            ]),
                            tanda.length ? el('span', { class: 'gz-baris__tanda' }, tanda) : null,
                        ]),
                    ]);
                    tombol.style.setProperty('--gz-pin', titik.color);
                    tombol.addEventListener('click', function () { fokus(titik.id); });

                    return el('li', {}, [tombol]);
                }

                function gambarDaftar() {
                    const cocokan = entri.filter(cocok);
                    const potongan = cocokan.slice(0, BATAS_DAFTAR);
                    const wadah = document.createDocumentFragment();

                    potongan.forEach(function (item) { wadah.appendChild(barisDaftar(item)); });

                    daftarEl.replaceChildren(wadah);

                    hitungEl.textContent = cocokan.length === entri.length
                        ? entri.length + ' rumah bertitik lokasi'
                        : 'Menampilkan ' + cocokan.length + ' dari ' + entri.length + ' rumah';

                    if (! cocokan.length) {
                        catatanEl.textContent = 'Tidak ada rumah yang cocok dengan pencarian ini.';
                        catatanEl.hidden = false;
                    } else if (cocokan.length > BATAS_DAFTAR) {
                        catatanEl.textContent = (cocokan.length - BATAS_DAFTAR) + ' rumah lainnya tidak ditampilkan — persempit dengan kotak cari.';
                        catatanEl.hidden = false;
                    } else {
                        catatanEl.hidden = true;
                    }

                    return cocokan;
                }

                function gambarPenanda(cocokan) {
                    lapisanTitik.clearLayers();
                    cocokan.forEach(function (item) { lapisanTitik.addLayer(item.penanda); });
                }

                function batas(cocokan) {
                    const sumber = cocokan.length ? cocokan : entri;

                    return L.latLngBounds(sumber.map(function (item) {
                        return [item.titik.lat, item.titik.lng];
                    }));
                }

                function pasang(rapatkan) {
                    const cocokan = gambarDaftar();
                    gambarPenanda(cocokan);
                    if (rapatkan) rapikan(cocokan);
                    if (keadaan.terpilih) sorot(keadaan.terpilih, false);
                }

                function rapikan(cocokan) {
                    const kotak = batas(cocokan || entri.filter(cocok));
                    if (! kotak.isValid()) return;

                    if (kotak.getNorthEast().equals(kotak.getSouthWest())) {
                        map.setView(kotak.getCenter(), 17);

                        return;
                    }

                    map.fitBounds(kotak.pad(0.18), { animate: ! hematGerak });
                }

                function sorot(id, gulir) {
                    keadaan.terpilih = id;

                    entri.forEach(function (item) {
                        const node = item.penanda.getElement();
                        if (! node) return;
                        const pin = node.querySelector('.gz-pin');
                        if (pin) pin.classList.toggle('is-aktif', item.titik.id === id);
                    });

                    Array.from(daftarEl.querySelectorAll('.gz-baris')).forEach(function (baris) {
                        const aktif = id != null && baris.dataset.id === String(id);
                        baris.classList.toggle('is-aktif', aktif);
                        if (aktif && gulir) baris.scrollIntoView({ block: 'nearest', behavior: hematGerak ? 'auto' : 'smooth' });
                    });
                }

                function fokus(id) {
                    const item = entri.find(function (e) { return e.titik.id === id; });
                    if (! item) return;

                    bolehRapikanUlang = false;

                    const tujuan = [item.titik.lat, item.titik.lng];
                    const perbesaran = Math.max(map.getZoom(), 17);

                    if (! lapisanTitik.hasLayer(item.penanda)) lapisanTitik.addLayer(item.penanda);

                    if (hematGerak) {
                        map.setView(tujuan, perbesaran);
                        item.penanda.openPopup();

                        return;
                    }

                    /* Popup dibuka setelah peta selesai bergeser; kalau dibuka
                       lebih dulu, Leaflet tidak sempat menggesernya agar kartu
                       muat di layar dan bagian atasnya terpotong. */
                    let terbuka = false;
                    const buka = function () {
                        if (terbuka) return;
                        terbuka = true;
                        item.penanda.openPopup();
                    };

                    map.flyTo(tujuan, perbesaran, { duration: 0.6 });
                    map.once('moveend', buka);
                    setTimeout(buka, 750);
                }

                /* ---------- kendali ---------- */
                chipEls.forEach(function (chip) {
                    chip.addEventListener('click', function () {
                        keadaan.group = chip.dataset.group;
                        chipEls.forEach(function (lain) {
                            const aktif = lain === chip;
                            lain.classList.toggle('is-aktif', aktif);
                            lain.setAttribute('aria-pressed', aktif ? 'true' : 'false');
                        });
                        map.closePopup();
                        pasang(true);
                    });
                });

                let tundaCari;
                cariEl.addEventListener('input', function () {
                    clearTimeout(tundaCari);
                    tundaCari = setTimeout(function () {
                        keadaan.kata = cariEl.value.trim().toLowerCase();
                        pasang(false);
                    }, 180);
                });

                cariEl.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter') return;
                    event.preventDefault();
                    const pertama = daftarEl.querySelector('.gz-baris');
                    if (pertama) fokus(Number(pertama.dataset.id));
                });

                document.getElementById('gz-peta-semua').addEventListener('click', function () {
                    map.closePopup();
                    rapikan();
                });

                /* Pilihan awal mengikuti ?group= supaya tautan dari halaman lain
                   membuka peta yang sudah tersaring. */
                const chipAwal = chipEls.find(function (chip) { return chip.dataset.group === keadaan.group; }) || chipEls[0];
                keadaan.group = chipAwal.dataset.group;
                chipEls.forEach(function (chip) {
                    const aktif = chip === chipAwal;
                    chip.classList.toggle('is-aktif', aktif);
                    chip.setAttribute('aria-pressed', aktif ? 'true' : 'false');
                });

                pasang(true);

                /* Tinggi wadah peta belum tentu final saat skrip berjalan (font,
                   gambar, dan lebar bilah sisi masih bergerak), dan fitBounds pada
                   wadah setinggi nol menghasilkan tampilan seluruh dunia. Selama
                   pengguna belum menyentuh petanya sendiri, setiap perubahan ukuran
                   dipakai untuk merapikan ulang titiknya. */
                ['pointerdown', 'wheel'].forEach(function (nama) {
                    mapEl.addEventListener(nama, function () { bolehRapikanUlang = false; }, { passive: true });
                });

                /* Popup Leaflet memotong isinya begitu melewati maxHeight, jadi
                   batas itu harus mengikuti ruang yang benar-benar tersedia di peta. */
                function segarkanTinggiPopup() {
                    const tinggi = Math.max(220, mapEl.clientHeight - 96);
                    entri.forEach(function (item) {
                        const popup = item.penanda.getPopup();
                        if (popup) popup.options.maxHeight = tinggi;
                    });
                }

                function segarkanUkuran() {
                    map.invalidateSize({ animate: false });
                    segarkanTinggiPopup();
                    if (bolehRapikanUlang) rapikan();
                }

                requestAnimationFrame(segarkanUkuran);

                if (typeof ResizeObserver !== 'undefined') {
                    new ResizeObserver(segarkanUkuran).observe(mapEl);
                } else {
                    window.addEventListener('resize', segarkanUkuran);
                }
            })();
        </script>
    @endif

</x-filament-panels::page>
