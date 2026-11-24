<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Hidden(['disk', 'storage_key', 'metadata_overrides'])]
class BookFile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata_overrides' => 'array', 'size_bytes' => 'integer', 'processed_at' => 'datetime'];
    }

    public function kindleDeliveries(): HasMany
    {
        return $this->hasMany(KindleDelivery::class);
    }

    public function latestKindleDelivery(): HasOne
    {
        return $this->hasOne(KindleDelivery::class)->latestOfMany();
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
