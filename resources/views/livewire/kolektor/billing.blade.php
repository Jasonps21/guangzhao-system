<div>
    <div class="topbar">
        <h1>{{ $group->name }}</h1>
        <a href="{{ route('kolektor.groups') }}" wire:navigate>‹ Kembali</a>
    </div>

    <div class="container">
        <p class="summary">Periode <strong>{{ $period }}</strong></p>

        @if (session('billing-message'))
            <div class="flash">{{ session('billing-message') }}</div>
        @endif

        @forelse ($records as $record)
            @php($member = $record->member)
            <div class="card-wrap" wire:key="dues-{{ $record->id }}">
                <div class="card">
                    <div>
                        <div class="name">{{ $member->displayName() }}</div>
                        <div class="meta">
                            {{ $member->member_number }} ·
                            Rp {{ number_format($record->amount_due, 0, ',', '.') }}
                        </div>
                    </div>

                    @if ($record->status === \App\Enums\DuesStatus::Lunas)
                        <span class="badge-lunas">✓ Lunas</span>
                    @elseif ($record->status === \App\Enums\DuesStatus::MenungguPersetujuan)
                        <span class="badge-pending">⏳ Menunggu Persetujuan</span>
                    @elseif (! $member->hasCompleteLocation())
                        <button type="button" class="btn-need-loc" wire:click="openVisit({{ $member->id }})">
                            📍 Lengkapi Lokasi
                        </button>
                    @else
                        <div class="pay-actions">
                            <button
                                type="button"
                                class="btn-lunas"
                                wire:click="markPaid({{ $record->id }})"
                                wire:confirm="Tandai LUNAS {{ $period }}?&#10;&#10;{{ $member->displayName() }}&#10;Rp {{ number_format($record->amount_due, 0, ',', '.') }}"
                                wire:loading.attr="disabled"
                                wire:target="markPaid({{ $record->id }})"
                            >LUNAS</button>

                            <div class="pay-range">
                                <span class="pay-range-label">Bayar s/d</span>
                                <select wire:model="payUntil.{{ $record->member_id }}" aria-label="Bayar sampai bulan">
                                    @foreach ($monthOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button
                                    type="button"
                                    class="btn-range"
                                    wire:click="payThrough({{ $record->member_id }})"
                                    wire:confirm="Catat pembayaran {{ $member->displayName() }} dari {{ $period }} sampai bulan terpilih?&#10;&#10;Lebih dari 1 bulan akan menunggu persetujuan admin."
                                    wire:loading.attr="disabled"
                                    wire:target="payThrough({{ $record->member_id }})"
                                >Lunasi</button>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Baris lokasi rumah --}}
                <div class="loc-bar">
                    @if ($member->hasCompleteLocation())
                        <span class="loc-ok">📍 Lokasi lengkap</span>
                        @if ($member->googleMapsDirectionsUrl())
                            <a class="loc-link" href="{{ $member->googleMapsDirectionsUrl() }}" target="_blank" rel="noopener">Navigasi ⇢</a>
                        @endif
                        <button type="button" class="loc-btn" wire:click="confirmLocation({{ $member->id }})"
                            wire:loading.attr="disabled" wire:target="confirmLocation({{ $member->id }})">Masih sama</button>
                        <button type="button" class="loc-btn" wire:click="openVisit({{ $member->id }})">Perbarui</button>
                    @else
                        <span class="loc-warn">⚠️ Titik/foto rumah belum ada</span>
                        <button type="button" class="loc-btn" wire:click="openVisit({{ $member->id }})">Isi sekarang</button>
                    @endif
                </div>

                {{-- Panel kunjungan (GPS + foto + alamat) --}}
                @if ($activeMemberId === $member->id)
                    <div class="visit-panel">
                        <div class="visit-row">
                            <button type="button" class="btn-gps"
                                x-data
                                @click="
                                    if (! navigator.geolocation) { alert('Perangkat tidak mendukung GPS.'); return; }
                                    $el.textContent = 'Mengambil…';
                                    navigator.geolocation.getCurrentPosition(
                                        (p) => { $wire.set('lat', p.coords.latitude); $wire.set('lng', p.coords.longitude); $el.textContent = '📍 Ambil ulang titik'; },
                                        (e) => { alert('Gagal ambil lokasi: ' + e.message); $el.textContent = '📍 Ambil titik GPS'; },
                                        { enableHighAccuracy: true, timeout: 15000 }
                                    );
                                ">📍 Ambil titik GPS</button>
                            @if ($lat && $lng)
                                <span class="gps-ok">✓ {{ number_format($lat, 6) }}, {{ number_format($lng, 6) }}</span>
                            @endif
                        </div>
                        @error('lat') <div class="visit-err">{{ $message }}</div> @enderror

                        <label class="visit-label">Foto rumah</label>
                        <input type="file" accept="image/*" capture="environment" wire:model="photo">
                        <div wire:loading wire:target="photo" class="visit-hint">Mengunggah foto…</div>
                        @error('photo') <div class="visit-err">{{ $message }}</div> @enderror
                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="visit-preview" alt="Pratinjau foto rumah">
                        @elseif ($member->housePhotoUrl())
                            <img src="{{ $member->housePhotoUrl() }}" class="visit-preview" alt="Foto rumah tersimpan">
                        @endif

                        <label class="visit-label">Alamat (perbarui bila berubah)</label>
                        <textarea wire:model="houseAddress" rows="2" class="visit-input"></textarea>

                        <label class="visit-label">Hasil kunjungan</label>
                        <select wire:model="outcome" class="visit-input">
                            @foreach ($outcomeOptions as $opt)
                                <option value="{{ $opt->value }}">{{ $opt->getLabel() }}</option>
                            @endforeach
                        </select>

                        <label class="visit-label">Catatan / patokan (opsional)</label>
                        <input type="text" wire:model="note" class="visit-input" placeholder="mis. pagar hijau, sebelah warung">

                        <div class="visit-actions">
                            <button type="button" class="btn-save-visit" wire:click="saveVisit"
                                wire:loading.attr="disabled" wire:target="saveVisit,photo">Simpan lokasi</button>
                            <button type="button" class="btn-cancel-visit" wire:click="closeVisit">Batal</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="empty">Belum ada tagihan untuk periode ini. Hubungi admin untuk generate tagihan.</div>
        @endforelse
    </div>
</div>
