<div>
    <header class="appbar">
        <div class="appbar__inner">
            <div class="appbar__title">
                <span class="appbar__eyebrow">Kolektor</span>
                <h1 class="appbar__name">{{ Auth::user()->name }}</h1>
            </div>
            <button type="button" class="icon-btn" wire:click="logout" aria-label="Keluar">
                <svg class="ico" aria-hidden="true"><use href="#i-logout"/></svg>
            </button>
        </div>
    </header>

    <div class="shell">
        <div class="period">
            <div>
                <span class="period__label">Periode</span>
                <div class="period__value">{{ $period }}</div>
            </div>
        </div>

        <div class="list">
            @forelse ($groups as $group)
                <a class="shellcard group rise" href="{{ route('kolektor.billing', $group) }}" wire:navigate wire:key="group-{{ $group->id }}">
                    <div class="shellcard__core">
                        <div class="group__body">
                            <div class="group__text">
                                <h2 class="group__name">{{ $group->name }}</h2>
                                <p class="group__meta {{ $group->unpaid_count > 0 ? '' : 'group__meta--clear' }}">
                                    @if ($group->unpaid_count > 0)
                                        <svg class="ico ico--sm" aria-hidden="true"><use href="#i-users"/></svg>
                                        <span><strong>{{ $group->unpaid_count }}</strong> anggota belum bayar</span>
                                    @else
                                        <svg class="ico ico--sm" aria-hidden="true"><use href="#i-check"/></svg>
                                        <span>Semua sudah lunas</span>
                                    @endif
                                </p>
                            </div>
                            <span class="group__go">
                                <svg class="ico ico--sm" aria-hidden="true"><use href="#i-chevron"/></svg>
                            </span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="empty">
                    <div class="empty__ico">
                        <svg class="ico ico--lg" aria-hidden="true"><use href="#i-inbox"/></svg>
                    </div>
                    Anda belum ditugaskan ke kelompok mana pun.
                </div>
            @endforelse
        </div>
    </div>
</div>
