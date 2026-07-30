<?php

namespace Tests\Feature;

use App\Filament\Pages\KonfigurasiOrganisasi;
use App\Filament\Resources\CondolencePayments\CondolencePaymentResource;
use App\Filament\Resources\DuesRecords\DuesRecordResource;
use App\Filament\Resources\MemberGroups\MemberGroupResource;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\PaymentSubmissions\PaymentSubmissionResource;
use App\Filament\Resources\SocialFundRecords\SocialFundRecordResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\CondolencePayment;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberVisit;
use App\Models\PaymentSubmission;
use App\Models\SocialFundRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Menu operasional yang dilihat Admin maupun Super Admin.
     *
     * @var list<class-string<\Filament\Resources\Resource>>
     */
    private const OPERATIONAL_RESOURCES = [
        MemberResource::class,
        MemberGroupResource::class,
        DuesRecordResource::class,
        PaymentSubmissionResource::class,
        CondolencePaymentResource::class,
        SocialFundRecordResource::class,
    ];

    /**
     * Data yang bisa diinput Admin tapi hanya boleh dihapus Super Admin.
     *
     * @var list<class-string<Model>>
     */
    private const MANAGED_MODELS = [
        Member::class,
        MemberGroup::class,
        DuesRecord::class,
        CondolencePayment::class,
        SocialFundRecord::class,
    ];

    public function test_admin_sees_every_operational_menu(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (self::OPERATIONAL_RESOURCES as $resource) {
            $this->assertTrue($resource::canViewAny(), $resource.' harus terlihat oleh Admin');
        }
    }

    public function test_admin_cannot_see_the_settings_menus(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertFalse(UserResource::canAccess());
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(KonfigurasiOrganisasi::canAccess());
    }

    public function test_super_admin_sees_the_settings_menus(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->assertTrue(UserResource::canAccess());
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(KonfigurasiOrganisasi::canAccess());
    }

    public function test_admin_can_create_and_update_records(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (self::MANAGED_MODELS as $model) {
            $record = $model::factory()->create();

            $this->assertTrue(Gate::forUser($admin)->allows('create', $model), $model.' harus bisa diinput Admin');
            $this->assertTrue(Gate::forUser($admin)->allows('update', $record), $model.' harus bisa diubah Admin');
        }
    }

    public function test_admin_cannot_delete_any_record(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (self::MANAGED_MODELS as $model) {
            $record = $model::factory()->create();

            $this->assertTrue(Gate::forUser($admin)->denies('delete', $record), $model.' tidak boleh dihapus Admin');
            $this->assertTrue(Gate::forUser($admin)->denies('deleteAny', $model), $model.' tidak boleh dihapus massal oleh Admin');
        }
    }

    public function test_super_admin_can_delete_records(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        foreach (self::MANAGED_MODELS as $model) {
            $record = $model::factory()->create();

            $this->assertTrue(Gate::forUser($superAdmin)->allows('delete', $record), $model.' harus bisa dihapus Super Admin');
            $this->assertTrue(Gate::forUser($superAdmin)->allows('deleteAny', $model), $model.' harus bisa dihapus massal oleh Super Admin');
        }
    }

    public function test_only_super_admin_may_force_delete_or_restore_a_member(): void
    {
        $member = Member::factory()->create();
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($admin)->denies('forceDelete', $member));
        $this->assertTrue(Gate::forUser($admin)->denies('restore', $member));

        $this->assertTrue(Gate::forUser($superAdmin)->allows('forceDelete', $member));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('restore', $member));
    }

    public function test_delete_action_is_hidden_from_admin_on_the_member_edit_page(): void
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->admin()->create());
        $this->assertFalse(MemberResource::canDelete($member));

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->assertTrue(MemberResource::canDelete($member));
    }

    public function test_payment_submissions_cannot_be_created_from_the_panel(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($superAdmin)->denies('create', PaymentSubmission::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', PaymentSubmission::class));
    }

    public function test_member_visits_are_read_only_in_the_panel(): void
    {
        $visit = MemberVisit::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', MemberVisit::class));
        $this->assertTrue(Gate::forUser($superAdmin)->denies('create', MemberVisit::class));
        $this->assertTrue(Gate::forUser($superAdmin)->denies('update', $visit));
    }

    public function test_admin_cannot_manage_users(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->collector()->create();

        $this->assertTrue(Gate::forUser($admin)->denies('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->denies('create', User::class));
        $this->assertTrue(Gate::forUser($admin)->denies('update', $other));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $other));
    }

    public function test_super_admin_cannot_delete_their_own_account(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $other = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($superAdmin)->denies('delete', $superAdmin));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('delete', $other));
    }

    public function test_collectors_have_no_panel_permissions(): void
    {
        $collector = User::factory()->collector()->create();

        foreach (self::MANAGED_MODELS as $model) {
            $this->assertTrue(Gate::forUser($collector)->denies('viewAny', $model), $model.' tidak boleh dilihat Kolektor');
            $this->assertTrue(Gate::forUser($collector)->denies('create', $model), $model.' tidak boleh diinput Kolektor dari panel');
        }
    }
}
