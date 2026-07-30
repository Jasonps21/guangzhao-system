<div>
    <div class="auth rise">
        <span class="auth__eyebrow">Mode Lapangan</span>
        <h1 class="auth__title">Penagihan<br>Iuran.</h1>
        <p class="auth__sub">Masuk dengan akun kolektor Anda untuk mulai menagih.</p>

        <form wire:submit="login">
            @error('email')
                <div class="alert">
                    <svg class="ico" aria-hidden="true"><use href="#i-alert"/></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            <div class="field">
                <label class="field__label" for="email">Email</label>
                <input id="email" class="input" type="email" wire:model="email" autocomplete="username" inputmode="email">
            </div>

            <div class="field">
                <label class="field__label" for="password">Kata Sandi</label>
                <input id="password" class="input" type="password" wire:model="password" autocomplete="current-password">
            </div>

            <button type="submit" class="btn auth__submit" wire:loading.attr="disabled" wire:target="login">
                <span class="btn__label">Masuk</span>
                <span class="btn__ico">
                    <svg aria-hidden="true"><use href="#i-chevron"/></svg>
                </span>
            </button>
        </form>
    </div>
</div>
