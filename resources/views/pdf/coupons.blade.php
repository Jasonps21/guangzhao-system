<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        /* CJK font extracted from WenQuanYi Zen Hei so hanzi renders in DomPDF.
           Registered for both normal and bold weight (same file) — otherwise
           bold hanzi falls back to DejaVu and renders as boxes. */
        @font-face {
            font-family: 'WenQuanYi';
            font-style: normal;
            font-weight: normal;
            src: url("{{ storage_path('fonts/wqy-zenhei.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'WenQuanYi';
            font-style: normal;
            font-weight: bold;
            src: url("{{ storage_path('fonts/wqy-zenhei.ttf') }}") format('truetype');
        }

        /* Small top + side margins so the grid sits slightly lower (using the
           empty space at the bottom) and has breathing room left and right. */
        @page { margin: 9mm 8mm 6mm 8mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            color: #000;
            font-size: 9pt;
        }
        .sheet { width: 100%; }

        /* Joined bordered boxes (shared borders, no gaps) forming one clean grid
           of 2 columns × 5 rows = 10 coupons per sheet. */
        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        td.coupon {
            width: 50%;
            border: 1px solid #111;
            padding: 0;
            vertical-align: top;
        }
        /* Fixed height sized to the real content so all 10 coupons (2×5) fit on
           a single F4 sheet — verified against the DomPDF engine, not just a
           visual guess. overflow:hidden is a last-resort guard. */
        .inner {
            position: relative;
            height: 58mm;
            overflow: hidden;
            padding: 2mm 3.5mm;
        }
        .empty-cell { border: 1px solid #111; }

        /* Faint centered logo watermark behind each coupon's content. Low
           opacity keeps the member data fully legible on top of it. */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 40mm;
            height: 40mm;
            margin-top: -20mm;
            margin-left: -20mm;
            opacity: 0.08;
            z-index: 0;
        }

        /* Header — blue, centered. Org title uses a serif bold-italic face to
           echo the decorative Word heading; contact line is underlined. */
        .head { text-align: center; line-height: 1.05; color: #1d1de0; }
        .head .org {
            font-family: 'Times New Roman', serif;
            font-size: 10pt;
            font-weight: bold;
            font-style: italic;
            text-decoration: underline;
            letter-spacing: .2pt;
        }
        .head .hanzi {
            font-family: 'WenQuanYi', 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            font-weight: bold;
            margin: 0.2mm 0;
        }
        .head .contact {
            font-size: 6.5pt;
            font-weight: bold;
            text-decoration: underline;
            white-space: nowrap;
        }

        /* Member data rows — bold, labels uppercase with the colon aligned. */
        table.info { width: 100%; border-collapse: collapse; font-weight: bold; margin-top: 0.8mm; }
        table.info td { vertical-align: top; padding: 0.2mm 0; }
        table.info .label { width: 17mm; }
        table.info .sep { width: 2.5mm; }
        .hz { font-family: 'WenQuanYi', 'DejaVu Sans', sans-serif; }
        .note { font-weight: bold; font-style: italic; }
        .amount { font-weight: bold; }
        .month { font-weight: bold; color: #d00000; }

        .reg { font-weight: bold; margin-top: 0.8mm; }

        /* Klompok / no. urut pinned to the lower-left. */
        table.foot { border-collapse: collapse; font-weight: bold; margin-top: 0.6mm; }
        table.foot td { vertical-align: top; padding: 0.2mm 0; }
        table.foot .label { width: 17mm; }
        table.foot .sep { width: 2.5mm; }

        /* Ketua / Bendahara signatures on the right, as in the Word template. */
        .sign-box { position: absolute; right: 3mm; bottom: 3mm; }
        table.sign { border-collapse: collapse; font-size: 7.5pt; font-weight: bold; }
        table.sign td { width: 25mm; text-align: center; vertical-align: top; padding: 0 1mm; white-space: nowrap; }
        .sign .gap { height: 7mm; }
    </style>
</head>
<body>
@php
    $chunks = $records->chunk(10);
@endphp

@forelse ($chunks as $page)
    <div class="sheet">
        <table class="grid">
            @php $rows = $page->chunk(2); @endphp
            @foreach ($rows as $pair)
                <tr>
                    @foreach ($pair as $record)
                        @php $member = $record->member; @endphp
                        <td class="coupon">
                            <div class="inner">
                                @if (! empty($logo))
                                    <img class="watermark" src="{{ $logo }}" alt="">
                                @endif
                                <div class="head">
                                    <div class="org">{{ $organization->name }}</div>
                                    <div class="hanzi">{{ $organization->name_hanzi }}</div>
                                    @if ($organization->contact_line)
                                        <div class="contact">{{ $organization->contact_line }}</div>
                                    @endif
                                </div>

                                <table class="info">
                                    <tr>
                                        <td class="label">NAMA</td>
                                        <td class="sep">:</td>
                                        <td>@if ($member->name_hanzi)<span class="hz">{{ $member->name_hanzi }}</span> @endif{{ $member->name_pinyin ?: $member->name_indonesian }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">ALAMAT</td>
                                        <td class="sep">:</td>
                                        <td>{{ $member->address ? \Illuminate\Support\Str::limit($member->address, 60) : '-' }}@if ($member->bill_at_home) <span class="note">(tagih dirumah)</span>@endif</td>
                                    </tr>
                                    <tr>
                                        <td class="label">YURAN</td>
                                        <td class="sep">:</td>
                                        <td><span class="amount">Rp.{{ number_format($record->amount_due, 0, ',', '.') }},-</span></td>
                                    </tr>
                                    <tr>
                                        <td class="label">BULAN</td>
                                        <td class="sep">:</td>
                                        <td><span class="month">{{ $period }}</span></td>
                                    </tr>
                                </table>

                                <div class="reg">REG NO.</div>

                                <table class="foot">
                                    <tr>
                                        <td class="label">Klompok</td>
                                        <td class="sep">:</td>
                                        <td>{{ $member->group?->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">no. urut</td>
                                        <td class="sep">:</td>
                                        <td>{{ $member->no_urut ?? '-' }}</td>
                                    </tr>
                                </table>

                                <div class="sign-box">
                                    <table class="sign">
                                        <tr>
                                            <td>KETUA</td>
                                            <td>BENDAHARA</td>
                                        </tr>
                                        <tr>
                                            <td class="gap"></td>
                                            <td class="gap"></td>
                                        </tr>
                                        <tr>
                                            <td>@if ($organization->chairman_name)( {{ $organization->chairman_name }} )@endif</td>
                                            <td>@if ($organization->treasurer_name)( {{ $organization->treasurer_name }} )@endif</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    @endforeach
                    @if ($pair->count() === 1)
                        <td class="coupon empty-cell"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>
    @if (! $loop->last)
        <div style="page-break-after: always;"></div>
    @endif
@empty
    <p style="padding: 20mm; text-align:center;">Tidak ada tagihan untuk periode {{ $period }}.</p>
@endforelse
</body>
</html>
