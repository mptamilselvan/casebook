<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    public const GROUPS = [
        'CASE' => 'Case',
        'PIN_CASE' => 'Pin case',
        'PROMOTE_CASE' => 'Promote case',
        'EVENT' => 'Event',
        'TIMELINE' => 'Timeline',
        'DOCUMENT' => 'Document',
        'STAR_CASE' => 'Star case',
    ];

    public const ACTIONS = ['view', 'create', 'edit', 'delete'];

    protected $fillable = [
        'group',
        'action',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
