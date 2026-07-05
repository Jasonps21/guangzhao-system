<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">

    <form method="GET" class="fi-fieldset" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem">
        <label for="group-filter" style="font-weight:600">Kelompok</label>
        <select id="group-filter" name="group" onchange="this.form.submit()"
            style="padding:.5rem .75rem;border:1px solid #d6d3d1;border-radius:.5rem;min-width:14rem">
            <option value="">Semua kelompok</option>
            @foreach ($groups as $id => $name)
                <option value="{{ $id }}" @selected($selectedGroup === $id)>{{ $name }}</option>
            @endforeach
        </select>
        <noscript><button type="submit">Tampilkan</button></noscript>
        <span style="color:#78716c">{{ $points->count() }} rumah bertitik lokasi</span>
    </form>

    @if ($points->isEmpty())
        <x-filament::section>
            Belum ada anggota aktif dengan titik lokasi untuk pilihan ini. Titik terkumpul otomatis saat kolektor menagih di lapangan.
        </x-filament::section>
    @else
        <div id="member-map" style="height:70vh;border-radius:.75rem;overflow:hidden;z-index:0"></div>
        <script type="application/json" id="member-map-data">{!! $points->toJson() !!}</script>
        <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
        <script>
            (function () {
                const el = document.getElementById('member-map');
                const raw = document.getElementById('member-map-data');
                if (! el || ! raw || typeof L === 'undefined') return;

                const points = JSON.parse(raw.textContent);
                if (! points.length) return;

                const esc = (s) => (s == null ? '' : String(s).replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c])));

                const map = L.map(el);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(map);

                const markers = L.featureGroup();
                points.forEach((p) => {
                    const nav = p.directions
                        ? '<br><a href="' + esc(p.directions) + '" target="_blank" rel="noopener">Navigasi &#8599;</a>'
                        : '';
                    const grp = p.group ? '<br>' + esc(p.group) : '';
                    L.marker([p.lat, p.lng])
                        .bindPopup('<strong>' + esc(p.name) + '</strong>' + grp + nav)
                        .addTo(markers);
                });
                markers.addTo(map);
                map.fitBounds(markers.getBounds().pad(0.2));
            })();
        </script>
    @endif
</x-filament-panels::page>
