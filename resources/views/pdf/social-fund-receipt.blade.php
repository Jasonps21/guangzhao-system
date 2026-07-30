{{--
    Nota TANDA TERIMA dana sosial — mengikuti tata letak nota cetak yang sudah
    dipakai perkumpulan (lihat contoh-nota/tanda terima uang bantuan.jpeg):
    kalimat "Yang bertanda tangan dibawah ini", Nama & Alamat berkolon tanpa
    garis, lalu Sejumlah / Terbilang / Untuk keperluan dengan titik-titik isian,
    dan tiga kolom tanda tangan dengan "Makassar," di atas kolom penerima.

    Hanya satu kalimat yang berubah menurut arah dananya:
    - KELUAR → perkumpulan memberi; penandatangan = penerima bantuan.
    - MASUK  → perkumpulan menerima; penandatangan = penyumbang.

    @var \App\Models\SocialFundRecord $record
    @var \App\Models\OrganizationSetting $organization
    @var string|null $logo
--}}
@php
    $isIncoming = $record->isIncoming();
    $isGoods = $record->isInGoods();
    $subject = $isGoods
        ? ($isIncoming ? 'sumbangan barang' : 'bantuan barang')
        : 'dana sosial';

    /*
     * Nama perkumpulan disimpan huruf kapital semua untuk kop surat, tetapi di
     * tengah kalimat nota aslinya ditulis biasa ("perkumpulan sosial Guang
     * Zhao Makassar") — dikapitalkan seperlunya supaya kalimatnya tetap satu baris.
     */
    $organizationName = \Illuminate\Support\Str::title($organization->name);

    $sentence = $isIncoming
        ? 'Telah menyerahkan '.$subject.' kepada '.$organizationName.' Makassar,'
        : 'Telah terima '.$subject.' dari '.$organizationName.' Makassar,';

    /*
     * Blok tanda tangan dipatok ke dasar halaman, jadi baris isian yang
     * bertambah (sumbangan barang punya baris "Berupa" ekstra, nilai panjang
     * bisa melipat) berpotensi menabraknya. Baris dihitung dulu — batas karakter
     * per baris mengikuti sisa lebar tiap baris setelah labelnya — lalu jarak
     * antarbarisnya dirapatkan seperlunya.
     */
    $wrapped = fn (?string $text, int $perLine): int => max(0, (int) ceil(mb_strlen((string) $text) / $perLine) - 1);

    $extraLines = ($isGoods ? 1 : 0)
        + $wrapped($sentence, 85)
        + $wrapped($record->party_address, 78)
        + $wrapped($record->item_description, 74)
        + $wrapped($record->purpose, 74);

    $dense = $extraLines >= 1;
    $bodySize = $extraLines >= 2 ? '12pt' : '13pt';
    $blockGap = $dense ? '1.4mm' : '2.5mm';
    $rowGap = $dense ? '0.6mm' : '1.5mm';
    $cellGap = $dense ? '0.3mm' : '0.6mm';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.note-styles')

        /* Ukuran & kerapatan dihitung dari banyaknya baris isian (lihat @php di atas). */
        .note { font-size: {{ $bodySize }}; }

        /* Kalimat pengantar & penutup menjorok ke dalam seperti nota aslinya. */
        .indent { padding-left: 13mm; margin: {{ $blockGap }} 0; }

        /* Tiap baris isian berdiri sebagai tabel sendiri supaya lebar kolom
           labelnya mengikuti panjang labelnya masing-masing — kalau disatukan,
           label terpanjang mendorong semua nilai jauh ke kanan. */
        table.row { width: 100%; border-collapse: collapse; margin-top: {{ $rowGap }}; }
        table.row td { vertical-align: bottom; padding: {{ $cellGap }} 0; }
        table.row .label { width: 1%; white-space: nowrap; }
        /* Kolon ikut di dalam sel label (bukan sel sendiri) supaya duduk pada
           baris yang sama persis dengan teks labelnya. */
        table.row .label .pad { display: inline-block; width: 20mm; }
        table.row .plain { padding-left: 3mm; }
        /* Titik-titik isian: nilainya dicetak tepat di atas garis. */
        table.row .dots { border-bottom: 1px dotted #000; padding-left: 2mm; }
        table.row .close { width: 1%; white-space: nowrap; padding-left: 2mm; }
        .words { font-style: italic; }

        /* Tiga kolom tanda tangan; kolom kanan memuat "Makassar," di atasnya. */
        table.sign td { width: 33.33%; text-align: center; vertical-align: bottom; }
        .sign-name { min-width: 30mm; }
    </style>
</head>
<body>
<div class="note">
    @include('pdf.partials.letterhead')

    <table class="title-row">
        <tr>
            <td class="receipt-no">&nbsp;</td>
            <td class="title">TANDA TERIMA</td>
            <td class="receipt-no">No. {{ $record->receipt_number }}</td>
        </tr>
    </table>

    <div class="indent">Yang bertanda tangan dibawah ini:</div>

    {{-- Nama & Alamat sengaja satu tabel agar kolonnya sejajar seperti nota asli. --}}
    <table class="row">
        <tr>
            <td class="label"><span class="pad">Nama</span>:</td>
            <td class="plain">{{ $record->party_name }}</td>
        </tr>
        <tr>
            <td class="label"><span class="pad">Alamat</span>:</td>
            <td class="plain">{{ $record->party_address }}</td>
        </tr>
    </table>

    <div class="indent">{{ $sentence }}</div>

    @if ($isGoods)
        <table class="row">
            <tr>
                <td class="label">Berupa</td>
                <td class="dots">{{ $record->item_description }}</td>
            </tr>
        </table>
    @endif

    @if (! $isGoods || $record->amount !== null)
        <table class="row">
            <tr>
                <td class="label">{{ $isGoods ? 'Senilai Rp' : 'Sejumlah Rp' }}</td>
                <td class="dots" style="width: 60%;">{{ number_format($record->amount ?? 0, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </table>
        <table class="row">
            <tr>
                <td class="label">Terbilang: (</td>
                <td class="plain words">{{ $record->amountInWords() }}</td>
                <td class="close">),</td>
            </tr>
        </table>
    @endif

    <table class="row">
        <tr>
            <td class="label">Untuk keperluan</td>
            <td class="dots">{{ $record->purpose }}</td>
        </tr>
    </table>

    <div class="sign-box">
        <table class="sign">
            <tr>
                <td>Menyetujui,</td>
                <td>Mengetahui,</td>
                <td>
                    <div>Makassar, {{ $record->transacted_at->translatedFormat('d F Y') }}</div>
                    <div>Yang Menerima,</div>
                </td>
            </tr>
            <tr>
                <td class="sign-gap"></td>
                <td class="sign-gap"></td>
                <td class="sign-gap"></td>
            </tr>
            <tr>
                <td>( <span class="sign-name">{{ $organization->chairman_name }}</span> )</td>
                <td>( <span class="sign-name">{{ $organization->treasurer_name }}</span> )</td>
                {{-- Dana masuk diterima pengurus; dana keluar diterima pihak
                     yang namanya tercetak di atas. --}}
                <td>( <span class="sign-name">{{ $isIncoming ? '' : $record->party_name }}</span> )</td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
