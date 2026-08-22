{{--
    Isi jendela detail satu baris jejak audit.

    Kelasnya diberi awalan gz- dan gayanya ditulis sendiri di partial
    admin-styles: panel ini tidak punya build tema Tailwind sendiri, jadi kelas
    utilitas yang tidak dipakai Filament tidak ikut terkompilasi dan tidak akan
    berpengaruh apa-apa di sini.

    Seluruh penerjemahan dikerjakan ActivityNarrator; berkas ini hanya menatanya.
--}}
@php
    $changes = $narrator->changes($log);
    $details = $narrator->details($log);
@endphp

<div class="gz-log">
    <dl class="gz-log-grid">
        <div class="gz-log-item">
            <dt>Waktu</dt>
            <dd>{{ $log->created_at?->translatedFormat('d F Y, H:i:s') }}</dd>
        </div>
        <div class="gz-log-item">
            <dt>Pengguna</dt>
            <dd>{{ $log->causer?->name ?? 'Sistem' }}</dd>
        </div>
        <div class="gz-log-item">
            <dt>Golongan</dt>
            <dd>{{ $log->category()->getLabel() }}</dd>
        </div>
        <div class="gz-log-item">
            <dt>Objek</dt>
            <dd>{{ $narrator->subject($log) ?? '—' }}</dd>
        </div>
    </dl>

    @if ($details !== [])
        <section class="gz-log-section">
            <h3>Rincian</h3>
            <dl class="gz-log-grid">
                @foreach ($details as $label => $value)
                    <div class="gz-log-item">
                        <dt>{{ $label }}</dt>
                        <dd>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    @if ($changes !== [])
        <section class="gz-log-section">
            <h3>Perubahan</h3>
            <div class="gz-log-scroll">
                <table class="gz-log-table">
                    <thead>
                        <tr>
                            <th>Kolom</th>
                            <th>Dari</th>
                            <th>Jadi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($changes as $label => $change)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="gz-log-was">{{ $change['dari'] }}</td>
                                <td>{{ $change['jadi'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
