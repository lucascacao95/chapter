<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Hidden(['recipient', 'idempotency_key'])]
class KindleDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function bookFile(): BelongsTo
    {
        return $this->belongsTo(BookFile::class);
    }
}
