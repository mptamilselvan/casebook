<?php

namespace App\Models;

use App\Mail\VerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'is_enabled', 'plan_id', 'price_id', 'plan_name', 'plan_created_date'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CourtCase::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function usage(): HasOne
    {
        return $this->hasOne(UserUsage::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }

    public function assignFreePlan(): void
    {
        if ($this->plan_id !== null) {
            return;
        }

        $plan = Plan::query()->with('prices')->where('code', Plan::FREE)->first();
        $price = $plan?->inrPrice();
        if ($plan === null || $price === null) {
            return;
        }

        $this->assignPlan($plan, $price);
    }

    public function assignPlan(Plan $plan, Price $price): void
    {
        $this->forceFill([
            'plan_id' => $plan->id,
            'price_id' => $price->id,
            'plan_name' => $plan->name,
            'plan_created_date' => now()->toDateString(),
        ])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        Mail::to($this)->send(new VerifyEmail($this));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_enabled' => 'boolean',
            'plan_created_date' => 'date',
        ];
    }
}
