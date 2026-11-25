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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'owner_id', 'is_enabled', 'plan_id', 'price_id', 'plan_name', 'plan_created_date', 'billing_interval', 'razorpay_subscription_id', 'subscription_status', 'plan_renews_on'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CO_ADMIN = 'co-admin';

    public const ROLE_ELDER = 'elder';

    public const ROLE_MEMBER = 'member';

    public const ROLES = [
        self::ROLE_SUPERADMIN,
        self::ROLE_ADMIN,
        self::ROLE_CO_ADMIN,
        self::ROLE_ELDER,
        self::ROLE_MEMBER,
    ];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function roleName(): string
    {
        return Role::SLUGS[$this->role] ?? $this->role;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(self::class, 'owner_id');
    }

    public function people(): HasMany
    {
        return $this->hasMany(self::class, 'owner_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CourtCase::class);
    }

    public function assignedCases(): BelongsToMany
    {
        return $this->belongsToMany(CourtCase::class, 'case_user', 'user_id', 'case_id');
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

    /**
     * @param  array{id?: string|null, status?: string|null, renews_on?: string|null}  $subscription
     */
    public function assignPlan(Plan $plan, Price $price, array $subscription = []): void
    {
        $changingPlan = (int) $this->plan_id !== (int) $plan->id;
        $free = $price->amount === 0;

        $this->forceFill([
            'plan_id' => $plan->id,
            'price_id' => $price->id,
            'plan_name' => $plan->name,
            'plan_created_date' => $this->plan_created_date === null || $changingPlan
                ? now()->toDateString()
                : $this->plan_created_date,
            'billing_interval' => $free ? null : $price->interval,
            'razorpay_subscription_id' => $free ? null : ($subscription['id'] ?? $this->razorpay_subscription_id),
            'subscription_status' => $free ? null : ($subscription['status'] ?? 'active'),
            'plan_renews_on' => $free ? null : ($subscription['renews_on'] ?? $this->plan_renews_on),
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
            'plan_renews_on' => 'date',
        ];
    }
}
