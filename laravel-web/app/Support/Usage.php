<?php

namespace App\Support;

use App\Models\CourtCase;
use App\Models\User;
use App\Models\UserUsage;
use Illuminate\Support\Facades\DB;

class Usage
{
    public static function summary(User $user): array
    {
        $user->loadMissing('plan');
        $usage = UserUsage::query()->where('user_id', $user->id)->first();
        $bytes = (int) ($usage->storage_bytes ?? 0);
        $cases = (int) ($usage->cases_count ?? 0);
        $plan = $user->plan;

        return [
            'storage_bytes' => $bytes,
            'storage_label' => Casebook::sizeLabel($bytes),
            'storage_limit_bytes' => $plan === null ? null : $plan->space_limit * 1073741824,
            'storage_limit_label' => $plan === null ? null : $plan->space_limit.' GB',
            'cases_count' => $cases,
            'cases_limit' => $plan?->cases_limit,
        ];
    }

    public static function caseBlockReason(User $user): ?string
    {
        $user->loadMissing('plan');
        $limit = $user->plan?->cases_limit;
        if ($limit === null) {
            return null;
        }

        $count = (int) (UserUsage::query()->where('user_id', $user->id)->value('cases_count') ?? 0);
        if ($count >= $limit) {
            return 'Your '.$user->plan->name.' plan allows '.$limit.' cases.';
        }

        return null;
    }

    public static function storageBlockReason(User $user, int $incomingBytes, int $replacingBytes = 0): ?string
    {
        if ($incomingBytes <= 0) {
            return null;
        }

        $user->loadMissing('plan');
        if ($user->plan === null) {
            return null;
        }

        $limit = $user->plan->space_limit * 1073741824;
        $used = (int) (UserUsage::query()->where('user_id', $user->id)->value('storage_bytes') ?? 0);
        $next = $used - max(0, $replacingBytes) + $incomingBytes;
        if ($next > $limit) {
            return 'This file exceeds your '.$user->plan->space_limit.' GB storage.';
        }

        return null;
    }

    public static function change(int $userId, int $bytes = 0, int $cases = 0): void
    {
        if ($userId <= 0 || ($bytes === 0 && $cases === 0)) {
            return;
        }

        DB::transaction(function () use ($userId, $bytes, $cases) {
            $usage = UserUsage::query()->where('user_id', $userId)->lockForUpdate()->first();
            if ($usage === null) {
                $usage = UserUsage::query()->create([
                    'user_id' => $userId,
                    'storage_bytes' => 0,
                    'cases_count' => 0,
                ]);
            }

            $usage->storage_bytes = max(0, $usage->storage_bytes + $bytes);
            $usage->cases_count = max(0, $usage->cases_count + $cases);
            $usage->save();
        });
    }

    public static function userIdForCase(int $caseId): ?int
    {
        $userId = CourtCase::withoutGlobalScope('owner')->whereKey($caseId)->value('user_id');

        return $userId === null ? null : (int) $userId;
    }
}
