<?php

namespace Tests\Feature;

use App\Mail\VerifyEmail;
use App\Models\CourtCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_email_is_not_given_a_token(): void
    {
        Mail::fake();

        $this->postJson('/api/login', [
            'email' => 'new.advocate@firm.test',
        ])->assertOk()
            ->assertJsonPath('status', 'verification_sent');

        $this->assertDatabaseHas('users', [
            'email' => 'new.advocate@firm.test',
            'email_verified_at' => null,
        ]);
        Mail::assertSent(VerifyEmail::class);
        $this->getJson('/api/casebook')->assertUnauthorized();
    }

    public function test_email_without_a_dot_after_the_at_sign_is_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/login', [
            'email' => 'advocate@nodot',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_verification_link_returns_a_token_and_opens_the_casebook(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'later@advocate.test',
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->postJson('/api/email/verify', ['url' => $url]);

        $response->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonPath('user.email', 'later@advocate.test');

        $this->assertNotNull($user->fresh()->email_verified_at);

        $token = $response->json('token');

        $this->withToken($token)
            ->getJson('/api/casebook')
            ->assertOk()
            ->assertJsonStructure(['cases', 'counts', 'templates', 'filters', 'tabs']);
    }

    public function test_verified_user_can_sign_in_and_create_a_case(): void
    {
        $user = User::factory()->create([
            'email' => 'meera@advocate.test',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'Meera@Advocate.test',
        ])->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->json('token');

        $this->withToken($token)
            ->postJson('/api/cases', [
                'state' => 'Tamil Nadu',
                'district' => 'Chennai',
                'court' => 'Madras High Court',
                'case_number' => 'O.S. 10',
                'case_year' => '2026',
                'party1_role' => 'Plaintiff',
                'party2_role' => 'Defendant',
                'petitioners' => ['Ramesh Kumar'],
                'respondents' => ['State of TN'],
            ])
            ->assertCreated()
            ->assertJsonPath('title', 'O.S. 10/2026')
            ->assertJsonPath('parties_label', 'Ramesh Kumar vs. State of TN')
            ->assertJsonPath('user_id', $user->id);

        $this->withToken($token)
            ->getJson('/api/casebook')
            ->assertOk()
            ->assertJsonPath('cases.0.user_id', $user->id)
            ->assertJsonCount(1, 'cases');
    }

    public function test_an_advocate_cannot_open_another_advocates_case(): void
    {
        $owner = User::factory()->create();
        $case = CourtCase::query()->create([
            'user_id' => $owner->id,
            'title' => 'O.S. 1/2026',
            'court' => 'Madras High Court',
        ]);

        $other = User::factory()->create();
        $token = $other->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/cases/'.$case->id)
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/casebook')
            ->assertOk()
            ->assertJsonCount(0, 'cases')
            ->assertJsonPath('case', null);
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->postJson('/api/email/verify', [
            'url' => $url.'&extra=1',
        ])->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_authenticated_advocate_can_update_tasks_and_sign_out(): void
    {
        $user = User::factory()->create();
        $case = CourtCase::query()->create([
            'user_id' => $user->id,
            'title' => 'O.S. 1/2026',
            'court' => 'Madras High Court',
            'parties_label' => 'A vs. B',
            'type' => 'New Filing',
            'status' => 'Active',
            'judge' => 'Unassigned',
            'stage' => 'Drafting Phase',
            'is_pinned' => false,
            'tasks' => '',
            'party1_role' => 'Petitioner',
            'party2_role' => 'Respondent',
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->putJson('/api/cases/'.$case->id.'/tasks', [
            'tasks' => 'File the counter.',
        ])->assertOk()->assertJsonPath('tasks', 'File the counter.');

        $this->withToken($token)->postJson('/api/logout')->assertOk()->assertJsonPath('status', 'logged_out');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_adding_a_party_keeps_the_name(): void
    {
        $user = User::factory()->create();
        $case = CourtCase::query()->create([
            'user_id' => $user->id,
            'title' => 'O.S. 1/2026',
            'court' => 'Madras High Court',
            'party1_role' => 'Petitioner',
            'party2_role' => 'Respondent',
        ]);
        $case->caseParties()->create([
            'side' => 1,
            'name' => '',
            'sort_order' => 0,
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->putJson('/api/cases/'.$case->id.'/parties', [
            'party1_role' => 'Petitioner',
            'party2_role' => 'Respondent',
            'side1' => ['Suresh'],
            'side2' => [],
        ])->assertOk()
            ->assertJsonPath('parties.0.name', 'Suresh')
            ->assertJsonPath('parties.0.side', 1);

        $this->assertSame('Suresh', $case->caseParties()->where('side', 1)->value('name'));
    }
}
