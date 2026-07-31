<div>
    <div class="auth rise">
        <span class="auth__eyebrow">Guang Zhao</span>
        <h1 class="auth__title">Selamat<br>datang.</h1>
        <p class="auth__sub">Masukkan nama pengguna dan kata sandi Anda. Sistem akan membuka halaman yang sesuai.</p>

        <form wire:submit="login">
            @error('username')
                <div class="alert">
                    <svg class="ico" aria-hidden="true"><use href="#i-alert"/></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            <div class="field">
                <label class="field__label" for="username">Nama Pengguna</label>
                <input
                    id="username"
                    class="input"
                    type="text"
                    wire:model="username"
                    autocomplete="username"
                    autocapitalize="none"
                    autocorrect="off"
                    spellcheck="false"
                    enterkeyhint="next"
                    autofocus
                >
            </div>

            <div class="field">
                <label class="field__label" for="password">Kata Sandi</label>
                <input
                    id="password"
                    class="input"
                    type="password"
                    wire:model="password"
                    autocomplete="current-password"
                    enterkeyhint="go"
                >
            </div>

            <button type="submit" class="btn auth__submit" wire:loading.attr="disabled" wire:target="login">
                <span class="btn__label" wire:loading.remove wire:target="login">Masuk</span>
                <span class="btn__label" wire:loading wire:target="login">Memeriksa…</span>
                <span class="btn__ico">
                    <svg aria-hidden="true"><use href="#i-chevron"/></svg>
                </span>
            </button>
        </form>
    </div>
</div>
