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

        @page { margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            color: #000;
            font-size: 9pt;
        }
        .hanzi { font-family: 'WenQuanYi', 'DejaVu Sans', sans-serif; }

        .head { line-height: 1.2; margin-bottom: 2mm; }
        .head-table { width: 100%; border-collapse: collapse; }
        .head-logo { width: 22mm; vertical-align: middle; }
        .head-logo img { width: 20mm; height: 20mm; }
        .head-text { vertical-align: middle; text-align: center; }
        .head .org { font-size: 12pt; font-weight: bold; letter-spacing: .3pt; }
        .head .org-hanzi { font-size: 12pt; font-weight: bold; margin: 0.5mm 0; }
        .head .contact { font-size: 7.5pt; }
        .head .title { font-size: 11pt; font-weight: bold; margin-top: 1.5mm; text-decoration: underline; }
        .rule { border-top: 2px solid #000; margin: 1.5mm 0 3mm; }

        .group-name { font-size: 10pt; font-weight: bold; margin: 0 0 1.5mm; }
        .group-name .basis { font-weight: normal; font-size: 8pt; color: #444; }

        table.list { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        table.list th, table.list td {
            border: 0.7pt solid #000;
            padding: 0.7mm 1.6mm;
            vertical-align: top;
        }
        table.list th {
            background: #e5e5e5;
            font-size: 8.5pt;
            text-align: left;
        }
        /* Slightly taller than the text so "Keterangan" is still writable by hand,
           but tight enough to avoid wasting blank paper. */
        table.list td { font-size: 8.5pt; height: 6mm; }
        .col-no { width: 9mm; text-align: center; }
        .col-hanzi { width: 30mm; }
        .col-nama { width: 48mm; }
        .col-telp { width: 32mm; }
        .col-ket { width: 80mm; }

        /* Each group starts on a fresh page so it can be torn off and handed out. */
        .group + .group { page-break-before: always; }

        .footer { font-size: 7.5pt; color: #555; text-align: right; margin-top: 2mm; }
    </style>
</head>
<body>
    <div class="head">
        <table class="head-table">
            <tr>
                @if (! empty($logo))
                    <td class="head-logo"><img src="{{ $logo }}" alt=""></td>
                @endif
                <td class="head-text">
                    <div class="org">{{ $organization->name }}</div>
                    @if ($organization->name_hanzi)
                        <div class="org-hanzi hanzi">{{ $organization->name_hanzi }}</div>
                    @endif
                    @if ($organization->contact_line)
                        <div class="contact">{{ $organization->contact_line }}</div>
                    @endif
                    <div class="title">DAFTAR ANGGOTA</div>
                </td>
                @if (! empty($logo))
                    <td class="head-logo"></td>
                @endif
            </tr>
        </table>
    </div>
    <div class="rule"></div>

    @forelse ($groups as $group)
        <div class="group">
            <div class="group-name">
                Kelompok: {{ $group->name }}
                @if ($group->basis)
                    <span class="basis">({{ $group->basis->getLabel() }})</span>
                @endif
            </div>

            <table class="list">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-hanzi">Nama Hanzi</th>
                        <th class="col-nama">Nama Indonesia</th>
                        <th>Alamat</th>
                        <th class="col-telp">No. Telepon</th>
                        <th class="col-ket">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($group->members as $index => $member)
                        <tr>
                            <td class="col-no">{{ $index + 1 }}</td>
                            <td class="hanzi">{{ $member->name_hanzi }}</td>
                            <td>{{ $member->name_indonesian }}</td>
                            <td>{{ $member->address }}</td>
                            <td>{{ $member->phone }}</td>
                            <td></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: #777;">Tidak ada anggota.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p style="text-align: center; color: #777;">Tidak ada data untuk dicetak.</p>
    @endforelse

    <div class="footer">Dicetak: {{ $printedAt }}</div>
</body>
</html>
