<div>
    <div class="topbar">
        <h1>Penagihan Iuran</h1>
    </div>
    <form class="login-box" wire:submit="login">
        <h2>Masuk Kolektor</h2>

        @error('email')
            <div class="alert">{{ $message }}</div>
        @enderror

        <label for="email">Email</label>
        <input id="email" type="email" wire:model="email" autocomplete="username" inputmode="email">

        <label for="password">Kata Sandi</label>
        <input id="password" type="password" wire:model="password" autocomplete="current-password">

        <button type="submit">Masuk</button>
    </form>
</div>
