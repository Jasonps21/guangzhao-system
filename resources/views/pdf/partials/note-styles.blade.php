{{--
    Gaya bersama seluruh nota cetak A5 melintang (210 × 148 mm). Disisipkan di
    dalam <style> tiap nota agar tampilan kop, baris isian, dan blok tanda tangan
    seragam. Ukuran huruf sengaja besar: nota diisi dan dibaca di atas meja,
    bukan di layar, dan ruang A5 melintang memang cukup untuk itu.
--}}
@include('pdf.partials.fonts')

@page { margin: 0; }
* { box-sizing: border-box; }
body {
    font-family: 'Times New Roman', serif;
    margin: 0;
    color: #000;
    font-size: 13pt;
    line-height: 1.45;
}
/* Tinggi dikunci ke area cetak (148mm − padding) supaya blok tanda tangan bisa
   dipatok ke dasar halaman dan nota terisi penuh, bukan menggantung di atas. */
.note {
    position: relative;
    height: 140mm;
    padding: 4mm 11mm;
}

/* ---- Kop surat ----
   Sengaja dibuat ringkas: tiap milimeter yang dihemat di sini jadi cadangan
   ruang untuk nama/terbilang panjang di badan nota, yang bisa memakan dua baris
   ekstra dan mendorong isi ke blok tanda tangan. */
table.letterhead { width: 100%; border-collapse: collapse; }
.lh-logo { width: 17mm; vertical-align: middle; }
.lh-logo img { width: 16mm; height: 16mm; }
.lh-text { text-align: center; vertical-align: middle; line-height: 1.1; }
.lh-org { font-size: 15pt; font-weight: bold; letter-spacing: .6pt; }
.lh-hanzi { font-family: 'WenQuanYi', 'DejaVu Sans', sans-serif; font-size: 14pt; font-weight: bold; }
.lh-contact { font-size: 8pt; letter-spacing: .2pt; }
/* Garis ganda tebal-tipis, meniru kop nota cetak lama. */
.lh-rule { border-top: 3px solid #000; margin-top: 1mm; }
.lh-rule-thin { border-top: 1px solid #000; margin-top: 0.7mm; margin-bottom: 2.5mm; }

/* ---- Judul & nomor nota ---- */
table.title-row { width: 100%; border-collapse: collapse; }
table.title-row td { vertical-align: bottom; }
.title {
    text-align: center;
    font-size: 15pt;
    font-weight: bold;
    letter-spacing: 3pt;
    text-decoration: underline;
}
.receipt-no { width: 42mm; text-align: right; font-size: 11pt; white-space: nowrap; }

/* ---- Baris isian ----
   Nilai isian diletakkan di dalam sel bertepi bawah, bukan span melayang, agar
   tulisannya benar-benar duduk di atas garis. */
table.fields { width: 100%; border-collapse: collapse; margin-top: 2mm; }
table.fields td { padding: 0.8mm 0 0.4mm; vertical-align: bottom; }
table.fields .label { white-space: nowrap; padding-right: 2mm; }
table.fields .value { border-bottom: 1px solid #000; padding-left: 2mm; }
table.fields .tail { white-space: nowrap; padding-left: 2mm; }

/* ---- Tanda tangan, dipatok ke dasar halaman ---- */
.sign-box { position: absolute; left: 11mm; right: 11mm; bottom: 5mm; }
table.sign { width: 100%; border-collapse: collapse; }
table.sign td { vertical-align: top; }
.sign-gap { height: 17mm; }
/* Nama ditengahkan di antara kurung supaya tidak menempel ke kurung buka. */
.sign-name { display: inline-block; min-width: 42mm; text-align: center; }
