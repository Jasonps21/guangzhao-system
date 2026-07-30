{{--
    Pemilih titik rumah di atas peta: ketik nama jalan → tekan Cari → klik hasil,
    atau langsung klik/geser penanda di peta. Kolom Latitude & Longitude terisi
    sendiri, jadi petugas tidak perlu tahu apa itu koordinat.

    Peta memakai Leaflet + ubin OpenStreetMap dan pencarian alamat Nominatim —
    keduanya gratis tanpa kunci API, sehingga tidak menambah dependensi aplikasi.
    Bila internet mati, peta tidak tampil tetapi kolom koordinat manual di
    bawahnya tetap bisa dipakai.
--}}
@php
    // Kolom latitude/longitude bertetangga dengan komponen ini di dalam
    // formulir, jadi jalur state-nya diambil dari induk yang sama.
    $prefix = \Illuminate\Support\Str::beforeLast($getStatePath(), '.');
    $mapId = 'gz-map-'.\Illuminate\Support\Str::random(8);
@endphp

<div wire:ignore class="gz-map">
    <div class="gz-map-search">
        <input
            type="text"
            id="{{ $mapId }}-q"
            class="gz-map-input"
            placeholder="Ketik nama jalan atau tempat, misal: Jl. Bontosua Makassar"
            autocomplete="off"
        >
        <button type="button" id="{{ $mapId }}-btn" class="gz-map-btn">Cari</button>
    </div>

    <div id="{{ $mapId }}-results" class="gz-map-results"></div>

    <div id="{{ $mapId }}" class="gz-map-canvas"></div>

    <p class="gz-map-hint">
        Klik di peta atau geser penanda untuk menentukan titik rumah. Titik yang terpilih
        otomatis mengisi kolom Latitude &amp; Longitude di bawah.
    </p>
</div>

<script>
    (function () {
        const mapId = @js($mapId);
        const latPath = @js($prefix.'.latitude');
        const lngPath = @js($prefix.'.longitude');
        // Pusat awal saat anggota belum punya titik: kantor perkumpulan di Makassar.
        const fallback = [-5.1476, 119.4327];

        function boot() {
            const el = document.getElementById(mapId);

            if (! el || el.dataset.ready === '1') {
                return;
            }

            el.dataset.ready = '1';

            const component = window.Livewire.find(
                el.closest('[wire\\:id]').getAttribute('wire:id'),
            );

            const readNumber = (path) => {
                const value = component.get(path);

                return value === null || value === '' ? null : Number(value);
            };

            const startLat = readNumber(latPath);
            const startLng = readNumber(lngPath);
            const hasPoint = startLat !== null && startLng !== null;

            const map = L.map(el).setView(hasPoint ? [startLat, startLng] : fallback, hasPoint ? 17 : 13);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap',
            }).addTo(map);

            let marker = hasPoint
                ? L.marker([startLat, startLng], { draggable: true }).addTo(map)
                : null;

            const write = (lat, lng) => {
                component.set(latPath, lat.toFixed(7), false);
                component.set(lngPath, lng.toFixed(7), false);
            };

            const place = (lat, lng) => {
                if (marker) {
                    marker.setLatLng([lat, lng]);
                } else {
                    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                    marker.on('dragend', () => {
                        const p = marker.getLatLng();
                        write(p.lat, p.lng);
                    });
                }

                write(lat, lng);
            };

            if (marker) {
                marker.on('dragend', () => {
                    const p = marker.getLatLng();
                    write(p.lat, p.lng);
                });
            }

            map.on('click', (e) => place(e.latlng.lat, e.latlng.lng));

            // Peta di dalam bagian yang terlipat lahir dengan ukuran nol —
            // hitung ulang begitu wadahnya benar-benar terlihat.
            new ResizeObserver(() => map.invalidateSize()).observe(el);

            const results = document.getElementById(mapId + '-results');
            const input = document.getElementById(mapId + '-q');

            const search = async () => {
                const query = input.value.trim();

                if (query === '') {
                    return;
                }

                results.innerHTML = '<div class="gz-map-note">Mencari…</div>';

                try {
                    const response = await fetch(
                        'https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=id&q='
                            + encodeURIComponent(query),
                        { headers: { 'Accept-Language': 'id' } },
                    );
                    const found = await response.json();

                    if (! found.length) {
                        results.innerHTML = '<div class="gz-map-note">Alamat tidak ditemukan. Coba kata kunci lain, atau klik langsung di peta.</div>';

                        return;
                    }

                    results.innerHTML = '';

                    found.forEach((item) => {
                        const row = document.createElement('button');
                        row.type = 'button';
                        row.className = 'gz-map-result';
                        row.textContent = item.display_name;
                        row.addEventListener('click', () => {
                            const lat = Number(item.lat);
                            const lng = Number(item.lon);
                            map.setView([lat, lng], 17);
                            place(lat, lng);
                            results.innerHTML = '';
                        });
                        results.appendChild(row);
                    });
                } catch (error) {
                    results.innerHTML = '<div class="gz-map-note">Pencarian gagal — periksa koneksi internet, atau klik langsung di peta.</div>';
                }
            };

            document.getElementById(mapId + '-btn').addEventListener('click', search);
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    search();
                }
            });
        }

        function withLeaflet(callback) {
            if (window.L) {
                callback();

                return;
            }

            if (! document.getElementById('gz-leaflet-css')) {
                const css = document.createElement('link');
                css.id = 'gz-leaflet-css';
                css.rel = 'stylesheet';
                css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(css);
            }

            let js = document.getElementById('gz-leaflet-js');

            if (! js) {
                js = document.createElement('script');
                js.id = 'gz-leaflet-js';
                js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                document.head.appendChild(js);
            }

            js.addEventListener('load', callback);
        }

        document.addEventListener('livewire:navigated', () => withLeaflet(boot));

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => withLeaflet(boot));
        } else {
            withLeaflet(boot);
        }
    })();
</script>
