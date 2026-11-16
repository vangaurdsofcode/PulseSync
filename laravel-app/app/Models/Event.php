<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $fillable = ['site_id', 'external_id', 'type', 'occurred_at', 'payload', 'metadata'];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'payload' => 'array', 'metadata' => 'array'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
