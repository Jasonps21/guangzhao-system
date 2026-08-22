<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\CondolencePayment;
use App\Models\DuesReceipt;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberRelationship;
use App\Models\MemberVisit;
use App\Models\OrganizationSetting;
use App\Models\PaymentSubmission;
use App\Models\SocialFundRecord;
use App\Models\User;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Menerjemahkan satu baris jejak audit menjadi bahasa Indonesia.
 *
 * Baris mentahnya tidak terbaca manusia: eventnya "updated", subjeknya nama
 * kelas, dan perubahannya array bersarang berisi kode enum. Kelas ini tahu
 * kosakatanya; halaman Log Aktivitas hanya menampilkan hasilnya. Karena
 * terpisah dari UI, seluruh kosakata ini bisa diuji tanpa membuka halaman.
 */
class ActivityNarrator
{
    /**
     * Nama Indonesia tiap jenis objek.
     *
     * @var array<class-string, string>
     */
    private const SUBJECTS = [
        Member::class => 'Anggota',
        MemberGroup::class => 'Kelompok',
        MemberVisit::class => 'Kunjungan rumah',
        MemberRelationship::class => 'Hubungan keluarga',
        DuesRecord::class => 'Tagihan iuran',
        DuesReceipt::class => 'Nota pembayaran iuran',
        PaymentSubmission::class => 'Setoran kolektor',
        CondolencePayment::class => 'Uang duka',
        SocialFundRecord::class => 'Dana sosial',
        OrganizationSetting::class => 'Konfigurasi organisasi',
        User::class => 'Pengguna',
    ];

    /**
     * Kata kerja untuk tiap event bawaan Eloquent.
     *
     * @var array<string, string>
     */
    private const VERBS = [
        'created' => 'ditambahkan',
        'updated' => 'diubah',
        'deleted' => 'dihapus',
        'restored' => 'dipulihkan',
    ];

    /**
     * Nama Indonesia tiap kolom. Kolom yang tidak terdaftar tetap tampil,
     * hanya saja apa adanya — log tidak boleh menyembunyikan perubahan hanya
     * karena istilahnya belum diterjemahkan.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'address' => 'Alamat',
        'address_snapshot' => 'Alamat saat dikunjungi',
        'amount' => 'Jumlah',
        'amount_due' => 'Tagihan',
        'amount_paid' => 'Dibayar',
        'basis' => 'Dasar kelompok',
        'chairman_name' => 'Nama ketua',
        'collection_method' => 'Metode penagihan',
        'contact_line' => 'Baris kontak',
        'description' => 'Keterangan',
        'direction' => 'Arah dana',
        'email' => 'Email',
        'from_month' => 'Bulan awal',
        'fund_form' => 'Bentuk dana',
        'given_at' => 'Tanggal pemberian',
        'group_id' => 'Kelompok',
        'is_active' => 'Aktif',
        'issued_by' => 'Diterbitkan oleh',
        'latitude' => 'Lintang',
        'logo_path' => 'Logo',
        'longitude' => 'Bujur',
        'member_id' => 'Anggota',
        'member_number' => 'No. anggota',
        'monthly_dues_amount' => 'Iuran per bulan',
        'months_count' => 'Jumlah bulan',
        'name' => 'Nama',
        'name_hanzi' => 'Nama Mandarin',
        'name_indonesian' => 'Nama Indonesia',
        'name_pinyin' => 'Nama pinyin',
        'note' => 'Catatan',
        'outcome' => 'Hasil kunjungan',
        'paid_at' => 'Tanggal bayar',
        'party_name' => 'Nama pihak',
        'period_month' => 'Bulan',
        'period_year' => 'Tahun',
        'purpose' => 'Keperluan',
        'recipient_name' => 'Penerima',
        'recorded_by' => 'Dicatat oleh',
        'related_member_id' => 'Anggota terkait',
        'relationship_type' => 'Jenis hubungan',
        'review_notes' => 'Catatan verifikasi',
        'reviewed_at' => 'Tanggal verifikasi',
        'reviewed_by' => 'Diverifikasi oleh',
        'role' => 'Peran',
        'status' => 'Status',
        'to_month' => 'Bulan akhir',
        'total_amount' => 'Total',
        'transacted_at' => 'Tanggal transaksi',
        'treasurer_name' => 'Nama bendahara',
        'user_id' => 'Petugas',
        'username' => 'Nama pengguna',
        'visited_at' => 'Waktu kunjungan',
    ];

    /** Kolom yang isinya rupiah. */
    private const MONEY = [
        'amount', 'amount_due', 'amount_paid', 'monthly_dues_amount', 'total_amount',
    ];

    /**
     * Kalimat kepala satu baris log.
     *
     * Kegiatan yang dicatat lewat {@see ActivityRecorder} sudah membawa
     * kalimatnya sendiri; yang perlu diterjemahkan hanya event Eloquent.
     */
    public function title(ActivityLog $log): string
    {
        $verb = self::VERBS[(string) $log->event] ?? null;

        if ($verb === null || $log->subject_type === null) {
            return (string) $log->description;
        }

        return $this->subjectName($log).' '.$verb;
    }

