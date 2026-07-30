{{--
    Nota uang duka. Satu berkas melayani dua varian yang isinya nyaris sama,
    persis seperti pasangan kertas yang selama ini dicetak:

    - 'terima'    → TANDA TERIMA, ditandatangani keluarga penerima (家属签收).
    - 'pemberian' → TANDA PEMBERIAN, ditandatangani pengurus (福利部).

    @var \App\Models\CondolencePayment $payment
    @var \App\Models\OrganizationSetting $organization
    @var string|null $logo
    @var string $variant 'terima' | 'pemberian'
--}}
@php
    $isHandover = $variant === 'pemberian';
    $givenAt = $payment->given_at;

    /*
     * Nota ini punya blok tanda tangan yang dipatok ke dasar halaman, jadi isi
     * yang membengkak (nominal besar → terbilang panjang, atau nama penerima
     * panjang) bisa turun sampai menabraknya. Diperkirakan dulu berapa baris
     * ekstra yang akan terpakai — sekitar 78 karakter per baris untuk kalimat
     * pembuka dan 38 karakter untuk kolom nama — lalu ukuran huruf badan nota
     * diturunkan seperlunya. Nota normal tetap tercetak besar.
     */
    $statement = 'TELAH TERIMA UANG DUKA SEBESAR: Rp '.number_format($payment->amount, 0, ',', '.')
        .' ('.$payment->amountInWords().')';
    $extraLines = (int) ceil(mb_strlen($statement) / 78) - 1
        + (int) ceil(max(1, mb_strlen((string) $payment->recipient_name)) / 38) - 1;

    $bodySize = match (true) {
        $extraLines >= 2 => '11pt',
        $extraLines === 1 => '12pt',
        default => '13pt',
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.note-styles')

        /* Ukuran badan nota dihitung dari panjang isinya (lihat blok @php di
           atas); seluruh elemen di bawah memakai em agar ikut menyesuaikan. */
        .note { font-size: {{ $bodySize }}; }

        /* Kalimat pembuka nota, dibesarkan sedikit karena inilah inti notanya. */
        .statement { font-size: 1.04em; margin-top: 2mm; }
        .amount { font-weight: bold; }

        /* Blok mandarin: 兹收到 di tengah, lalu kalimat panjangnya di bawah. */
        .cn { font-family: 'WenQuanYi', 'DejaVu Sans', sans-serif; }
        .cn-head { text-align: center; font-size: 1.08em; text-decoration: underline; margin: 3mm 0 2mm; }
        .cn-body { font-size: 1em; line-height: 1.5; }
        /* Nama almarhum ditulis di atas garis isian di tengah kalimat. */
        .cn-blank {
            display: inline-block;
            min-width: 32mm;
            text-align: center;
            border-bottom: 1px solid #000;
        }
        .date-row { font-size: 1em; margin-bottom: 2mm; }
        /* Ruang tanda tangan uang duka dipendekkan: lembar terima punya dua
           baris peran penerima sehingga blok bawahnya sudah tinggi. */
        .sign-gap { height: 11mm; }
    </style>
</head>
<body>
<div class="note">
    @include('pdf.partials.letterhead')

    <table class="title-row">
        <tr>
            <td class="receipt-no">&nbsp;</td>
            <td class="title">{{ $isHandover ? 'TANDA PEMBERIAN' : 'TANDA TERIMA' }}</td>
            <td class="receipt-no">No. {{ $payment->receipt_number }}</td>
        </tr>
    </table>

    <div class="statement">
        {{ $isHandover ? 'TELAH MEMBERI' : 'TELAH TERIMA' }} UANG DUKA SEBESAR:
        <span class="amount">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
        ({{ \Illuminate\Support\Str::upper($payment->amountInWords()) }})
    </div>
    <div class="statement">DARI {{ \Illuminate\Support\Str::upper($organization->name) }} MAKASSAR, UNTUK:</div>

    <table class="fields">
        <tr>
            <td class="label">TN/ NY</td>
            <td class="value">{{ $payment->recipient_name }}</td>
            <td class="label" style="padding-left: 8mm;">ANGGOTA NO.</td>
            <td class="value" style="width: 38mm;">{{ $payment->recipient_member_number }}</td>
        </tr>
    </table>

    <div class="cn cn-head">兹收到</div>
    <div class="cn cn-body">
        {{ $organization->name_hanzi }}{{ $isHandover ? '交给' : '交來' }}<span
            class="cn-blank">{{ $payment->deceased_name_hanzi }}</span>会友逝世赙仪（{{ $payment->amountInChinese() }}）此据,
    </div>

    {{-- Tanggal menempel di atas nama penanda tangan kanan (bukan sebagai baris
         tersendiri) supaya blok bawah tetap pendek: dengan begitu nama atau
         terbilang yang sampai memakan dua baris ekstra pun tidak menabraknya. --}}
    <div class="sign-box">
        <table class="sign">
            <tr>
                <td style="vertical-align: bottom;">PENGANTAR,</td>
                <td style="text-align: right;">
                    <div class="cn date-row">公元 {{ $givenAt->year }} 年 {{ $givenAt->month }} 月 {{ $givenAt->day }} 日</div>
                    <div class="cn">{{ $isHandover ? '福利部,' : '家属签收,' }}</div>
                    @unless ($isHandover)
                        <div>PENERIMA,</div>
                    @endunless
                </td>
            </tr>
            <tr>
                <td class="sign-gap"></td>
                <td class="sign-gap"></td>
            </tr>
            <tr>
                <td>( <span class="sign-name">{{ $payment->escort_name }}</span> )</td>
                <td style="text-align: right;">( <span class="sign-name"></span> )</td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
