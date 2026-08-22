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

    /* ---- Perbaikan tabrakan stackedOnMobile x visibleFrom ----
       Filament memaksa tiap sel header tampil kembali mulai 640px lewat
       `.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell`, dan
       aturan itu lebih spesifik daripada `.fi-ta-header-cell.lg:fi-visible`
       yang menyembunyikan kolom sekunder. Penjaga serupa sudah ditulis Filament
       untuk `tbody`, tetapi tidak untuk `thead` — akibatnya di rentang
       640-1024px header menampilkan 7 kolom sementara isinya hanya 5, sehingga
       setiap kolom bergeser dan datanya terbaca di bawah judul yang salah.

       Di bawah ini penjaga yang hilang itu dilengkapi, mengikuti bentuk aturan
       `tbody` milik Filament sendiri. */
    .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.lg\:fi-visible {
        display: none;
    }

    @media (min-width: 64rem) {
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.lg\:fi-visible {
            display: table-cell;
        }
    }

    /* ---- Tabel bertumpuk di layar HP ----
       stackedOnMobile() menaruh label kolom di atas nilainya, sehingga satu
       baris memakan dua kali lipat tinggi dan hanya muat satu anggota per
       layar. Di bawah 640px keduanya didudukkan berdampingan, jadi kartunya
       separuh lebih pendek tanpa ada data yang disembunyikan.

       Yang diatur hanya label dan isinya, bukan `display` selnya: sel itulah
       yang dipakai Filament untuk menyembunyikan kolom sekunder lewat
       `visibleFrom()`, dan menimpanya akan memunculkan kembali kolom yang
       justru sedang disembunyikan. */
    @media (max-width: 39.9375rem) {
        .fi-ta-table-stacked-on-mobile .fi-ta-cell-label,
        .fi-ta-table-stacked-on-mobile .fi-ta-cell-content {
            display: inline-block;
            vertical-align: top;
        }

        /* Padding atas & tinggi baris bawaan label dibuat sama dengan isinya,
           supaya baris pertama keduanya sejajar. */
        .fi-ta-table-stacked-on-mobile .fi-ta-cell-label {
            width: 38%;
            margin-block: 0;
            padding-block: 0;
            padding-inline-end: 0.75rem;
            line-height: 1.25rem;
        }

        .fi-ta-table-stacked-on-mobile .fi-ta-cell-content {
            width: 62%;
        }
    }

    /* ---- Jendela detail Log Aktivitas ----
       Ditulis sebagai CSS biasa, bukan kelas utilitas Tailwind: panel ini tidak
       punya build tema sendiri, jadi utilitas yang tidak dipakai Filament tidak
       ikut terkompilasi dan tidak akan berpengaruh. */
    .gz-log {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        font-size: 0.875rem;
    }

    .gz-log-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.625rem 1.5rem;
        margin: 0;
    }

    @media (min-width: 40rem) {
        .gz-log-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .gz-log-item dt {
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: rgb(113 113 122);
    }

    .gz-log-item dd {
        margin: 0.125rem 0 0;
        color: rgb(24 24 27);
        overflow-wrap: anywhere;
    }

    .gz-log-section h3 {
        margin: 0 0 0.5rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(63 63 70);
    }

    .gz-log-scroll {
        overflow-x: auto;
    }

    .gz-log-table {
        width: 100%;
        border-collapse: collapse;
        text-align: start;
    }

    .gz-log-table th {
        padding: 0 0.75rem 0.375rem 0;
        font-size: 0.75rem;
        font-weight: 500;
        text-align: start;
        white-space: nowrap;
        color: rgb(113 113 122);
    }

    .gz-log-table td {
        padding: 0.375rem 0.75rem 0.375rem 0;
        border-top: 1px solid rgb(228 228 231);
        vertical-align: top;
        overflow-wrap: anywhere;
    }

    .gz-log-was {
        color: rgb(113 113 122);
        text-decoration: line-through;
    }

    .dark .gz-log-item dd {
        color: rgb(228 228 231);
    }

    .dark .gz-log-section h3 {
        color: rgb(212 212 216);
    }

    .dark .gz-log-item dt,
    .dark .gz-log-table th,
    .dark .gz-log-was {
        color: rgb(161 161 170);
    }

    .dark .gz-log-table td {
        border-top-color: rgb(63 63 70);
    }
</style>
