{{--
    Gaya tambahan panel admin. Disisipkan langsung ke <head> lewat render hook
    supaya tidak perlu proses build Tailwind terpisah untuk perubahan kecil.
--}}
<style>
    /*
     * Kolom foto anggota: tampilkan siluet orang sebagai gambar bawaan selama
     * fotonya belum ada, bukan sekadar tulisan "seret & jatuhkan". FilePond
     * menyembunyikan lapisan ini sendiri (opacity 0) begitu ada berkas terunggah,
     * jadi siluetnya otomatis berganti dengan foto aslinya.
     */
    .gz-avatar-upload .filepond--drop-label {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23a1a1aa'%3E%3Cpath d='M12 12.5a4.75 4.75 0 1 0 0-9.5 4.75 4.75 0 0 0 0 9.5Z'/%3E%3Cpath d='M12 14.25c-4.28 0-7.75 2.3-7.75 5.13 0 .34.28.62.62.62h14.26c.34 0 .62-.28.62-.62 0-2.83-3.47-5.13-7.75-5.13Z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: center 30%;
        background-size: 46%;
        border-radius: 9999px;
    }

    /* Kalimat panjang bawaan diganti label pendek yang duduk di bawah siluet.
       Elemen <label> tetap utuh agar kliknya masih membuka pemilih berkas. */
    .gz-avatar-upload .filepond--drop-label label {
        display: block;
        align-self: flex-end;
        width: 100%;
        padding-bottom: 0.9rem;
        font-size: 0;
        line-height: 1;
        text-align: center;
        cursor: pointer;
    }

    .gz-avatar-upload .filepond--drop-label label::after {
        content: 'Pilih Foto';
        font-size: 0.7rem;
        font-weight: 500;
        color: rgb(113 113 122);
    }

    .dark .gz-avatar-upload .filepond--drop-label label::after {
        color: rgb(161 161 170);
    }

    /* ---- Pemilih titik rumah di peta ---- */
    .gz-map-search {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .gz-map-input {
        flex: 1;
        border: 1px solid rgb(212 212 216);
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
    }

    .gz-map-btn {
        border-radius: 0.5rem;
        padding: 0.5rem 1.1rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #fff;
        background: rgb(217 119 6);
    }

    .gz-map-results:not(:empty) {
        margin-bottom: 0.5rem;
        border: 1px solid rgb(228 228 231);
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .gz-map-result {
        display: block;
        width: 100%;
        padding: 0.5rem 0.75rem;
        text-align: left;
        font-size: 0.8125rem;
        border-bottom: 1px solid rgb(244 244 245);
    }

    .gz-map-result:hover {
        background: rgb(254 243 199);
    }

    .gz-map-note {
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
        color: rgb(113 113 122);
    }

    .gz-map-canvas {
        height: 340px;
        border-radius: 0.5rem;
        z-index: 0;
    }

    .gz-map-hint {
        margin-top: 0.5rem;
        font-size: 0.75rem;
        color: rgb(113 113 122);
    }

    .dark .gz-map-input {
        background: rgb(39 39 42);
        border-color: rgb(63 63 70);
        color: rgb(228 228 231);
    }
</style>
