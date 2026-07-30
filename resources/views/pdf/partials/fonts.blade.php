{{--
    Font CJK hasil ekstraksi WenQuanYi Zen Hei agar hanzi ter-render di DomPDF.
    Didaftarkan untuk bobot normal dan bold (berkas sama) — tanpa ini, hanzi tebal
    jatuh ke DejaVu dan tercetak sebagai kotak. Disisipkan di dalam <style>.
--}}
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
