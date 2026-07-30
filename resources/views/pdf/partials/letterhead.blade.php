{{--
    Kop surat nota: logo di kiri, nama perkumpulan + nama hanzi + baris alamat
    di tengah, ditutup garis ganda — mengikuti nota cetak yang selama ini dipakai.

    @var \App\Models\OrganizationSetting $organization
    @var string|null $logo Data URI logo, null bila tidak ada.
--}}
<table class="letterhead">
    <tr>
        @if (! empty($logo))
            <td class="lh-logo"><img src="{{ $logo }}" alt=""></td>
        @endif
        <td class="lh-text">
            <div class="lh-org">{{ $organization->name }}</div>
            @if ($organization->name_hanzi)
                <div class="lh-hanzi">{{ $organization->name_hanzi }}</div>
            @endif
            @if ($organization->contact_line)
                <div class="lh-contact">{{ $organization->contact_line }}</div>
            @endif
        </td>
        {{-- Kolom penyeimbang selebar logo agar blok teks benar-benar di tengah. --}}
        @if (! empty($logo))
            <td class="lh-logo"></td>
        @endif
    </tr>
</table>
<div class="lh-rule"></div>
<div class="lh-rule-thin"></div>
