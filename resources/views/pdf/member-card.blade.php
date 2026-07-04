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

        @page { margin: 0; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            color: #000;
            font-size: 8pt;
        }
        /* No explicit width/height: the card auto-fills the 86×54mm page and
           padding is applied inside it. DomPDF does not honour box-sizing for
           width/height, so setting them here would add the padding on top and
           spill onto a second page. */
        .card { padding: 1.5mm 3.5mm; }

        /* Header: logo pinned left, centered blue org block, then rule. */
        .head { line-height: 1.05; color: #1d3a8a; }
        .head-table { width: 100%; border-collapse: collapse; }
        .head-logo { width: 12mm; vertical-align: middle; }
        .head-logo img { width: 11mm; height: 11mm; }
        .head-text { vertical-align: middle; text-align: center; }
        .head .org { font-size: 7.5pt; font-weight: bold; letter-spacing: .2pt; }
        .head .hanzi { font-family: 'WenQuanYi', 'DejaVu Sans', sans-serif; font-size: 8pt; font-weight: bold; margin: 0.2mm 0; }
        .head .contact { font-size: 6pt; font-weight: bold; white-space: nowrap; }
        .rule { border-top: 2.5px solid #1d3a8a; margin: 0.8mm 0; }
        .title { text-align: center; font-size: 7pt; font-weight: bold; letter-spacing: 2pt; color: #1d3a8a; margin-bottom: 1.5mm; }

        /* Body: data on the left, photo pinned to the right. */
        .body { display: table; width: 100%; }
        .info { display: table-cell; vertical-align: top; }
        .photo { display: table-cell; width: 20mm; vertical-align: top; text-align: right; }
        .photo img { width: 18mm; height: 23mm; object-fit: cover; border: 1px solid #b9b9b9; }
        .photo .placeholder {
            width: 18mm; height: 23mm; border: 1px solid #b9b9b9;
            display: inline-block; text-align: center; font-size: 6.5pt;
            color: #999;
        }

        table.data { width: 100%; border-collapse: collapse; }
        table.data td { vertical-align: top; padding: 0.5mm 0; font-size: 7pt; line-height: 1.05; }
        table.data .label { width: 17mm; color: #555; white-space: nowrap; }
        table.data .sep { width: 2mm; color: #555; }
        table.data .value { font-weight: bold; }
        .hz { font-family: 'WenQuanYi', 'DejaVu Sans', sans-serif; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <table class="head-table">
                <tr>
                    @if (! empty($logo))
                        <td class="head-logo"><img src="{{ $logo }}" alt=""></td>
                    @endif
                    <td class="head-text">
                        <div class="org">{{ $organization->name }}</div>
                        <div class="hanzi">{{ $organization->name_hanzi }}</div>
                        @if ($organization->contact_line)
                            <div class="contact">{{ $organization->contact_line }}</div>
                        @endif
                    </td>
                    @if (! empty($logo))
                        <td class="head-logo"></td>
                    @endif
                </tr>
            </table>
        </div>
        <div class="rule"></div>
        <div class="title">KARTU ANGGOTA</div>

        <div class="body">
            <div class="info">
                <table class="data">
                    <tr>
                        <td class="label">Nama</td>
                        <td class="sep">:</td>
                        <td class="value">@if ($member->name_hanzi)<span class="hz">{{ \Illuminate\Support\Str::limit($member->name_hanzi, 12) }}</span> @endif{{ \Illuminate\Support\Str::limit($member->displayName(), 38) }}</td>
                    </tr>
                    <tr>
                        <td class="label">No. Anggota</td>
                        <td class="sep">:</td>
                        <td class="value">{{ $member->member_number }}</td>
                    </tr>
                    <tr>
                        <td class="label">Alamat</td>
                        <td class="sep">:</td>
                        <td class="value">{{ $member->address ? \Illuminate\Support\Str::limit($member->address, 48) : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Kelompok</td>
                        <td class="sep">:</td>
                        <td class="value">{{ $member->group?->name ?? '-' }}</td>
                    </tr>
                </table>
            </div>
            <div class="photo">
                @if ($member->photo_path && file_exists(storage_path('app/private/'.$member->photo_path)))
                    <img src="{{ storage_path('app/private/'.$member->photo_path) }}" alt="">
                @else
                    <div class="placeholder">Tanpa<br>Foto</div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
