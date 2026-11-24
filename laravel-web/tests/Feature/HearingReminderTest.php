<?php

namespace Tests\Feature;

use App\Mail\NextHearingReminder;
use App\Models\CourtCase;
use App\Models\HearingReminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HearingReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_emails_and_pushes_once_for_each_reminder_day(): void
    {
        config([
            'services.onesignal.app_id' => 'app-id',
            'services.onesignal.rest_api_key' => 'rest-key',
        ]);
        Mail::fake();
        Http::fake([
            'https://api.onesignal.com/*' => Http::response(['id' => 'notification-1'], 200),
        ]);

        $user = User::factory()->create();
        $due = CourtCase::query()->create([
            'user_id' => $user->id,
            'title' => 'O.S. 10/2026',
            'court' => 'Madras High Court',
            'next_hearing' => today()->addDays(3)->toDateString(),
        ]);
        CourtCase::query()->create([
            'user_id' => $user->id,
            'title' => 'O.S. 11/2026',
            'court' => 'Madras High Court',
            'next_hearing' => today()->addDays(4)->toDateString(),
        ]);

        $this->artisan('casebook:hearing-reminders')->assertSuccessful();
        $this->artisan('casebook:hearing-reminders')->assertSuccessful();

        Mail::assertSent(NextHearingReminder::class, function (NextHearingReminder $mail) use ($user, $due) {
            return $mail->hasTo($user->email)
                && $mail->case->is($due)
                && $mail->whenLabel === '3 days';
        });
        Mail::assertSentCount(1);
        Http::assertSentCount(1);
        $reminder = HearingReminder::query()->sole();
        $this->assertSame($due->id, $reminder->case_id);
        $this->assertTrue($reminder->hearing_on->isSameDay(today()->addDays(3)));
        $this->assertSame(3, $reminder->days_before);
    }

    public function test_the_header_lists_the_users_push_notifications(): void
    {
        $user = User::factory()->create();
        $case = CourtCase::query()->create([
            'user_id' => $user->id,
            'title' => 'O.S. 10/2026',
            'court' => 'Madras High Court',
        ]);
        $other = User::factory()->create();
        $otherCase = CourtCase::query()->create([
            'user_id' => $other->id,
            'title' => 'O.S. 99/2026',
            'court' => 'Madras High Court',
        ]);
        $sent = now();
        HearingReminder::query()->create([
            'case_id' => $case->id,
            'hearing_on' => today()->addDays(3)->toDateString(),
            'days_before' => 3,
            'sent_at' => $sent,
        ]);
        HearingReminder::query()->create([
            'case_id' => $otherCase->id,
            'hearing_on' => today()->addDay()->toDateString(),
            'days_before' => 1,
            'sent_at' => $sent,
        ]);

        $title = 'Hearing in 3 days · O.S. 10/2026';

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee($title, false)
            ->assertSee($sent->format('d M Y'), false)
            ->assertDontSee('Hearing in 1 day · O.S. 99/2026', false);

        Sanctum::actingAs($user);
        $this->getJson('/api/casebook')
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.title', $title)
            ->assertJsonPath('notifications.0.date', $sent->format('d M Y'));
    }
}
