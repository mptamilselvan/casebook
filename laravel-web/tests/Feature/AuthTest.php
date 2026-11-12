<?php

namespace Tests\Feature;

use App\Mail\VerifyEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_see_the_email_login_screen(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in with your email')
            ->assertSee('Email');
    }

    public function test_verified_user_signs_in_with_email_and_opens_the_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'meera@advocate.test',
        ]);

        $this->post(route('login.store'), [
            'email' => 'Meera@Advocate.test',
        ])->assertRedirect(route('cases.index'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('cases.index'))
            ->assertOk()
            ->assertSee('Case Book')
            ->assertSee('New Case');
    }

    public function test_email_without_a_dot_after_the_at_sign_is_not_sent(): void
    {
        Mail::fake();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'advocate@nodot',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 0);
        Mail::assertNothingSent();
    }

    public function test_unknown_email_receives_a_verification_link_and_stays_out(): void
    {
        Mail::fake();

        $this->post(route('login.store'), [
            'email' => 'new.advocate@firm.test',
        ])->assertRedirect()
            ->assertSessionHas('status');

        $user = User::query()->where('email', 'new.advocate@firm.test')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertGuest();
        $this->get('/')->assertRedirect(route('login'));

        Mail::assertSent(VerifyEmail::class, function (VerifyEmail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && str_contains($mail->verificationUrl(), '/email/verify/'.$user->id.'/');
        });
    }

    public function test_unverified_user_is_sent_another_link_instead_of_the_dashboard(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'waiting@advocate.test',
        ]);

        $this->post(route('login.store'), [
            'email' => 'waiting@advocate.test',
        ])->assertRedirect()
            ->assertSessionHas('status');

        $this->assertGuest();
        $this->assertNull($user->fresh()->email_verified_at);
        Mail::assertSent(VerifyEmail::class);
    }

    public function test_verification_link_opens_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'verified.later@advocate.test',
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect(route('cases.index'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->get(route('cases.index'))
            ->assertOk()
            ->assertSee('New Case');
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get('/email/verify/'.$user->id.'/'.sha1('someone-else@advocate.test'))
            ->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertGuest();
    }
}
