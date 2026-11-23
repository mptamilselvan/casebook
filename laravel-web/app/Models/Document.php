<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUsersCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use BelongsToUsersCase;

    protected $fillable = [
        'name',
        'folder',
        'size',
        'file_path',
    ];

    public function courtCase(): BelongsTo
    {
        return $this->belongsTo(CourtCase::class, 'case_id');
    }
}
