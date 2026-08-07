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
            font-family: 'Times New Roman', serif;
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
        /* Header + the four flowing data rows (Nama…Bulan) live here. A fixed
           height with overflow:hidden caps how far long names/addresses can push
           down, so they can never collide with the pinned bottom block. */
        .body-top { height: 46mm; overflow: hidden; }
        /* Garis isian pada kupon cadangan yang menempati slot sisa satu kelompok.
           Dibuat dari border, bukan deretan karakter titik, supaya panjangnya
           selalu pas selebar kolomnya berapa pun ukuran fontnya. Kotak kosong
           kosong perlu height eksplisit: tanpa itu inline-block tanpa isi runtuh
           jadi nol di DomPDF dan garisnya tidak tergambar sama sekali.

           Tingginya 4,2mm karena DomPDF menempatkan inline-block dari sisi ATAS
           kotak barisnya, bukan dari garis dasar seperti browser. Angka itu kira-
           kira setinggi ascender font 14pt, sehingga tepi bawahnya — garisnya —
           jatuh sejajar garis dasar label di sebelahnya, seperti isian formulir.
           Pada 3,4mm garisnya mengambang ±0,8mm di atas baseline. Bila ukuran
           font table.info berubah, angka ini harus disetel ulang lewat render
           PDF sungguhan. */
        .fill {
            display: inline-block;
            width: 100%;
            height: 4.2mm;
            border-bottom: 0.6pt dotted #333;
        }
        /* Iuran dan No. Urut cukup garis pendek: "Rp." dan ",-" ikut tercetak
           mengapit garisnya, dan nomor urut paling panjang tiga angka. */
        .fill-amount { width: 26mm; }
        .fill-number { width: 16mm; }

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
            opacity: 0.22;
            z-index: 0;
        }

        /* Header — blue, centered. Org title uses a serif bold-italic face to
           echo the decorative Word heading; contact line is underlined. */
        .head { text-align: center; line-height: 0.9; color: #0000cc; }
        .head .org {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            font-weight: bold;
            font-style: italic;
            text-decoration: underline;
            letter-spacing: .2pt;
        }
        /* Title hanzi: enlarged and faux-bolded via overprint (WenQuanYi is a
           single weight, so font-weight:bold alone does not thicken it). The
           .hzb span is centered as an inline-block; .over overlaps the base
           glyphs nudged 0.4pt right. No line-height:0 needed here since the
           hanzi sits alone on its line. */
        .head .hanzi {
            font-family: 'WenQuanYi', serif;
            font-size: 13pt;
            font-weight: bold;
            margin: 0.2mm 0;
        }
        .head .hanzi .hzb { position: relative; display: inline-block; }
        .head .hanzi .over { position: absolute; left: 0.4pt; top: 0; }
        /* Contact line — same decorative serif face as the org title
           ("perkumpulan"): bold italic underlined. nowrap + a small size keep
           the long address/phone string on a single line. */
        .head .contact {
            font-family: 'Times New Roman', serif;
            font-size: 10pt;
            font-weight: bold;
            font-style: italic;
            text-decoration: underline;
            letter-spacing: -.1pt;
            white-space: nowrap;
        }

        /* Member data rows — bold, larger font. Rows are packed tightly
           (minimal padding) so the six data lines sit close together and use
           up the empty space in the coupon. */
        table.info { width: 100%; border-collapse: collapse; font-weight: bold; font-size: 14pt; margin-top: 2mm; line-height: 1.05; }
        table.info td { vertical-align: top; padding: 0 0; }
        table.info .label { width: 20mm; }
        table.info .sep { width: 2.5mm; }
        /* Member-name hanzi: enlarged to 11pt and faux-bolded. WenQuanYi ships a
           single weight, so font-weight:bold does not thicken it in DomPDF.
           Instead we overprint a second copy (.over) nudged right by 0.4pt to
           fatten the strokes. line-height:0 stops the taller 11pt glyph from
           inflating the row's line box, so the hanzi shares one baseline with
           the 9pt label and latin/pinyin name (all "sama rata"). Because that
           collapses the line box, the absolutely-positioned .over must be pushed
           back down (top) to re-overlap the base glyph. That top offset is tuned
           empirically and is coupled to BOTH this font-size AND the body font's
           line metrics (it was 11.06pt under DejaVu Sans, 8.55pt under Times New
           Roman). If you change either, retune: render the coupon PDF and adjust
           top until the two 11pt draws share one baseline (Δy ≈ 0). */
        .hz {
            position: relative;
            font-family: 'WenQuanYi', 'DejaVu Sans', sans-serif;
            font-size: 14pt;
            line-height: 0;
        }
        /* Pinyin (e.g. "Huang Ying Qiang") shown small and de-emphasised so the
           Indonesian name stays the prominent one on the Nama line. */
        .pinyin { font-size: 9pt; font-weight: normal; font-style: italic; }
        .amount { font-weight: bold; }
        .month { font-weight: bold; color: #d00000; }

        /* Kelompok / No. Urut pinned to the bottom-left corner (mirroring the
           signatures on the right) so they are ALWAYS printed in full, no matter
           how many lines the Nama / Alamat above wrapped to. Before this they
           flowed after the info block and got clipped off the box on long data. */
        .foot-box { position: absolute; left: 3.5mm; bottom: 2mm; }
        table.foot { border-collapse: collapse; font-weight: bold; font-size: 14pt; margin-top: 0; line-height: 1.05; }
        table.foot td { vertical-align: top; padding: 0 0; }
        table.foot .label { width: 20mm; }
        table.foot .sep { width: 2.5mm; }

        /* Ketua / Bendahara signatures on the right, as in the Word template. */
        /* Ketua / Bendahara pinned to the bottom-right corner. Kept compact
           (small font, tight columns) so it stays in the corner and does not
           overlap the Kelompok / No. Urut data on the left. */
        .sign-box { position: absolute; right: 2mm; bottom: 2mm; }
        table.sign { border-collapse: collapse; font-size: 8.5pt; font-weight: bold; }
        table.sign td { width: 20mm; text-align: center; vertical-align: top; padding: 0 0.5mm; white-space: nowrap; }
        .sign .gap { height: 7mm; }
    </style>
</head>
<body>
@php
    /*
     * Imposisi "cut & stack" per kelompok. Seluruh lembar dicetak lalu seluruh
     * tumpukan dipotong menjadi 10 tumpukan (satu per posisi kupon); bila tiap
     * tumpukan disusun sesuai urutan baca, setiap tumpukan sudah urut No. Urut.
     * Untuk satu kelompok berisi N anggota di P = ceil(N/10) lembar, kupon pada
     * slot ke-s (0-based, urutan baca 0..9) di lembar ke-p berisi anggota
     * indeks s*P + p. Slot yang melewati N dirender sebagai kupon kosong.
     */
    $pages = collect();

    foreach ($records->groupBy(fn ($record) => $record->member->group_id) as $groupRecords) {
        $items = $groupRecords->values();
        $sheetCount = (int) ceil($items->count() / 10);

        for ($sheet = 0; $sheet < $sheetCount; $sheet++) {
            $slots = [];

            for ($slot = 0; $slot < 10; $slot++) {
                $slots[] = $items->get($slot * $sheetCount + $sheet);
            }

            /*
             * Kelompoknya ikut dibawa karena slot sisa bernilai null: kupon
             * cadangan tetap harus mencetak nama kelompok pemilik lembar ini,
             * padahal tidak ada anggota di slot itu untuk ditanyai. Satu lembar
             * selalu milik satu kelompok — groupBy di atas yang menjaminnya.
             */
            $pages->push([
                'group' => $items->first()->member->group,
                'slots' => collect($slots),
            ]);
        }
    }
@endphp

@forelse ($pages as $page)
    <div class="sheet">
        <table class="grid">
            @php $rows = $page['slots']->chunk(2); @endphp
            @foreach ($rows as $pair)
                <tr>
                    @foreach ($pair as $record)
                        @php
                            /*
                             * $record bernilai null pada slot sisa. Kupon tetap
                             * dicetak utuh; hanya empat baris datanya yang jadi
                             * garis isian, siap ditulis tangan untuk anggota baru
                             * atau kupon yang hilang. Lembarnya toh tetap keluar
                             * dari printer entah kotak ini diisi atau tidak.
                             */
                            $member = $record?->member;

                            /*
                             * Distribute the leftover vertical space evenly between the four
                             * data rows so short-data coupons fill the box instead of leaving
                             * a gap above the pinned Kelompok / No. Urut. We estimate how many
                             * wrapped lines each field needs (value column fits ~26 latin chars
                             * at 14pt; a hanzi glyph ≈ 2 of those), then spread the remaining
                             * millimetres as equal padding under each row. Long data yields
                             * ~0 gap and stays tight; .body-top clips any overflow as a guard.
                             */
                            $valueChars = 26;
                            $alamatText = '-';

                            if ($member !== null) {
                                $nameLatin = ($member->name_indonesian ?: $member->name_pinyin) ?? '';
                                $namaWidth = mb_strlen($member->name_hanzi ?? '') * 2 + mb_strlen($nameLatin)
                                    + ($member->name_indonesian && $member->name_pinyin ? mb_strlen($member->name_pinyin) + 3 : 0);
                                $namaLines = max(1, (int) ceil($namaWidth / $valueChars));

                                $alamatText = $member->address ? \Illuminate\Support\Str::limit($member->address, 48) : '-';
                                $alamatLines = max(1, (int) ceil(mb_strlen($alamatText) / $valueChars));
                            } else {
                                // Garis isian selalu satu baris, jadi jaraknya melebar penuh.
                                $namaLines = 1;
                                $alamatLines = 1;
                            }

                            $usedMm = ($namaLines + $alamatLines + 2) * 5.2;
                            $rowGapMm = round(max(0.0, 31 - $usedMm) / 4, 2);
                        @endphp
                        <td class="coupon">
                            <div class="inner">
                                @if (! empty($logo))
                                    <img class="watermark" src="{{ $logo }}" alt="">
                                @endif
                                <div class="body-top">
                                <div class="head">
                                    <div class="org">{{ $organization->name }}</div>
                                    <div class="hanzi"><span class="hzb">{{ $organization->name_hanzi }}<span class="over">{{ $organization->name_hanzi }}</span></span></div>
                                    @if ($organization->contact_line)
                                        <div class="contact">{{ $organization->contact_line }}</div>
                                    @endif
                                </div>

                                <table class="info">
                                    <tr>
                                        <td class="label" style="padding-bottom: {{ $rowGapMm }}mm;">Nama</td>
                                        <td class="sep">:</td>
                                        <td style="padding-bottom: {{ $rowGapMm }}mm;">@if ($member)@if ($member->name_hanzi)<span class="hz">{{ $member->name_hanzi }}</span> @endif{{ $member->name_indonesian ?: $member->name_pinyin }}@if ($member->name_indonesian && $member->name_pinyin)<span class="pinyin"> ({{ $member->name_pinyin }})</span>@endif@else<span class="fill"></span>@endif</td>
                                    </tr>
                                    <tr>
                                        <td class="label" style="padding-bottom: {{ $rowGapMm }}mm;">Alamat</td>
                                        <td class="sep">:</td>
                                        <td style="padding-bottom: {{ $rowGapMm }}mm;">@if ($member){{ $alamatText }}@else<span class="fill"></span>@endif</td>
                                    </tr>
                                    <tr>
                                        <td class="label" style="padding-bottom: {{ $rowGapMm }}mm;">Iuran</td>
                                        <td class="sep">:</td>
                                        <td style="padding-bottom: {{ $rowGapMm }}mm;"><span class="amount">Rp.@if ($member){{ number_format($record->amount_due, 0, ',', '.') }}@else<span class="fill fill-amount"></span>@endif,-</span></td>
                                    </tr>
                                    <tr>
                                        <td class="label">Bulan</td>
                                        <td class="sep">:</td>
                                        <td><span class="month">{{ $period }}</span></td>
                                    </tr>
                                </table>
                                </div>

                                <div class="foot-box">
                                    <table class="foot">
                                        <tr>
                                            <td class="label">Kelompok</td>
                                            <td class="sep">:</td>
                                            <td>{{ $page['group']?->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">No. Urut</td>
                                            <td class="sep">:</td>
                                            <td>@if ($member){{ $member->no_urut ?? '-' }}@else<span class="fill fill-number"></span>@endif</td>
                                        </tr>
                                    </table>
                                </div>

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