    /** Objek yang dikenai kegiatan, ditulis sebagaimana pengurus mengenalinya. */
    public function subject(ActivityLog $log): ?string
    {
        if ($log->subject instanceof Model) {
            return $this->describe($log->subject);
        }

        /*
         * Subjek yang sudah terhapus tetap harus bisa dikenali, dan satu-satunya
         * sisa jejaknya adalah properti log. Penghapusan menyimpan nilainya di
         * 'old', bukan 'attributes'.
         */
        $attributes = $this->arrayProperty($log, 'attributes') + $this->arrayProperty($log, 'old');

        foreach (['name', 'name_indonesian', 'member_number', 'receipt_number', 'username'] as $key) {
            if (filled($attributes[$key] ?? null)) {
                return (string) $attributes[$key];
            }
        }

        return null;
    }

    /**
     * Daftar perubahan "dari → jadi", sudah diterjemahkan.
     *
     * @return array<string, array{dari: string, jadi: string}>
     */
    public function changes(ActivityLog $log): array
    {
        $new = $this->arrayProperty($log, 'attributes');
        $old = $this->arrayProperty($log, 'old');

        $changes = [];

        // Gabungan kuncinya, bukan hanya nilai baru: penghapusan hanya menyisakan
        // nilai lama, dan yang hilang justru itulah yang perlu terlihat.
        foreach (array_keys($new + $old) as $key) {
            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            if ($before === $after) {
                continue;
            }

            $changes[self::FIELDS[$key] ?? Str::headline((string) $key)] = [
                'dari' => $this->value($log, (string) $key, $before),
                'jadi' => $this->value($log, (string) $key, $after),
            ];
        }

        return $changes;
    }

    /**
     * Detail tambahan di luar perubahan kolom — periode yang dicetak, jumlah
     * lembar, alamat IP, dan sejenisnya.
     *
     * @return array<string, string>
     */
    public function details(ActivityLog $log): array
    {
        $properties = $log->properties instanceof Collection
            ? $log->properties->all()
            : (array) $log->properties;

        unset($properties['attributes'], $properties['old']);

        $details = [];

        foreach ($properties as $key => $value) {
            $details[self::FIELDS[$key] ?? Str::headline((string) $key)] = $this->plain($key, $value);
        }

        return $details;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayProperty(ActivityLog $log, string $key): array
    {
        $value = $log->properties[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    private function subjectName(ActivityLog $log): string
    {
        return $this->subjectLabel((string) $log->subject_type);
    }

    /** Nama Indonesia satu jenis objek, dipakai juga oleh penyaringnya. */
    public function subjectLabel(string $subjectType): string
    {
        return self::SUBJECTS[$subjectType] ?? Str::headline(class_basename($subjectType));
    }

    /** Sebutan satu objek sebagaimana pengurus mengenalinya. */
    private function describe(Model $model): string
    {
        return match (true) {
            $model instanceof Member => $model->displayName(),
            $model instanceof User => (string) $model->name,
            $model instanceof MemberGroup => (string) $model->name,
            $model instanceof DuesReceipt,
            $model instanceof CondolencePayment,
            $model instanceof SocialFundRecord => (string) $model->receipt_number,
            $model instanceof DuesRecord => trim(($model->member?->displayName() ?? '').' — '.$model->period_month.'/'.$model->period_year, ' —'),
            $model instanceof PaymentSubmission => (string) ($model->member?->displayName() ?? '-'),
            $model instanceof MemberVisit => (string) ($model->member?->displayName() ?? '-'),
            $model instanceof OrganizationSetting => (string) $model->name,
            default => class_basename($model).' #'.$model->getKey(),
        };
    }

    /** Nilai satu kolom, diterjemahkan memakai cast model aslinya bila ada. */
    private function value(ActivityLog $log, string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (in_array($key, self::MONEY, true) && is_numeric($value)) {
            return 'Rp '.number_format((float) $value, 0, ',', '.');
        }

        return $this->castLabel((string) $log->subject_type, $key, $value)
            ?? $this->plain($key, $value);
    }

    /**
     * Kode enum seperti "super_admin" tidak berguna bagi pembaca. Cast model
     * aslinya yang tahu label manusianya.
     */
    private function castLabel(string $subjectType, string $key, mixed $value): ?string
    {
        if (! is_string($value) || ! class_exists($subjectType)) {
            return null;
        }

        $cast = (new $subjectType)->getCasts()[$key] ?? null;

        if (! is_string($cast) || ! enum_exists($cast)) {
            return null;
        }

        $case = is_a($cast, BackedEnum::class, allow_string: true) ? $cast::tryFrom($value) : null;

        return $case instanceof HasLabel ? $case->getLabel() : $case?->name;
    }

    private function plain(string $key, mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn (mixed $item): string => (string) $item, $value));
        }

        if (in_array($key, self::MONEY, true) && is_numeric($value)) {
            return 'Rp '.number_format((float) $value, 0, ',', '.');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}([ T]|$)/', $value)) {
            return Carbon::parse($value)->translatedFormat('d M Y H:i');
        }

        return (string) $value;
    }
}
