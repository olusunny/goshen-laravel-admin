<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddonUpdateBackup extends Model
{
    protected $guarded = [];

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
