{{--
    Nota TANDA TERIMA pembayaran iuran beberapa bulan sekaligus (bayar langsung
    di kantor), mengikuti bentuk nota bernomor yang selama ini dipakai.

    @var \App\Models\DuesReceipt $receipt
    @var \App\Models\OrganizationSetting $organization
    @var string|null $logo
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.note-styles')

        /* Nomor nota dicetak merah seperti nomor seri pada nota lama. */
        .receipt-no.serial { color: #c00000; font-weight: bold; font-size: 13pt; letter-spacing: 1pt; }
        .intro { margin-top: 3mm; }
        .amount-words { font-style: italic; }
        .sign-place { text-align: right; }
    </style>
</head>
<body>
<div class="note">
    @include('pdf.partials.letterhead')

    <table class="title-row">
        <tr>
            <td class="receipt-no">&nbsp;</td>
            <td class="title">TANDA TERIMA</td>
            <td class="receipt-no serial">{{ $receipt->receipt_number }}</td>
        </tr>
    </table>

    <div class="intro">TELAH TERIMA DARI ANGGOTA {{ \Illuminate\Support\Str::upper($organization->name) }},</div>

    {{-- Dipecah jadi beberapa tabel: kalau disatukan, label terpanjang
         ("PEMBAYARAN IURAN DARI BULAN") ikut melebarkan kolom label semua baris
         sehingga nama & alamat terdorong jauh ke kanan. --}}
    <table class="fields">
        <tr>
            <td class="label" style="width: 34mm; padding-left: 6mm;">BERNAMA</td>
            <td class="value">{{ $receipt->member->displayName() }}</td>
        </tr>
        <tr>
            <td class="label" style="width: 34mm; padding-left: 6mm;">ALAMAT</td>
            <td class="value">{{ $receipt->member->address }}</td>
        </tr>
    </table>

    <table class="fields">
        <tr>
            <td class="label">PEMBAYARAN IURAN DARI BULAN</td>
            <td class="value">{{ $receipt->fromMonthLabel() }}</td>
            <td class="label" style="padding-left: 4mm;">S/d</td>
            <td class="value">{{ $receipt->toMonthLabel() }}</td>
            <td class="tail">, {{ $receipt->period_year }}</td>
        </tr>
    </table>

    <table class="fields">
        <tr>
            <td class="label" style="width: 34mm;">SEBANYAK Rp.</td>
            <td class="value">{{ number_format($receipt->total_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label" style="width: 34mm;">TERBILANG</td>
            <td class="value amount-words">{{ $receipt->totalInWords() }}</td>
        </tr>
    </table>

    <div class="sign-box">
        <table class="sign">
            <tr>
                <td>&nbsp;</td>
                <td class="sign-place">
                    MAKASSAR, {{ $receipt->issued_at->translatedFormat('d F Y') }}
                    <div>YANG MENERIMA,</div>
                </td>
            </tr>
            <tr>
                <td class="sign-gap"></td>
                <td class="sign-gap"></td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td class="sign-place">( <span class="sign-name">{{ $receipt->receiverName() }}</span> )</td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
