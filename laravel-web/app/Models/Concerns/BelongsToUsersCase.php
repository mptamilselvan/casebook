<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToUsersCase
{
    protected static function bootBelongsToUsersCase(): void
    {
        static::addGlobalScope('owner', function (Builder $builder) {
            $userId = auth()->id() ?? request()->user()?->id;
            if ($userId === null) {
                return;
            }

            $builder->whereHas('courtCase');
        });
    }
}
