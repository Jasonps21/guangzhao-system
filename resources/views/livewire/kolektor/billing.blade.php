<div>
    <header class="appbar">
        <div class="appbar__inner">
            <a href="{{ route('kolektor.groups') }}" class="icon-btn" wire:navigate aria-label="Kembali ke daftar kelompok">
                <svg class="ico" aria-hidden="true"><use href="#i-arrow-left"/></svg>
            </a>
            <div class="appbar__title">
                <span class="appbar__eyebrow">Penagihan</span>
                <h1 class="appbar__name">{{ $group->name }}</h1>
            </div>
        </div>

        {{-- Pencarian ikut menempel: kolektor mencari sambil berdiri di depan pintu,
             tidak boleh harus menggulir balik ke atas dulu. --}}
        <div class="searchbar">
            <svg class="ico searchbar__ico" aria-hidden="true"><use href="#i-search"/></svg>
            <input
                type="search"
                class="searchbar__input"
                wire:model.live.debounce.400ms="search"
                placeholder="Cari nama atau alamat…"
                aria-label="Cari anggota berdasarkan nama, alamat, atau nomor"
                enterkeyhint="search"
            >
            <button type="button" class="searchbar__clear" wire:click="clearSearch"
                aria-label="Hapus pencarian" @class(['is-hidden' => ! $isSearching])>
                <svg class="ico ico--sm" aria-hidden="true"><use href="#i-close"/></svg>
            </button>
        </div>
    </header>

    <div class="shell">
        <div class="period">
            <div>
                <span class="period__label">Periode</span>
                <div class="period__value">{{ $period }}</div>
            </div>
            <span class="period__who">
                @if ($isSearching)
                    {{ $records->count() }} dari {{ $totalCount }} anggota
                @else
                    {{ $totalCount }} anggota
                @endif
            </span>
        </div>

        @if (session('billing-message'))
            <div class="flash">
                <svg class="ico" aria-hidden="true"><use href="#i-info"/></svg>
                <span>{{ session('billing-message') }}</span>
            </div>
        @endif

        <div class="list">
            @forelse ($records as $record)
                @php($member = $record->member)
                <article class="shellcard rise" wire:key="dues-{{ $record->id }}">
                    <div class="shellcard__core">
                        <div class="row__head">
                            <span class="row__index" aria-hidden="true">{{ $member->no_urut }}</span>

                            <div class="row__title">
                                <h2 class="row__name">{{ $member->displayName() }}</h2>
                                <p class="row__meta">
                                    <span>{{ $member->member_number }}</span>
                                    <span class="sep"></span>
                                    <b>Rp {{ number_format($record->amount_due, 0, ',', '.') }}</b>
                                </p>
                                {{-- Alamat tampil langsung: inilah yang dipakai kolektor
                                     untuk menuju rumah, jangan disembunyikan di balik panel. --}}
                                @if (filled($member->address))
                                    <p class="row__address">
                                        <svg class="ico ico--sm" aria-hidden="true"><use href="#i-pin"/></svg>
                                        <span>{{ $member->address }}</span>
                                    </p>
                                @endif
                                {{-- Pengingat menanyakan nomor sebelum panel dibuka. --}}
                                @if (blank($member->phone))
                                    <p class="row__nowa">
                                        <svg class="ico ico--sm" aria-hidden="true"><use href="#i-alert"/></svg>
                                        <span>Nomor telepon belum ada</span>
                                    </p>
                                @endif
                            </div>

                            @if ($record->status === \App\Enums\DuesStatus::Lunas)
                                <span class="pill pill--ok">
                                    <svg class="ico ico--sm" aria-hidden="true"><use href="#i-check"/></svg>
                                    Lunas
                                </span>
                            @elseif ($record->status === \App\Enums\DuesStatus::MenungguPersetujuan)
                                <span class="pill pill--wait">
                                    <svg class="ico ico--sm" aria-hidden="true"><use href="#i-clock"/></svg>
                                    Menunggu
                                </span>
                            @endif
                        </div>

                        {{-- Baris lokasi rumah. Bila belum lengkap, alasannya cukup
                             ditempel di atas tombol aksi agar kartu tidak berlapis-lapis. --}}
                        @if ($member->hasCompleteLocation())
                            <div class="locbar">
                                <div class="locbar__status locbar__status--ok">
                                    <svg class="ico ico--sm" aria-hidden="true"><use href="#i-pin"/></svg>
                                    <span>Lokasi lengkap</span>
                                    @if ($member->googleMapsDirectionsUrl())
                                        <a class="locbar__nav" href="{{ $member->googleMapsDirectionsUrl() }}" target="_blank" rel="noopener">
                                            Navigasi
                                            <svg class="ico ico--sm" aria-hidden="true"><use href="#i-arrow-ur"/></svg>
                                        </a>
                                    @endif
                                </div>
                                <div class="locbar__actions">
                                    <button type="button" class="chip chip--confirm"
                                        wire:click="confirmLocation({{ $member->id }})"
                                        wire:confirm="Rumah {{ $member->displayName() }} masih di titik yang sama?"
                                        wire:loading.attr="disabled" wire:target="confirmLocation({{ $member->id }})">
                                        <svg class="ico ico--sm" aria-hidden="true"><use href="#i-check"/></svg>
                                        Masih sama
                                    </button>
                                    <button type="button" class="chip chip--neutral" wire:click="openVisit({{ $member->id }})">
                                        Ubah lokasi
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- Zona aksi lokasi. Berdiri sendiri, tidak boleh ikut tertutup
                             oleh status tagihan: iuran yang sudah dilunasi admin di kantor
                             dulu menghapus seluruh zona aksi kartu, sehingga rumah yang
                             belum berkoordinat mustahil dilengkapi kolektor di lapangan. --}}
                        @unless ($member->hasCompleteLocation())
                            <div class="actions">
                                <p class="actions__note">
                                    <svg class="ico ico--sm" aria-hidden="true"><use href="#i-alert"/></svg>
                                    Titik &amp; foto rumah belum ada
                                </p>
                                <button type="button" class="btn btn--locate" wire:click="openVisit({{ $member->id }})">
                                    <span class="btn__label">Lengkapi Lokasi</span>
                                    <span class="btn__ico"><svg aria-hidden="true"><use href="#i-pin"/></svg></span>
                                </button>
                            </div>
                        @endunless

                        {{-- Zona aksi pembayaran --}}
                        @if ($record->status === \App\Enums\DuesStatus::Lunas)
                            @if ($record->canBeUndoneBy(auth()->user()))
                                <div class="actions">
                                    <button
                                        type="button"
                                        class="btn btn--undo"
                                        wire:click="undoPayment({{ $record->id }})"
                                        wire:confirm="Batalkan pembayaran ini?&#10;&#10;{{ $member->displayName() }}&#10;Tagihan kembali BELUM BAYAR."
                                        wire:loading.attr="disabled"
                                        wire:target="undoPayment({{ $record->id }})"
                                    >
                                        <svg class="ico ico--sm" aria-hidden="true"><use href="#i-undo"/></svg>
                                        Batalkan pembayaran
                                    </button>
                                </div>
                            @endif
                        @elseif ($record->status === \App\Enums\DuesStatus::MenungguPersetujuan)
                            {{-- Menunggu admin — tidak ada aksi kolektor. --}}
                        @elseif ($member->hasCompleteLocation())
                            <div class="actions">
                                <button
                                    type="button"
                                    class="btn btn--pay"
                                    wire:click="markPaid({{ $record->id }})"
                                    wire:confirm="Tandai LUNAS {{ $period }}?&#10;&#10;{{ $member->displayName() }}&#10;Rp {{ number_format($record->amount_due, 0, ',', '.') }}"
                                    wire:loading.attr="disabled"
                                    wire:target="markPaid({{ $record->id }})"
                                >
                                    <span class="btn__label">Tandai Lunas</span>
                                    <span class="btn__ico"><svg aria-hidden="true"><use href="#i-check"/></svg></span>
                                </button>

                                <div class="range">
                                    <span class="range__label">Bayar s/d</span>
                                    <select class="range__select" wire:model="payUntil.{{ $record->member_id }}" aria-label="Bayar sampai bulan">
                                        @foreach ($monthOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button
                                        type="button"
                                        class="range__go"
                                        wire:click="payThrough({{ $record->member_id }})"
                                        wire:confirm="Catat pembayaran {{ $member->displayName() }} dari {{ $period }} sampai bulan terpilih?&#10;&#10;Lebih dari 1 bulan akan menunggu persetujuan admin."
                                        wire:loading.attr="disabled"
                                        wire:target="payThrough({{ $record->member_id }})"
                                    >Lunasi</button>
                                </div>
                            </div>
                        @endif

                        {{-- Panel kunjungan (GPS + foto + alamat) --}}
                        @if ($activeMemberId === $member->id)
                            <div class="visit">
                                <span class="visit__legend">Kunjungan Rumah</span>

                                <div class="gps-row">
                                    <button type="button" class="btn btn--gps"
                                        x-data
                                        @click="
                                            if (! navigator.geolocation) { alert('Perangkat tidak mendukung GPS.'); return; }
                                            $refs.gpsText.textContent = 'Mengambil…';
                                            navigator.geolocation.getCurrentPosition(
                                                (p) => { $wire.set('lat', p.coords.latitude); $wire.set('lng', p.coords.longitude); $refs.gpsText.textContent = 'Ambil ulang titik'; },
                                                (e) => { alert('Gagal ambil lokasi: ' + e.message); $refs.gpsText.textContent = 'Ambil titik GPS'; },
                                                { enableHighAccuracy: true, timeout: 15000 }
                                            );
                                        ">
                                        <svg class="ico ico--sm" aria-hidden="true"><use href="#i-crosshair"/></svg>
                                        <span x-ref="gpsText">Ambil titik GPS</span>
                                    </button>
                                    @if ($lat && $lng)
                                        <span class="gps-ok">
                                            <svg class="ico ico--sm" aria-hidden="true"><use href="#i-check"/></svg>
                                            {{ number_format($lat, 6) }}, {{ number_format($lng, 6) }}
                                        </span>
                                    @endif
                                </div>
                                @error('lat')
                                    <p class="err"><svg class="ico ico--sm" aria-hidden="true"><use href="#i-alert"/></svg>{{ $message }}</p>
                                @enderror

                                {{-- Foto dikecilkan di HP sebelum dikirim. Kompresi di server
                                     baru bekerja setelah seluruh byte sampai, jadi tidak menolong
                                     saat sinyal lemah — justru di situ uploadnya gagal. --}}
                                <div class="field" x-data="housePhotoUpload()">
                                    <span class="field__label">Foto rumah</span>
                                    <label class="filepick" :class="busy && 'is-busy'">
                                        <span class="filepick__ico">
                                            <svg class="ico ico--sm" aria-hidden="true"><use href="#i-camera"/></svg>
                                        </span>
                                        <span x-text="label">{{ $photo ? 'Foto siap — ketuk untuk ganti' : 'Ambil atau pilih foto rumah' }}</span>
                                        <input type="file" accept="image/*" capture="environment"
                                            x-ref="input" @change="pick($event)" :disabled="busy">
                                    </label>

                                    <div class="uploadbar" x-show="busy" x-cloak>
                                        <div class="uploadbar__track">
                                            <div class="uploadbar__fill" :style="`width: ${progress}%`"></div>
                                        </div>
                                        <p class="hint" x-text="progress < 100 ? `Mengirim foto… ${progress}%` : 'Menyimpan…'"></p>
                                    </div>

                                    {{-- Kegagalan harus terlihat. Sebelumnya spinner berputar
                                         selamanya tanpa pesan apa pun ketika koneksi putus. --}}
                                    <template x-if="failed">
                                        <div class="uploadfail">
                                            <p class="err">
                                                <svg class="ico ico--sm" aria-hidden="true"><use href="#i-alert"/></svg>
                                                <span x-text="failed"></span>
                                            </p>
                                            <div class="uploadfail__actions">
                                                <button type="button" class="chip chip--neutral" @click="retry()">Coba lagi</button>
                                                <button type="button" class="chip chip--confirm"
                                                    wire:click="saveLocationOnly"
                                                    wire:confirm="Simpan titik GPS saja tanpa foto?&#10;&#10;Foto rumah masih perlu diambil sebelum anggota ini bisa ditandai lunas."
                                                    wire:loading.attr="disabled" wire:target="saveLocationOnly">Simpan titik saja</button>
                                            </div>
                                        </div>
                                    </template>

                                    @error('photo')
                                        <p class="err"><svg class="ico ico--sm" aria-hidden="true"><use href="#i-alert"/></svg>{{ $message }}</p>
                                    @enderror
                                    @if ($photo)
                                        <img src="{{ $photo->temporaryUrl() }}" class="preview" alt="Pratinjau foto rumah">
                                    @elseif ($member->housePhotoUrl())
                                        <img src="{{ $member->housePhotoUrl() }}" class="preview" alt="Foto rumah tersimpan">
                                    @endif
                                </div>

                                {{-- Kunjungan rumah adalah satu-satunya kesempatan menanyakan
                                     nomor, jadi wajib diisi. Banyak anggota lansia hanya punya
                                     telepon rumah, jadi nomor rumah ikut diterima. Kolom yang
                                     berisi dua nomor sekaligus tidak diisikan otomatis (tidak
                                     akan lolos), tapi tetap ditampilkan agar bisa dicocokkan. --}}
                                <div class="field">
                                    <label class="field__label" for="wa-{{ $member->id }}">Nomor Telepon / WhatsApp <span class="field__req">wajib</span></label>
                                    <input id="wa-{{ $member->id }}" type="tel" inputmode="numeric" autocomplete="tel"
                                        class="input" wire:model="phone" placeholder="08xxxxxxxxxx atau 0411xxxxxx">
                                    @if (filled($member->phone) && ! \App\Support\PhoneNumber::isValid($member->phone))
                                        <p class="hint">Tercatat sebelumnya: {{ $member->phone }} — isi satu nomor saja.</p>
                                    @endif
                                    @error('phone')
                                        <p class="err"><svg class="ico ico--sm" aria-hidden="true"><use href="#i-alert"/></svg>{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="field">
                                    <label class="field__label" for="addr-{{ $member->id }}">Alamat (perbarui bila berubah)</label>
                                    <textarea id="addr-{{ $member->id }}" class="input" wire:model="houseAddress" rows="2"></textarea>
                                </div>

                                <div class="field">
                                    <label class="field__label" for="outcome-{{ $member->id }}">Hasil kunjungan</label>
                                    <select id="outcome-{{ $member->id }}" class="input" wire:model="outcome">
                                        @foreach ($outcomeOptions as $opt)
                                            <option value="{{ $opt->value }}">{{ $opt->getLabel() }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="field">
                                    <label class="field__label" for="note-{{ $member->id }}">Catatan / patokan (opsional)</label>
                                    <input id="note-{{ $member->id }}" type="text" class="input" wire:model="note" placeholder="mis. pagar hijau, sebelah warung">
                                </div>

                                <div class="visit__actions">
                                    <button type="button" class="btn btn--save" wire:click="saveVisit"
                                        wire:loading.attr="disabled" wire:target="saveVisit,photo">Simpan lokasi</button>
                                    <button type="button" class="btn btn--ghost" wire:click="closeVisit">Batal</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty">
                    <div class="empty__ico">
                        <svg class="ico ico--lg" aria-hidden="true"><use href="#i-{{ $isSearching ? 'search' : 'inbox' }}"/></svg>
                    </div>
                    @if ($isSearching)
                        Tidak ada anggota yang cocok dengan<br><strong>“{{ $search }}”</strong>.
                        <button type="button" class="btn btn--ghost empty__reset" wire:click="clearSearch">
                            Tampilkan semua anggota
                        </button>
                    @else
                        Belum ada tagihan untuk periode ini.<br>Hubungi admin untuk generate tagihan.
                    @endif
                </div>
            @endforelse
        </div>
    </div>
</div>
