<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToUsersCase
{
    protected static function bootBelongsToUsersCase(): void
    {
        static::addGlobalScope('owner', function (Builder $builder) {
            $userId = auth()->id();
            if ($userId === null) {
                return;
            }

            $builder->whereHas('courtCase', function (Builder $query) use ($userId) {
                $query->where($query->qualifyColumn('user_id'), $userId);
            });
        });
    }
}
