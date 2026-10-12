<?php

namespace App\Models;

use App\Enums\DuesCategory;
use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Enums\WhatsappOwner;
use App\Support\PinyinConverter;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'member_number',
        'name_hanzi',
        'name_pinyin',
        'name_indonesian',
        'gender',
        'address',
        'latitude',
        'longitude',
        'house_photo_path',
        'location_updated_at',
        'location_updated_by',
        'phone',
        'whatsapp_phone',
        'whatsapp_owner',
        'photo_path',
        'dues_category',
        'monthly_dues_amount',
        'group_id',
        'no_urut',
        'status',
        'status_changed_at',
        'joined_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dues_category' => DuesCategory::class,
            'status' => MemberStatus::class,
            'gender' => Gender::class,
            'whatsapp_owner' => WhatsappOwner::class,
            'monthly_dues_amount' => 'decimal:2',
            'status_changed_at' => 'datetime',
            'location_updated_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'joined_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Member $member): void {
            // §6.6 — auto-fill pinyin from hanzi when pinyin is empty (still overridable).
            if (filled($member->name_hanzi) && blank($member->name_pinyin)) {
                $member->name_pinyin = app(PinyinConverter::class)->fromName($member->name_hanzi);
            }

            // §E — record the moment status changes so it is auditable.
            if ($member->isDirty('status')) {
                $member->status_changed_at = now();
            }

            // Nomor anggota selalu mengikuti kelompok dan no. urut. Disimpan
            // terpisah, keduanya pernah bercerai: kupon mencetak "57" sementara
            // kodenya masih "7-056-2".
            if ($member->group_id !== null && $member->no_urut !== null) {
                $group = $member->relationLoaded('group') && $member->group?->getKey() === $member->group_id
                    ? $member->group
                    : MemberGroup::query()->find($member->group_id);

                if ($group !== null) {
                    $number = self::numberFor($group, (int) $member->no_urut);

                    $member->member_number = $member->status === null || $member->status->isActive()
                        ? $number
                        : $member->archivedNumber($number);
                }
            }
        });
    }

    /**
     * Nomor anggota untuk no. urut tertentu di sebuah kelompok, mis. "7-057".
     */
    public static function numberFor(MemberGroup $group, int $noUrut): string
    {
        return $group->memberNumberPrefix().'-'.str_pad((string) $noUrut, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Kode arsip untuk anggota yang keluar, meninggal, atau pindah: "7-014-K".
     *
     * No. urut diperlakukan sebagai kursi — pengurus mengisinya lagi dengan
     * orang baru — jadi kode aslinya dilepas. Kursi yang kosong dua kali
     * mendapat "-K2", "-K3", dan seterusnya.
     */
    private function archivedNumber(string $number): string
    {
        if (preg_match('/^'.preg_quote($number, '/').'-K\d*$/', (string) $this->member_number) === 1) {
            return $this->member_number;
        }

        $taken = self::withTrashed()
            ->where('member_number', 'like', $number.'-K%')
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->pluck('member_number');

        $candidate = $number.'-K';

        for ($suffix = 2; $taken->contains($candidate); $suffix++) {
            $candidate = $number.'-K'.$suffix;
        }

        return $candidate;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['member_number', 'name_hanzi', 'name_pinyin', 'name_indonesian', 'status', 'monthly_dues_amount', 'group_id', 'address', 'latitude', 'longitude'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<MemberGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MemberGroup::class, 'group_id');
    }

    /**
     * @return HasMany<DuesRecord, $this>
     */
    public function duesRecords(): HasMany
    {
        return $this->hasMany(DuesRecord::class);
    }

    /**
     * Riwayat kunjungan lapangan (terbaru dulu).
     *
     * @return HasMany<MemberVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(MemberVisit::class)->latest('visited_at');
    }

    /**
     * Hubungan kekeluargaan yang dimiliki anggota ini (sisi pemilik). Baris
     * kebalikannya disimpan otomatis, jadi relasi selalu terlihat dari kedua sisi.
     *
     * @return HasMany<MemberRelationship, $this>
     */
    public function familyRelationships(): HasMany
    {
        return $this->hasMany(MemberRelationship::class, 'member_id');
    }

    /**
     * Petugas yang terakhir memperbarui titik lokasi.
     *
     * @return BelongsTo<User, $this>
     */
    public function locationUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'location_updated_by');
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Lokasi dianggap lengkap bila sudah ada titik GPS dan foto rumah — syarat
     * kebijakan "wajib sekali" saat kunjungan pertama.
     */
    public function hasCompleteLocation(): bool
    {
        return $this->hasLocation() && filled($this->house_photo_path);
    }

    /**
     * Tautan Google Maps untuk melihat titik rumah. Null bila belum ada titik.
     */
    public function googleMapsUrl(): ?string
    {
        if (! $this->hasLocation()) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.$this->latitude.','.$this->longitude;
    }

    /**
     * Tautan navigasi (rute) Google Maps menuju rumah anggota.
     */
    public function googleMapsDirectionsUrl(): ?string
    {
        if (! $this->hasLocation()) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='.$this->latitude.','.$this->longitude;
    }

    /**
     * URL foto rumah terkini pada disk publik. Null bila belum ada.
     */
    public function housePhotoUrl(): ?string
    {
        return filled($this->house_photo_path)
            ? Storage::disk('public')->url($this->house_photo_path)
            : null;
    }

    /**
     * Only members that should be billed, printed coupons/cards for. (§6.5)
     *
     * @param  Builder<Member>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', MemberStatus::Aktif);
    }

    /**
     * Pencarian anggota untuk pemilih kupon — nama Indonesia, pinyin, hanzi,
     * atau nomor anggota. Dropdown "Cetak Kupon Anggota Tertentu" memakainya
     * karena memuat 1.240 anggota sekaligus terlalu berat.
     *
     * @param  Builder<Member>  $query
     */
    public function scopeSearchByNameOrNumber(Builder $query, string $term): void
    {
        $query->where(function (Builder $inner) use ($term): void {
            foreach (['name_indonesian', 'name_pinyin', 'name_hanzi', 'member_number'] as $column) {
                $inner->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Aktif;
    }

    public function displayName(): string
    {
        return $this->name_indonesian
            ?: $this->name_pinyin
            ?: $this->name_hanzi
            ?: $this->member_number;
    }
}
