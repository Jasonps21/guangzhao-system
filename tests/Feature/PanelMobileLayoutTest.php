<?php

namespace Tests\Feature;

use App\Filament\Pages\PembayaranKantor;
use App\Filament\Resources\CondolencePayments\Pages\ListCondolencePayments;
use App\Filament\Resources\DuesRecords\Pages\ListDuesRecords;
use App\Filament\Resources\MemberGroups\Pages\EditMemberGroup;
use App\Filament\Resources\MemberGroups\Pages\ListMemberGroups;
use App\Filament\Resources\MemberGroups\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\RelationManagers\DuesRecordsRelationManager;
use App\Filament\Resources\Members\RelationManagers\FamilyRelationshipsRelationManager;
use App\Filament\Resources\Members\RelationManagers\VisitsRelationManager;
use App\Filament\Resources\PaymentSubmissions\Pages\ListPaymentSubmissions;
use App\Filament\Resources\SocialFundRecords\Pages\ListSocialFundRecords;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\DuesArrearsList;
use App\Filament\Widgets\DuesGroupSummary;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Panel admin dibuka dari HP, bukan cuma dari laptop. Tabel bawaan Filament
 * melebar ke samping, sehingga pengurus harus menggeser terus untuk membaca
 * satu baris.
 *
 * Dua lapis penanganannya diuji di sini:
 *   1. stackedOnMobile() dipasang global, jadi tiap baris menjadi kartu
 *      bertumpuk di bawah 640px — termasuk pada tabel yang tidak pernah
 *      disentuh berkas konfigurasinya.
 *   2. Kolom sekunder diberi visibleFrom('lg') supaya kartunya tetap pendek,
 *      sementara kolom inti tidak boleh ikut hilang.
 */
class PanelMobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ambang 'lg' (1024px) sengaja lebih tinggi daripada ambang penumpukan
     * Filament (640px), supaya HP dalam posisi miring tidak kembali
     * menampilkan tabel lebar yang harus digeser.
     */
    private const NARROW_SCREEN_BREAKPOINT = 'lg';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->superAdmin()->create());
    }

    /**
     * @param  class-string  $page
     * @param  list<string>  $alwaysVisible
     * @param  list<string>  $hiddenOnNarrowScreens
     */
    #[DataProvider('panelTables')]
    public function test_table_stacks_on_a_phone_and_keeps_only_its_core_columns(
        string $page,
        array $alwaysVisible,
        array $hiddenOnNarrowScreens,
    ): void {
        $this->assertTableReadsOnAPhone(
            Livewire::test($page)->instance()->getTable(),
            $alwaysVisible,
            $hiddenOnNarrowScreens,
        );
    }

    /**
     * @return array<string, array{class-string, list<string>, list<string>}>
     */
    public static function panelTables(): array
    {
        return [
            'Anggota' => [
                ListMembers::class,
                ['member_number', 'name_indonesian', 'group.name', 'monthly_dues_amount', 'status'],
                ['name_hanzi', 'lokasi'],
            ],
            'Iuran' => [
                ListDuesRecords::class,
                ['member.member_number', 'member.name_indonesian', 'period', 'amount_due', 'status'],
                ['paid_at', 'recordedBy.name', 'collection_method'],
            ],
            'Setoran Kolektor' => [
                ListPaymentSubmissions::class,
                ['member.name_indonesian', 'collector.name', 'period', 'total_amount', 'status'],
                ['created_at', 'months', 'reviewedBy.name'],
            ],
            'Dana Sosial' => [
                ListSocialFundRecords::class,
                ['receipt_number', 'transacted_at', 'direction', 'party_name', 'amount'],
                ['fund_form', 'purpose'],
            ],
            'Uang Duka' => [
                ListCondolencePayments::class,
                ['receipt_number', 'given_at', 'recipient_name', 'amount'],
                ['escort_name'],
            ],
            'Pembayaran Kantor' => [
                PembayaranKantor::class,
                ['receipt_number', 'issued_at', 'member.name_indonesian', 'period', 'total_amount'],
                ['months_count', 'received_by_name'],
            ],
            // Tabel berikut kolomnya sudah sedikit, jadi tidak ada yang
            // disembunyikan. Keduanya membuktikan lapis global bekerja: berkas
            // konfigurasinya tidak disentuh sama sekali.
            'Kelompok' => [
                ListMemberGroups::class,
                ['name', 'basis', 'members_count', 'collectors.name'],
                [],
            ],
            'Pengguna' => [
                ListUsers::class,
                ['name', 'username', 'role', 'groups.name', 'is_active'],
                [],
            ],
        ];
    }

    public function test_report_widgets_stack_on_a_phone(): void
    {
        foreach ([DuesGroupSummary::class, DuesArrearsList::class] as $widget) {
            $table = Livewire::test($widget, ['year' => 2026, 'month' => 8])
                ->instance()
                ->getTable();

            $this->assertTrue(
                $table->isStackedOnMobile(),
                $widget.' tidak menumpuk di layar HP.',
            );
        }
    }

    public function test_member_relation_managers_stack_on_a_phone(): void
    {
        $member = Member::factory()->create();

        $this->assertTableReadsOnAPhone(
            $this->relationManagerTable(DuesRecordsRelationManager::class, $member, EditMember::class),
            ['period', 'amount_due', 'amount_paid', 'status'],
            ['paid_at', 'recordedBy.name'],
        );

        $this->assertTableReadsOnAPhone(
            $this->relationManagerTable(VisitsRelationManager::class, $member, EditMember::class),
            ['visited_at', 'collector.name', 'outcome', 'photo_path'],
            ['address_snapshot', 'note'],
        );

        $this->assertTableReadsOnAPhone(
            $this->relationManagerTable(FamilyRelationshipsRelationManager::class, $member, EditMember::class),
            ['relationship_type', 'relatedMember.name_indonesian', 'relatedMember.member_number'],
            [],
        );
    }

    public function test_group_members_relation_manager_stacks_on_a_phone(): void
    {
        $this->assertTableReadsOnAPhone(
            $this->relationManagerTable(
                MembersRelationManager::class,
                MemberGroup::factory()->create(),
                EditMemberGroup::class,
            ),
            ['member_number', 'name_indonesian', 'monthly_dues_amount', 'status'],
            [],
        );
    }

    /**
     * @param  class-string  $relationManager
     * @param  class-string  $pageClass
     */
    private function relationManagerTable(string $relationManager, mixed $ownerRecord, string $pageClass): Table
    {
        return Livewire::test($relationManager, [
            'ownerRecord' => $ownerRecord,
            'pageClass' => $pageClass,
        ])->instance()->getTable();
    }

    /**
     * @param  list<string>  $alwaysVisible
     * @param  list<string>  $hiddenOnNarrowScreens
     */
    private function assertTableReadsOnAPhone(Table $table, array $alwaysVisible, array $hiddenOnNarrowScreens): void
    {
        $this->assertTrue($table->isStackedOnMobile(), 'Tabel ini tidak menumpuk di layar HP.');

        foreach ($alwaysVisible as $name) {
            $column = $table->getColumn($name);

            $this->assertNotNull($column, "Kolom inti '{$name}' tidak ada lagi di tabel ini.");
            $this->assertNull(
                $column->getVisibleFrom(),
                "Kolom inti '{$name}' ikut disembunyikan di layar sempit.",
            );
        }

        foreach ($hiddenOnNarrowScreens as $name) {
            $column = $table->getColumn($name);

            $this->assertNotNull($column, "Kolom sekunder '{$name}' tidak ada lagi di tabel ini.");
            $this->assertSame(
                self::NARROW_SCREEN_BREAKPOINT,
                $column->getVisibleFrom(),
                "Kolom sekunder '{$name}' masih memenuhi layar sempit.",
            );
        }
    }
}
