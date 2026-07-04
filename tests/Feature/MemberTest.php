<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Support\PinyinConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_pinyin_is_auto_filled_from_hanzi_when_empty(): void
    {
        $member = Member::factory()->create([
            'name_hanzi' => '张三',
            'name_pinyin' => null,
        ]);

        $this->assertSame('Zhang San', $member->name_pinyin);
    }

    public function test_pinyin_is_not_overwritten_when_provided(): void
    {
        $member = Member::factory()->create([
            'name_hanzi' => '张三',
            'name_pinyin' => 'Tjong Sam',
        ]);

        $this->assertSame('Tjong Sam', $member->name_pinyin);
    }

    public function test_changing_status_records_the_timestamp(): void
    {
        $member = Member::factory()->active()->create(['status_changed_at' => null]);

        $member->update(['status' => MemberStatus::Meninggal]);

        $this->assertNotNull($member->refresh()->status_changed_at);
    }

    public function test_active_scope_only_returns_active_members(): void
    {
        Member::factory(2)->active()->create();
        Member::factory()->deceased()->create();

        $this->assertSame(2, Member::query()->active()->count());
    }

    public function test_pinyin_converter_handles_surnames(): void
    {
        $this->assertSame('Zhang San', app(PinyinConverter::class)->fromName('张三'));
    }
}
