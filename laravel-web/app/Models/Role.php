<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const SLUGS = [
        'superadmin' => 'Superadmin',
        'admin' => 'Admin',
        'co-admin' => 'Co-admin',
        'elder' => 'Elder',
        'member' => 'Member',
    ];

    protected $fillable = [
        'slug',
        'name',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
