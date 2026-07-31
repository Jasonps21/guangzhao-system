<div>
    <div class="gate">
        <div class="gate__inner rise">
            {{-- Segel perkumpulan: penanda "ini aplikasi kita" sebelum apa pun dibaca. --}}
            <div class="seal">
                <picture>
                    <source srcset="{{ asset('images/logo-288.webp') }}" type="image/webp">
                    <img src="{{ asset('images/logo-192.png') }}"
                         alt="Lambang Perkumpulan Sosial Guang Zhao Makassar"
                         width="192" height="192" fetchpriority="high">
                </picture>
            </div>

            <p class="gate__org">{{ $organization->name }}</p>
            @if ($organization->name_hanzi)
                <p class="gate__hanzi" lang="zh">{{ $organization->name_hanzi }}</p>
            @endif
            <p class="gate__place">Makassar</p>

            <hr class="gate__rule">

            <div class="gate__card">
                <form class="gate__form" wire:submit="login">
                    <h1 class="gate__title">Selamat datang.</h1>
                    <p class="gate__sub">Masukkan nama pengguna dan kata sandi Anda.</p>

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

                    {{-- Kata sandi bisa dilihat sebentar sebelum dikirim: mengetik
                         di balik titik-titik adalah sumber gagal masuk paling sering
                         bagi pengguna lansia, apalagi di papan ketik HP. --}}
                    <div class="field" x-data="{ show: false }">
                        <label class="field__label" for="password">Kata Sandi</label>
                        <div class="field__wrap">
                            <input
                                id="password"
                                class="input"
                                type="password"
                                :type="show ? 'text' : 'password'"
                                wire:model="password"
                                autocomplete="current-password"
                                enterkeyhint="go"
                            >
                            <button
                                type="button"
                                class="reveal"
                                x-on:click="show = ! show"
                                x-bind:aria-pressed="show"
                                x-bind:aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                x-bind:title="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                aria-label="Tampilkan kata sandi"
                                title="Tampilkan kata sandi"
                            >
                                <svg class="ico" aria-hidden="true">
                                    <use href="#i-eye" x-bind:href="show ? '#i-eye-off' : '#i-eye'"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn gate__submit" wire:loading.attr="disabled" wire:target="login">
                        <span class="btn__label" wire:loading.remove wire:target="login">Masuk</span>
                        <span class="btn__label" wire:loading wire:target="login">Memeriksa…</span>
                        <span class="btn__ico">
                            <svg aria-hidden="true"><use href="#i-chevron"/></svg>
                        </span>
                    </button>
                </form>
            </div>

            <p class="gate__foot">Lupa kata sandi? Hubungi pengurus.</p>
        </div>
    </div>
</div>
