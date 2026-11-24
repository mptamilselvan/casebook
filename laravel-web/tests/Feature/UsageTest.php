<?php

namespace Tests\Feature;

use App\Models\CourtCase;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserUsage;
use App\Support\Casebook;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_and_case_counts_follow_the_plan_limits(): void
    {
        Storage::fake(Casebook::FILE_DISK);
        $this->withoutVite();
        $this->seed(PlanSeeder::class);

        $user = User::factory()->create();
        $user->assignFreePlan();
        Plan::query()->where('code', Plan::FREE)->update(['cases_limit' => 1]);

        $this->actingAs($user);

        $payload = [
            'state' => 'Tamil Nadu',
            'district' => 'Chennai',
            'court' => 'Madras High Court',
            'case_number' => 'O.S. 10',
            'case_year' => '2026',
            'party1_role' => 'Plaintiff',
            'party2_role' => 'Defendant',
            'petitioners' => ['Ramesh Kumar'],
            'respondents' => ['State of TN'],
        ];

        $this->post(route('cases.store'), $payload)->assertRedirect();
        $this->post(route('cases.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('case');

        $this->assertSame(1, CourtCase::query()->count());
        $this->assertDatabaseHas('user_usages', [
            'user_id' => $user->id,
            'cases_count' => 1,
            'storage_bytes' => 0,
        ]);

        $case = CourtCase::query()->first();
        $this->post(route('documents.store', $case), [
            'folder' => 'Pleadings',
            'name' => 'Plaint',
            'file' => UploadedFile::fake()->create('plaint.pdf', 1, 'application/pdf'),
        ])->assertRedirect();

        $document = $case->documents()->first();
        $this->assertSame(1024, $document->bytes);
        $this->assertSame(1024, UserUsage::query()->where('user_id', $user->id)->value('storage_bytes'));

        $this->get('/')
            ->assertOk()
            ->assertSee('1.0 KB of 1 GB')
            ->assertSee('1 of 1 cases');

        Plan::query()->where('code', Plan::FREE)->update(['space_limit' => 0]);
        $this->actingAs($user->fresh());

        $this->post(route('documents.store', $case), [
            'folder' => 'Pleadings',
            'name' => 'Another',
            'file' => UploadedFile::fake()->create('more.pdf', 1, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasErrors('file');
        $this->assertSame(1, $case->documents()->count());

        Sanctum::actingAs($user);
        $this->postJson('/api/cases', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('case');

        $this->deleteJson('/api/cases/'.$case->id.'/documents/'.$document->id)->assertOk();
        $this->assertSame(0, UserUsage::query()->where('user_id', $user->id)->value('storage_bytes'));

        $this->deleteJson('/api/cases/'.$case->id)->assertOk();
        $this->assertSame(0, UserUsage::query()->where('user_id', $user->id)->value('cases_count'));
    }
}
