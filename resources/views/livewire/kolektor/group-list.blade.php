<div>
    <div class="topbar">
        <h1>Pilih Kelompok</h1>
        <button type="button" wire:click="logout">Keluar</button>
    </div>

    <div class="container">
        <p class="summary">Periode <strong>{{ $period }}</strong> · {{ Auth::user()->name }}</p>

        @forelse ($groups as $group)
            <a class="group-link" href="{{ route('kolektor.billing', $group) }}" wire:navigate>
                <div class="card">
                    <div>
                        <div class="name">{{ $group->name }}</div>
                        <div class="meta">
                            @if ($group->unpaid_count > 0)
                                {{ $group->unpaid_count }} anggota belum bayar
                            @else
                                Semua sudah lunas
                            @endif
                        </div>
                    </div>
                    <div style="font-size:1.5rem;color:#b45309;">›</div>
                </div>
            </a>
        @empty
            <div class="empty">Anda belum ditugaskan ke kelompok mana pun.</div>
        @endforelse
    </div>
</div>
