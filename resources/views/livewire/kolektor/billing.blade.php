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
            <div class="card" wire:key="dues-{{ $record->id }}">
                <div>
                    <div class="name">{{ $record->member->displayName() }}</div>
                    <div class="meta">
                        {{ $record->member->member_number }} ·
                        Rp {{ number_format($record->amount_due, 0, ',', '.') }}
                    </div>
                </div>

                @if ($record->status === \App\Enums\DuesStatus::Lunas)
                    <span class="badge-lunas">✓ Lunas</span>
                @elseif ($record->status === \App\Enums\DuesStatus::MenungguPersetujuan)
                    <span class="badge-pending">⏳ Menunggu Persetujuan</span>
                @else
                    <div class="pay-actions">
                        <button
                            type="button"
                            class="btn-lunas"
                            wire:click="markPaid({{ $record->id }})"
                            wire:confirm="Tandai LUNAS {{ $period }}?&#10;&#10;{{ $record->member->displayName() }}&#10;Rp {{ number_format($record->amount_due, 0, ',', '.') }}"
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
                                wire:confirm="Catat pembayaran {{ $record->member->displayName() }} dari {{ $period }} sampai bulan terpilih?&#10;&#10;Lebih dari 1 bulan akan menunggu persetujuan admin."
                                wire:loading.attr="disabled"
                                wire:target="payThrough({{ $record->member_id }})"
                            >Lunasi</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="empty">Belum ada tagihan untuk periode ini. Hubungi admin untuk generate tagihan.</div>
        @endforelse
    </div>
</div>
